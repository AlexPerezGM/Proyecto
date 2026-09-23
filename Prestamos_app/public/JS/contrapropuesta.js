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
    if (!detail || !opcionSeleccionada) return;

    // Adaptamos el diseño del modal al estilo HUD
    detail.innerHTML = `
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
        <div style="background: white; padding: 10px; border-radius: 8px;">
            <label style="display: block; font-size: 0.7rem; color: #6b7280; text-transform: uppercase;">Monto Reajustado</label>
            <span style="font-size: 1.1rem; color: #111827;">${fmt(opcionSeleccionada.monto)}</span>
        </div>
        <div style="background: white; padding: 10px; border-radius: 8px;">
            <label style="display: block; font-size: 0.7rem; color: #6b7280; text-transform: uppercase;">Plazo</label>
            <span style="font-size: 1.1rem; color: #111827;">${Number(opcionSeleccionada.plazo || 0)} meses</span>
        </div>
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 10px; border-radius: 8px; grid-column: 1 / -1;">
            <label style="display: block; font-size: 0.7rem; color: #166534; text-transform: uppercase;">Cuota Segura</label>
            <span style="font-size: 1.2rem; color: #15803d; font-weight: 900;">${fmt(opcionSeleccionada.cuota)}</span>
        </div>
      </div>
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
      console.error("Respuesta con error del servidor:", raw);
      return { ok: false, error: 'Respuesta invalida del servidor. Revisa la consola.' };
    }
  }

  async function confirmarSeleccion() {
    if (!opcionSeleccionada || !ID_PRESTAMO) return;

    const btn = document.getElementById('confirmOkBtn');
    if (btn) {
      btn.disabled = true;
      btn.textContent = '[ PROCESANDO... ]';
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
        throw new Error(data.error || data.msg || 'No se pudo confirmar la contrapropuesta');
      }

      sessionStorage.removeItem('ev_resultado_' + ID_PRESTAMO);
      alert('Oferta aplicada y enviada a revisión del supervisor exitosamente.');
      window.location.href = APP_BASE + 'views/prestamos.php';
    } catch (e) {
      alert('Error: ' + (e.message || 'No se pudo procesar la solicitud'));
      if (btn) {
        btn.disabled = false;
        btn.textContent = '[ INICIAR MODIFICACIÓN ]';
      }
    }
  }

  async function confirmarOriginal() {
    if (!confirm('¿Confirmar el envío de la solicitud original a evaluación del supervisor?')) return;
    
    try {
      const data = await callApi({
        action: 'confirmar_original',
        id_prestamo: ID_PRESTAMO
      });
      
      if (!data.ok) {
        throw new Error(data.error || data.msg || 'Error al procesar la confirmación');
      }

      sessionStorage.removeItem('ev_resultado_' + ID_PRESTAMO);
      alert('Contrato generado. Solicitud enviada a revisión del supervisor exitosamente.');
      window.location.href = APP_BASE + 'views/prestamos.php';
    } catch(e) {
      alert('Error: ' + (e.message || 'No se pudo procesar la solicitud'));
    }
  }

  async function rechazarTodo() {
    if (!confirm('¿Confirmar que deseas descartar esta operación y borrar la solicitud temporal?')) {
      return;
    }

    try {
      const data = await callApi({
        action: 'rechazar_contrapropuesta',
        id_prestamo: ID_PRESTAMO,
      });
      
      if (!data.ok) {
        throw new Error(data.error || data.msg || 'No se pudo rechazar la contrapropuesta');
      }

      sessionStorage.removeItem('ev_resultado_' + ID_PRESTAMO);
      alert('Solicitud cancelada. El registro temporal ha sido eliminado del sistema.');
      window.location.href = APP_BASE + 'views/prestamos.php';
    } catch (e) {
      alert('Error: ' + (e.message || 'No se pudo procesar el rechazo'));
    }
  }

  document.getElementById('confirmOkBtn')?.addEventListener('click', confirmarSeleccion);
  document.getElementById('confirmOverlay')?.addEventListener('click', function (e) {
    if (e.target === this) cerrarModal();
  });

  window.abrirConfirmacion = abrirConfirmacion;
  window.cerrarModal = cerrarModal;
  window.rechazarTodo = rechazarTodo;
})();

window.confirmarOriginal = confirmarOriginal;