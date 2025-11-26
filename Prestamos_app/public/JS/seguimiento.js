// /assets/js/seguimiento.js
(function () {
  const API = (window.APP_BASE || '/') + 'api/seguimiento.php';
  const API_NOTIFY = (window.APP_BASE || '/') + 'api/enviarnotificacion.php';

  // UI refs
  const $tabBtns = document.querySelectorAll('.tab-btn');
  const $tabEval = document.getElementById('tabEvaluacion');
  const $tabMora = document.getElementById('tabMorosos');

  const $q = document.getElementById('qSeg');
  const $fDesde = document.getElementById('fDesde');
  const $fHasta = document.getElementById('fHasta');
  const $btnBuscar = document.getElementById('btnBuscarSeg');
  const $btnExport = document.getElementById('btnExportSeg');
  const $tabla = document.querySelector('#tablaPrestamos tbody');
  const $paginacion = document.getElementById('paginacionSeg');
  const $err = document.getElementById('errorBoxSeg');
  const $spinner = document.getElementById('spinnerSeg');

  // Morosos UI refs
  const $qMorosos = document.getElementById('qMorosos');
  const $btnBuscarMorosos = document.getElementById('btnBuscarMorosos');
  const $btnExportMorosos = document.getElementById('btnExportMorosos');
  const $tablaMorosos = document.querySelector('#tablaMorosos tbody');
  const $paginacionMorosos = document.getElementById('paginacionMorosos');
  const $errMorosos = document.getElementById('errorBoxMorosos');
  const $spinnerMorosos = document.getElementById('spinnerMorosos');

  // Evaluación
  let currentLoan = null;
  let curredtClientId = null;
  const $evCliente = document.getElementById('evCliente');
  const $evMonto = document.getElementById('evMonto');
  const $evIngresos = document.getElementById('evIngresos');
  const $evEgresos = document.getElementById('evEgresos');
  const $evCapacidad = document.getElementById('evCapacidad');
  const $evRiesgo = document.getElementById('evRiesgo');
  const $btnAprobar = document.getElementById('btnAprobar');
  const $btnRechazar = document.getElementById('btnRechazar');

  const $btnCheckData = document.getElementById('btnCheckData');
  const $evDataResult = document.getElementById('evDataResult');
  const $evDocs = document.getElementById('evDocs');
  const $btnVerDocsEvaluacion = document.getElementById('btnVerDocsEvaluacion');

  // Morosos
  const $moCliente = document.getElementById('moCliente');
  const $moEstado = document.getElementById('moEstado');
  const $moMonto = document.getElementById('moMonto');
  const $moDias = document.getElementById('moDias');
  const $btnsAbrirNotif = document.querySelectorAll('.js-abrir-notif');

  // Modal Notificaciones
  const $modalNotif = document.getElementById('modalNotif');
  const $nfEmail = document.getElementById('nfEmail');
  const $nfAsunto = document.getElementById('nfAsunto');
  const $nfPlantilla = document.getElementById('nfPlantilla');
  const $nfMensaje = document.getElementById('nfMensaje');
  const $nfPreview = document.getElementById('nfPreview');
  const $btnEnviarNotif = document.getElementById('btnEnviarNotif');

  // Modal Detalle
  const $modalDetalle = document.getElementById('modalDetalle');
  const $detalleContent = document.getElementById('detalleContent');

  const openModal = (el) => el && el.classList.add('show');
  const closeModal = (el) => el && el.classList.remove('show');
  document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => closeModal(b.closest('.modal'))));
  window.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal.show').forEach(m => closeModal(m)); });

  function money(n) { n = +n || 0; try { return n.toLocaleString('es-DO', { style: 'currency', currency: 'DOP' }); } catch (e) { return n; } }

  function showSpinner(v) { if (!$spinner) return; if (v) { $spinner.hidden = false; $spinner.setAttribute('aria-hidden', 'false'); } else { $spinner.hidden = true; $spinner.setAttribute('aria-hidden', 'true'); } }

  function showSpinnerMorosos(v) { if (!$spinnerMorosos) return; if (v) { $spinnerMorosos.hidden = false; $spinnerMorosos.setAttribute('aria-hidden', 'false'); } else { $spinnerMorosos.hidden = true; $spinnerMorosos.setAttribute('aria-hidden', 'true'); } }

  let lastListResponse = null;
  let lastMorososResponse = null;

  async function jsonFetch(body) {
    $err.hidden = true; showSpinner(true);
    try {
      const res = await fetch(API, { method: 'POST', headers: { 'Accept': 'application/json' }, body });
      const t = await res.text();
      try { return JSON.parse(t); }
      catch (parseErr) {
        $err.hidden = false; $err.textContent = 'Respuesta no-JSON de la API:\n' + t.slice(0, 2000);
        throw parseErr;
      }
    } catch (e) {
      if ($err.hidden) { $err.hidden = false; $err.textContent = 'Error consultando API:\n' + (e.message || e); }
      throw e;
    } finally { showSpinner(false); }
  }

  async function jsonFetchMorosos(body) {
    $errMorosos.hidden = true; showSpinnerMorosos(true);
    try {
      const res = await fetch(API, { method: 'POST', headers: { 'Accept': 'application/json' }, body });
      const t = await res.text();
      try { return JSON.parse(t); }
      catch (parseErr) {
        $errMorosos.hidden = false; $errMorosos.textContent = 'Respuesta no-JSON de la API:\n' + t.slice(0, 2000);
        throw parseErr;
      }
    } catch (e) {
      if ($errMorosos.hidden) { $errMorosos.hidden = false; $errMorosos.textContent = 'Error consultando API:\n' + (e.message || e); }
      throw e;
    } finally { showSpinnerMorosos(false); }
  }
  $tabBtns.forEach(b => b.addEventListener('click', () => {
    $tabBtns.forEach(x => x.classList.remove('active')); b.classList.add('active');
    const tab = b.dataset.tab;
    $tabEval.style.display = (tab === 'evaluacion') ? '' : 'none';
    $tabMora.style.display = (tab === 'morosos') ? '' : 'none';
    if (tab === 'morosos') {
      cargarMorosos(1);
      if (currentLoan) {
        refreshPlantillas().catch(() => { });
      }
    }
  }));
  let currentPage = 1; let PAGE_SIZE = 10; let totalPages = 1;
  async function cargar(page = 1) {
    currentPage = page;
    const params = new URLSearchParams({ action: 'list_loans', q: $q.value.trim(), page, size: PAGE_SIZE });
    if ($fDesde && $fDesde.value) params.set('desde', $fDesde.value);
    if ($fHasta && $fHasta.value) params.set('hasta', $fHasta.value);
    const json = await jsonFetch(params);
    lastListResponse = json;

    const rows = (json.data || []);
    if (!rows.length) {
      $tabla.innerHTML = '<tr><td colspan="8" style="text-align:center;">No hay resultados.</td></tr>';
    } else {
      $tabla.innerHTML = rows.map(r => {
        const pill = r.estado_prestamo === 'En mora' ? 'mora' : (r.estado_prestamo === 'Pendiente' ? 'pendiente' : 'activo');
        const isEvaluable = (r.estado_prestamo === 'En evaluacion');
        return `
          <tr>
            <td>${r.id_prestamo}</td>
            <td>${r.nombre || ''} ${r.apellido || ''}</td>
            <td><span class="pill ${pill}">${r.estado_prestamo || '-'}</span></td>
            <td>${money(r.monto_solicitado)}</td>
            <td>${r.plazo_meses || '-'} m</td>
            <td>${r.fecha_inicio || '-'}</td>
            <td>${money(r.capacidad_pago || 0)}</td>
            <td>
              <button class="btn btn-light" data-evaluar="${r.id_prestamo}" ${isEvaluable ? '' : 'disabled title="Solo disponible en estado En evaluacion"'}>Evaluar</button>
              <button class="btn" data-mora="${r.id_prestamo}">Moroso</button>
              <button class="btn btn-light" data-detalle="${r.id_prestamo}">Detalle</button>
            </td>
          </tr>
        `;
      }).join('');
    }
    const total = +json.total || 0; totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    let html = `<div class=\"pager-inner\">`;
    html += `<button data-page="${Math.max(1, currentPage - 1)}" class="btn btn-light">◄ Prev</button>`;
    html += `<span class=\"pager-info\">Página ${currentPage} de ${totalPages} (${total} registros)</span>`;
    html += `<button data-page="${Math.min(totalPages, currentPage + 1)}" class="btn btn-light">Next ►</button>`;
    html += `</div>`;
    $paginacion.innerHTML = html;
  }

  if ($paginacion) $paginacion.addEventListener('click', e => {
    const b = e.target.closest('button[data-page]'); if (!b) return; cargar(+b.dataset.page);
  });
  if ($btnBuscar) $btnBuscar.addEventListener('click', () => cargar(1));
  if ($q) $q.addEventListener('keydown', e => { if (e.key === 'Enter') cargar(1); });
  if ($fDesde) $fDesde.addEventListener('change', () => cargar(1));
  if ($fHasta) $fHasta.addEventListener('change', () => cargar(1));
  // Listado de morosos
  let currentPageMorosos = 1; let PAGE_SIZE_MOROSOS = 10; let totalPagesMorosos = 1;
  async function cargarMorosos(page = 1) {
    currentPageMorosos = page;
    const params = new URLSearchParams({ action: 'list_morosos', q: $qMorosos.value.trim(), page, size: PAGE_SIZE_MOROSOS });
    const json = await jsonFetchMorosos(params);
    lastMorososResponse = json;
    const rows = (json.data || []);
    if (!rows.length) {
      $tablaMorosos.innerHTML = '<tr><td colspan="9" style="text-align:center;">No hay préstamos en mora.</td></tr>';
    } else {
      $tablaMorosos.innerHTML = rows.map(r => {
        const diasCritico = (r.dias_atraso || 0) > 30;
        return `
          <tr ${diasCritico ? 'data-dias-critico="true"' : ''}>
            <td>${r.id_prestamo}</td>
            <td>${r.nombre || ''} ${r.apellido || ''}</td>
            <td>${money(r.monto_solicitado)}</td>
            <td style="color:#c53030; font-weight:bold;">${money(r.monto_mora || 0)}</td>
            <td style="color:#c53030;">${r.dias_atraso || 0} días</td>
            <td>${r.cuotas_vencidas || 0}</td>
            <td>${r.plazo_meses || '-'} m</td>
            <td>${r.fecha_inicio || '-'}</td>
            <td>
              <button class="btn btn-light" data-seleccionar-moroso="${r.id_prestamo}">Seleccionar</button>
              <button class="btn btn-light" data-detalle="${r.id_prestamo}">Detalle</button>
            </td>
          </tr>
        `;
      }).join('');
    }
    const total = +json.total || 0; totalPagesMorosos = Math.max(1, Math.ceil(total / PAGE_SIZE_MOROSOS));
    let html = `<div class=\"pager-inner\">`;
    html += `<button data-page="${Math.max(1, currentPageMorosos - 1)}" class="btn btn-light">◄ Prev</button>`;
    html += `<span class=\"pager-info\">Página ${currentPageMorosos} de ${totalPagesMorosos} (${total} morosos)</span>`;
    html += `<button data-page="${Math.min(totalPagesMorosos, currentPageMorosos + 1)}" class="btn btn-light">Next ►</button>`;
    html += `</div>`;
    $paginacionMorosos.innerHTML = html;
  }
  if ($paginacionMorosos) $paginacionMorosos.addEventListener('click', e => {
    const b = e.target.closest('button[data-page]'); if (!b) return; cargarMorosos(+b.dataset.page);
  });
  if ($btnBuscarMorosos) $btnBuscarMorosos.addEventListener('click', () => cargarMorosos(1));
  if ($qMorosos) $qMorosos.addEventListener('keydown', e => { if (e.key === 'Enter') cargarMorosos(1); });
  document.addEventListener('click', async (e) => {
    const bEval = e.target.closest('[data-evaluar]');
    const bMora = e.target.closest('[data-mora]');
    const bDet = e.target.closest('[data-detalle]');
    const bSeleccionarMoroso = e.target.closest('[data-seleccionar-moroso]');
    if (!bEval && !bMora && !bDet && !bSeleccionarMoroso) return;

    const id = +(bEval ? bEval.dataset.evaluar : (bMora ? bMora.dataset.mora : (bSeleccionarMoroso ? bSeleccionarMoroso.dataset.seleccionarMoroso : bDet.dataset.detalle)));
    currentLoan = id;
    if (bEval) {
      if (bEval.hasAttribute('disabled')) { return; }
      document.querySelector('[data-tab="evaluacion"]').click();
      const json = await jsonFetch(new URLSearchParams({ action: 'get_eval_data', id_prestamo: id }));
      $evCliente.textContent = `${json.cliente?.nombre || ''} ${json.cliente?.apellido || ''}`.trim() || '—';
      // guardar el id del cliente actual para poder ver los documentos
      curredtClientId = json.prestamo?.id_cliente || null;
      if ($btnVerDocsEvaluacion) $btnVerDocsEvaluacion.disabled = !curredtClientId;
      $evMonto.textContent = money(json.prestamo?.monto_solicitado);
      $evIngresos.textContent = money(json.finanzas?.ingresos_mensuales);
      $evEgresos.textContent = money(json.finanzas?.egresos_mensuales);
      const cap = (+json.finanzas?.ingresos_mensuales || 0) - (+json.finanzas?.egresos_mensuales || 0);
      $evCapacidad.textContent = money(cap);
      $evRiesgo.innerHTML = (json.riesgos || []).map(r => `<option value=\"${r.id}\">${r.nivel}</option>`).join('');
      if (json.documentos) {
        renderDocuments(json.documentos);
      } else {
        loadDocuments(id).catch(() => { });
      }
      $evDataResult.textContent = '—';
    }
    if (bMora || bSeleccionarMoroso) {
      document.querySelector('[data-tab="morosos"]').click();
      const json = await jsonFetch(new URLSearchParams({ action: 'get_mora_data', id_prestamo: id }));
      const clienteData = await jsonFetch(new URLSearchParams({ action: 'get_eval_data', id_prestamo: id }));
      $moCliente.textContent = `${clienteData.cliente?.nombre || ''} ${clienteData.cliente?.apellido || ''}`.trim() || '—';

      $moEstado.textContent = json.estado || '—';
      $moMonto.textContent = money(json.monto_acumulado || 0);
      $moDias.textContent = `${json.dias_atraso || 0} días`;
      const base = await jsonFetch(new URLSearchParams({ action: 'notif_bootstrap', id_prestamo: id }));
      const opts = (base.plantillas || []).map(p => `<option value=\"${p.id}\">${p.asunto} — ${p.tipo}</option>`).join('');
      if ($nfPlantilla) $nfPlantilla.innerHTML = opts;
      if ($nfEmail) $nfEmail.value = base.para || '';
    }
    if (bDet) {
      openModal($modalDetalle);
      $detalleContent.textContent = 'Cargando...';
      try {
        const json = await jsonFetch(new URLSearchParams({ action: 'get_detail', id_prestamo: id }));
        if (json.historial) {
          const lines = (json.historial || []).map(h => `${h.fecha || ''} — ${h.evento || JSON.stringify(h)}`);
          $detalleContent.textContent = lines.join('\n') || 'Sin historial.';
        } else {
          $detalleContent.textContent = JSON.stringify(json, null, 2);
        }
      } catch (e) {
        $detalleContent.textContent = 'Error cargando detalle: ' + (e.message || e);
      }
    }
  });
  // llama a la vista de documentos donde se listan todos los documentos del cliente
  if ($btnVerDocsEvaluacion) $btnVerDocsEvaluacion.addEventListener('click', () => {
    if (!curredtClientId) return alert('Selecciona un cliente para evaluar')
    const url = (window.APP_BASE || '/') + 'views/docs_cliente.php?id_cliente=' + encodeURIComponent(curredtClientId);
    window.open(url, '_blank');
  });
  // funciones para tomar la decision de la evaluacion
  async function decidir(decision) {
    if (!currentLoan) return alert('Selecciona un préstamo.');
    const body = new URLSearchParams({
      action: 'decidir_evaluacion',
      id_prestamo: currentLoan,
      id_nivel_riesgo: $evRiesgo.value || '',
      decision
    });
    const json = await jsonFetch(body);
    if (!json.ok) return alert(json.msg || 'No se pudo registrar la decisión');
    alert('Decisión registrada.');
    cargar(currentPage);
  }
  if ($btnAprobar) $btnAprobar.addEventListener('click', () => decidir('Aprobado'));
  if ($btnRechazar) $btnRechazar.addEventListener('click', () => decidir('Rechazado'));
  async function loadDocuments(id) {
    if (!id) return;
    try {
      const json = await jsonFetch(new URLSearchParams({ action: 'get_documents', id_prestamo: id }));
      if (json.documentos) renderDocuments(json.documentos); else if (json.data) renderDocuments(json.data);
    } catch (e) { console.warn('No se pudieron cargar documentos', e); }
  }
  function renderDocuments(docs) {
    if (!docs || !docs.length) { $evDocs.textContent = 'Sin documentos registrados.'; return; }
    const html = docs.map(d => {
      const name = d.tipo || d.nombre || d.filename || 'Documento';
      const url = d.url || d.path || '';
      const link = url ? `<a href="${url}" target="_blank" rel="noopener">Ver</a>` : '<span style="color:#666">(sin archivo)</span>';
      return `${name} — ${d.descripcion || ''} ${link}`;
    }).join('\n');
    $evDocs.innerHTML = html.replace(/\n/g, '<br>');
  }
  // Datacrédito
  if ($btnCheckData) $btnCheckData.addEventListener('click', async () => {
    if (!currentLoan) return alert('Selecciona primero un préstamo en la lista.');
    $evDataResult.textContent = 'Verificando…';
    try {
      const json = await jsonFetch(new URLSearchParams({ action: 'check_datacredito', id_prestamo: currentLoan }));
      const data = json.data_completa || json.data || (json.resultado && typeof json.resultado === 'object' ? json.resultado : null);
      if (data && typeof data === 'object' && data.score) {
        let html = '<h3> Reporte de buro de credito</h3>';
        html += '<p><strong>Fuente:</strong> ' + (data.fuente || 'No disponible') + '</p>';
        html += '<p><strong>Nombre consultado:</strong> ' + ((data.cliente?.nombre || data.identificacion?.nombre || '') + ' ' + (data.cliente?.apellido || '')).trim() + '</p>';
        html += '<p><strong>Identificacion:</strong> ' + (data.cliente?.cedula || data.identificacion?.numero || '-') + '</p>';
        //Score 
        html += '<div class="mini-grid" style="margin-top: 10px;">';
        html += '<div><strong>Puntaje crediticio:</strong> <span >' + (data.score?.valor ?? '—') + '</span></div>';
        html += '<div><strong>Nivel:</strong> <span >' + (data.score?.nivel ?? '—') + '</span></div>';
        html += '<div><strong>Riesgo:</strong> <span ">' + (data.score?.riesgo ?? '—') + '</span></div>';
        html += '</div>';
        // Resumen crediticio
        if (data.resumen_crediticio) {
          const resumen = data.resumen_crediticio;
          html += '<h3 style="margin-top: 15px; margin-bottom: 5px;"> Resumen crediticio</h3>';
          html += '<ul>';
          html += '<li>Prestamos activos: <strong>' + resumen.prestamos_activos + '</strong></li>';
          html += '<li>Tarjetas de credito: <strong>' + resumen.tarjetas_credito + '</strong></li>';
          html += '<li>Lineas de credito: <strong>' + resumen.lineas_credito + '</strong></li>';
          html += '<li>Consultas recientes: <strong>' + resumen.consultas_recientes + '</strong></li>';
          html += '<li>Atraso maximo reportado: <strong style="color:' + (resumen.atraso_maximo > 30 ? '#c53030' : '#38a169') + ';">' + resumen.atraso_maximo + ' dias</strong></li>';
          html += '<li>Recomendación:  <strong style = font-size: 14px>' + (resumen.recomendacion || '-') + '</strong></li>';
          html += '</ul>';
        }
        // Alertas
        if (data.alertas && data.alertas.length > 0) {
          html += '<h3 style="margin-top: 15px; margin-bottom: 5px; color:#c53030;">Alertas</h3>';
          html += '<ul style="color:#c53030;">';
          html += data.alertas.map(a => '<li>' + a + '</li>').join('');
          html += '</ul>';
        }
        // Historial 
        if (data.historial_24_meses) {
          const atrasos = data.historial_24_meses.filter(h => h.dias_mora > 0).length;
          html += '<h3 style="margin-top: 15px; margin-bottom: 5px;">Historial de pagos (ultimos 24 meses)</h3>';
          html += '<p>Meses con atrasos: <strong>' + atrasos + '</strong></p>';
        }
        $evDataResult.innerHTML = html;
      } else {
        if (typeof json.resultado === 'string') {
          $evDataResult.innerHTML = '<pre class="dc-raw">' + json.resultado.replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])) + '</pre>';
        } else {
          $evDataResult.textContent = JSON.stringify(json.resultado, null, 2);
        }
      }
    } catch (e) { $evDataResult.textContent = 'Error verificando Datacrédito'; }
  });


  $btnsAbrirNotif.forEach(btn => btn.addEventListener('click', async () => {
    await refreshPlantillas().catch(() => { });
    $nfAsunto.value = '';
    $nfMensaje.value = ''; $nfPreview.textContent = '—';
    if ($nfPlantilla && $nfPlantilla.options.length) {
      $nfPlantilla.value = $nfPlantilla.options[0].value;
      $nfPlantilla.dispatchEvent(new Event('change'));
    }
    openModal($modalNotif);
  }));

  if ($nfPlantilla) $nfPlantilla.addEventListener('change', async () => {
    if (!$nfPlantilla.value) return;
    const json = await jsonFetch(new URLSearchParams({ action: 'plantilla_preview', id_plantilla: $nfPlantilla.value, id_prestamo: currentLoan || 0 }));
    $nfPreview.textContent = json.preview || '—';
    $nfAsunto.value = json.asunto || '';
    $nfMensaje.value = json.preview || '';
  });

  if ($btnEnviarNotif) $btnEnviarNotif.addEventListener('click', async () => {
    if (!$nfEmail.value) return alert('Indica el email del cliente.');
    if (!$nfAsunto.value) return alert('Indica el asunto del correo.');
    if (!$nfMensaje.value) return alert('Escribe el mensaje del correo.');
    const params = new URLSearchParams({
      correo: $nfEmail.value,
      asunto: $nfAsunto.value,
      mensaje: $nfMensaje.value
    });
    try {
      const res = await fetch(API_NOTIFY, { method: 'POST', body: params });
      const text = await res.text();
      let data;
      try { data = JSON.parse(text); } catch (_) { data = { success: false, message: text.slice(0, 2000) }; }
      if (!data.success) return alert(data.message || 'No se pudo enviar la notificación.');
      alert('Notificación enviada correctamente.');
      closeModal($modalNotif);
    } catch (e) {
      alert('Error enviando notificación: ' + (e.message || e));
    }
  });
  // Export CSV
  function toCSV(rows) {
    if (!rows || !rows.length) return '';
    const keys = Object.keys(rows[0]);
    const esc = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
    const header = keys.map(esc).join(',');
    const body = rows.map(r => keys.map(k => esc(r[k])).join(',')).join('\n');
    return header + '\n' + body;
  }
  if ($btnExport) $btnExport.addEventListener('click', () => {
    const json = lastListResponse || { data: [] };
    const rows = (json.data || []).map(r => ({
      id: r.id_prestamo,
      cliente: (r.nombre || '') + ' ' + (r.apellido || ''),
      estado: r.estado_prestamo,
      monto: r.monto_solicitado,
      plazo: r.plazo_meses,
      fecha: r.fecha_inicio,
      capacidad: r.capacidad_pago
    }));
    const csv = toCSV(rows);
    if (!csv) return alert('No hay datos para exportar.');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url;
    const fname = `prestamos_export_${(new Date()).toISOString().slice(0, 10)}.csv`;
    a.setAttribute('download', fname); document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
  });
  // Export CSV Morosos
  if ($btnExportMorosos) $btnExportMorosos.addEventListener('click', () => {
    const json = lastMorososResponse || { data: [] };
    const rows = (json.data || []).map(r => ({
      id: r.id_prestamo,
      cliente: (r.nombre || '') + ' ' + (r.apellido || ''),
      monto_prestamo: r.monto_solicitado,
      deuda_mora: r.monto_mora,
      dias_atraso: r.dias_atraso,
      cuotas_vencidas: r.cuotas_vencidas,
      plazo: r.plazo_meses,
      fecha_inicio: r.fecha_inicio
    }));
    const csv = toCSV(rows);
    if (!csv) return alert('No hay datos de morosos para exportar.');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url;
    const fname = `morosos_export_${(new Date()).toISOString().slice(0, 10)}.csv`;
    a.setAttribute('download', fname); document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
  });
  cargar(1);
  async function refreshPlantillas() {
    const json = await jsonFetch(new URLSearchParams({ action: 'notif_bootstrap', id_prestamo: currentLoan || 0 }));
    const opts = (json.plantillas || []).map(p => `<option value=\"${p.id}\">${p.asunto} — ${p.tipo}</option>`).join('');
    if ($nfPlantilla) $nfPlantilla.innerHTML = opts;
    if ($nfEmail && (!$nfEmail.value || !$nfEmail.value.trim())) {
      $nfEmail.value = json.para || '';
    }
  }
})();
