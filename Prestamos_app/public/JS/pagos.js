// public/JS/pagos.js
document.addEventListener('DOMContentLoaded', () => {
  const ROOT = location.pathname.replace(/\/views\/.*/, '/');
  const API = ROOT + 'api/pagos.php';

  const $q = document.getElementById('q');
  const $btnBuscar = document.getElementById('btnBuscar');
  const $tab = document.querySelector('#tablaResultados tbody');
  const $err = document.getElementById('errorBox');

  const $panel = document.getElementById('panelResumen');
  const ui = {
    estado: document.getElementById('p_estado'),
    saldo: document.getElementById('p_saldo'),
    cnum: document.getElementById('c_num'),
    cfecha: document.getElementById('c_fecha'),
    ccap: document.getElementById('c_capital'),
    cint: document.getElementById('c_interes'),
    ccarg: document.getElementById('c_cargos'),
    csal: document.getElementById('c_saldo'),
    csal_restante: document.getElementById('c_saldo_restante'),
    mora: document.getElementById('p_mora'),
    total: document.getElementById('p_total_hoy'),
  };

  const MODS = {
    efectivo: document.getElementById('modalEfectivo'),
    transfer: document.getElementById('modalTransfer'),
    garantia: document.getElementById('modalGarantia'),
    cierre: document.getElementById('modalCierre'),
    comp: document.getElementById('modalComprobante'),
  };
  const open = (el) => el.classList.add('show');
  const close = (el) => el.classList.remove('show');
  document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => close(b.closest('.modal'))));
  window.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal.show').forEach(m => close(m)); });

  const $btnEfectivo = document.getElementById('btnEfectivo');
  const $btnTransfer = document.getElementById('btnTransfer');
  const $btnGarantia = document.getElementById('btnGarantia');
  const $btnCerrar = document.getElementById('btnCerrar');
  const $btnAplicarMora = document.getElementById('btnAplicarMora');

  const $ef_id = document.getElementById('ef_id_prestamo');
  const $tr_id = document.getElementById('tr_id_prestamo');
  const $ga_id = document.getElementById('ga_id_prestamo');
  const $cl_id = document.getElementById('cl_id_prestamo');

  let prestamoSel = null;

  async function jsonFetch(body) {
    $err.hidden = true;
    const res = await fetch(API, {
      method: 'POST',
      body: body instanceof FormData ? body : new URLSearchParams(body)
    });
    const txt = await res.text();
    let js;
    try { js = JSON.parse(txt); }
    catch (e) { $err.hidden = false; $err.textContent = 'Respuesta no-JSON:\n' + txt.slice(0, 2000); throw e; }
    if (js && js.ok === false && js.msg) { $err.hidden = false; $err.textContent = js.msg; }
    return js;
  }

  function fmt(v) {
    const n = Number(v || 0);
    try {
      return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
    } catch (e) {
      return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
  }

  function pintarTabla(rows) {
    if (!rows || rows.length === 0) {
      $tab.innerHTML = `<tr><td colspan="5" class="muted">Sin resultados…</td></tr>`;
      return;
    }
    $tab.innerHTML = rows.map(r => `
      <tr>
        <td>${r.id_prestamo}</td>
        <td>${r.nombre} ${r.apellido}</td>
        <td>${r.numero_documento ?? '-'}</td>
        <td>${r.estado_prestamo ?? '-'}</td>
        <td><button class="btn-light" data-sel="${r.id_prestamo}">Seleccionar</button></td>
      </tr>
    `).join('');
  }

  async function cargarCronograma(id) {
    try {
      const js = await jsonFetch({
        action: 'cronograma', id_prestamo: id
      });
      const filas = js.cronograma || [];
      const $tbCron = document.querySelector('#tablaCronograma tbody');
      if (!$tbCron) return;
      if (filas.length === 0) {
        $tbCron.innerHTML = `<tr><td colspan="7" class="muted"> Sin cuotas...</td></tr>`;
        return;
      }
      $tbCron.innerHTML = filas.map(r => `
      <tr data-id="${r.id_cronograma_cuota}">
        <td>${r.numero_cuota}</td>
        <td>${r.fecha_vencimiento}</td>
        <td>${Number(r.capital_cuota).toFixed(2)}</td>
        <td>${Number(r.interes_cuota).toFixed(2)}</td>
        <td>${Number(r.cargos_cuota).toFixed(2)}</td>
        <td>${Number(r.saldo_cuota).toFixed(2)}</td>
        <td>${r.estado_cuota}</td>
      </tr>`).join('');
    } catch (e) {
      console.error('Error cargando cronograma:', e);
    }

  }

  async function cargarResumen(id) {
    const js = await jsonFetch({ action: 'summary', id_prestamo: id });
    prestamoSel = js.resumen;

    ui.estado.textContent = prestamoSel?.estado ?? '-';
    ui.saldo.textContent = Number(prestamoSel?.saldo_total || 0).toFixed(2);

    const c = prestamoSel?.cuota_actual;
    if (c) {
      ui.cnum.textContent = c.numero_cuota;
      ui.cfecha.textContent = c.fecha_vencimiento;
      ui.ccap.textContent = Number(c.capital || 0).toFixed(2);
      ui.cint.textContent = Number(c.interes || 0).toFixed(2);
      ui.ccarg.textContent = Number(c.cargos || 0).toFixed(2);
      ui.csal.textContent = Number(c.cuota_a_pagar || 0).toFixed(2);
      if (ui.csal_restante) {
        ui.csal_restante.textContent = Number(c.saldo_restante || 0).toFixed(2);
      }
    } else {
      ui.cnum.textContent = '-';
      ui.cfecha.textContent = '-';
      ui.ccap.textContent = ui.cint.textContent = ui.ccarg.textContent = ui.csal.textContent = '0.00';
    }

    const mora = Number(js.mora?.mora_total || 0);
    ui.mora.textContent = mora.toFixed(2);
    const totalHoy = Number(prestamoSel?.cuota_actual?.cuota_a_pagar || 0) + mora;
    ui.total.textContent = totalHoy.toFixed(2);

    $panel.style.display = 'block';
    $ef_id.value = $tr_id.value = $ga_id.value = $cl_id.value = prestamoSel.id_prestamo;
  }

  $btnBuscar.addEventListener('click', async () => {
    const js = await jsonFetch({ action: 'search', q: $q.value.trim() });
    const rows = js.data || [];
    pintarTabla(rows);
    if (rows.length === 1) await cargarResumen(+rows[0].id_prestamo);
  });
  $q.addEventListener('keydown', e => { if (e.key === 'Enter') $btnBuscar.click(); });

  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-sel]');
    if (!b) return;
    await cargarResumen(+b.dataset.sel);
  });

  $btnEfectivo.addEventListener('click', () => open(MODS.efectivo));
  $btnTransfer.addEventListener('click', () => open(MODS.transfer));
  $btnGarantia.addEventListener('click', () => open(MODS.garantia));
  $btnCerrar.addEventListener('click', () => open(MODS.cierre));

  function onSubmit(form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const js = await jsonFetch(new FormData(form));
      if (js && js.ok === false) return;

      const $c = document.getElementById('compContenido');
      $c.innerHTML = '';

      if (js.comprobante) {
        const c = js.comprobante;
        $c.innerHTML = `
          <div style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; background: #fff; padding: 0 10px;">
            <div style="text-align: center; margin-bottom: 20px;">
              <div style="font-size: 24px; margin-bottom: 5px;">Comprobante de pago</div>
              <p style="margin: 2px 0; font-size: 14px; color: #777;">
                RNC: 000-00000-0
                Telefono: (829)-555-5555
                ${c.fecha}
              </p>
              <div style="margin-top: 10px; font-weight: bold; text-transform: uppercase; font-size: 18px; letter-spacing: 1px;">
                Factura N°: ${js.id_pago || '-'}
              </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 15px;">
              <div>
                <span style="font-size: 16px; color: #999; text-transform: uppercase;">Cliente: </span>
                <strong style="font-size: 16px;">${c.nombre} ${c.apellido}</strong>
              </div>
              <div style="text-align: right;">
                <span style="font-size: 16px; color: #999; text-transform: uppercase;">Prestamo</span>
                <strong>N° ${c.id_prestamo}</strong>
              </div>
            </div>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
              <tr>
                <td style="padding: 5px 0; color: #666;">Capital pagado</td>
                <td style="padding: 5px 0; text-align: right; font-weight: 600;">${c.moneda} ${fmt(c.capital)}</td>
              </tr>
              <tr>
                <td style="padding: 5px 0; color: #666;">Interes pagado</td>
                <td style="padding: 5px 0; text-align: right; font-weight: 600;">${c.moneda} ${fmt(c.interes)}</td>
              </tr>
              <tr style="border-bottom: 2px solid #333;">
                <td style="padding: 5px 0; color: #666;">Mora pagada</td>
                <td style="padding: 5px 0; text-align: right; font-weight: 600;">${c.moneda} ${fmt(c.mora)}</td>
              </tr>
              <tr>
                <td style="padding: 5px 0; font-size: 18px; color: #666;">Total pagado</td>
                <td style="padding: 5px 0; text-align: right; font-weight: 800;">${c.moneda} ${fmt(c.monto)}</td>
              </tr>
            </table>

            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; font-size: 14px;">
              <strong style="color: #555;">Concepto:</strong>
              <p style="margin: 8px 0; color: #333; line-height: 1.4;">
                Pago de la cuota N° <b>${c.numero_cuota || 'General'}</b>.<br>
                <span style="font-size: 12px; color: #666;">
                  Metodo: ${c.metodo} ${c.referencia ? `(Ref: ${c.referencia})` : ''}
                </span>
              </p>
              ${c.observacion ? `<p style="margin-top: 5px; font-style: italic; color: #666;">Obs: ${c.observacion}</p>` : ''}
            </div>
            <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #ccc;">
              *** Gracias por su preferencia ***
            </div>
            <div style="text-align: center; margin-top: 15px;">
              <button onclick="window.print()" style="background: none; border: 1px solid #ccc; padding: 5px 10px; cursor: pointer; border-radius: 4px; font-size: 0.8rem;">🖨️ Imprimir</button>
            </div>
          </div>
        `;
        open(MODS.comp);
      } else if (js.comprobante_cierre) {
        const c = js.comprobante_cierre;
        $c.innerHTML = `
          <div>
            <h4 style="margin:0 0 8px">Comprobante de cierre de préstamo</h4>
            <p><b>Préstamo:</b> ${c.id_prestamo}</p>
            <p><b>Fecha:</b> ${c.fecha}</p>
            ${c.observacion ? `<p><b>Observación:</b> ${c.observacion}</p>` : ''}
          </div>
        `;
        open(MODS.comp);
      }

      close(form.closest('.modal'));
      if (prestamoSel) {
        await cargarResumen(prestamoSel.id_prestamo);
        await cargarCronograma(prestamoSel.id_prestamo);
      }
      form.reset();
    });
  }
  onSubmit(document.getElementById('frmEfectivo'));
  onSubmit(document.getElementById('frmTransfer'));
  onSubmit(document.getElementById('frmGarantia'));
  onSubmit(document.getElementById('frmCierre'));
});
