(() => {
  const API = (window.APP_BASE || '/') + 'api/prestamos.php';
  const API_CLIENTES = (window.APP_BASE || '/') + 'api/clientes.php';

  const $err = document.getElementById('errorBox');
  const openModal = el => el.classList.add('show');
  const closeModal = el => el.classList.remove('show');
  document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => closeModal(b.closest('.modal'))));
  window.addEventListener('keydown', e => { if (e.key === 'Escape') { document.querySelectorAll('.modal.show').forEach(m => closeModal(m)); } });

  async function jsonFetch(url, body) {
    if ($err) $err.hidden = true;
    try {
      const res = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body });
      const text = await res.text();
      try { return JSON.parse(text); }
      catch (parseErr) { if ($err) { $err.hidden = false; $err.textContent = 'Respuesta no-JSON de la API:\n' + text.slice(0, 2000); } throw parseErr; }
    } catch (e) { if (!$err || ($err && $err.hidden)) { if ($err) { $err.hidden = false; $err.textContent = 'Error consultando API:\n' + (e.message || e); } else { console.error('Error consultando API:', e); } } throw e; }
  }
  // Catálogos y moneda
  const $selMoneda = document.getElementById('selMoneda');
  let MONEDAS = [];
  let PERIODOS = [];
  let AMORTIZACION = [];
  let GARANTIAS = [];

  async function cargarCatalogos() {
    const js = await jsonFetch(API, new URLSearchParams({ action: 'catalogos' }));
    MONEDAS = js.data?.monedas || [];
    PERIODOS = js.data?.periodos || [];
    AMORTIZACION = js.data?.amortizacion || [];
    GARANTIAS = js.data?.garantias || [];
    $selMoneda.innerHTML = MONEDAS.map(m => `<option value="${m.id}">${m.txt}</option>`).join('');
    document.getElementById('per_personal').innerHTML = PERIODOS.map(p => `<option value="${p.id}">${p.txt}</option>`).join('');
    document.getElementById('per_hipo').innerHTML = PERIODOS.map(p => `<option value="${p.id}">${p.txt}</option>`).join('');
    document.getElementById('amort_personal').innerHTML = AMORTIZACION.map(a => `<option value="${a.id}">${a.txt}</option>`).join('');
    document.getElementById('amort_hipo').innerHTML = AMORTIZACION.map(a => `<option value="${a.id}">${a.txt}</option>`).join('');
    const garOpts = GARANTIAS.map(g => `<option value="${g.txt}">${g.txt}</option>`).join('');
    const $garP = document.getElementById('garantia_personal'); if ($garP) $garP.innerHTML = `<option value="">Seleccionar...</option>` + garOpts;
    const $garH = document.getElementById('garantia_hipo'); if ($garH) $garH.innerHTML = `<option value="">Seleccionar...</option>` + garOpts;
    // valores por defecto
    const defs = js.data?.defaults || [];
    const pers = defs.find(d => +d.id_tipo_prestamo === 1);
    const hipo = defs.find(d => +d.id_tipo_prestamo === 2);
    if (pers) {
      document.getElementById('tasa_personal').value = pers.tasa_interes;
      document.getElementById('monto_personal').placeholder = `≥ ${(+pers.monto_minimo).toFixed(2)} DOP`;
      document.getElementById('amort_personal').value = pers.id_tipo_amortizacion || 1;

      const $plP = document.getElementById('plazo_personal');
      if ($plP) {
        const min = parseInt(pers.plazo_minimo_meses || '6', 10);
        const max = parseInt(pers.plazo_maximo_meses || '60', 10);
        const opts = [];
        for (let i = min; i <= max; i++) opts.push(`<option value="${i}">${i} meses</option>`);
        $plP.innerHTML = `<option value="">Seleccionar...</option>` + opts.join('');
      }
    }
    if (hipo) {
      document.getElementById('tasa_hipo').value = hipo.tasa_interes;
      document.getElementById('monto_hipo').placeholder = `≥ ${(+hipo.monto_minimo).toFixed(2)} DOP`;
      document.getElementById('amort_hipo').value = hipo.id_tipo_amortizacion || 2;
      // llenar plazos hipotecarios
      const $plH = document.getElementById('plazo_hipo');
      if ($plH) {
        const min = parseInt(hipo.plazo_minimo_meses || '12', 10);
        const max = parseInt(hipo.plazo_maximo_meses || '360', 10);
        const opts = [];
        for (let i = min; i <= max; i++) opts.push(`<option value="${i}">${i} meses</option>`);
        $plH.innerHTML = `<option value="">Seleccionar...</option>` + opts.join('');
      }
    }
  }

  // Buscar/seleccionar cliente
  const $qC = document.getElementById('qCliente');
  const $btnBuscarC = document.getElementById('btnBuscarCliente');
  const $resC = document.getElementById('resClientes');
  const $boxInfoC = document.getElementById('boxInfoCliente');
  const $infoGrid = document.getElementById('infoClienteGrid');
  let CLIENTE = null;

  // Configuración de documentos por tipo de préstamo
  const DOC_TYPES_PERSONAL = [
    { value: 'CEDULA', label: 'Cédula / documento de identidad' },
    { value: 'PASAPORTE', label: 'Pasaporte' },
    { value: 'CONTRATO', label: 'Contrato de préstamo personal' },
    { value: 'OTRO', label: 'Otro documento de respaldo' }
  ];

  const DOC_TYPES_HIPO = [
    { value: 'CEDULA', label: 'Cédula / documento de identidad' },
    { value: 'SEGURO', label: 'Seguro del inmueble' },
    { value: 'CONTRATO', label: 'Contrato de préstamo hipotecario' },
    { value: 'LICENCIA', label: 'Licencia de conducir' },
    { value: 'SEGURO', label: 'Póliza de seguro (vehículo/vida)' },
    { value: 'OTRO', label: 'Otros documentos (título, tasación, etc.)' }
  ];

  const docPersonal = {
    tipo: document.getElementById('tipoDocPersonal'),
    archivo: document.getElementById('archivoDocPersonal'),
    subir: document.getElementById('btnSubirDocPersonal'),
    ver: document.getElementById('btnVerDocsPersonal'),
    box: document.getElementById('boxDocsPersonal')
  };

  const docHipotecario = {
    tipo: document.getElementById('tipoDocHipotecario'),
    archivo: document.getElementById('archivoDocHipotecario'),
    subir: document.getElementById('btnSubirDocHipotecario'),
    ver: document.getElementById('btnVerDocsHipotecario'),
    box: document.getElementById('boxDocsHipotecario')
  };

  function initDocSelect(doc, tipos) {
    if (!doc.tipo) return;
    doc.tipo.innerHTML = '<option value=\"\">Seleccione...</option>' +
      tipos.map(t => `<option value="${t.value}">${t.label}</option>`).join('');
    doc.tipo.disabled = true;
    if (doc.archivo) doc.archivo.disabled = true;
    if (doc.subir) doc.subir.disabled = true;
    if (doc.ver) doc.ver.disabled = true;
    if (doc.box) doc.box.textContent = '';
  }

  initDocSelect(docPersonal, DOC_TYPES_PERSONAL);
  initDocSelect(docHipotecario, DOC_TYPES_HIPO);

  function docsViewerUrl() {
    if (!CLIENTE || !CLIENTE.id_cliente) return null;
    return (window.APP_BASE || '/') + 'views/docs_cliente.php?id_cliente=' + encodeURIComponent(CLIENTE.id_cliente);
  }

  async function subirDocumentoClienteDesdePrestamo(tipo_archivo, file, destinoBox) {
    if (!CLIENTE || !CLIENTE.id_cliente) {
      alert('Primero selecciona un cliente.');
      return;
    }
    if (!tipo_archivo) {
      alert('Selecciona un tipo de documento.');
      return;
    }
    if (!file) {
      alert('Selecciona un archivo.');
      return;
    }

    const fd = new FormData();
    fd.append('action', 'upload_doc');
    fd.append('id_cliente', CLIENTE.id_cliente);
    fd.append('tipo_archivo', tipo_archivo);
    fd.append('archivo', file);

    // Info básica para nombres de archivos
    if (CLIENTE.nombre) {
      const partes = CLIENTE.nombre.split(' ');
      fd.append('nombre', partes[0] || CLIENTE.nombre);
      fd.append('apellido', partes.slice(1).join(' ') || '');
    }
    if (CLIENTE.documento && CLIENTE.documento !== '-') {
      fd.append('numero_documento', CLIENTE.documento);
    }

    const js = await jsonFetch(API_CLIENTES, fd);
    if (!js.ok) {
      throw new Error(js.msg || js.error || 'Error al subir el documento');
    }

    if (destinoBox) {
      const url = docsViewerUrl();
      destinoBox.innerHTML = `
        <span>📎 Documento subido correctamente.</span>
        ${url ? `<div class="docs-folder-link" style="margin-top:4px;">
          <a href="${url}" target="_blank" class="btn btn-light btn-xs">Ver documentos del cliente</a>
        </div>` : ''}
      `;
    }
    return js;
  }
  // Eventos UI documentos - Personal
  if (docPersonal.tipo && docPersonal.archivo && docPersonal.subir) {
    docPersonal.tipo.addEventListener('change', () => {
      const hasTipo = !!docPersonal.tipo.value;
      docPersonal.archivo.disabled = !hasTipo;
      docPersonal.archivo.value = '';
      docPersonal.subir.disabled = true;
    });

    docPersonal.archivo.addEventListener('change', () => {
      const hasFile = docPersonal.archivo.files && docPersonal.archivo.files.length > 0;
      docPersonal.subir.disabled = !(hasFile && docPersonal.tipo.value);
    });

    docPersonal.subir.addEventListener('click', async () => {
      try {
        const file = docPersonal.archivo.files[0];
        docPersonal.subir.disabled = true;
        await subirDocumentoClienteDesdePrestamo(docPersonal.tipo.value, file, docPersonal.box);
        docPersonal.archivo.value = '';
        docPersonal.subir.disabled = true;
        alert('Documento subido correctamente.');
      } catch (e) {
        console.error(e);
        alert(e.message || 'Error al subir el documento.');
      }
    });
  }

  if (docPersonal.ver) {
    docPersonal.ver.addEventListener('click', () => {
      const url = docsViewerUrl();
      if (!url) {
        alert('Primero selecciona un cliente.');
        return;
      }
      window.open(url, '_blank');
    });
  }
  // Eventos UI documentos - Hipotecario
  if (docHipotecario.tipo && docHipotecario.archivo && docHipotecario.subir) {
    docHipotecario.tipo.addEventListener('change', () => {
      const hasTipo = !!docHipotecario.tipo.value;
      docHipotecario.archivo.disabled = !hasTipo;
      docHipotecario.archivo.value = '';
      docHipotecario.subir.disabled = true;
    });

    docHipotecario.archivo.addEventListener('change', () => {
      const hasFile = docHipotecario.archivo.files && docHipotecario.archivo.files.length > 0;
      docHipotecario.subir.disabled = !(hasFile && docHipotecario.tipo.value);
    });

    docHipotecario.subir.addEventListener('click', async () => {
      try {
        const file = docHipotecario.archivo.files[0];
        docHipotecario.subir.disabled = true;
        await subirDocumentoClienteDesdePrestamo(docHipotecario.tipo.value, file, docHipotecario.box);
        docHipotecario.archivo.value = '';
        docHipotecario.subir.disabled = true;
        alert('Documento subido correctamente.');
      } catch (e) {
        console.error(e);
        alert(e.message || 'Error al subir el documento.');
      }
    });
  }

  if (docHipotecario.ver) {
    docHipotecario.ver.addEventListener('click', () => {
      const url = docsViewerUrl();
      if (!url) {
        alert('Primero selecciona un cliente.');
        return;
      }
      window.open(url, '_blank');
    });
  }

  function habilitarUI_docsParaCliente() {
    [docPersonal, docHipotecario].forEach(doc => {
      if (!doc.tipo) return;
      doc.tipo.disabled = false;
      if (doc.ver) doc.ver.disabled = false;
      if (doc.box && CLIENTE) {
        doc.box.innerHTML = `
          <small class="mini">
            Los documentos se guardarán en el expediente de <b>${CLIENTE.nombre}</b>.
          </small>
        `;
      }
    });
  }
  // búsqueda clientes
  $qC?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); $btnBuscarC?.click(); }
  });

  $btnBuscarC?.addEventListener('click', async () => {
    const q = $qC.value.trim();
    if (!q) {
      alert('Por favor ingrese un nombre o número de cédula para buscar');
      return;
    }
    const js = await jsonFetch(API, new URLSearchParams({ action: 'buscar_cliente', q }));
    $resC.innerHTML = `
      <table class="table-simple"><thead><tr>
        <th>ID</th><th>Nombre</th><th>Documento</th><th>Email</th><th>Teléfono</th><th>Ingresos</th><th></th>
      </tr></thead><tbody>
      ${js.data.map(r => `
        <tr>
          <td>${r.id_cliente}</td>
          <td>${r.nombre} ${r.apellido}</td>
          <td>${r.numero_documento ?? '-'}</td>
          <td>${r.email ?? '-'}</td>
          <td>${r.telefono ?? '-'}</td>
          <td>$${(+r.ingresos_mensuales || 0).toFixed(2)}</td>
          <td><button class="btn btn-light" data-sel="${r.id_cliente}">Seleccionar</button></td>
        </tr>`).join('')}
      </tbody></table>
    `;
  });

  document.getElementById('resClientes')?.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-sel]'); if (!b) return;
    const id_cliente = +b.dataset.sel;
    const q = $qC.value.trim();
    const js = await jsonFetch(API, new URLSearchParams({ action: 'buscar_cliente', q }));
    const clienteData = js.data.find(r => r.id_cliente === id_cliente);
    if (!clienteData) {
      alert('Error al obtener los datos del cliente');
      return;
    }
    CLIENTE = {
      id_cliente: clienteData.id_cliente,
      nombre: `${clienteData.nombre} ${clienteData.apellido}`,
      documento: clienteData.numero_documento || '-',
      email: clienteData.email || '-',
      telefono: clienteData.telefono || '-',
      ingresos: `$${(+clienteData.ingresos_mensuales || 0).toFixed(2)}`,
      fecha_nacimiento: clienteData.fecha_nacimiento || '-',
      direccion: clienteData.direccion_completa || '-',
      ocupacion: clienteData.ocupacion || '-',
      empresa: clienteData.empresa || '-'
    };
    $boxInfoC.classList.remove('hidden');
    $infoGrid.innerHTML = `
      <div class="info-group">
        <strong>Nombre:</strong>
        <div>${CLIENTE.nombre}</div>
      </div>
      <div class="info-group">
        <strong>Fecha de Nacimiento:</strong>
        <div>${CLIENTE.fecha_nacimiento}</div>
      </div>
      <div class="info-group">
        <strong>Dirección:</strong>
        <div>${CLIENTE.direccion}</div>
      </div>
      <div class="info-group">
        <strong>Teléfono:</strong>
        <div>${CLIENTE.telefono}</div>
      </div>
      <div class="info-group">
        <strong>Ingresos Mensuales:</strong>
        <div>${CLIENTE.ingresos}</div>
      </div>
      <div class="info-group">
        <strong>Email:</strong>
        <div>${CLIENTE.email}</div>
      </div>
      <div class="info-group">
        <strong>Ocupación:</strong>
        <div>${CLIENTE.ocupacion}${CLIENTE.empresa !== '-' ? ` - ${CLIENTE.empresa}` : ''}</div>
      </div>
      <div class="info-group">
        <strong>Cédula:</strong>
        <div>${CLIENTE.documento}</div>
      </div>
    `;
    const icp = document.getElementById('id_cliente_personal'); if (icp) icp.value = CLIENTE.id_cliente;
    const ich = document.getElementById('id_cliente_hipo'); if (ich) ich.value = CLIENTE.id_cliente;
    habilitarUI_docsParaCliente();
  });

  document.getElementById('btnAbrirCrearCliente')?.addEventListener('click', () => openModal(document.getElementById('modalCrearCliente')));
  document.getElementById('frmClienteQuick')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const js = await jsonFetch(API_CLIENTES, new FormData(e.target));
    if (!js.ok) return alert(js.msg || 'Error');
    closeModal(document.getElementById('modalCrearCliente'));
    $btnBuscarC?.click();
  });
  // Abrir modales de solicitud
  document.getElementById('btnPrestamoPersonal')?.addEventListener('click', () => {
    const fp = document.getElementById('frmPersonal');
    if (fp) fp.querySelector('[name="fecha_solicitud"]').value = new Date().toISOString().split('T')[0];
    openModal(document.getElementById('modalPersonal'));
  });
  document.getElementById('btnPrestamoHipotecario')?.addEventListener('click', () => {
    const fh = document.getElementById('frmHipotecario');
    if (fh) fh.querySelector('[name="fecha_solicitud"]').value = new Date().toISOString().split('T')[0];
    openModal(document.getElementById('modalHipotecario'));
    try { actualizarPorcentajeHipotecario(); } catch (_) { }
  });
  document.getElementById('btnMinPersonal')?.addEventListener('click', () => { });
  document.getElementById('btnMinHipotecario')?.addEventListener('click', () => { });
  // Envío solicitudes
  function withMoneda(fd) {
    fd.set('id_tipo_moneda', $selMoneda.value || '1');
    return fd;
  }
  document.getElementById('frmPersonal')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const js = await jsonFetch(API, withMoneda(new FormData(e.target)));
    if (!js.ok) return alert(js.msg || 'Error');
    closeModal(document.getElementById('modalPersonal'));
    alert(`Préstamo creado: #${js.id_prestamo}\nContrato: ${js.numero_contrato || 'N/A'}`);
    cargarPrestamos(1);
  });
  document.getElementById('frmHipotecario')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const porc = parseFloat((document.getElementById('porc_fin')?.value) || '0');
    if (porc > 80) return alert('El porcentaje no puede exceder 80%');
    const js = await jsonFetch(API, withMoneda(new FormData(e.target)));
    if (!js.ok) return alert(js.msg || 'Error');
    closeModal(document.getElementById('modalHipotecario'));
    alert(`Préstamo creado: #${js.id_prestamo}\nContrato: ${js.numero_contrato || 'N/A'}`);
    cargarPrestamos(1);
  });

  function actualizarPorcentajeHipotecario() {
    const $m = document.getElementById('monto_hipo');
    const $v = document.getElementById('valor_inmueble');
    const $p = document.getElementById('porc_fin');
    if (!$m || !$v || !$p) return;
    const monto = parseFloat(($m.value || '').replace(',', '.'));
    const valor = parseFloat(($v.value || '').replace(',', '.'));
    if (!isFinite(monto) || !isFinite(valor) || valor <= 0) {
      $p.value = '';
      $p.setCustomValidity('');
      return;
    }
    const pct = (monto / valor) * 100;
    const pct2 = Math.round(pct * 100) / 100;
    $p.value = pct2.toFixed(2);
    if (pct2 > 80) {
      $p.setCustomValidity('El porcentaje supera el máximo permitido (80%)');
    } else {
      $p.setCustomValidity('');
    }
  }
  const $montoH = document.getElementById('monto_hipo');
  const $valorInm = document.getElementById('valor_inmueble');
  const $porcFin = document.getElementById('porc_fin');
  if ($porcFin) { $porcFin.readOnly = true; }
  $montoH?.addEventListener('input', actualizarPorcentajeHipotecario);
  $valorInm?.addEventListener('input', actualizarPorcentajeHipotecario);
  // paginacion
  const PAGE = { cur: 1, size: 10 };
  const $tblP = document.querySelector('#tablaPrestamos tbody');
  const $pager = document.getElementById('pagPrestamos');
  async function cargarPrestamos(page = 1) {
    PAGE.cur = page;
    const fd = new URLSearchParams({
      action: 'list',
      q: (document.getElementById('qPrestamo')?.value || '').trim(),
      tipo: document.getElementById('fTipoPrestamo')?.value || '',
      page, size: PAGE.size
    });
    const js = await jsonFetch(API, fd);
    $tblP.innerHTML = (js.data || []).map(r => `
      <tr>
        <td>${r.id_prestamo}</td>
        <td>${r.nombre} ${r.apellido}</td>
        <td>${r.tipo_prestamo}</td>
        <td>$${(+r.monto_solicitado).toFixed(2)}</td>
        <td>${(+r.tasa_interes || 0).toFixed(2)}%</td>
        <td>${r.plazo_meses} m</td>
        <td>${r.estado_prestamo || '-'}</td>
        <td>${r.proximo_pago || '-'}</td>
        <td><button class="btn btn-light" data-verp="${r.id_prestamo}">Ver</button></td>
      </tr>
    `).join('');
    const total = +js.total || 0, pages = Math.max(1, Math.ceil(total / PAGE.size));
    $pager.innerHTML = Array.from({ length: pages }, (_, i) => `<button ${i + 1 === page ? 'class="active"' : ''} data-p="${i + 1}">${i + 1}</button>`).join('');
  }
  document.getElementById('btnBuscarPrestamo')?.addEventListener('click', () => cargarPrestamos(1));
  document.getElementById('qPrestamo')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); cargarPrestamos(1); }
  });
  $pager?.addEventListener('click', e => { const b = e.target.closest('[data-p]'); if (b) cargarPrestamos(+b.dataset.p); });
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-verp]'); if (!b) return;
    const id = b.dataset.verp;
    const js = await jsonFetch(API, new URLSearchParams({ action: 'get', id_prestamo: id }));
    const p = js.data || {};
    const cron = js.cronograma || [];
    const resumen = js.resumen || {};
    const html = `
      <div class="grid-2">
        <div>
          <h4>Resumen del Préstamo</h4>
          <p><b>Código:</b> ${p.id_prestamo}</p>
          <p><b>Cliente:</b> ${p.nombre} ${p.apellido}</p>
          <p><b>Tipo:</b> ${p.tipo_prestamo}</p>
          <p><b>Contrato:</b> ${p.numero_contrato || 'N/A'}</p>
          <p><b>Monto:</b> $${(+p.monto_solicitado || 0).toFixed(2)}</p>
          <p><b>Tasa:</b> ${(+p.tasa_interes || 0).toFixed(2)}</p>
          <p><b>Plazo:</b> ${p.plazo_meses} meses</p>
          <p><b>Frecuencia:</b> ${p.periodo_txt || '-'}</p>
          <p><b>Amortización:</b> ${p.amortizacion_txt || '-'}</p>
          <p><b>Estado:</b> <span class="badge ${(p.estado || '').toLowerCase()}">${p.estado || '-'}</span></p>
          
          <h4>Resumen Financiero</h4>
          <p><b>Total Capital:</b> $${(+resumen.total_capital || 0).toFixed(2)}</p>
          <p><b>Total Interés:</b> $${(+resumen.total_interes || 0).toFixed(2)}</p>
          <p><b>Total a Pagar:</b> $${(+resumen.total_pagar || 0).toFixed(2)}</p>
        </div>
        <div>
          <h4>Cronograma de Pagos</h4>
          <div class="table-responsive" style="max-height:500px; overflow:auto;">
            <table class="table-simple">
              <thead><tr><th>#</th><th>Vence</th><th>Capital</th><th>Interés</th><th>Cargos</th><th>Cuota</th><th>Saldo</th><th>Estado</th></tr></thead>
              <tbody>
                ${cron.map(c => `
                <tr class="estado-${(c.estado_cuota || '').toLowerCase()}">
                  <td>${c.numero_cuota}</td>
                  <td>${c.fecha_vencimiento}</td>
                  <td>$${(+c.capital_cuota).toFixed(2)}</td>
                  <td>$${(+c.interes_cuota).toFixed(2)}</td>
                  <td>$${(+c.cargos_cuota || 0).toFixed(2)}</td>
                  <td>$${(+c.total_monto).toFixed(2)}</td>
                  <td>$${(+c.saldo_cuota).toFixed(2)}</td>
                  <td><span class="badge ${(c.estado_cuota || '').toLowerCase()}">${c.estado_cuota}</span></td>
                </tr>`).join('')}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    `;
    document.getElementById('verPrestamoContenido').innerHTML = html;
    openModal(document.getElementById('modalVerPrestamo'));
  });
  // Exportar Cronograma pdf
  document.getElementById('btnExportarCronograma')?.addEventListener('click', () => {
    const $content = document.getElementById('verPrestamoContenido');

    const $printArea = document.createElement('div');
    $printArea.innerHTML = '<h1> Cronograma de pagos</h1>' + $content.querySelector('.table-responsive table')?.outerHTML;

    const w = window.open('', '_blank');
    w.document.write('<html><head><title>Cronograma de pagos</title>');
    w.document.write('<style>@media print { .table-simple { width: 100%; border-collapse: collapse; } .table-simple th, .table-simple td { border; 1px solid #ddd; padding: 8px; text-align: left; } h1 {text-align: center; }}</style>');
    w.document.write('</head><body>');
    w.document.write($printArea.innerHTML);
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    w.print();
  });
  // Desembolso 
  const $qDes = document.getElementById('qDesembolso');
  const $btnDes = document.getElementById('btnBuscarDesembolso');
  const $boxDes = document.getElementById('boxDesembolso');
  const $met = document.getElementById('metodo_entrega');
  async function cargarMetodos() {
    const js = await jsonFetch(API, new URLSearchParams({ action: 'metodos' }));
    if ($met) $met.innerHTML = (js.data || []).map(m => `<option value="${m.id}">${m.txt}</option>`).join('');
  }
  $btnDes?.addEventListener('click', async () => {
    const js = await jsonFetch(API, new URLSearchParams({ action: 'buscar_prestamo', q: $qDes.value.trim() }));
    if (!(js.data || []).length) { alert('Sin resultados'); return; }
    const p = js.data[0];
    const $desRes = document.getElementById('desResumen'); if ($desRes) $desRes.value = `${p.cliente} · ${p.tipo} · #${p.id_prestamo} · $${(+p.monto_solicitado).toFixed(2)}`;
    const $idp = document.getElementById('id_prestamo_des'); if ($idp) $idp.value = p.id_prestamo;
    $boxDes?.classList.remove('hidden');
  });
  $qDes?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); $btnDes?.click(); }
  });
  document.getElementById('frmDesembolso')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const js = await jsonFetch(API, new FormData(e.target));
    if (!js.ok) return alert(js.msg || 'Error');
    alert('Desembolso registrado');
  });
  // Recibo
  const $btnRecibo = document.getElementById('btnRecibo');
  $btnRecibo?.addEventListener('click', async () => {
    const id = document.getElementById('id_prestamo_des').value;
    const url = API + '?action=recibo_html&id_prestamo=' + encodeURIComponent(id);
    const html = await fetch(url).then(r => r.text());
    document.getElementById('reciboHTML').innerHTML = html;
    openModal(document.getElementById('modalRecibo'));
  });
  document.getElementById('btnReciboImprimir')?.addEventListener('click', () => {
    const w = window.open('', '_blank'); w.document.write(document.getElementById('reciboHTML').innerHTML); w.document.close(); w.focus(); w.print();
  });
  document.getElementById('btnReciboDescargar')?.addEventListener('click', () => {
    const w = window.open('', '_blank'); w.document.write(document.getElementById('reciboHTML').innerHTML); w.document.close(); w.focus(); w.print();
  });
  // Cargar inicial
  (async () => {
    await cargarCatalogos().catch(() => { });
    await cargarMetodos().catch(() => { });
    cargarPrestamos(1);
    const fd = document.getElementById('fecha_desembolso');
    if (fd) fd.value = new Date().toISOString().split('T')[0];
    try { actualizarPorcentajeHipotecario(); } catch (_) { }
  })();
})();
