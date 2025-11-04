// /assets/js/seguimiento.js
(() => {
  const API = (window.APP_BASE || '/') + 'api/seguimiento.php';

  // UI refs
  const $tabBtns = document.querySelectorAll('.tab-btn');
  const $tabEval = document.getElementById('tabEvaluacion');
  const $tabMora = document.getElementById('tabMorosos');

  const $q = document.getElementById('qSeg');
  const $btnBuscar = document.getElementById('btnBuscarSeg');
  const $tabla = document.querySelector('#tablaPrestamos tbody');
  const $paginacion = document.getElementById('paginacionSeg');
  const $err = document.getElementById('errorBoxSeg');

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

  // helpers
  const openModal = (el) => el.classList.add('show');
  const closeModal = (el) => el.classList.remove('show');
  document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => closeModal(b.closest('.modal'))));
  window.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal.show').forEach(m=>closeModal(m)); });

  function money(n){ n = +n || 0; return n.toLocaleString('es-DO', {style:'currency', currency:'DOP'}); }

  async function jsonFetch(body){
    $err.hidden = true;
    try{
      const res = await fetch(API, { method:'POST', headers:{'Accept':'application/json'}, body });
      const t = await res.text();
      try { return JSON.parse(t); }
      catch(parseErr){
        $err.hidden = false; $err.textContent = 'Respuesta no-JSON de la API:\n' + t.slice(0,2000);
        throw parseErr;
      }
    }catch(e){
      if ($err.hidden){ $err.hidden = false; $err.textContent = 'Error consultando API:\n' + (e.message || e); }
      throw e;
    }
  }

  // Tabs
  $tabBtns.forEach(b => b.addEventListener('click', () => {
    $tabBtns.forEach(x => x.classList.remove('active')); b.classList.add('active');
    const tab = b.dataset.tab;
    $tabEval.style.display = (tab === 'evaluacion') ? '' : 'none';
    $tabMora.style.display = (tab === 'morosos') ? '' : 'none';
  }));

  // Listado básico (siempre visible)
  let currentPage = 1; const PAGE_SIZE = 10;
  async function cargar(page=1){
    currentPage = page;
    const params = new URLSearchParams({ action:'list_loans', q:$q.value.trim(), page, size: PAGE_SIZE });
    const json = await jsonFetch(params);

    $tabla.innerHTML = (json.data || []).map(r => {
      const pill = r.estado_prestamo === 'En mora' ? 'mora' : (r.estado_prestamo === 'Pendiente' ? 'pendiente' : 'activo');
      return `
        <tr>
          <td>${r.id_prestamo}</td>
          <td>${r.nombre} ${r.apellido}</td>
          <td><span class="pill ${pill}">${r.estado_prestamo || '-'}</span></td>
          <td>${money(r.monto_solicitado)}</td>
          <td>${r.plazo_meses} m</td>
          <td>${r.fecha_ref || '-'}</td>
          <td>${money(r.mora_acumulada || 0)}</td>
          <td>
            <button class="btn btn-light" data-evaluar="${r.id_prestamo}">Evaluar</button>
            <button class="btn" data-mora="${r.id_prestamo}">Moroso</button>
          </td>
        </tr>
      `;
    }).join('');

    // paginación
    const total = +json.total || 0, pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    let html = ''; for (let p=1;p<=pages;p++){ html += `<button ${p===page?'class="active"':''} data-page="${p}">${p}</button>`; }
    $paginacion.innerHTML = html;
  }

  $paginacion.addEventListener('click', e => {
    const b = e.target.closest('button[data-page]'); if (!b) return; cargar(+b.dataset.page);
  });
  $btnBuscar.addEventListener('click', () => cargar(1));
  $q.addEventListener('keydown', e => { if (e.key === 'Enter') cargar(1); });

  // Clicks en acciones de la lista
  document.addEventListener('click', async (e) => {
    const bEval = e.target.closest('[data-evaluar]');
    const bMora = e.target.closest('[data-mora]');
    if (!bEval && !bMora) return;

    const id = +(bEval ? bEval.dataset.evaluar : bMora.dataset.mora);
    currentLoan = id;

    if (bEval){
      // Cambia a tab evaluación
      document.querySelector('[data-tab="evaluacion"]').click();
      const json = await jsonFetch(new URLSearchParams({ action:'get_eval_data', id_prestamo:id }));
      $evCliente.textContent = `${json.cliente?.nombre || ''} ${json.cliente?.apellido || ''}`.trim() || '—';
      $evMonto.textContent = money(json.prestamo?.monto_solicitado);
      $evIngresos.textContent = money(json.finanzas?.ingresos_mensuales);
      $evEgresos.textContent  = money(json.finanzas?.egresos_mensuales);
      const cap = (+json.finanzas?.ingresos_mensuales||0) - (+json.finanzas?.egresos_mensuales||0);
      $evCapacidad.textContent = money(cap);

      // niveles de riesgo
      $evRiesgo.innerHTML = (json.riesgos || []).map(r => `<option value="${r.id}">${r.nivel}</option>`).join('');
    }

    if (bMora){
      // Cambia a tab morosos
      document.querySelector('[data-tab="morosos"]').click();
      const json = await jsonFetch(new URLSearchParams({ action:'get_mora_data', id_prestamo:id }));
      $moEstado.textContent = json.estado || '—';
      $moMonto.textContent  = money(json.monto_acumulado || 0);
      $moDias.textContent   = `${json.dias_atraso || 0} días`;

      // carga plantillas/config para notificar
      const base = await jsonFetch(new URLSearchParams({ action:'notif_bootstrap', id_prestamo:id }));
      $selPlantilla.innerHTML = (base.plantillas || []).map(p => `<option value="${p.id}">${p.asunto} — ${p.tipo}</option>`).join('');
      $nfPlantilla.innerHTML = $selPlantilla.innerHTML;
      $cfgDiasAnt.value = base.config?.dias_anticipacion ?? '';
      $cfgMetodo.value  = base.config?.metodo ?? 'email';
      $cfgFechaVenc.value = base.config?.fecha_envio || '';
      $cfgEscalamiento.value = base.config?.escalamiento || '';
    }
  });

  // Decisiones
  async function decidir(decision){
    if (!currentLoan) return alert('Selecciona un préstamo.');
    const body = new URLSearchParams({
      action:'decidir_evaluacion',
      id_prestamo: currentLoan,
      id_nivel_riesgo: $evRiesgo.value || '',
      decision
    });
    const json = await jsonFetch(body);
    if (!json.ok) return alert(json.msg || 'No se pudo registrar la decisión');
    alert('Decisión registrada.');
    cargar(currentPage);
  }
  $btnAprobar.addEventListener('click', ()=>decidir('Aprobado'));
  $btnRechazar.addEventListener('click', ()=>decidir('Rechazado'));

  // Config / preview plantillas
  $btnPreview.addEventListener('click', async () => {
    if (!$selPlantilla.value) return;
    const json = await jsonFetch(new URLSearchParams({ action:'plantilla_preview', id_plantilla:$selPlantilla.value, id_prestamo: currentLoan||0 }));
    $previewBox.textContent = json.preview || '—';
  });

  $btnGuardarCfg.addEventListener('click', async () => {
    const body = new URLSearchParams({
      action:'notif_save_config',
      dias_anticipacion:$cfgDiasAnt.value||0,
      metodo:$cfgMetodo.value||'email',
      fecha_envio:$cfgFechaVenc.value||'',
      escalamiento:$cfgEscalamiento.value||'[]'
    });
    const json = await jsonFetch(body);
    if (!json.ok) return alert(json.msg || 'No se pudo guardar.');
    alert('Configuración guardada.');
  });

  // Modal Notif
  document.getElementById('btnAbrirNotif').addEventListener('click', () => {
    $nfEmail.value = ''; $nfAsunto.value = '';
    $nfMensaje.value = ''; $nfPreview.textContent = '—';
    openModal($modalNotif);
  });

  $nfPlantilla.addEventListener('change', async () => {
    if (!$nfPlantilla.value) return;
    const json = await jsonFetch(new URLSearchParams({ action:'plantilla_preview', id_plantilla:$nfPlantilla.value, id_prestamo: currentLoan||0 }));
    $nfPreview.textContent = json.preview || '—';
    if (!$nfAsunto.value) $nfAsunto.value = json.asunto || '';
    if (!$nfMensaje.value) $nfMensaje.value = json.preview || '';
  });

  $btnEnviarNotif.addEventListener('click', async () => {
    if (!$nfEmail.value) return alert('Indica el email del cliente.');
    const body = new URLSearchParams({
      action:'notif_send',
      id_prestamo: currentLoan||0,
      email:$nfEmail.value,
      asunto:$nfAsunto.value,
      mensaje:$nfMensaje.value,
      id_plantilla:$nfPlantilla.value||''
    });
    const json = await jsonFetch(body);
    if (!json.ok) return alert(json.msg || 'No se pudo enviar.');
    alert('Notificación enviada (simulada/API).');
    closeModal($modalNotif);
  });

  // inicio
  cargar(1);
})();
