window.APP_BASE = typeof window.APP_BASE === 'string' ? window.APP_BASE : '';
const idFromQuery = new URLSearchParams(window.location.search).get('id_prestamo');
window.ID_PRESTAMO = Number.isFinite(Number(window.ID_PRESTAMO))
  ? Number(window.ID_PRESTAMO)
  : (Number(idFromQuery) || 0);
 
const cached = sessionStorage.getItem('ev_resultado_' + window.ID_PRESTAMO);
if (cached) {
  try {
    const d = JSON.parse(cached);
    const c = d.cliente || {};
    const fmt = v => v !== undefined ? v : '—';
 
    const elHistPagos = document.getElementById('txtHistPagos');
    const elTiempo    = document.getElementById('txtTiempoCliente');
    const elActivos   = document.getElementById('txtCreditosActivos');
 
    if (elHistPagos) {
      const ok  = fmt(c.pagos_ok);
      const bad = fmt(c.pagos_vencidos);
      elHistPagos.textContent = (c.pagos_ok !== undefined)
        ? `${ok} al día, ${bad} vencidos`
        : '—';
    }
    if (elTiempo) {
      elTiempo.textContent = c.meses_cliente !== undefined
        ? (c.meses_cliente > 12
            ? Math.floor(c.meses_cliente/12) + ' año(s)'
            : c.meses_cliente + ' mes(es)')
        : '—';
    }
    if (elActivos) elActivos.textContent = fmt(c.prestamos_activos);
  } catch(e) {}
}
 
const btnContrato = document.getElementById('btnGenerarContrato');
if (btnContrato) {
  btnContrato.addEventListener('click', () => {
    const appBase = (typeof window.APP_BASE === 'string' && window.APP_BASE) ? window.APP_BASE : '/';
    const contratoUrl = `${appBase}views/contrato.php?id_prestamo=${encodeURIComponent(window.ID_PRESTAMO)}`;
    window.open(contratoUrl, '_blank');
  });
}