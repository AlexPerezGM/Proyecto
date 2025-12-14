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
  let currendtIdNomina = null;

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

  const $tipoDocEmpleados = document.getElementById('tipo_doc_empleado');
  const $archivoDoc = document.getElementById('archivo_doc');
  const $btnSubirDoc = document.getElementById('btnSubirDoc');
  const $boxDocs = document.getElementById('boxDocs');

  const $txtSearchNomina = document.getElementById('txtSearchNomina');
  const $btnBuscarNomina = document.getElementById('btnBuscarNomina');
  const $btnProcesarNomina = document.getElementById('btnProcesarNomina');
  const $msgInfoNomina = document.getElementById('msgInfoNomina');

  const $frecuencia = document.getElementById('selFrecuencia');
  const $fechaInicio = document.getElementById('fechaInicio');
  const $fechaFin = document.getElementById('fechaFin');
  const $fechaPago = document.getElementById('fechaPago');

  const $modalVer = document.getElementById('modalVer');
  const closeModal = (el) => el.classList.remove('show');

  $frecuencia?.addEventListener('change', () => {
    const hoy = new Date();
    const año = hoy.getFullYear();
    const mes = hoy.getMonth();

    if ($frecuencia.value === 'Mensual') {
      $fechaInicio.value = new Date(año, mes, 1).toISOString().split('T')[0];
      $fechaFin.value = new Date(año, mes + 1, 0).toISOString().split('T')[0];
      $fechaPago.value = new Date(año, mes + 1, 0).toISOString().split('T')[0];
    } else {
      if (hoy.getDate() <= 15) {
        $fechaInicio.value = new Date(año, mes, 1).toISOString().split('T')[0];
        $fechaFin.value = new Date(año, mes, 15).toISOString().split('T')[0];
        $fechaPago.value = new Date(año, mes, 15).toISOString().split('T')[0];
      } else {
        $fechaInicio.value = new Date(año, mes, 16).toISOString().split('T')[0];
        $fechaFin.value = new Date(año, mes + 1, 0).toISOString().split('T')[0];
        $fechaPago.value = new Date(año, mes + 1, 0).toISOString().split('T')[0];
      }
    }
  });

  if ($frecuencia) $frecuencia.dispatchEvent(new Event('change'));

  document.querySelectorAll('[data-close]').forEach(b => {
    b.addEventListener('click', () => closeModal(b.closest('.modal')));
  });

  window.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal.show').forEach(m => closeModal(m));
    }
  });

  document.getElementById("numero_documento").addEventListener("blur", async () => {
    const raw = document.getElementById("numero_documento").value || '';
    const cedula = raw.replace(/\D/g, '');

    const params = new URLSearchParams({
      action: 'validarCedula',
      cedula: cedula
    });

    const res = await jsonFetch(API, params);
    if (!res.ok) {
      alert(res.msg || 'Error al validar la cédula');
      return;
    }
  })

  let CATALOGOS = {};
  let isEditMode = false;
  document.querySelectorAll('[data-close-ajuste]').forEach(b => b.addEventListener('click', () =>
    $modalAjuste && $modalAjuste.classList.remove('show')));

  function resetDocsUIForCreate() {
    if (!$tipoDocEmpleados || !$archivoDoc || !$btnSubirDoc || !$boxDocs)
      return;
    isEditMode = false;
    $tipoDocEmpleados.disabled = false;
    $tipoDocEmpleados.value = '';
    $archivoDoc.disabled = false;
    $archivoDoc.value = '';
    $btnSubirDoc.disabled = true;
  }

  function prepareDocsUIForEdit(idEmpleado) {
    if (!$tipoDocEmpleados || !$archivoDoc || !$btnSubirDoc || !$boxDocs)
      return;
    isEditMode = true;
    $tipoDocEmpleados.disabled = false;
    $tipoDocEmpleados.value = '';
    $archivoDoc.disabled = true;
    $archivoDoc.value = '';
    $btnSubirDoc.disabled = true;
    $boxDocs.innerHTML = '<p>Cargando documentos…</p>';
    cargarDocumentos(idEmpleado);
  }

  async function cargarDocumentos(id_empleado) {
    if (!$boxDocs)
      return;
    const res = await jsonFetch(API, new URLSearchParams({
      action: 'list_docs',
      id_empleado
    }));

    if (!res.ok) {
      $boxDocs.innerHTML = '<p>Error cargando documentos</p>';
      return;
    }
    let html = '';
    const viewerUrl = (window.APP_BASE || '/') + 'views/docs_empleados.php?id_empleado=' + encodeURIComponent(id_empleado);
    html += `
      <div class="docs-folder-link">
        <a href="${viewerUrl}" target="_blank" class="btn btn-light">
          Abrir documentos del empleado
        </a>
      </div>
    `;
    const files = Array.isArray(res.files) ? res.files : [];
    if (files.length === 0) {
      html += '<p>No hay documentos cargados.</p>';
    } else {
      html += files.map(f => `
        <div class="doc-item">
          <a href="${f.ruta}" target="_blank" class="btn btn-light" aria-label="Ver documento</a>
        </div>
      `).join('');
    }
    $boxDocs.innerHTML = html;
  }

  async function subirDocumentoEmpleado(idEmpleado, tipo, file, extraData = {}) {
    const fd = new FormData();
    fd.append('action', 'upload_doc');
    fd.append('id_empleado', idEmpleado);
    fd.append('tipo_archivo', tipo);
    fd.append('archivo', file);

    if (extraData.nombre)
      fd.append('nombre', extraData.nombre);
    if (extraData.apellido)
      fd.append('apellido', extraData.apellido);
    if (extraData.numero_documento)
      fd.append('numero_documento', extraData.numero_documento);

    const res = await jsonFetch(API, fd);
    if (!res.ok) {
      throw new Error(res.error || res.msg || 'Error al subir documento');
    }
    return res;
  }

  if ($tipoDocEmpleados && $archivoDoc && $btnSubirDoc) {
    $tipoDocEmpleados.addEventListener('change', () => {
      if ($tipoDocEmpleados.value) {
        $archivoDoc.disabled = false;
      } else {
        $archivoDoc.disabled = true;
        $archivoDoc.value = '';
        $btnSubirDoc.disabled = true;
      }
    });

    $archivoDoc.addEventListener('change', () => {
      if (isEditMode) {
        $btnSubirDoc.disabled = !$archivoDoc.files.length;
      } else {
        $btnSubirDoc.disabled = true;
      }
    });

    $btnSubirDoc.addEventListener('click', async () => {
      if (!isEditMode) {
        alert('En registro nuevo, el documento se sube al guardar el empleado.');
        return;
      }
      const idEmpleado = document.getElementById('id_empleado').value;
      if (!idEmpleado) {
        alert('Primero guarda el empleado antes de subir documentos.');
        return;
      }
      if (!$tipoDocEmpleados.value) {
        alert('Selecciona el tipo de documento.');
        return;
      }
      if (!$archivoDoc.files.length) {
        alert('Selecciona un archivo.');
        return;
      }

      try {
        await subirDocumentoEmpleado(idEmpleado, $tipoDocEmpleados.value, $archivoDoc.files[0]);
        $archivoDoc.value = '';
        $btnSubirDoc.disabled = true;
        cargarDocumentos(idEmpleado);
      } catch (e) {
        alert(e.message);
      }
    });
  }

  $btnNuevoAjuste?.addEventListener('click', () => {
    openModal($modalAjuste);
    if ($frmAjuste)
      $frmAjuste.reset();
    if ($ajusteIdAjuste)
      $ajusteIdAjuste.value = 0;
    if ($ajusteIdCatalogo)
      $ajusteIdCatalogo.innerHTML = '<option value="">Seleccionar...</option>';
    if ($ajusteIdCatalogo)
      $ajusteIdCatalogo.disabled = true;
    const $arrAjusteFormEl = document.getElementById('arrAjusteForm');
    if ($arrAjusteFormEl)
      $arrAjusteFormEl.hidden = true;
    const t = document.getElementById('modalAjusteTitulo'); if (t) t.textContent = ' Nuevo ajuste para empleado';
  });

  $ajusteTipo?.addEventListener('change', () => {
    const tipo = $ajusteTipo.value;
    if ($ajusteIdCatalogo) $ajusteIdCatalogo.disabled = true;
    if ($ajusteIdCatalogo) $ajusteIdCatalogo.innerHTML = '<option value="">Seleccionar...</option>';

    if (tipo === 'Deduccion' && CATALOGOS.deducciones) {
      CATALOGOS.deducciones.forEach(d => {
        if ($ajusteIdCatalogo)
          $ajusteIdCatalogo.innerHTML += `<option value="${d.id_tipo_deduccion}">${d.tipo_deduccion}</option>`;
      });
      if ($ajusteIdCatalogo) $ajusteIdCatalogo.disabled = false;
    } else if (tipo === 'Beneficio' && CATALOGOS.beneficios) {
      CATALOGOS.beneficios.forEach(b => {
        if ($ajusteIdCatalogo)
          $ajusteIdCatalogo.innerHTML += `<option value="${b.id_beneficio}">${b.tipo_beneficio}</option>`;
      });
      if ($ajusteIdCatalogo)
        $ajusteIdCatalogo.disabled = false;
    }
  });

  function esMayorDeEdad(fechaStr) {
    const hoy = new Date(), f = new Date(fechaStr);
    if (isNaN(f))
      return false;
    let edad = hoy.getFullYear() - f.getFullYear();
    const m = hoy.getMonth() - f.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < f.getDate())) edad--;
    return edad >= 18;
  }

  async function jsonFetch(url, body) {
    if ($errEmp) $errEmp.hidden = true;
    const res = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body });
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch (e) {
      if ($errEmp) {
        $errEmp.hidden = false; $errEmp.textContent = 'Respuesta no-JSON:\n' + text.slice(0, 1500);
      }
      throw e;
    }
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
    if ($idJefeEl)
      $idJefeEl.innerHTML = '<option value="">—</option>' + (j.jefes || []).map(o => `<option value="${o.id_empleado}">${o.nombre}</option>`).join('');
    const $selEmp = document.getElementById('selEmpleado');
    if ($selEmp)
      $selEmp.innerHTML = '<option value="ALL">Todos</option>' + (j.jefes || []).map(o => `<option value="${o.id_empleado}">${o.nombre}</option>`).join('');

    CATALOGOS = { deducciones: j.deducciones || [], beneficios: j.beneficios || [] };
    // Mapas auxiliares para mostrar texto de género y nombre de jefe
    window.__GENERO_MAP__ = {};
    (j.generos || []).forEach(g => { window.__GENERO_MAP__[String(g.id_genero)] = g.genero; });
    window.__JEFES_MAP__ = {};
    (j.jefes || []).forEach(p => { window.__JEFES_MAP__[String(p.id_empleado)] = p.nombre; });
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
          <button class="btn" data-ajustes="${r.id_empleado}">Ajustes</button>
        </td>
      </tr>
    `).join('');
    const total = +j.total || 0;
    const pages = Math.max(1, Math.ceil(total / PAGE));
    let html = ''; for (let i = 1; i <= pages; i++) html += `<button ${i === p ? 'class="active"' : ''} data-p="${i}">${i}</button>`;
    if ($pagEmp) $pagEmp.innerHTML = html;
  }

  $pagEmp?.addEventListener('click', e => {
    const b = e.target.closest('button[data-p]');
    if (!b)
      return;
    cargarEmpleados(+b.dataset.p);
  });
  $btnBuscarEmp?.addEventListener('click', () => cargarEmpleados(1));
  $qEmp?.addEventListener('keydown', e => {
    if (e.key === 'Enter') cargarEmpleados(1);
  });

  $btnNuevoEmp?.addEventListener('click', () => {
    if ($frmEmp)
      $frmEmp.reset();
    if ($frmEmp && $frmEmp.action)
      $frmEmp.action.value = 'emp_create';
    const idEl = document.getElementById('id_empleado'); if (idEl) idEl.value = '';
    if ($modalEmpTitulo)
      $modalEmpTitulo.textContent = 'Nuevo empleado';
    resetDocsUIForCreate();
    openModal($modalEmp);
  });

  async function loadEmp(id) {
    const j = await jsonFetch(API, new URLSearchParams({ action: 'emp_get', id_empleado: id }));
    if (!j.ok)
      return alert('Error al cargar datos del empleado: ' + (j.msg || ''));

    if ($frmEmp)
      $frmEmp.reset();
    if ($frmEmp && $frmEmp.action)
      $frmEmp.action.value = 'emp_update';
    if ($modalEmpTitulo)
      $modalEmpTitulo.textContent = 'Editar empleado';
    for (const k of ['id_empleado', 'nombre', 'apellido', 'numero_documento', 'fecha_nacimiento', 'telefono', 'email', 'ciudad', 'sector', 'calle', 'numero_casa', 'cargo', 'departamento', 'fecha_contratacion', 'salario_base', 'id_jefe']) {
      const el = document.getElementById(k); if (el) el.value = j[k] ?? '';
    }
    const g = document.getElementById('genero'); if (g) g.value = j.genero || '';
    const tc = document.getElementById('id_tipo_contrato'); if (tc) tc.value = j.id_tipo_contrato || '';
    const ij = document.getElementById('id_jefe'); if (ij) ij.value = j.id_jefe || '';

    if ($ajusteIdEmpleado) $ajusteIdEmpleado.value = id;
    prepareDocsUIForEdit(id);
    loadAjustes(id);

    openModal($modalEmp);
  }

  document.addEventListener('click', async (e) => {
    const v = e.target.closest('[data-ver]');
    const ed = e.target.closest('[data-editar]');
    const aj = e.target.closest('[data-ajustes]');

    if (!v && !ed && !aj)
      return;
    const id = (v ? v.dataset.ver : ed ? ed.dataset.editar : aj.dataset.ajustes);
    if (ed) {
      loadEmp(id);
    } else if (v) {
      const data = await jsonFetch(API, new URLSearchParams({ action: 'emp_get', id_empleado: id }));
      if (!data.ok)
        return alert('Error al cargar datos del empleado: ' + (data.msg || ''));
      const c = data;
      const generoTxt = (function () {
        const val = c.genero;
        if (val == null) return '';
        const m = window.__GENERO_MAP__ || {};
        if (m[String(val)]) return m[String(val)];
        if (String(val) === '1') return 'Masculino';
        if (String(val) === '2') return 'Femenino';
        return typeof val === 'string' ? val : String(val);
      })();
      const jefeTxt = (function () {
        if (c.jefe) return c.jefe;
        const idJ = c.id_jefe;
        const m = window.__JEFES_MAP__ || {};
        if (m && idJ != null && m[String(idJ)]) return m[String(idJ)];
        return '-';
      })();
      const html = `
        <div class="grid-2">
          <div>
            <h4>Información personal</h4>
            <p><b>Nombre:</b> ${c.nombre} ${c.apellido}</p>
            <p><b>Fecha nac.:</b> ${c.fecha_nacimiento || ''}</p>
            <p><b>Género:</b> ${generoTxt}</p>
            <p><b>Documento:</b> ${c.tipo_documento || ''} ${c.numero_documento || ''}</p>
          </div>
          <div>
            <h4>Contacto</h4>
            <p><b>Email:</b> ${c.email || '-'}</p>
            <p><b>Teléfono:</b> ${c.telefono || '-'}</p>

            <h4>Dirección</h4>
            <p>${c.ciudad || ''}, ${c.sector || ''}, ${c.calle || ''} #${c.numero_casa || ''}</p>
          </div>
          <div>
            <h4>Información empleado</h4>
            <p><b>Salario base:</b> $${(+c.salario_base || 0).toFixed(2)}</p>
            <p><b>Cargo:</b> ${c.cargo || '-'}</p>
            <p><b>Departamento:</b> ${c.departamento || '-'}</p>
            <p><b>Jefe:</b> ${jefeTxt}</p>
          </div> 
          <div>
            <h4>Ver docuementos</h4>
            <button class="btn btn-light" onclick="window.open('${(window.APP_BASE || '/') + 'views/docs_empleados.php?id_empleado=' + encodeURIComponent(c.id_empleado)}', '_blank')">Abrir documentos del empleado</button>
          </div>
        </div>
      `;
      document.getElementById('verContenido').innerHTML = html;
      openModal($modalVer);
    } else if (aj) {
      loadAjustes(id);
      if ($modalAjuste) openModal($modalAjuste);
    }
  });

  // Guardar empleado
  $frmEmp?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fnEl = document.getElementById('fecha_nacimiento'); const fn = fnEl ? fnEl.value : '';
    if (!esMayorDeEdad(fn))
      return alert('El empleado debe ser mayor de edad.');

    const emEl = document.getElementById('email'); const em = emEl ? emEl.value.trim() : '';
    if (em && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em))
      return alert('Email no válido.');

    const res = await jsonFetch(API, new FormData($frmEmp));
    if (!res.ok) {
      if ($errEmpForm) {
        $errEmpForm.hidden = false; $errEmpForm.textContent = res.msg || 'Error al guardar';
      }
      return;
    }
    if ($errEmpForm)
      $errEmpForm.hidden = true;
    if ($modalEmp)
      $modalEmp.classList.remove('show');
    cargarEmpleados(pageEmp);
    cargarOptions();
  });

  async function loadAjustes(id_empleado) {
    if ($errAjusteList)
      $errAjusteList.hidden = true;
    if ($tbAjustes)
      $tbAjustes.innerHTML = '<tr><td colspan="7">Cargando ajustes...</td></tr>';
    if ($ajusteIdEmpleado)
      $ajusteIdEmpleado.value = id_empleado;
    const params = new URLSearchParams({ action: 'ajuste_list', id_empleado });
    const j = await jsonFetch(API, params);
    if (!j.ok) {
      if ($errAjusteList) {
        $errAjusteList.hidden = false; $errAjusteList.textContent = j.msg || 'Error al cargar ajustes.';
      }
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
    if ($errAjusteForm)
      $errAjusteForm.hidden = true;
    const porcentaje = +($ajustePorcentaje ? $ajustePorcentaje.value : 0);
    const monto = +($ajusteMonto ? $ajusteMonto.value : 0);

    if (porcentaje > 0 && monto > 0) {
      if ($errAjusteForm) {
        $errAjusteForm.hidden = false; $errAjusteForm.textContent = 'Debe ingresar solo Porcentaje o Monto, no ambos.';
      }
      return;
    }
    if (porcentaje === 0 && monto === 0) {
      if ($errAjusteForm) {
        $errAjusteForm.hidden = false; $errAjusteForm.textContent = 'Debe ingresar un valor de Porcentaje o Monto.';
      }
      return;
    }

    const fd = new FormData($frmAjuste);
    const j = await jsonFetch(API, fd);

    if (j.ok) {
      alert(j.msg);
      if ($modalAjuste)
        $modalAjuste.classList.remove('show');
      loadAjustes($ajusteIdEmpleado ? $ajusteIdEmpleado.value : 0);
    } else {
      if ($errAjusteForm) { $errAjusteForm.hidden = false; $errAjusteForm.textContent = j.msg || 'Error al guardar el ajuste.'; }
    }
  });

  async function abrirAjustesEmpleado(idEmpleado) {
    if ($modalAjuste)
      openModal($modalAjuste);
    if ($frmAjuste)
      $frmAjuste.reset();

    if ($ajusteIdEmpleado)
      $ajusteIdEmpleado.value = idEmpleado;
    if ($ajusteIdAjuste)
      $ajusteIdAjuste.value = 0;

    if ($ajusteTipo)
      $ajusteTipo.value = '';
    if ($ajusteIdCatalogo) {
      $ajusteIdCatalogo.innerHTML = '<option value="">Seleccionar...</option>';
      $ajusteIdCatalogo.disabled = true;
    }

    if ($ajusteVigencia)
      $ajusteVigencia.value = new Date().toISOString().split('T')[0];

    const $arrAjusteFormEl = document.getElementById('arrAjusteForm');
    if ($arrAjusteFormEl) $arrAjusteFormEl.hidden = true;

    loadAjustes(idEmpleado);
  }

  // Nomina
  const $periodo = document.getElementById('periodo');
  const $selEmpleado = document.getElementById('selEmpleado');
  const $btnCalcular = document.getElementById('btnCalcular');
  const $btnGuardar = document.getElementById('btnGuardarNomina');
  const $btnComprob = document.getElementById('btnComprobantes');
  const $tbNom = document.querySelector('#tablaNomina tbody');
  const $tot = document.getElementById('resumenTotales');
  const $errNom = document.getElementById('errorNomina');

  let nomRows = [];
  const PERIODO_RE = /^\d{4}-\d{2}$/;
  function validarPeriodo(val) {
    return PERIODO_RE.test((val || '').trim());
  }
  function rowsToPayload() {
    return Array.isArray(nomRows) ? nomRows : [];
  }

  async function listarEmpleados() {
    let per = $periodo ? ($periodo.value || '').trim() : '';
    if (!validarPeriodo(per)) {
      const ff = $fechaFin ? $fechaFin.value : '';
      if (ff) {
        const d = new Date(ff);
        if (!isNaN(d)) {
          const y = d.getFullYear();
          const m = String(d.getMonth() + 1).padStart(2, '0');
          per = `${y}-${m}`;
        }
      }
    }
    if (!validarPeriodo(per))
      return alert('Seleccione un periodo válido (YYYY-MM) o defina fecha fin')
    const q = $txtSearchNomina ? $txtSearchNomina.value.trim() : '';

    if ($tbNom) $tbNom.innerHTML = '<tr><td colspan="6" class="text-center">Cargando...</td></tr>';

    const params = new URLSearchParams({
      action: 'buscar_empleado',
      periodo: per,
      q: q
    });

    const res = await jsonFetch(API, params);
    if (!res.ok)
      return alert(res.msg || 'Error al buscar empleados');

    modoEdicionNomina = true;
    nomRows = res.rows || [];

    if ($tbNom) {
      $tbNom.innerHTML = (nomRows || []).map((r, i) => `
      <tr data-id="${r.id_empleado}" data-idx="${i}">
        <td>${r.nombre}<br><small class="text-muted">${r.cargo}</small></td>
        <td>$${(+r.salario_base).toFixed(2)}</td>
        <td>
            <input type="number" class="form-control form-control-sm hx-input"
            placeholder="0" value=""
            step="0.01" min="0" style="width: 80px; text-align: center;">
            <small class="text-muted">Horas extras</small>
        </td>
        <td>$${(+r.bonificaciones).toFixed(2)}</td>
        <td>$${(+r.deducciones).toFixed(2)}</td>
      </tr>
    `).join('');
    }
    if ($msgInfoNomina)
      $msgInfoNomina.style.display = 'block';
    if ($tot)
      $tot.innerHTML = '';
    if ($btnGuardar)
      $btnGuardar.disabled = true;
  }

  $btnBuscarNomina?.addEventListener('click', listarEmpleados);
  $txtSearchNomina?.addEventListener('keydown', e => {
    if (e.key === 'Enter')
      listarEmpleados();
  });

  $btnProcesarNomina?.addEventListener('click', async () => {
    const per = $periodo ? $periodo.value.trim() : '';
    if (!validarPeriodo(per))
      return alert('periodo invalido');

    const itemsToSave = [];
    document.querySelectorAll('#tablaNomina tbody tr').forEach(tr => {
      const id = tr.dataset.id;
      const input = tr.querySelector('.hx-input, .hx-input-manual');
      if (id && input && input.value > 0) {
        itemsToSave.push({
          id_empleado: id,
          monto: input.value || '0'
        });
      }
    });

    if (itemsToSave.length > 0) {
      const fd = new FormData();
      fd.append('action', 'horas_extras_g');
      fd.append('periodo', per);

      itemsToSave.forEach((it, index) => {
        fd.append(`items[${index}][id_empleado]`, it.id_empleado);
        fd.append(`items[${index}][monto]`, it.monto);
      });

      const saveRes = await jsonFetch(API, fd);
      if (!saveRes.ok)
        return alert("Error al guardar" + saveRes.msg);
    }

    const paramsCalc = new URLSearchParams({
      action: 'nomina_preview',
      periodo: per,
      id_empleado: 'ALL'
    });
    if ($tbNom)
      $tbNom.innerHTML = '<tr><td colspan="6" class="text-center">Calculando totales...</td></tr>';

    const calcRes = await jsonFetch(API, paramsCalc);
    if (!calcRes.ok)
      return alert("Error al calcular la nomina: " + calcRes.msg);

    modoEdicionNomina = false;
    if ($msgInfoNomina)
      $msgInfoNomina.style.display = 'none';
    pintarNomina(calcRes.rows || []);
    if ($btnGuardar)
      $btnGuardar.disabled = false;
  });

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
        <td>$${(+r.salario_base).toFixed(2)}</td>
        <td>
            <input type="number" class="form-control form-control-sm hx-input-manual" 
                   value="${(+r.horas_extra).toFixed(2)}" step="0.01" min="0" 
                   style="width: 120px;"
                   onchange="document.getElementById('btnGuardarNomina').disabled = true; alert('Has modificado valores. Presiona Guardar y Calcular nuevamente.');">
        </td>
        <td class="text-success">$${(+r.bonificaciones).toFixed(2)}</td>
        <td class="text-danger">$${(+r.deducciones).toFixed(2)}</td>
        <td class="fw-bold">$${neto.toFixed(2)}</td>
        <td>
            <button class="btn btn-sm btn-light btn-outline-secondary btn-imprimir-ind"
            type="button"
            title="Comprobante"> Comprobante
            </button>
        </td>
      </tr>`;
    }).join('');

    if ($tot)
      $tot.innerHTML =
        `<div class="d-flex justify-content-between alert alert-secondary">
          <span class="fs-5"><b>Total salario base:</b> $${totBase.toFixed(2)}</span>
          <span class="fs-5"><b>Total fondos a pagar:</b> $${totNeto.toFixed(2)}</span>
        </div>`;
  }

  $btnCalcular?.addEventListener('click', async () => {
    if (!$fechaInicio.value || !$fechaFin.value || !$fechaPago.value) {
      alert("Seleccione totas las fehcas requeridas."); return;
    }

    const params = new URLSearchParams({
      action: 'nomina_preview',
      frecuencia: $frecuencia.value,
      fecha_inicio: $fechaInicio.value,
      fecha_fin: $fechaFin.value,
      fecha_pago: $fechaPago.value,
      id_empleado: 'ALL'
    });

    if ($tbNom) $tbNom.innerHTML = '<tr><td colspan="7" class="text-center">Calculando...</td></tr>';

    const j = await jsonFetch(API, params);
    if (!j.ok) {
      if ($errNom) {
        $errNom.hidden = false; $errNom.textContent = j.msg || 'Error';
      }
      return;
    }
    if ($errNom) $errNom.hidden = true;
    if (j.id_nomina) {
      currendtIdNomina = j.id_nomina;
    }
    pintarNomina(j.rows || []);
    if ($btnGuardar)
      $btnGuardar.disabled = false;
  });

  $tbNom.querySelectorAll('.hx-input, .hx-input-manual').forEach((input, i) => {
    input.addEventListener('input', () => {
      const val = parseFloat(input.value) || 0;
      nomRows[i].horas_extra = val;

      const r = nomRows[i];
      const neto = (+r.salario_base) + (+r.horas_extra) + (+r.bonificaciones) - (+r.deducciones);

      const tr = input.closest('tr');
      tr.children[5].textContent = `$${neto.toFixed(2)}`;
    });
  });

  $tbNom?.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-imprimir-ind');
    if (!btn) return;

    if (!currendtIdNomina) {
      alert('Primero debe calcular y guardar la nomina.');
      return;
    }

    const tr = btn.closest('tr');
    const idEmpleado = tr.dataset.id;

    const params = new URLSearchParams({
      action: 'nomina_comprobantes',
      id_nomina: currendtIdNomina,
      id_empleado: idEmpleado
    });
    const j = await jsonFetch(API, params);

    if (!j.ok) {
      alert(j.msg || 'Error generando comprobante');
      return;
    }

    const w = window.open('', '_blank');
    w.document.write(j.html || '<p>Error generando comprobante</p>');
    w.document.close();
    w.focus();
  });

  $btnGuardar?.addEventListener('click', async () => {
    if (!confirm('Esta seguro de guardar esta nomina?')) return;

    const fd = new FormData();
    fd.append('action', 'nomina_save');
    fd.append('frecuencia', $frecuencia.value);
    fd.append('fecha_inicio', $fechaInicio.value);
    fd.append('fecha_fin', $fechaFin.value);
    fd.append('fecha_pago', $fechaPago.value);


    const j = await jsonFetch(API, fd);
    if (!j.ok) {
      alert(j.msg || 'Error guardando la nomina');
    } else {
      alert(j.msg || 'Nomina guardada con exito');
      $btnGuardar.disabled = true;
    }
  });

  $btnComprob?.addEventListener('click', async () => {
    if (!currendtIdNomina) {
      alert('Primero debe calcular y guardar la nomina.');
      return;
    }
    const j = await jsonFetch(API, new URLSearchParams({
      action: 'nomina_comprobantes',
      id_nomina: currendtIdNomina,
    }));
    if (!j.ok) return alert(j.msg || 'Error generando comprobantes');
    const w = window.open('', '_blank');
    w.document.write(j.html || '<p>Sin datos</p>');
    w.document.close();
    w.focus();
  });

  cargarOptions().then(() => { cargarEmpleados(1); });
});
