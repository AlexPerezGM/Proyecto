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
        const elNombre = document.getElementById('nombre'); if (elNombre) elNombre.value = p.nombre || '';
        const elApellido = document.getElementById('apellido'); if (elApellido) elApellido.value = p.apellido || '';
        const elFN = document.getElementById('fecha_nacimiento'); if (elFN) elFN.value = p.fecha_nacimiento || '';
        const elGenero = document.getElementById('genero_modal'); if (elGenero) elGenero.value = p.genero || '';

        if (p.email) { const el = document.getElementById('email'); if (el) el.value = p.email; }
        if (p.telefono) { const el = document.getElementById('telefono'); if (el) el.value = p.telefono; }
        if (p.ciudad) { const el = document.getElementById('ciudad'); if (el) el.value = p.ciudad; }
        if (p.sector) { const el = document.getElementById('sector'); if (el) el.value = p.sector; }
        if (p.calle) { const el = document.getElementById('calle'); if (el) el.value = p.calle; }
        if (p.numero_casa) { const el = document.getElementById('numero_casa'); if (el) el.value = p.numero_casa; }

        const nEl = document.getElementById('nombre'); if (nEl) nEl.readOnly = true;
        const aEl = document.getElementById('apellido'); if (aEl) aEl.readOnly = true;
        const fEl = document.getElementById('fecha_nacimiento'); if (fEl) fEl.readOnly = true;
        const gEl = document.getElementById('genero_modal'); if (gEl) gEl.readOnly = true;
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
    $boxDocs.innerHTML = '<p>Cargando documentos…</p>';
    cargarDocumentos(idCliente);
  }

  async function cargarDocumentos(id_cliente) {
    if (!$boxDocs) return;
    const res = await jsonFetch(API, new URLSearchParams({
      action: 'list_docs',
      id_cliente
    }));

    if (!res.ok) {
      $boxDocs.innerHTML = '<p>Error cargando documentos</p>';
      return;
    }
    let html = '';
    // En vez de abrir el índice feo de Apache, abrimos un visor bonito
    const viewerUrl = (window.APP_BASE || '/') + 'views/docs_cliente.php?id_cliente=' + encodeURIComponent(id_cliente);
    html += `
      <div class="docs-folder-link">
        <a href="${viewerUrl}" target="_blank" class="btn btn-light">
          Abrir documentos del cliente
        </a>
      </div>
    `;
    const files = Array.isArray(res.files) ? res.files : [];
    if (files.length === 0) {
      html += '<p>No hay documentos cargados.</p>';
    } else {
      html += files.map(f => `
        <div class="doc-item">
          <span>${f.nombre}</span>
          <a href="${f.ruta}" target="_blank" class="btn btn-light">Ver</a>
        </div>
      `).join('');
    }
    $boxDocs.innerHTML = html;
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
        cargarDocumentos(idCliente);
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
    const params = new URLSearchParams({ action: 'get', id_cliente: id });
    const c = await jsonFetch(API, params);

    if (v) {
      const html = `
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
      `;
      document.getElementById('verContenido').innerHTML = html;
      openModal($modalVer);
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
