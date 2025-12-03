(() => {
  const API = (window.APP_BASE || '/') + 'api/clientes.php';
  const $q = document.getElementById('q');
  const $btnBuscar = document.getElementById('btnBuscar');
  const $tabla = document.getElementById('tablaClientes').querySelector('tbody');
  const $paginacion = document.getElementById('paginacion');
  const $err = document.getElementById('errorBox');

  const $inpDoc = document.getElementById('inpDoc') || document.getElementById('numero_documento');
  const $selTipoDoc = document.getElementById('selTipoDoc') || document.getElementById('id_tipo_documento_modal');
  const $inpExistingId = document.getElementById('inpExistingId') || document.getElementById('existing_id_dp');

  // Modales
  const $modalForm = document.getElementById('modalForm');
  const $modalVer = document.getElementById('modalVer');
  const $frm = document.getElementById('frmCliente');
  const $btnAbrirCrear = document.getElementById('btnAbrirCrear');
  const $modalTitulo = document.getElementById('modalTitulo');

  // Controles de documentos
  const $tipoDocCliente = document.getElementById('tipo_doc_cliente');
  const $archivoDoc = document.getElementById('archivo_doc');
  const $btnSubirDoc = document.getElementById('btnSubirDoc');
  const $boxDocs = document.getElementById('boxDocs');

  let currentPage = 1;
  const PAGE_SIZE = 10;
  let isEditMode = false;

  const openModal = (el) => {
    if (!el) return;
    el.classList.add('show');
    try { el.setAttribute('aria-hidden', 'false'); } catch (e) { }
    document.querySelectorAll('.modal').forEach(m => { if (m !== el) m.setAttribute('aria-hidden', 'true'); });
    const focusable = el.querySelector('input,button,select,textarea,[tabindex]:not([tabindex="-1"])');
    if (focusable && typeof focusable.focus === 'function') focusable.focus();
  };

  const closeModal = (el) => {
    if (!el) return;
    const opener = document.getElementById('btnAbrirCrear');
    if (opener && typeof opener.focus === 'function') opener.focus();
    el.classList.remove('show');
    try { el.setAttribute('aria-hidden', 'true'); } catch (e) { }
  };

  document.querySelectorAll('[data-close]').forEach(b => {
    b.addEventListener('click', () => closeModal(b.closest('.modal')));
  });

  window.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal.show').forEach(m => closeModal(m));
    }
  });

  document.getElementById('numero_documento').addEventListener('blur', async () => {
    const raw = document.getElementById('numero_documento').value || '';
    const cedula = raw.replace(/\D/g, '');

    const param = new URLSearchParams({
      action: 'validarCedula',
      cedula: cedula
    });

    const res = await jsonFetch(API, param);
    if (!res.ok) {
      alert(res.msg || 'Error validando cedula');
      return;
    }
  })

  async function jsonFetch(url, body) {
    $err.hidden = true;
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body
      });
      const text = await res.text();
      try {
        return JSON.parse(text);
      } catch (parseErr) {
        $err.hidden = false;
        $err.textContent = 'Respuesta no-JSON de la API:\n' + text.slice(0, 2000);
        throw parseErr;
      }
    } catch (e) {
      if ($err.hidden) {
        $err.hidden = false;
        $err.textContent = 'Error consultando API:\n' + (e.message || e);
      }
      throw e;
    }
  }
  async function verificarTerceros() {
    const numDoc = $inpDoc.value.trim();
    const tipoDoc = $selTipoDoc.value;

    if (isEditMode || !numDoc || !tipoDoc) return;
    $inpExistingId.value = "0";

    try {
      const params = new URLSearchParams({
        action: 'cargar_persona',
        numero_documento: numDoc,
        id_tipo_documento: tipoDoc
      });
      const res = await jsonFetch(API, params);

      if (res.found) {
        if (res.is_client) {
          alert('Ya existe un cliente con este numero de cedula.');
          // implementar para redirigir a edicion si es necesario
          $inpDoc.value = '';
          return;
        }
        const p = res.data;
        if ($inpExistingId) $inpExistingId.value = p.id_datos_persona;
        const elNombre = document.getElementById('nombre');
        if (elNombre) elNombre.value = p.nombre || '';

        const elApellido = document.getElementById('apellido');
        if (elApellido) elApellido.value = p.apellido || '';

        const elFN = document.getElementById('fecha_nacimiento');
        if (elFN) elFN.value = p.fecha_nacimiento || '';

        const elGenero = document.getElementById('genero_modal');
        if (elGenero) elGenero.value = p.genero || '';

        if (p.email) {
          const el = document.getElementById('email');
          if (el) el.value = p.email;
        }
        if (p.telefono) {
          const el = document.getElementById('telefono');
          if (el) el.value = p.telefono;
        }
        if (p.ciudad) {
          const el = document.getElementById('ciudad');
          if (el) el.value = p.ciudad;
        }
        if (p.sector) {
          const el = document.getElementById('sector');
          if (el) el.value = p.sector;
        }
        if (p.calle) {
          const el = document.getElementById('calle');
          if (el) el.value = p.calle;
        }
        if (p.numero_casa) {
          const el = document.getElementById('numero_casa');
          if (el) el.value = p.numero_casa;
        }

        const nEl = document.getElementById('nombre');
        if (nEl) nEl.readOnly = true;
        const aEl = document.getElementById('apellido');
        if (aEl) aEl.readOnly = true;
        const fEl = document.getElementById('fecha_nacimiento');
        if (fEl) fEl.readOnly = true;
        const gEl = document.getElementById('genero_modal');
        if (gEl) gEl.readOnly = true;
      } else {
        $inpExistingId.value = "0";
        document.getElementById('nombre').readOnly = false;
        document.getElementById('apellido').readOnly = false;
        document.getElementById('fecha_nacimiento').readOnly = false;
        document.getElementById('genero_modal').readOnly = false;
      }
    } catch (e) {
      console.error('Error verificando persona: ', e)
    }
  }
  if ($inpDoc) {
    $inpDoc.addEventListener('blur', verificarTerceros);
  }

  // Catálogos
  async function cargarCatalogos() {
    const cats = await jsonFetch(API, new URLSearchParams({ action: 'catalogos' }));
    const $gen = document.getElementById('genero_modal');
    const $td = document.getElementById('id_tipo_documento_modal');
    if (Array.isArray(cats.generos) && $gen) {
      $gen.innerHTML = '<option value="">Seleccione…</option>' +
        cats.generos.map(g => `<option value="${g.id_genero}">${g.genero}</option>`).join('');
    }
    if (Array.isArray(cats.tipos_documento) && $td) {
      $td.innerHTML = '<option value="">Seleccione…</option>' +
        cats.tipos_documento.map(t => `<option value="${t.id_tipo_documento}">${t.tipo_documento}</option>`).join('');
    }
  }
  document.addEventListener('DOMContentLoaded', cargarCatalogos);

  // Documentos del cliente
  function resetDocsUIForCreate() {
    if (!$tipoDocCliente || !$archivoDoc || !$btnSubirDoc || !$boxDocs) return;
    isEditMode = false;
    $tipoDocCliente.disabled = false;
    $tipoDocCliente.value = '';
    $archivoDoc.disabled = false;
    $archivoDoc.value = '';
    // En registro, el doc se sube al guardar (no con el botón)
    $btnSubirDoc.disabled = true;
  }

  function prepareDocsUIForEdit(idCliente) {
    if (!$tipoDocCliente || !$archivoDoc || !$btnSubirDoc || !$boxDocs) return;
    isEditMode = true;
    $tipoDocCliente.disabled = false;
    $tipoDocCliente.value = '';
    $archivoDoc.disabled = true;
    $archivoDoc.value = '';
    $btnSubirDoc.disabled = true;

    const viewerUrl = (window.APP_BASE || '/') +
      'views/docs_cliente.php?id_cliente=' + encodeURIComponent(idCliente);

    $boxDocs.innerHTML = `
      <div class="docs-folder-link">
        <button type="button" id="btnAbrirDocsCliente" class="btn btn-light">
          Abrir documentos del cliente
        </button>
      </div>
    `;

    const btnAbrir = document.getElementById('btnAbrirDocsCliente');
    if (btnAbrir) {
      btnAbrir.addEventListener('click', () => {
        window.open(viewerUrl, '_blank');
      });
    }
  }

  async function cargarDocumentos(id_cliente) {
    if (!$boxDocs) return;
    return;
  }

  async function subirDocumentoCliente(idCliente, tipo, file, extraData = {}) {
    const fd = new FormData();
    fd.append('action', 'upload_doc');
    fd.append('id_cliente', idCliente);
    fd.append('tipo_archivo', tipo);
    fd.append('archivo', file);

    if (extraData.nombre) fd.append('nombre', extraData.nombre);
    if (extraData.apellido) fd.append('apellido', extraData.apellido);
    if (extraData.numero_documento) fd.append('numero_documento', extraData.numero_documento);

    const res = await jsonFetch(API, fd);
    if (!res.ok) {
      throw new Error(res.error || res.msg || 'Error al subir documento');
    }
    return res;
  }

  if ($tipoDocCliente && $archivoDoc && $btnSubirDoc) {
    $tipoDocCliente.addEventListener('change', () => {
      if ($tipoDocCliente.value) {
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
        alert('En registro nuevo, el documento se sube al guardar el cliente.');
        return;
      }
      const idCliente = document.getElementById('id_cliente').value;
      if (!idCliente) {
        alert('Primero guarda el cliente antes de subir documentos.');
        return;
      }
      if (!$tipoDocCliente.value) {
        alert('Selecciona el tipo de documento.');
        return;
      }
      if (!$archivoDoc.files.length) {
        alert('Selecciona un archivo.');
        return;
      }

      try {
        await subirDocumentoCliente(idCliente, $tipoDocCliente.value, $archivoDoc.files[0]);
        $archivoDoc.value = '';
        $btnSubirDoc.disabled = true;
      } catch (e) {
        alert(e.message);
      }
    });
  }
  // Listado principal
  async function cargar(page = 1) {
    currentPage = page;
    const params = new URLSearchParams({
      action: 'list',
      q: $q.value.trim(),
      page,
      size: PAGE_SIZE
    });
    const json = await jsonFetch(API, params);
    const rows = json.data || [];
    $tabla.innerHTML = rows.map(r => `
      <tr>
        <td>${r.id_cliente}</td>
        <td>${r.nombre} ${r.apellido}</td>
        <td>${r.numero_documento ?? '-'}</td>
        <td>${r.email ?? '-'}</td>
        <td>${r.telefono ?? '-'}</td>
        <td>${r.estado_prestamo}</td>
        <td>${(r.creado_en || '').slice(0, 10)}</td>
        <td>
          <button class="btn btn-light" data-ver="${r.id_cliente}">Ver</button>
          <button class="btn" data-editar="${r.id_cliente}">Modificar</button>
          <button class="btn btn-danger" data-borrar="${r.id_cliente}">Eliminar</button>
        </td>
      </tr>
    `).join('');

    const total = +json.total || 0;
    const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    let html = '';
    for (let p = 1; p <= pages; p++) {
      html += `<button ${p === page ? 'class="active"' : ''} data-page="${p}">${p}</button>`;
    }
    $paginacion.innerHTML = html;
  }

  $paginacion.addEventListener('click', e => {
    const b = e.target.closest('button[data-page]');
    if (!b) return;
    cargar(+b.dataset.page);
  });

  $btnBuscar.addEventListener('click', () => cargar(1));
  $q.addEventListener('keydown', e => {
    if (e.key === 'Enter') cargar(1);
  });

  $btnAbrirCrear.addEventListener('click', () => {
    if ($frm) {
      $frm.reset();
      const act = $frm.querySelector('[name="action"]'); if (act) act.value = 'create';
    }
    const idClienteEl = document.getElementById('id_cliente'); if (idClienteEl) idClienteEl.value = '';
    $modalTitulo.textContent = 'Registrar cliente';
    resetDocsUIForCreate();
    openModal($modalForm);
    if ($inpExistingId) $inpExistingId.value = "0";
    const n = document.getElementById('nombre'); if (n) n.readOnly = false;
    const a = document.getElementById('apellido'); if (a) a.readOnly = false;
    const f = document.getElementById('fecha_nacimiento'); if (f) f.readOnly = false;
    const gm = document.getElementById('genero_modal');
    if (gm) gm.disabled = false;
  });

  document.addEventListener('click', async (e) => {
    const v = e.target.closest('[data-ver]');
    const ed = e.target.closest('[data-editar]');
    const del = e.target.closest('[data-borrar]');
    if (!v && !ed && !del) return;

    const id = v ? v.dataset.ver : (ed ? ed.dataset.editar : del.dataset.borrar);
    const params = new URLSearchParams({
      action: 'get',
      id_cliente: id
    });
    const c = await jsonFetch(API, params);

    if (v) {
      const html = `
        <style>
          .tab-nav {display: flex; border-bottom: 2px solid #eee; margin-bottom: 15px;}
          .tab-nav button { flex: 1; padding: 10px; border: none; background: none; cursor: pointer; font-weight: bold; color: #666;}
          .tab-nav button.active { border-bottom: 3px solid #0d6efd; color: #0d63fd;}
          .tab-pane { display: none; }
          .tab-pane.active { display: block; }
          .promo-box { padding: 10px; background: #d1e7dd; color: #0f5132; border-radius: 5px; margin-bottom: 10px; }
          .alert-box { padding: 10px; background: #f8d7da; color: #842029; border-radius: 5px; margin-bottom: 10px; }
        </style>
        <div class="tab-nav">
            <button type="button" class="active" onclick="verTab('datos', null, this)">Datos Personales</button>
            <button type="button" onclick="verTab('prestamos', ${c.id_cliente}, this)">Préstamos</button>
        </div>

        <div id="view-datos" class="tab-pane active">
          <div class="grid-2">
            <div>
              <h4>Información personal</h4>
              <p><b>Nombre:</b> ${c.nombre} ${c.apellido}</p>
              <p><b>Fecha nac.:</b> ${c.fecha_nacimiento || ''}</p>
              <p><b>Género:</b> ${c.genero_txt || ''}</p>
              <p><b>Documento:</b> ${c.tipo_documento || ''} ${c.numero_documento || ''}</p>
              <p><b>Estado:</b> ${c.estado_prestamo || ''}</p>
            </div>
            <div>
              <h4>Contacto</h4>
              <p><b>Email:</b> ${c.email || '-'}</p>
              <p><b>Teléfono:</b> ${c.telefono || '-'}</p>
              <h4>Dirección</h4>
              <p>${c.ciudad || ''}, ${c.sector || ''}, ${c.calle || ''} #${c.numero_casa || ''}</p>
            </div>
            <div>
              <h4>Información financiera</h4>
              <p><b>Ingresos:</b> $${(+c.ingresos_mensuales || 0).toFixed(2)}</p>
              <p><b>Egresos:</b> $${(+c.egresos_mensuales || 0).toFixed(2)}</p>
              <p><b>Fuente:</b> ${c.fuente || '-'}</p>
              <p><b>Ocupación:</b> ${c.ocupacion || '-'} (${c.empresa || '-'})</p>
            </div>
            <div>
              <h4>Ver docuementos</h4>
              <button class="btn btn-light" onclick="window.open('${(window.APP_BASE || '/') + 'views/docs_cliente.php?id_cliente=' + encodeURIComponent(c.id_cliente)}', '_blank')">Abrir documentos del cliente</button>
            </div>
          </div>
        </div>
        <div id="view-prestamos" class="tab-pane">
            <div id="loader-prestamos" class="text-center">Cargando historial del cliente...</div>
            <div id="content-prestamos"></div>
        </div>
      `;
      document.getElementById('verContenido').innerHTML = html;
      openModal($modalVer);

      window.verTab = async (tabName, id_cliente = null, btn = null) => {
        document.querySelectorAll('.tab-nav button').forEach(b => b.classList.remove('active'));
        if (btn && typeof btn.classList !== 'undefined') {
          btn.classList.add('active');
        } else {
          // fallback: try to find a matching button
          const fallback = document.querySelector(`.tab-nav button[onclick*="${tabName}"]`);
          if (fallback) fallback.classList.add('active');
        }
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        const view = document.getElementById('view-' + tabName);
        if (view) view.classList.add('active');

        if (tabName === 'prestamos' && id_cliente) {
          const contentDiv = document.getElementById('content-prestamos');
          const loader = document.getElementById('loader-prestamos');

          if (contentDiv.innerHTML.trim() !== "") return;

          try {
            const API_Prestamos = (window.APP_BASE || '/') + 'api/prestamos.php';

            const formData = new FormData();
            formData.append('action', 'historial_cliente');
            formData.append('id_cliente', id_cliente);

            const res = await fetch(API_Prestamos, { method: 'POST', body: formData });
            const textRes = await res.text();
            let data;
            try {
              data = JSON.parse(textRes);
            } catch (errJson) {
              loader.style.display = 'none';
              loader.innerHTML = `<p style="color:red"> Error cargando historial: respuesta inválida del servidor.</p>`;
              console.error('Respuesta no-JSON de historial_cliente:', textRes);
              return;
            }

            if (!data || !data.ok) {
              loader.style.display = 'none';
              const msg = (data && (data.error || data.msg)) ? (data.error || data.msg) : 'Error al cargar';
              loader.innerHTML = `<p style="color:red"> Error cargando historial: ${msg}</p>`;
              console.error('Error en historial_cliente:', msg, data);
              return;
            }

            loader.style.display = 'none';

            let html = '';
            html += `<h4>Historial de préstamos activos</h4>`;

            html += `<table class="table-simple" style="width:100%; font-size:0.9em">
                      <thead>
                          <tr>
                              <th>Fecha de solicitud</th>
                              <th>Número de contrato</th>
                              <th>Monto solicitado</th>
                              <th>Saldo total pendiente</th>
                              <th>Estado del préstamo</th>
                              <th>Cuota a pagar</th>
                              <th>Cuotas pagadas</th>
                              <th>Pagos</th>
                          </tr>
                      </thead>
                      <tbody>`;
            if (data.historial.length === 0) {
              html += `<tr><td colspan="6" class="text-center">No hay historial de préstamos.</td></tr>`;
            } else {
              data.historial.forEach(p => {
                const fecha = p.fecha_solicitud ? String(p.fecha_solicitud).slice(0, 10) : '';
                const color = p.estado === 'Activo' ? 'blue' : (p.estado === 'Pagado' ? 'green' : 'red');
                const atrasoHtml = (p.cuotas_atrasadas && p.cuotas_atrasadas > 0)
                  ? `<span style="color:red; font-weight:bold">${p.cuotas_atrasadas} Cuotas</span>`
                  : `<span style="color:green">Al dia</span>`;
                html += `
                        <tr>
                          <td>${fecha}</td>
                          <td>${p.numero_contrato || ''}</td>
                          <td>$${parseFloat(p.monto_solicitado || 0).toFixed(2)}</td>
                          <td>$${parseFloat(p.saldo_actual || 0).toFixed(2)}</td>
                          <td><span style="color:${color}">${p.estado}</span></td>
                          <td>$${parseFloat(p.total_a_pagar || 0).toFixed(2)}</td>
                          <td>${p.cuotas_pagadas || 0}</td>
                          <td>${atrasoHtml}</td>
                        </tr>
                    `;
              });
            }
            html += `</tbody></table>`;
            contentDiv.innerHTML = html;
          } catch (e) {
            loader.innerHTML = `<p style="color:red"> Error cargando historial: ${e.message}</p>`;
            console.error(e);
          }
        }
      };
    }

    if (ed) {
      if ($frm) {
        $frm.reset();
        const act = $frm.querySelector('[name="action"]'); if (act) act.value = 'update';
      }
      $modalTitulo.textContent = 'Modificar cliente';
      const map = {
        id_cliente: 'id_cliente', nombre: 'nombre', apellido: 'apellido',
        fecha_nacimiento: 'fecha_nacimiento', genero: 'genero_modal',
        id_tipo_documento: 'id_tipo_documento_modal', numero_documento: 'numero_documento',
        telefono: 'telefono', email: 'email',
        ciudad: 'ciudad', sector: 'sector', calle: 'calle', numero_casa: 'numero_casa',
        ingresos_mensuales: 'ingresos_mensuales', fuente: 'fuente_ingresos',
        egresos_mensuales: 'egresos_mensuales', ocupacion: 'ocupacion', empresa: 'empresa'
      };
      for (const k in map) {
        const el = document.getElementById(map[k]); if (el) el.value = c[k] ?? '';
      }
      prepareDocsUIForEdit(c.id_cliente);
      openModal($modalForm);
    }

    if (del) {
      if (!confirm('¿Eliminar este cliente? Esta acción no se puede deshacer.')) return;
      const paramsDel = new URLSearchParams({ action: 'delete', id_cliente: id });
      const r = await jsonFetch(API, paramsDel);
      if (!r.ok) {
        alert(r.error || r.msg || 'Error al eliminar');
        return;
      }
      cargar(currentPage);
      return;
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

  $frm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fn = document.getElementById('fecha_nacimiento').value;
    if (!esMayorDeEdad(fn)) return alert('El cliente debe ser mayor de edad.');
    const em = document.getElementById('email').value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) return alert('Email no válido.');

    const ing = +document.getElementById('ingresos_mensuales').value;
    const egr = +document.getElementById('egresos_mensuales').value;
    if (isNaN(ing) || isNaN(egr) || ing < 10000 || ing <= egr) {
      return alert('Verifica ingresos/egresos (ingresos ≥ 10,000 y mayores que egresos).');
    }

    const formData = new FormData($frm);
    const mode = formData.get('action');

    const json = await jsonFetch(API, formData);
    if (!json.ok) {
      alert(json.msg || json.error || 'Error en la operación');
      return;
    }

    if (mode === 'create' && $tipoDocCliente && $archivoDoc && $tipoDocCliente.value && $archivoDoc.files.length) {
      const idCliente = json.id_cliente;
      const nombre = document.getElementById('nombre').value;
      const apellido = document.getElementById('apellido').value;
      const numeroDoc = document.getElementById('numero_documento').value;
      try {
        await subirDocumentoCliente(idCliente, $tipoDocCliente.value, $archivoDoc.files[0], {
          nombre,
          apellido,
          numero_documento: numeroDoc
        });
      } catch (e2) {
        alert('El cliente se guardó, pero el documento dio error: ' + e2.message);
      }
    }

    closeModal($modalForm);
    cargar(currentPage);
  });

  cargar(1);
})();
