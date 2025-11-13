// /assets/js/seguimiento.js
(function () {
  const API = (window.APP_BASE || '/') + 'api/seguimiento.php';

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

  // Evaluación
  let currentLoan = null;
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

  // Morosos
  const $moEstado = document.getElementById('moEstado');
  const $moMonto = document.getElementById('moMonto');
  const $moDias = document.getElementById('moDias');
  const $btnAbrirNotif = document.getElementById('btnAbrirNotif');

  // Config Notif
  const $cfgDiasAnt = document.getElementById('cfgDiasAnt');
  const $cfgMetodo = document.getElementById('cfgMetodo');
  const $cfgFechaVenc = document.getElementById('cfgFechaVenc');
  const $cfgEscalamiento = document.getElementById('cfgEscalamiento');
  const $selPlantilla = document.getElementById('selPlantilla');
  const $btnPreview = document.getElementById('btnPreview');
  const $btnGuardarCfg = document.getElementById('btnGuardarCfg');
  const $previewBox = document.getElementById('previewBox');

  // Modal Notif
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

  // helpers
  const openModal = (el) => el && el.classList.add('show');
  const closeModal = (el) => el && el.classList.remove('show');
  document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => closeModal(b.closest('.modal'))));
  window.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal.show').forEach(m => closeModal(m)); });

  function money(n) { n = +n || 0; try { return n.toLocaleString('es-DO', { style: 'currency', currency: 'DOP' }); } catch (e) { return n; } }

  function showSpinner(v) { if (!$spinner) return; if (v) { $spinner.hidden = false; $spinner.setAttribute('aria-hidden', 'false'); } else { $spinner.hidden = true; $spinner.setAttribute('aria-hidden', 'true'); } }

  let lastListResponse = null;

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

  // Tabs
  $tabBtns.forEach(b => b.addEventListener('click', () => {
    $tabBtns.forEach(x => x.classList.remove('active')); b.classList.add('active');
    const tab = b.dataset.tab;
    $tabEval.style.display = (tab === 'evaluacion') ? '' : 'none';
    $tabMora.style.display = (tab === 'morosos') ? '' : 'none';
  }));

  // Listado básico (siempre visible)
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
        return `
          <tr>
            <td>${r.id_prestamo}</td>
            <td>${r.nombre || ''} ${r.apellido || ''}</td>
            <td><span class="pill ${pill}">${r.estado_prestamo || '-'}</span></td>
            <td>${money(r.monto_solicitado)}</td>
            <td>${r.plazo_meses || '-'} m</td>
            <td>${r.fecha_ref || '-'}</td>
            <td>${money(r.mora_acumulada || 0)}</td>
            <td>
              <button class="btn btn-light" data-evaluar="${r.id_prestamo}">Evaluar</button>
              <button class="btn" data-mora="${r.id_prestamo}">Moroso</button>
              <button class="btn btn-light" data-detalle="${r.id_prestamo}">Detalle</button>
            </td>
          </tr>
        `;
      }).join('');
    }

    // paginación mejorada
    const total = +json.total || 0; totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    let html = `<div class=\"pager-inner\">`;
    html += `<button data-page="${Math.max(1, currentPage - 1)}" class="btn btn-light">◄ Prev</button>`;
    html += `<span class=\"pager-info\">Página ${currentPage} de ${totalPages} (${total} registros)</span>`;
    html += `<button data-page="${Math.min(totalPages, currentPage + 1)}" class="btn btn-light">Next ►</button>`;
    html += `</div>`;
    $paginacion.innerHTML = html;
  }

  $paginacion.addEventListener('click', e => {
    const b = e.target.closest('button[data-page]'); if (!b) return; cargar(+b.dataset.page);
  });
  $btnBuscar.addEventListener('click', () => cargar(1));
  $q.addEventListener('keydown', e => { if (e.key === 'Enter') cargar(1); });
  if ($fDesde) $fDesde.addEventListener('change', () => cargar(1));
  if ($fHasta) $fHasta.addEventListener('change', () => cargar(1));

  // Clicks en acciones de la lista
  document.addEventListener('click', async (e) => {
    const bEval = e.target.closest('[data-evaluar]');
    const bMora = e.target.closest('[data-mora]');
    const bDet = e.target.closest('[data-detalle]');
    if (!bEval && !bMora && !bDet) return;

    const id = +(bEval ? bEval.dataset.evaluar : (bMora ? bMora.dataset.mora : bDet.dataset.detalle));
    currentLoan = id;

    if (bEval) {
      // Cambia a tab evaluación
      document.querySelector('[data-tab="evaluacion"]').click();
      const json = await jsonFetch(new URLSearchParams({ action: 'get_eval_data', id_prestamo: id }));
      $evCliente.textContent = `${json.cliente?.nombre || ''} ${json.cliente?.apellido || ''}`.trim() || '—';
      $evMonto.textContent = money(json.prestamo?.monto_solicitado);
      $evIngresos.textContent = money(json.finanzas?.ingresos_mensuales);
      $evEgresos.textContent = money(json.finanzas?.egresos_mensuales);
      const cap = (+json.finanzas?.ingresos_mensuales || 0) - (+json.finanzas?.egresos_mensuales || 0);
      $evCapacidad.textContent = money(cap);

      // niveles de riesgo
      $evRiesgo.innerHTML = (json.riesgos || []).map(r => `<option value=\"${r.id}\">${r.nivel}</option>`).join('');
      // Documentos si vienen en la respuesta
      if (json.documentos) {
        renderDocuments(json.documentos);
      } else {
        // intentar cargar documentos por separado
        loadDocuments(id).catch(() => { });
      }
      // limpiar Datacredito
      $evDataResult.textContent = '—';
    }

    if (bMora) {
      // Cambia a tab morosos
      document.querySelector('[data-tab="morosos"]').click();
      const json = await jsonFetch(new URLSearchParams({ action: 'get_mora_data', id_prestamo: id }));
      $moEstado.textContent = json.estado || '—';
      $moMonto.textContent = money(json.monto_acumulado || 0);
      $moDias.textContent = `${json.dias_atraso || 0} días`;

      // carga plantillas/config para notificar
      const base = await jsonFetch(new URLSearchParams({ action: 'notif_bootstrap', id_prestamo: id }));
      $selPlantilla.innerHTML = (base.plantillas || []).map(p => `<option value=\"${p.id}\">${p.asunto} — ${p.tipo}</option>`).join('');
      $nfPlantilla.innerHTML = $selPlantilla.innerHTML;
      $cfgDiasAnt.value = base.config?.dias_anticipacion ?? '';
      $cfgMetodo.value = base.config?.metodo ?? 'email';
      $cfgFechaVenc.value = base.config?.fecha_envio || '';
      $cfgEscalamiento.value = base.config?.escalamiento || '';
    }

    if (bDet) {
      // Mostrar detalle; si la API provee `get_detail` lo usaremos, si no mostraremos la respuesta cruda
      openModal($modalDetalle);
      $detalleContent.textContent = 'Cargando...';
      try {
        const json = await jsonFetch(new URLSearchParams({ action: 'get_detail', id_prestamo: id }));
        // si la API devuelve `historial` lo formateamos, si no mostramos JSON
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



  // Decisiones
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
  $btnAprobar.addEventListener('click', () => decidir('Aprobado'));
  $btnRechazar.addEventListener('click', () => decidir('Rechazado'));

  // Config / preview plantillas
  $btnPreview.addEventListener('click', async () => {
    if (!$selPlantilla.value) return;
    const json = await jsonFetch(new URLSearchParams({ action: 'plantilla_preview', id_plantilla: $selPlantilla.value, id_prestamo: currentLoan || 0 }));
    $previewBox.textContent = json.preview || '—';
  });

  $btnGuardarCfg.addEventListener('click', async () => {
    const body = new URLSearchParams({
      action: 'notif_save_config',
      dias_anticipacion: $cfgDiasAnt.value || 0,
      metodo: $cfgMetodo.value || 'email',
      fecha_envio: $cfgFechaVenc.value || '',
      escalamiento: $cfgEscalamiento.value || '[]'
    });
    const json = await jsonFetch(body);
    if (!json.ok) return alert(json.msg || 'No se pudo guardar.');
    alert('Configuración guardada.');
  });

  // Load documents helper
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
      // esperar estructura { ok:true, resultado: {...} }
      if (json.resultado) $evDataResult.textContent = typeof json.resultado === 'string' ? json.resultado : JSON.stringify(json.resultado);
      else $evDataResult.textContent = json.msg || JSON.stringify(json);
    } catch (e) { $evDataResult.textContent = 'Error verificando Datacrédito'; }
  });

  // Modal Notif
  document.getElementById('btnAbrirNotif').addEventListener('click', () => {
    $nfEmail.value = ''; $nfAsunto.value = '';
    $nfMensaje.value = ''; $nfPreview.textContent = '—';
    openModal($modalNotif);
  });

  $nfPlantilla.addEventListener('change', async () => {
    if (!$nfPlantilla.value) return;
    const json = await jsonFetch(new URLSearchParams({ action: 'plantilla_preview', id_plantilla: $nfPlantilla.value, id_prestamo: currentLoan || 0 }));
    $nfPreview.textContent = json.preview || '—';
    if (!$nfAsunto.value) $nfAsunto.value = json.asunto || '';
    if (!$nfMensaje.value) $nfMensaje.value = json.preview || '';
  });

  $btnEnviarNotif.addEventListener('click', async () => {
    if (!$nfEmail.value) return alert('Indica el email del cliente.');
    const body = new URLSearchParams({
      action: 'notif_send',
      id_prestamo: currentLoan || 0,
      email: $nfEmail.value,
      asunto: $nfAsunto.value,
      mensaje: $nfMensaje.value,
      id_plantilla: $nfPlantilla.value || ''
    });
    const json = await jsonFetch(body);
    if (!json.ok) return alert(json.msg || 'No se pudo enviar.');
    alert('Notificación enviada (simulada/API).');
    closeModal($modalNotif);
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

  $btnExport.addEventListener('click', () => {
    const json = lastListResponse || { data: [] };
    const rows = (json.data || []).map(r => ({
      id: r.id_prestamo,
      cliente: (r.nombre || '') + ' ' + (r.apellido || ''),
      estado: r.estado_prestamo,
      monto: r.monto_solicitado,
      plazo: r.plazo_meses,
      fecha: r.fecha_ref,
      mora: r.mora_acumulada
    }));
    const csv = toCSV(rows);
    if (!csv) return alert('No hay datos para exportar.');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url;
    const fname = `prestamos_export_${(new Date()).toISOString().slice(0, 10)}.csv`;
    a.setAttribute('download', fname); document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
  });

  // inicio
  cargar(1);
})();
