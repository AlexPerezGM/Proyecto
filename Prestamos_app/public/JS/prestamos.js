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
      catch (parseErr) {
        if ($err) {
          $err.hidden = false;
          $err.textContent = 'Respuesta no-JSON de la API:\n' + text.slice(0, 2000);
        }
        throw parseErr;
      }
    } catch (e) {
      if (!$err || ($err && $err.hidden)) {
        if ($err) {
          $err.hidden = false; $err.textContent = 'Error consultando API:\n' + (e.message || e);
        } else {
          console.error('Error consultando API:', e);
        }
      }
      throw e;
    }
  }

  // Catálogos y moneda
  const $selMoneda = document.getElementById('selMoneda');
  let MONEDAS = [];
  let PERIODOS = [];
  let AMORTIZACION = [];
  let GARANTIAS = [];
  let PRESTAMO_ACTUAL = null;
  let POLITICAS = [];

  async function cargarCatalogos() {
    const js = await jsonFetch(API, new URLSearchParams({ action: 'catalogos' }));
    MONEDAS = js.data?.monedas || [];
    PERIODOS = js.data?.periodos || [];
    AMORTIZACION = js.data?.amortizacion || [];
    GARANTIAS = js.data?.garantias || [];
    POLITICAS = js.data?.politicas || [];

    $selMoneda.innerHTML = MONEDAS.map(m => `<option value="${m.id}">${m.txt}</option>`).join('');
    // Popular moneda en cancelación si existe el select
    const $monCan = document.getElementById('moneda_cancelacion');
    if ($monCan) {
      $monCan.innerHTML = MONEDAS.map(m => `<option value="${m.id}">${m.txt}</option>`).join('');
      // por defecto usar la seleccionada en topbar
      $monCan.value = $selMoneda?.value || (MONEDAS[0]?.id ?? '1');
    }

    document.getElementById('per_personal').innerHTML = PERIODOS.map(p => `<option value="${p.id}">${p.txt}</option>`).join('');
    document.getElementById('per_hipo').innerHTML = PERIODOS.map(p => `<option value="${p.id}">${p.txt}</option>`).join('');

    document.getElementById('amort_personal').innerHTML = AMORTIZACION.map(a => `<option value="${a.id}">${a.txt}</option>`).join('');
    document.getElementById('amort_hipo').innerHTML = AMORTIZACION.map(a => `<option value="${a.id}">${a.txt}</option>`).join('');

    const politicasOpts = POLITICAS.map(p => {
      const perc = parseFloat(p.porcentaje_penalidad);
      const show = isNaN(perc) ? '' : ` (${perc.toFixed(2)}%)`;
      return `<option value="${p.id}">${p.txt}${show}</option>`;
    }).join('');

    const $polP = document.getElementById('politica_personal');
    if ($polP) $polP.innerHTML = `<option value="">Seleccionar...</option>` + politicasOpts;

    const $polH = document.getElementById('politica_hipo');
    if ($polH) $polH.innerHTML = `<option value="">Seleccionar...</option>` + politicasOpts;

    const garOpts = GARANTIAS.map(g => `<option value="${g.id}">${g.txt}</option>`).join('');
    const $garP = document.getElementById('garantia_personal'); if ($garP) $garP.innerHTML = `<option value="">Seleccionar...</option>` + garOpts;
    const $garH = document.getElementById('garantia_hipo'); if ($garH) $garH.innerHTML = `<option value="">Seleccionar...</option>` + garOpts;
    if ($garP) $garP.innerHTML = `<option value="">Seleccionar...</option>` + garOpts;
    if ($garH) $garH.innerHTML = `<option value="">Seleccionar...</option>` + garOpts;

    // valores por defecto
    const defs = js.data?.defaults || [];
    const pers = defs.find(d => +d.id_tipo_prestamo === 1);
    const hipo = defs.find(d => +d.id_tipo_prestamo === 2);

    if (pers) {
      document.getElementById('tasa_personal').value = pers.tasa_interes;
      document.getElementById('monto_personal').placeholder = `≥ ${(+pers.monto_minimo).toFixed(2)} DOP`;
      document.getElementById('amort_personal').value = pers.id_tipo_amortizacion || 1;

      if (pers.id_politica_cancelacion && $polP) {
        $polP.value = pers.id_politica_cancelacion;
      }

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

      if (hipo.id_politica_cancelacion && $polH) {
        $polH.value = hipo.id_politica_cancelacion;
      }

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
  let LAST_PRESTAMO_ID = null;

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
    const b = e.target.closest('[data-sel]');
    if (!b)
      return;
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

    LAST_PRESTAMO_ID = null;

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
    const icp = document.getElementById('id_cliente_personal');
    if (icp)
      icp.value = CLIENTE.id_cliente;
    const ich = document.getElementById('id_cliente_hipo');
    if (ich)
      ich.value = CLIENTE.id_cliente;
    habilitarUI_docsParaCliente();
  });

  document.getElementById('btnAbrirCrearCliente')?.addEventListener('click', () => openModal(document.getElementById('modalCrearCliente')));
  document.getElementById('frmClienteQuick')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const js = await jsonFetch(API_CLIENTES, new FormData(e.target));
    if (!js.ok)
      return alert(js.msg || 'Error');
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
    try {
      actualizarPorcentajeHipotecario();
    } catch (_) { }
  });

  document.getElementById('btnMinPersonal')?.addEventListener('click', () => { });
  document.getElementById('btnMinHipotecario')?.addEventListener('click', () => { });

  function withMoneda(fd) {
    fd.set('id_tipo_moneda', $selMoneda.value || '1');
    return fd;
  }

  document.getElementById('frmPersonal')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const js = await jsonFetch(API, withMoneda(new FormData(e.target)));
    if (!js.ok)
      return alert(js.msg || 'Error');

    LAST_PRESTAMO_ID = js.id_prestamo || null;

    closeModal(document.getElementById('modalPersonal'));
    alert(`Préstamo creado: #${js.id_prestamo}\nContrato: ${js.numero_contrato || 'N/A'}`);
    cargarPrestamos(1);
  });

  document.getElementById('frmHipotecario')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const porc = parseFloat((document.getElementById('porc_fin')?.value) || '0');
    if (porc > 80)
      return alert('El porcentaje no puede exceder 80%');

    const js = await jsonFetch(API, withMoneda(new FormData(e.target)));
    if (!js.ok)
      return alert(js.msg || 'Error');

    LAST_PRESTAMO_ID = js.id_prestamo || null;

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

  if ($porcFin) {
    $porcFin.readOnly = true;
  }
  $montoH?.addEventListener('input', actualizarPorcentajeHipotecario);
  $valorInm?.addEventListener('input', actualizarPorcentajeHipotecario);

  function abrirModalDocsPrestamo(prestamo) {
    const existente = document.getElementById('modalDocsPrestamoOverlay');
    if (existente) existente.remove();

    const overlay = document.createElement('div');
    overlay.id = 'modalDocsPrestamoOverlay';
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.background = 'rgba(15,23,42,0.45)';
    overlay.style.zIndex = '9999';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'flex-start';
    overlay.style.justifyContent = 'center';
    overlay.style.overflowY = 'auto';
    overlay.style.padding = '40px 16px';
    overlay.innerHTML = `
      <div
        class="panel-docs-prestamo"
        style="
          background:#ffffff;
          max-width: 820px;
          width: 100%;
          border-radius: 16px;
          box-shadow: 0 18px 45px rgba(15,23,42,0.25);
          padding: 20px 24px 18px;
          position: relative;
        "
      >
        <button
          type="button"
          data-close-modal
          style="
            position:absolute;
            top:10px;
            right:12px;
            border:none;
            background:transparent;
            font-size:20px;
            line-height:1;
            cursor:pointer;
          "
        >&times;</button>

        <h3 style="margin:0 0 18px 0; font-size:18px;">
          Documentación del préstamo #${prestamo.id_prestamo} - ${prestamo.nombre} ${prestamo.apellido}
        </h3>

        <div style="margin-top:8px;">
          <div style="margin-bottom:10px;">
            <label for="doc_tipo_prestamo" style="display:block;font-weight:600;margin-bottom:4px;">
              Tipo de documento
            </label>
            <select
              id="doc_tipo_prestamo"
              style="
                width:100%;
                padding:8px 10px;
                border-radius:8px;
                border:1px solid #d1d5db;
                font-size:14px;
              "
            >
              <option value="">Seleccione tipo...</option>
              <option value="CONTRATO">Contrato del préstamo</option>
              <option value="GARANTIA">Garantía / Aval</option>
              <option value="SEGURO">Póliza de seguro</option>
              <option value="OTRO">Otro documento relacionado</option>
            </select>
          </div>

          <div style="margin-bottom:10px;">
            <label for="doc_archivo_prestamo" style="display:block;font-weight:600;margin-bottom:4px;">
              Archivo (PDF, JPG o PNG)
            </label>
            <input
              type="file"
              id="doc_archivo_prestamo"
              accept=".pdf,.jpg,.jpeg,.png"
              style="
                width:100%;
                padding:6px 8px;
                border-radius:8px;
                border:1px solid #d1d5db;
                font-size:14px;
                background:#f9fafb;
              "
            />
          </div>

          <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;">
            <button
              type="button"
              id="btnAgregarDocPrestamo"
              class="btn"
            >
              Agregar documento
            </button>
            <button
              type="button"
              id="btnAbrirDocsClienteDesdePrestamo"
              class="btn btn-light"
            >
              Abrir documentos del cliente
            </button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);

    overlay.addEventListener('click', (e) => {
      if (e.target === overlay || e.target.dataset.closeModal !== undefined) {
        overlay.remove();
      }
    });

    const btnAgregar = overlay.querySelector('#btnAgregarDocPrestamo');
    if (btnAgregar) {
      btnAgregar.addEventListener('click', async () => {
        try {
          if (!prestamo.id_cliente || !prestamo.id_prestamo) {
            alert('No se encontró el cliente o el préstamo asociado.');
            return;
          }

          const tipoSelect = overlay.querySelector('#doc_tipo_prestamo');
          const fileInput = overlay.querySelector('#doc_archivo_prestamo');

          if (!tipoSelect || !fileInput) {
            alert('No se encontró el formulario de documentos.');
            return;
          }

          if (!tipoSelect.value) {
            alert('Selecciona el tipo de documento.');
            return;
          }

          if (!fileInput.files || !fileInput.files.length) {
            alert('Selecciona un archivo.');
            return;
          }

          const fd = new FormData();
          fd.append('action', 'upload_doc');
          fd.append('id_cliente', prestamo.id_cliente);
          fd.append('id_prestamo', prestamo.id_prestamo); // para que el PHP lo meta en la carpeta del préstamo
          fd.append('tipo_archivo', tipoSelect.value);
          fd.append('archivo', fileInput.files[0]);

          const res = await jsonFetch(API, fd);
          if (!res.ok) {
            throw new Error(res.error || res.msg || 'Error al subir documento');
          }

          alert('Documento del préstamo subido correctamente.');
          tipoSelect.value = '';
          fileInput.value = '';
        } catch (err) {
          console.error(err);
          alert(err.message || 'Error al subir el documento del préstamo.');
        }
      });
    }

    const btnAbrir = overlay.querySelector('#btnAbrirDocsClienteDesdePrestamo');
    if (btnAbrir) {
      btnAbrir.addEventListener('click', () => {
        if (!prestamo.id_cliente) {
          alert('No se encontró el cliente asociado a este préstamo.');
          return;
        }
        const appBase = window.APP_BASE || '/';
        const url = appBase + 'views/docs_cliente.php?id_cliente=' +
          encodeURIComponent(prestamo.id_cliente);

        const w = window.open(url, '_blank', 'noopener');
        if (w) w.focus();
      });
    }
  }

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
    const b = e.target.closest('[data-verp]');
    if (!b) return;

    const id = b.dataset.verp;
    const js = await jsonFetch(API, new URLSearchParams({ action: 'get', id_prestamo: id }));
    const p = js.data || {};
    PRESTAMO_ACTUAL = p;
    const cron = js.cronograma || [];
    const resumen = js.resumen || {};

    const diasMora = cron.reduce((max, c) => {
      if (c.estado_cuota === 'Vencida') {
        const diff = (new Date() - new Date(c.fecha_vencimiento)) / (1000 * 60 * 60 * 24);
        return diff > max ? diff : max;
      }
      return max;
    }, 0);

    const esActivo = p.estado === 'Activo' || p.estado === 'En mora';
    let botonesAccion = '';

    if (esActivo) {
      botonesAccion += `<button class="btn btn-primary text-danger" id="btnAbrirCancelar" style="border-color:#fca5a5; color:#ffffff;">Cancelación Anticipada</button>`;
    }
    if (p.estado === 'Solicitado') {
      botonesAccion += `<button class="btn btn-light" id="btnEjecutarGarantia" style="margin-left:8px; border-color:#f59e0b; color:#b45309;">Usar Garantía</button>`;
    }

    const html = `
      <div class="grid-2">
        <div>
          <h4>Resumen del Préstamo</h4>
          <p><b>Código:</b> ${p.id_prestamo}</p>
          <p><b>Cliente:</b> ${p.nombre} ${p.apellido}</p>
          <p><b>Tipo:</b> ${p.tipo_prestamo}</p>
          <p><b>Contrato:</b> ${p.numero_contrato || 'N/A'}</p>
          <p><b>Monto:</b> $${(+p.monto_solicitado || 0).toFixed(2)}</p>
          <p><b>Tasa:</b> ${(+p.tasa_interes || 0).toFixed(2)}%</p>
          <p><b>Plazo:</b> ${p.plazo_meses} meses</p>
          <p><b>Frecuencia:</b> ${p.periodo_txt || '-'}</p>
          <p><b>Amortización:</b> ${p.amortizacion_txt || '-'}</p>
          <p><b>Estado:</b> <span class="badge ${(p.estado || '').toLowerCase()}">${p.estado || '-'}</span></p>
          <p><b>Direccion de Cobro:</b> ${(p.ciudad || '')}, ${(p.sector || '')}, ${(p.calle || '')}, Casa No. ${(p.numero_casa || '')}</p>
          <p><b>Direccion de Garantia:</b> ${p.direccion_garantia || '-'}</p>
          <h4>Resumen Financiero</h4>
          <p><b>Total Capital:</b> $${(+resumen.total_capital || 0).toFixed(2)}</p>
          <p><b>Total Interés:</b> $${(+resumen.total_interes || 0).toFixed(2)}</p>
          <p><b>Total a Pagar:</b> $${(+resumen.total_pagar || 0).toFixed(2)}</p>
          <div style="grid-column: 1/-1; display:flex; justify-content:flex-end; margin-bottom:10px;">
            ${botonesAccion} 
          </div>
        </div>
        <div>
          <h4>Cronograma de Pagos</h4>
          <div class="table-responsive" style="max-height:500px; overflow:auto;">
            <table class="table-simple">
              <thead>
                <tr>
                  <th>#</th><th>Vence</th><th>Capital</th><th>Interés</th><th>Cargos</th>
                  <th>Cuota</th><th>Saldo</th><th>Estado</th>
                </tr>
              </thead>
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
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    `;

    const cont = document.getElementById('verPrestamoContenido');
    if (cont) cont.innerHTML = html;

    const btnAgregarInner = document.getElementById('btnAgregarDocPrestamoInner');
    if (btnAgregarInner) {
      btnAgregarInner.addEventListener('click', () => {
        alert('Para subir documentos del préstamo use el botón "Subir documentos" en la parte superior.');
      });
    }

    const btnCancelar = document.getElementById('btnAbrirCancelar');
    if (btnCancelar) {
      btnCancelar.addEventListener('click', async () => {
        openModal(document.getElementById('modalCancelacion'));
        const boxLoad = document.getElementById('cancelacionLoading');
        const frm = document.getElementById('frmCancelacion');
        boxLoad.hidden = false;
        frm.classList.add('hidden');

        try {
          const res = await jsonFetch(API, new URLSearchParams({
            action: 'calcular_liquidacion',
            id_prestamo: p.id_prestamo
          }));
          const data = res.data;
          document.getElementById('id_prestamo_cancelar').value = p.id_prestamo;
          document.getElementById('txtPolitica').textContent = data.politica_txt || '-';
          document.getElementById('valCapital').textContent = '$' + (+data.capital_pendiente).toFixed(2);
          document.getElementById('valInteres').textContent = '$' + (+data.interes_vencido).toFixed(2);
          document.getElementById('valMora').textContent = '$' + (+data.cargos_mora).toFixed(2);
          document.getElementById('valPenalidad').textContent = '$' + (+data.penalidad).toFixed(2);
          document.getElementById('txtPorc').textContent = data.porcentaje;
          document.getElementById('valTotal').textContent = '$' + (+data.total_cancelacion).toFixed(2);
          document.getElementById('inputMontoTotal').value = data.total_cancelacion;

          boxLoad.hidden = true;
          frm.classList.remove('hidden');
        } catch (e) {
          alert('Error calculando: ' + e.message);
          closeModal(document.getElementById('modalCancelacion'));
        }
      });
    }

    const btnGarantia = document.getElementById('btnEjecutarGarantia');
    if (btnGarantia) {
      btnGarantia.addEventListener('click', async () => {
        if (!confirm('Esta seguro de usar la garantia de este prestamo?'));

        const razon = prompt('Por favor ingrese la razón para ejecutar la garantía:');
        if (!razon) return;

        const res = await jsonFetch(API, new URLSearchParams({
          action: 'ejecutar_garantia',
          id_prestamo: p.id_prestamo,
          observacion: razon
        }));
        if (res.ok) {
          alert(res.msg);
          closeModal(document.getElementById('modalVerPrestamo'));
          cargarPrestamos(PAGE.cur);
        }
      });
    }
    let cancelSending = false;
    document.getElementById('frmCancelacion')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (cancelSending) return;
      if (!confirm('Confirma la cancelación de este préstamo?')) return;

      const frm = e.target;
      const fd = new FormData(frm);
      const monedaSel = document.getElementById('moneda_cancelacion')?.value || ($selMoneda?.value || '1');
      fd.set('id_tipo_moneda', monedaSel);
      const montoRec = document.getElementById('total_recibido')?.value || '';
      fd.set('total_recibido', montoRec);

      cancelSending = true;
      try {
        const res = await jsonFetch(API, fd);
        if (res.ok) {
          alert(res.msg + (res.monto_recibido ? `\nMonto recibido: $${(+res.monto_recibido).toFixed(2)}` : ''));
          closeModal(document.getElementById('modalCancelacion'));
          closeModal(document.getElementById('modalVerPrestamo'));
          cargarPrestamos(PAGE.cur);
        } else {
          alert(res.msg || 'Error en la cancelación');
        }
      } finally {
        cancelSending = false;
      }
    });

    const btnAbrir = document.getElementById('btnAbrirDocsPrestamo');
    if (btnAbrir) {
      btnAbrir.addEventListener('click', () => {
        if (!p.id_cliente) {
          alert('No se encontró el cliente asociado a este préstamo.');
          return;
        }
        const appBase = window.APP_BASE || '/';
        const url = appBase + 'views/docs_cliente.php?id_cliente=' +
          encodeURIComponent(p.id_cliente);
        const w = window.open(url, '_blank', 'noopener');
        if (w) w.focus();
      });
    }
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

  const $btnSubirDocsPrestamoHeader = document.getElementById('btnSubirDocsPrestamo');
  if ($btnSubirDocsPrestamoHeader) {
    $btnSubirDocsPrestamoHeader.addEventListener('click', () => {
      if (!PRESTAMO_ACTUAL) {
        alert('Primero selecciona un préstamo de la lista y abre su detalle.');
        return;
      }
      abrirModalDocsPrestamo(PRESTAMO_ACTUAL);
    });
  }

  const $btnVerCarpetaDocsPrestamoHeader = document.getElementById('btnVerCarpetaDocsPrestamo');
  if ($btnVerCarpetaDocsPrestamoHeader) {
    $btnVerCarpetaDocsPrestamoHeader.addEventListener('click', () => {
      if (!PRESTAMO_ACTUAL || !PRESTAMO_ACTUAL.id_cliente) {
        alert('No se encontró el cliente asociado a este préstamo.');
        return;
      }
      const appBase = (window.APP_BASE || '/');
      const url = appBase + 'views/docs_cliente.php?id_cliente=' +
        encodeURIComponent(PRESTAMO_ACTUAL.id_cliente);

      const w = window.open(url, '_blank', 'noopener');
      if (w) w.focus();
    });
  }

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
    if (!(js.data || []).length) {
      alert('Sin resultados');
      return;
    }
    const p = js.data[0];
    const $desRes = document.getElementById('desResumen');
    if ($desRes) $desRes.value = `${p.cliente} · ${p.tipo} · #${p.id_prestamo} · $${(+p.monto_solicitado).toFixed(2)}`;
    const $idp = document.getElementById('id_prestamo_des');
    if ($idp) $idp.value = p.id_prestamo;
    $boxDes?.classList.remove('hidden');
  });
  $qDes?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault(); $btnDes?.click();
    }
  });
  document.getElementById('frmDesembolso')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const js = await jsonFetch(API, new FormData(e.target));
    if (!js.ok)
      return alert(js.msg || 'Error');
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
    const w = window.open('', '_blank');
    w.document.write(document.getElementById('reciboHTML').innerHTML);
    w.document.close();
    w.focus();
    w.print();
  });
  document.getElementById('btnReciboDescargar')?.addEventListener('click', () => {
    const w = window.open('', '_blank');
    w.document.write(document.getElementById('reciboHTML').innerHTML);
    w.document.close();
    w.focus();
    w.print();
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
