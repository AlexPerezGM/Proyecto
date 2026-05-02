window.APP_BASE = window.APP_BASE || '/';
const idPrestamoFromUrl = new URLSearchParams(window.location.search).get('id_prestamo');
window.ID_PRESTAMO = Number(window.ID_PRESTAMO || idPrestamoFromUrl || 0);
 
(function () {
  const steps      = [1, 2, 3, 4];
  const delays     = [800, 1400, 1600, 1000]; 
  const progressEl = document.getElementById('progressBar');
  const pctEl      = document.getElementById('progressPct');
  const mainLabel  = document.getElementById('mainLabel');
  const mainSub    = document.getElementById('mainSub');
  const spinnerIcon= document.getElementById('spinnerIcon');
  const spinnerEl  = document.getElementById('mainSpinner');
  const resultsBox = document.getElementById('resultsBox');
  const btnVer     = document.getElementById('btnVerResultados');
 
  let resultado    = null;
  let currentStep  = 0;
 
  function setProgress(pct) {
    progressEl.style.width = pct + '%';
    pctEl.textContent = pct + '%';
  }
 
  function activateStep(n) {
    const el = document.getElementById('step-' + n);
    if (!el) return;
    el.classList.add('running');
    el.querySelector('.ev-step-icon').innerHTML = '<div class="ev-step-spin"></div>';
  }
 
  function completeStep(n, ok = true) {
    const el = document.getElementById('step-' + n);
    if (!el) return;
    el.classList.remove('running');
    el.classList.add(ok ? 'done' : 'error');
    el.querySelector('.ev-step-icon').innerHTML = ok ? '✓' : '✗';
  }
 
  async function runStep(n, delay) {
    activateStep(n);
    setProgress(Math.round((n - 0.5) / steps.length * 100));
    await wait(delay);
    completeStep(n);
    setProgress(Math.round(n / steps.length * 100));
  }
 
  function wait(ms) { return new Promise(r => setTimeout(r, ms)); }
 
  async function callEval() {
    const res = await fetch(window.APP_BASE + 'api/Evaluar_prestamo.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'evaluar_prestamo', id_prestamo: window.ID_PRESTAMO })
    });
    const raw = await res.text();
    try {
      return JSON.parse(raw);
    } catch (_) {
      return { ok: false, error: 'Respuesta invalida del servidor', raw };
    }
  }
 
  async function run() {
    if (!window.ID_PRESTAMO || Number(window.ID_PRESTAMO) <= 0) {
      mainLabel.textContent = 'Prestamo invalido';
      mainSub.textContent = 'No se recibio un id_prestamo valido para evaluar';
      spinnerEl.style.display = 'none';
      spinnerIcon.textContent = '❌';
      resultsBox.innerHTML = '<div style="color:#dc2626; font-weight:600;">⚠️ No se puede iniciar la evaluacion.</div>';
      return;
    }

    const evalPromise = callEval(); 
 
    await runStep(1, delays[0]);
    await runStep(2, delays[1]);
    await runStep(3, delays[2]);
 
    let data;
    try {
      data = await evalPromise;
    } catch (e) {
      data = { ok: false, error: 'Error de conexión' };
    }
 
    if (!data.ok) {
      completeStep(3, false);
      completeStep(4, false);
      mainLabel.textContent = 'Error en la evaluación';
      mainSub.textContent   = data.error || 'Ocurrió un error inesperado';
      spinnerEl.style.display = 'none';
      spinnerIcon.textContent = '❌';
      resultsBox.innerHTML  = `<div style="color:#dc2626; font-weight:600;">⚠️ ${data.error}</div>`;
      return;
    }
 
    resultado = data;
    await runStep(4, delays[3]);
    setProgress(100);
 
    const dec = data.decision;
    const icons = { Aprobado: '✅', Rechazado: '❌', Contrapropuesta: '🔄', Revision_manual: '👁️' };
    const labels = {
      Aprobado: 'Préstamo aprobado',
      Rechazado: 'Préstamo rechazado',
      Contrapropuesta: 'Se generaron contrapropuestas',
      Revision_manual: 'Enviado a revisión manual',
    };
 
    spinnerEl.style.display = 'none';
    spinnerIcon.textContent = icons[dec] || '📊';
    mainLabel.textContent   = labels[dec] || 'Evaluación completada';
    mainSub.textContent     = '';

    let html = `
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
        <div>
          <div style="font-size:.75rem; font-weight:700; color:#6366f1; text-transform:uppercase; letter-spacing:.05em;">Puntaje interno</div>
          <div style="font-size:1.5rem; font-weight:800; color:${data.puntaje >= 80 ? '#16a34a' : data.puntaje >= 40 ? '#d97706' : '#dc2626'};">
            ${data.puntaje} pts
          </div>
        </div>
        <div>
          <div style="font-size:.75rem; font-weight:700; color:#6366f1; text-transform:uppercase; letter-spacing:.05em;">Nivel de riesgo</div>
          <div style="font-size:1.1rem; font-weight:700; color:${data.nivel_riesgo === 'Bajo' ? '#16a34a' : data.nivel_riesgo === 'Medio' ? '#d97706' : '#dc2626'};">
            ${data.nivel_riesgo}
          </div>
        </div>
        <div>
          <div style="font-size:.75rem; font-weight:700; color:#6366f1; text-transform:uppercase; letter-spacing:.05em;">Capacidad de pago</div>
          <div style="font-weight:600;">RD$ ${Number(data.capacidad_disponible).toLocaleString('es-DO', {minimumFractionDigits:2})}</div>
        </div>
        <div>
          <div style="font-size:.75rem; font-weight:700; color:#6366f1; text-transform:uppercase; letter-spacing:.05em;">Cuota calculada</div>
          <div style="font-weight:600;">RD$ ${Number(data.cuota_calculada).toLocaleString('es-DO', {minimumFractionDigits:2})}</div>
        </div>
      </div>
    `;
 
    if (dec === 'Rechazado' && data.razones_rechazo?.length) {
      html += `<div style="margin-top:10px; padding:8px 12px; background:#fff1f2; border-radius:8px; border-left:3px solid #dc2626;">
        <div style="font-weight:700; font-size:.8rem; color:#dc2626; margin-bottom:4px;">RAZONES DE RECHAZO</div>
        ${data.razones_rechazo.map(r => `<div style="font-size:.82rem; color:#7f1d1d;">• ${r}</div>`).join('')}
      </div>`;
    }
 
    resultsBox.innerHTML = html;
    resultsBox.classList.add('visible');
    btnVer.classList.add('visible');
    btnVer.style.display = 'flex';
    btnVer.textContent = dec === 'Contrapropuesta' ? '🔄 Ver contrapropuestas' : '📊 Ver resultados completos';
 
    sessionStorage.setItem('ev_resultado_' + window.ID_PRESTAMO, JSON.stringify(data));
  }
 
  btnVer.addEventListener('click', () => {
    if (!resultado) {
      return;
    }
    const destino = resultado.decision === 'Contrapropuesta'
      ? `${window.APP_BASE}views/contrapropuesta_v.php?id_prestamo=${encodeURIComponent(window.ID_PRESTAMO)}`
      : `${window.APP_BASE}views/resultado_v.php?id_prestamo=${encodeURIComponent(window.ID_PRESTAMO)}`;
    window.location.href = destino;
  });
 
  run();
})();