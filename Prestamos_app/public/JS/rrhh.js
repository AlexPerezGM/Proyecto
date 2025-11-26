document.addEventListener("DOMContentLoaded", () => {
  const API = (window.APP_BASE || '/') + 'api/rrhh.php';

  const $tabEmp = document.getElementById('tabEmpleados');
  const $tabNom = document.getElementById('tabNomina');

  const $secEmp = document.getElementById('secEmpleados');
  const $secNom = document.getElementById('secNomina');

  const showEmp = () => {
    if ($secEmp) $secEmp.style.display = '';
    if ($secNom) $secNom.style.display = 'none';
    $tabEmp?.classList.remove('btn-light');
    $tabNom?.classList.add('btn-light');
  };
  const showNom = () => {
    if ($secEmp) $secEmp.style.display = 'none';
    if ($secNom) $secNom.style.display = '';
    $tabEmp?.classList.add('btn-light');
    $tabNom?.classList.remove('btn-light');
  };

  $tabEmp?.addEventListener('click', showEmp);
  $tabNom?.addEventListener('click', showNom);

  const $qEmp = document.getElementById('qEmp');
  const $btnBuscarEmp = document.getElementById('btnBuscarEmp');
  const $btnNuevoEmp = document.getElementById('btnNuevoEmp');
  const $tbEmp = document.querySelector('#tablaEmpleados tbody');
  const $pagEmp = document.getElementById('paginacionEmp');
  const $errEmp = document.getElementById('errorEmp');

  const $modalEmp = document.getElementById('modalEmp');
  const $frmEmp = document.getElementById('frmEmpleado');
  const $modalEmpTitulo = document.getElementById('modalEmpTitulo');
  const $errEmpForm = document.getElementById('errorEmpForm');
  document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => $modalEmp && $modalEmp.classList.remove('show')));
  const openModal = (m) => { if (m) m.classList.add('show'); };

  const $modalAjuste = document.getElementById('modalAjuste');
  const $frmAjuste = document.getElementById('frmAjuste');
  const $btnNuevoAjuste = document.getElementById('btnNuevoAjuste');
  const $tbAjustes = document.querySelector('#tablaAjustes tbody');
  const $errAjusteList = document.getElementById('errorAjusteList');
  const $errAjusteForm = document.getElementById('errorAjusteForm');

  const $ajusteTipo = document.getElementById('ajuste_tipo');
  const $ajusteIdCatalogo = document.getElementById('ajuste_id_catalogo');
  const $ajusteIdEmpleado = document.getElementById('ajuste_id_empleado');
  const $ajusteIdAjuste = document.getElementById('ajuste_id_ajuste_empleado');
  const $ajustePorcentaje = document.getElementById('ajuste_porcentaje');
  const $ajusteMonto = document.getElementById('ajuste_monto');
  const $ajusteVigencia = document.getElementById('ajuste_vigente_desde');

  let CATALOGOS = {};
  document.querySelectorAll('[data-close-ajuste]').forEach(b => b.addEventListener('click', () => $modalAjuste && $modalAjuste.classList.remove('show')));

  $btnNuevoAjuste?.addEventListener('click', () => {
    openModal($modalAjuste);
    if ($frmAjuste) $frmAjuste.reset();
    if ($ajusteIdAjuste) $ajusteIdAjuste.value = 0;
    if ($ajusteIdCatalogo) $ajusteIdCatalogo.innerHTML = '<option value="">Seleccionar...</option>';
    if ($ajusteIdCatalogo) $ajusteIdCatalogo.disabled = true;
    const $arrAjusteFormEl = document.getElementById('arrAjusteForm');
    if ($arrAjusteFormEl) $arrAjusteFormEl.hidden = true;
    const t = document.getElementById('modalAjusteTitulo'); if (t) t.textContent = ' Nuevo ajuste para empleado';
  });

  $ajusteTipo?.addEventListener('change', () => {
    const tipo = $ajusteTipo.value;
    if ($ajusteIdCatalogo) $ajusteIdCatalogo.disabled = true;
    if ($ajusteIdCatalogo) $ajusteIdCatalogo.innerHTML = '<option value="">Seleccionar...</option>';

    if (tipo === 'Deduccion' && CATALOGOS.deducciones) {
      CATALOGOS.deducciones.forEach(d => {
        if ($ajusteIdCatalogo) $ajusteIdCatalogo.innerHTML += `<option value="${d.id_tipo_deduccion}">${d.tipo_deduccion}</option>`;
      });
      if ($ajusteIdCatalogo) $ajusteIdCatalogo.disabled = false;
    } else if (tipo === 'Beneficio' && CATALOGOS.beneficios) {
      CATALOGOS.beneficios.forEach(b => {
        if ($ajusteIdCatalogo) $ajusteIdCatalogo.innerHTML += `<option value="${b.id_beneficio}">${b.tipo_beneficio}</option>`;
      });
      if ($ajusteIdCatalogo) $ajusteIdCatalogo.disabled = false;
    }
  });

  function esMayorDeEdad(fechaStr) {
    const hoy = new Date(), f = new Date(fechaStr);
    if (isNaN(f)) return false;
    let edad = hoy.getFullYear() - f.getFullYear();
    const m = hoy.getMonth() - f.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < f.getDate())) edad--;
    return edad >= 18;
  }

  async function jsonFetch(url, body) {
    if ($errEmp) $errEmp.hidden = true;
    const res = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body });
    const text = await res.text();
    try { return JSON.parse(text); }
    catch (e) { if ($errEmp) { $errEmp.hidden = false; $errEmp.textContent = 'Respuesta no-JSON:\n' + text.slice(0, 1500); } throw e; }
  }

  async function cargarOptions() {
    const j = await jsonFetch(API, new URLSearchParams({ action: 'options' }));
    const sel = (id, arr, k = 'id', v = 'txt') => {
      const el = document.getElementById(id);
      if (!el) return;
      el.innerHTML = (arr || []).map(o => `<option value="${o[k]}">${o[v]}</option>`).join('');
    };
    sel('genero', j.generos, 'id_genero', 'genero');
    sel('id_tipo_contrato', j.contratos, 'id_tipo_contrato', 'tipo_contrato');
    const $idJefeEl = document.getElementById('id_jefe');
    if ($idJefeEl) $idJefeEl.innerHTML = '<option value="">—</option>' + (j.jefes || []).map(o => `<option value="${o.id_empleado}">${o.nombre}</option>`).join('');
    const $selEmp = document.getElementById('selEmpleado');
    if ($selEmp) $selEmp.innerHTML = '<option value="ALL">Todos</option>' + (j.jefes || []).map(o => `<option value="${o.id_empleado}">${o.nombre}</option>`).join('');

    CATALOGOS = { deducciones: j.deducciones || [], beneficios: j.beneficios || [] };
  }

  let pageEmp = 1, PAGE = 10;
  async function cargarEmpleados(p = 1) {
    pageEmp = p;
    const params = new URLSearchParams({ action: 'emp_list', q: $qEmp ? $qEmp.value.trim() : '', page: p, size: PAGE });
    const j = await jsonFetch(API, params);
    if ($tbEmp) $tbEmp.innerHTML = (j.data || []).map(r => `
      <tr>
        <td>${r.id_empleado}</td>
        <td>${r.nombre}</td>
        <td>${r.cedula || '-'}</td>
        <td>${r.email || '-'}</td>
        <td>${r.telefono || '-'}</td>
        <td>${r.cargo || '-'}</td>
        <td>$${(+r.salario_base || 0).toFixed(2)}</td>
        <td>${r.fecha_contratacion || ''}</td>
        <td>
          <button class="btn btn-light" data-ver="${r.id_empleado}">Ver</button>
          <button class="btn" data-editar="${r.id_empleado}">Editar</button>
        </td>
      </tr>
    `).join('');
    const total = +j.total || 0;
    const pages = Math.max(1, Math.ceil(total / PAGE));
    let html = ''; for (let i = 1; i <= pages; i++) html += `<button ${i === p ? 'class="active"' : ''} data-p="${i}">${i}</button>`;
    if ($pagEmp) $pagEmp.innerHTML = html;
  }

  $pagEmp?.addEventListener('click', e => { const b = e.target.closest('button[data-p]'); if (!b) return; cargarEmpleados(+b.dataset.p); });
  $btnBuscarEmp?.addEventListener('click', () => cargarEmpleados(1));
  $qEmp?.addEventListener('keydown', e => { if (e.key === 'Enter') cargarEmpleados(1); });

  $btnNuevoEmp?.addEventListener('click', () => {
    if ($frmEmp) $frmEmp.reset();
    if ($frmEmp && $frmEmp.action) $frmEmp.action.value = 'emp_create';
    const idEl = document.getElementById('id_empleado'); if (idEl) idEl.value = '';
    if ($modalEmpTitulo) $modalEmpTitulo.textContent = 'Nuevo empleado';
    openModal($modalEmp);
  });

  async function loadEmp(id) {
    const j = await jsonFetch(API, new URLSearchParams({ action: 'emp_get', id_empleado: id }));
    if (!j.ok) return alert('Error al cargar datos del empleado: ' + (j.msg || ''));

    if ($frmEmp) $frmEmp.reset();
    if ($frmEmp && $frmEmp.action) $frmEmp.action.value = 'emp_update';
    if ($modalEmpTitulo) $modalEmpTitulo.textContent = 'Editar empleado';
    for (const k of ['id_empleado', 'nombre', 'apellido', 'numero_documento', 'fecha_nacimiento', 'telefono', 'email', 'ciudad', 'sector', 'calle', 'numero_casa', 'cargo', 'departamento', 'fecha_contratacion', 'salario_base']) {
      const el = document.getElementById(k); if (el) el.value = j[k] ?? '';
    }
    const g = document.getElementById('genero'); if (g) g.value = j.genero || '';
    const tc = document.getElementById('id_tipo_contrato'); if (tc) tc.value = j.id_tipo_contrato || '';
    const ij = document.getElementById('id_jefe'); if (ij) ij.value = j.id_jefe || '';

    if ($ajusteIdEmpleado) $ajusteIdEmpleado.value = id;
    loadAjustes(id);

    openModal($modalEmp);
  }
  document.addEventListener('click', async (e) => {
    const v = e.target.closest('[data-ver]');
    const ed = e.target.closest('[data-editar]');
    if (!v && !ed) return;
    const id = (v ? v.dataset.ver : ed.dataset.editar);
    if (ed) {
      loadEmp(id);
    } else {
      const j = await jsonFetch(API, new URLSearchParams({ action: 'emp_get', id_empleado: id }));
      alert(`Empleado: ${j.nombre}\nCargo: ${j.cargo}\nSalario: $${(+j.salario_base || 0).toFixed(2)}`);
    }
  });
  // Guardar empleado
  $frmEmp?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fnEl = document.getElementById('fecha_nacimiento'); const fn = fnEl ? fnEl.value : '';
    if (!esMayorDeEdad(fn)) return alert('El empleado debe ser mayor de edad.');
    const emEl = document.getElementById('email'); const em = emEl ? emEl.value.trim() : '';
    if (em && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) return alert('Email no válido.');
    const res = await jsonFetch(API, new FormData($frmEmp));
    if (!res.ok) { if ($errEmpForm) { $errEmpForm.hidden = false; $errEmpForm.textContent = res.msg || 'Error al guardar'; } return; }
    if ($errEmpForm) $errEmpForm.hidden = true; if ($modalEmp) $modalEmp.classList.remove('show'); cargarEmpleados(pageEmp); cargarOptions(); // refresca combos de jefe
  });

  async function loadAjustes(id_empleado) {
    if ($errAjusteList) $errAjusteList.hidden = true;
    if ($tbAjustes) $tbAjustes.innerHTML = '<tr><td colspan="7">Cargando ajustes...</td></tr>';
    if ($ajusteIdEmpleado) $ajusteIdEmpleado.value = id_empleado;
    const params = new URLSearchParams({ action: 'ajuste_list', id_empleado });
    const j = await jsonFetch(API, params);
    if (!j.ok) {
      if ($errAjusteList) { $errAjusteList.hidden = false; $errAjusteList.textContent = j.msg || 'Error al cargar ajustes.'; }
      if ($tbAjustes) $tbAjustes.innerHTML = '<tr><td colspan="7">No se pudieron cargar los ajustes</td></tr>';
      return;
    }
    if (!j.data || j.data.length === 0) {
      if ($tbAjustes) $tbAjustes.innerHTML = '<tr><td colspan="7">No hay ajustes personalizados para este empleado</td></tr>';
      return;
    }

    if ($tbAjustes) {
      $tbAjustes.innerHTML = j.data.map(r => {
        const estadoClass = r.estado === 'Activo' ? 'badge-activo' : 'badge-inactivo';
        return `
            <tr>
                <td>${r.tipo}</td>
                <td>${r.nombre_ajuste}</td>
                <td>${r.porcentaje > 0 ? (+r.porcentaje).toFixed(2) : '-'}</td>
                <td>${r.monto > 0 ? (+r.monto).toFixed(2) : '-'}</td>
                <td>${r.vigente_desde}</td>
                <td><span class="badge ${estadoClass}">${r.estado}</span></td>
                <td>
                    <button class="btn btn-sm btn-light" data-toggle-status="${r.id_ajuste_empleado}" data-estado="${r.estado}">${r.estado === 'Activo' ? 'Desactivar' : 'Activar'}</button>
                </td>
            </tr>
        `;
      }).join('');

      $tbAjustes.querySelectorAll('[data-toggle-status]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const id = btn.dataset.toggleStatus;
          const estado = btn.dataset.estado === 'Activo' ? 'Inactivo' : 'Activo';
          const params = new URLSearchParams({ action: 'ajuste_toggle_status', id_ajuste_empleado: id, estado: estado });
          const res = await jsonFetch(API, params);
          if (res.ok) {
            alert(`Ajuste ${estado} con éxito.`);
            loadAjustes(id_empleado);
          } else {
            alert(res.msg || 'Error al cambiar estado.');
          }
        });
      });
    }
  }
  // Guardar ajuste
  $frmAjuste?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if ($errAjusteForm) $errAjusteForm.hidden = true;
    const porcentaje = +($ajustePorcentaje ? $ajustePorcentaje.value : 0);
    const monto = +($ajusteMonto ? $ajusteMonto.value : 0);

    if (porcentaje > 0 && monto > 0) {
      if ($errAjusteForm) { $errAjusteForm.hidden = false; $errAjusteForm.textContent = 'Debe ingresar solo Porcentaje o Monto, no ambos.'; }
      return;
    }
    if (porcentaje === 0 && monto === 0) {
      if ($errAjusteForm) { $errAjusteForm.hidden = false; $errAjusteForm.textContent = 'Debe ingresar un valor de Porcentaje o Monto.'; }
      return;
    }

    const fd = new FormData($frmAjuste);
    const j = await jsonFetch(API, fd);

    if (j.ok) {
      alert(j.msg);
      if ($modalAjuste) $modalAjuste.classList.remove('show');
      loadAjustes($ajusteIdEmpleado ? $ajusteIdEmpleado.value : 0);
    } else {
      if ($errAjusteForm) { $errAjusteForm.hidden = false; $errAjusteForm.textContent = j.msg || 'Error al guardar el ajuste.'; }
    }
  });

  // Nómina 
  const $periodo = document.getElementById('periodo');
  const $selEmpleado = document.getElementById('selEmpleado');
  const $btnCalcular = document.getElementById('btnCalcular');
  const $btnGuardar = document.getElementById('btnGuardarNomina');
  const $btnComprob = document.getElementById('btnComprobantes');
  const $tbNom = document.querySelector('#tablaNomina tbody');
  const $tot = document.getElementById('resumenTotales');
  const $errNom = document.getElementById('errorNomina');

  let nomRows = [];
  const PERIODO_RE = /^\d{4}-\d{2}$/; // YYYY-MM
  function validarPeriodo(val) { return PERIODO_RE.test((val || '').trim()); }
  function rowsToPayload() { return Array.isArray(nomRows) ? nomRows : []; }
  function pintarNomina(list) {
    nomRows = list || [];
    let totBase = 0, totHx = 0, totBon = 0, totDed = 0, totNeto = 0;
    if ($tbNom) $tbNom.innerHTML = nomRows.map(r => {
      const neto = (+r.salario_base) + (+r.horas_extra) + (+r.bonificaciones) - (+r.deducciones);
      totBase += +r.salario_base;
      totHx += +r.horas_extra;
      totBon += +r.bonificaciones;
      totDed += +r.deducciones;
      totNeto += neto;
      return `<tr data-id="${r.id_empleado}">
        <td>${r.nombre}</td>
        <td data-sal="$${(+r.salario_base).toFixed(2)}">$${(+r.salario_base).toFixed(2)}</td>
        <td>$${(+r.horas_extra).toFixed(2)}</td>
        <td>$${(+r.bonificaciones).toFixed(2)}</td>
        <td>$${(+r.deducciones).toFixed(2)}</td>
        <td>$${neto.toFixed(2)}</td>
      </tr>`;
    }).join('');
    if ($tot) $tot.textContent = `Totales — Salario base: $${totBase.toFixed(2)} | Horas extra: $${totHx.toFixed(2)} | Bonificaciones: $${totBon.toFixed(2)} | Deducciones: $${totDed.toFixed(2)} | Nómina total: $${totNeto.toFixed(2)}`;
  }

  $btnCalcular?.addEventListener('click', async () => {
    const per = $periodo ? $periodo.value.trim() : '';
    if (!validarPeriodo(per)) { if ($errNom) { $errNom.hidden = false; $errNom.textContent = 'Ingrese un periodo válido (YYYY-MM).'; } return; }
    const params = new URLSearchParams({ action: 'nomina_preview', periodo: per, id_empleado: $selEmpleado ? $selEmpleado.value : 'ALL' });
    const j = await jsonFetch(API, params);
    if (!j.ok) { if ($errNom) { $errNom.hidden = false; $errNom.textContent = j.msg || 'Error'; } return; }
    if ($errNom) $errNom.hidden = true; pintarNomina(j.rows || []);
  });

  $btnGuardar?.addEventListener('click', async () => {
    const per = $periodo ? $periodo.value.trim() : '';
    if (!validarPeriodo(per)) { if ($errNom) { $errNom.hidden = false; $errNom.textContent = 'Ingrese un periodo válido (YYYY-MM) antes de guardar.'; } return; }
    const rows = rowsToPayload();
    if (!rows.length) { if ($errNom) { $errNom.hidden = false; $errNom.textContent = 'No hay datos calculados para guardar.'; } return; }
    const fd = new FormData();
    fd.append('action', 'nomina_save');
    fd.append('periodo', per);
    const j = await jsonFetch(API, fd);
    if (!j.ok) { if ($errNom) { $errNom.hidden = false; $errNom.textContent = j.msg || 'No se pudo guardar.'; } return; }
    if ($errNom) $errNom.hidden = true; alert('Nómina guardada.');
  });

  $btnComprob?.addEventListener('click', async () => {
    const per = $periodo ? $periodo.value.trim() : '';
    const j = await jsonFetch(API, new URLSearchParams({ action: 'nomina_comprobantes', periodo: per }));
    if (!j.ok) return alert(j.msg || 'Error generando comprobantes');
    const w = window.open('', '_blank');
    w.document.write(j.html || '<p>Sin datos</p>');
    w.document.close();
    w.focus();
  });

  cargarOptions().then(() => { cargarEmpleados(1); });
});
