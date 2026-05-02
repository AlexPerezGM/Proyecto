(() => {
  const APP_BASE = window.APP_BASE || '/';
  const ID_PRESTAMO = Number(window.ID_PRESTAMO || 0);
  const API = APP_BASE + 'api/Evaluar_prestamo.php';

  let opcionSeleccionada = null;

  function fmt(n) {
    return 'RD$ ' + Number(n || 0).toLocaleString('es-DO', { minimumFractionDigits: 2 });
  }

  function abrirConfirmacion(cp) {
    opcionSeleccionada = cp || null;
    const detail = document.getElementById('confirmDetail');
    if (!detail || !opcionSeleccionada) {
      return;
    }

    detail.innerHTML = `
      <div class="row"><span class="lbl">Monto reajustado:</span><span class="val">${fmt(opcionSeleccionada.monto)}</span></div>
      <div class="row"><span class="lbl">Plazo:</span><span class="val">${Number(opcionSeleccionada.plazo || 0)} meses</span></div>
      <div class="row"><span class="lbl">Cuota mensual:</span><span class="val">${fmt(opcionSeleccionada.cuota)}</span></div>
      <div class="row"><span class="lbl">Tasa interes:</span><span class="val">${Number(opcionSeleccionada.tasa || 0).toFixed(2)}%</span></div>
      <div class="row"><span class="lbl">Total a pagar:</span><span class="val">${fmt(opcionSeleccionada.total_pagar)}</span></div>
    `;

    document.getElementById('confirmOverlay')?.classList.add('open');
  }

  function cerrarModal() {
    opcionSeleccionada = null;
    document.getElementById('confirmOverlay')?.classList.remove('open');
  }

  async function callApi(payload) {
    const res = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const raw = await res.text();
    try {
      return JSON.parse(raw);
    } catch (_) {
      return { ok: false, error: 'Respuesta invalida del servidor' };
    }
  }

  async function confirmarSeleccion() {
    if (!opcionSeleccionada || !ID_PRESTAMO) {
      return;
    }

    const btn = document.getElementById('confirmOkBtn');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Procesando...';
    }

    try {
      const payload = {
        action: 'confirmar_contrapropuesta',
        id_prestamo: ID_PRESTAMO,
        opcion: Number(opcionSeleccionada.opcion || 0),
      };
      if (opcionSeleccionada.id_contrapropuesta) {
        payload.id_contrapropuesta = Number(opcionSeleccionada.id_contrapropuesta);
      }

      const data = await callApi(payload);
      if (!data.ok) {
        throw new Error(data.error || 'No se pudo confirmar la contrapropuesta');
      }

      sessionStorage.removeItem('ev_resultado_' + ID_PRESTAMO);
      window.location.href = APP_BASE + 'views/Evaluacion_v.php?id_prestamo=' + encodeURIComponent(ID_PRESTAMO);
    } catch (e) {
      alert('Error: ' + (e.message || 'No se pudo procesar la solicitud'));
      if (btn) {
        btn.disabled = false;
        btn.textContent = '📋 Confirmar contrapropuesta';
      }
    }
  }

  async function rechazarTodo() {
    if (!confirm('Confirmar que el cliente rechaza las contrapropuestas y desea cancelar la solicitud?')) {
      return;
    }

    try {
      const data = await callApi({
        action: 'rechazar_contrapropuesta',
        id_prestamo: ID_PRESTAMO,
      });
      if (!data.ok) {
        throw new Error(data.error || 'No se pudo rechazar la contrapropuesta');
      }

      sessionStorage.removeItem('ev_resultado_' + ID_PRESTAMO);
      window.location.href = APP_BASE + 'views/resultado_v.php?id_prestamo=' + encodeURIComponent(ID_PRESTAMO);
    } catch (e) {
      alert('Error: ' + (e.message || 'No se pudo procesar el rechazo'));
    }
  }

  document.getElementById('confirmOkBtn')?.addEventListener('click', confirmarSeleccion);
  document.getElementById('confirmOverlay')?.addEventListener('click', function (e) {
    if (e.target === this) {
      cerrarModal();
    }
  });

  window.abrirConfirmacion = abrirConfirmacion;
  window.cerrarModal = cerrarModal;
  window.rechazarTodo = rechazarTodo;
})();
