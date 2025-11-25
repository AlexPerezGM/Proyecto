// public/JS/pagos.js
(() => {
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
      ui.csal.textContent = Number(c.saldo_cuota || 0).toFixed(2);
    } else {
      ui.cnum.textContent = '-';
      ui.cfecha.textContent = '-';
      ui.ccap.textContent = ui.cint.textContent = ui.ccarg.textContent = ui.csal.textContent = '0.00';
    }

    const mora = Number(js.mora?.mora_total || 0);
    ui.mora.textContent = mora.toFixed(2);
    const totalHoy = Number(prestamoSel?.cuota_actual?.saldo_cuota || 0) + mora;
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

  $btnAplicarMora.addEventListener('click', async () => {
    if (!prestamoSel) return;
    const js = await jsonFetch({ action: 'calc_mora', id_prestamo: prestamoSel.id_prestamo });
    const mora = Number(js.mora_total || 0);
    ui.mora.textContent = mora.toFixed(2);
    const totalHoy = Number(prestamoSel?.cuota_actual?.saldo_cuota || 0) + mora;
    ui.total.textContent = totalHoy.toFixed(2);
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
          <div>
            <h4 style="margin:0 0 8px">Recibo de pago</h4>
            <p><b>Préstamo:</b> ${prestamoSel?.id_prestamo ?? ''}</p>
            <p><b>Método:</b> ${c.metodo}</p>
            <p><b>Monto:</b> ${c.moneda} ${(+(c.monto)).toFixed(2)} (≈ RD$ ${Number(c.monto_dop ?? c.monto).toFixed(2)})</p>
            ${c.referencia ? `<p><b>Referencia:</b> ${c.referencia}</p>` : ''}
            ${c.observacion ? `<p><b>Observación:</b> ${c.observacion}</p>` : ''}
            <p><b>Cuotas pagadas:</b> ${c.cuotas_pagadas ?? 0}</p>
            <p><b>Fecha:</b> ${c.fecha}</p>
            ${c.pdf_url ? `<p><a class="btn-primary" href="${c.pdf_url}" target="_blank" rel="noopener">Imprimir factura (PDF)</a></p>` : ''}
            ${js.prestamo_cerrado ? `<p><b>Estado:</b> Préstamo CERRADO</p>` : ''}
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
      if (prestamoSel) await cargarResumen(prestamoSel.id_prestamo);
      form.reset();
    });
  }
  onSubmit(document.getElementById('frmEfectivo'));
  onSubmit(document.getElementById('frmTransfer'));
  onSubmit(document.getElementById('frmGarantia'));
  onSubmit(document.getElementById('frmCierre'));
})();
