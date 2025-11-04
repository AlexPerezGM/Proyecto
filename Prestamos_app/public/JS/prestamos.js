(() => {
  const API = (window.APP_BASE || '/') + 'api/prestamos.php';
  const API_CLIENTES = (window.APP_BASE || '/') + 'api/clientes.php';

  // utilidades (igual patrón de clientes.js)
  const $err = document.getElementById('errorBox');
  const openModal = el => el.classList.add('show');
  const closeModal = el => el.classList.remove('show');
  document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => closeModal(b.closest('.modal'))));
  window.addEventListener('keydown', e => { if (e.key === 'Escape'){ document.querySelectorAll('.modal.show').forEach(m=>closeModal(m)); } });

  async function jsonFetch(url, body){
    $err.hidden = true;
    try{
      const res = await fetch(url, { method: 'POST', headers: { 'Accept':'application/json' }, body });
      const text = await res.text();
      try { return JSON.parse(text); }
      catch(parseErr){ $err.hidden = false; $err.textContent = 'Respuesta no-JSON de la API:\n' + text.slice(0,2000); throw parseErr; }
    }catch(e){ if($err.hidden){ $err.hidden=false; $err.textContent='Error consultando API:\n'+(e.message||e); } throw e; }
  }

  // Tabs
  const tabs = document.querySelectorAll('.tabs .btn');
  const sections = {
    solicitar: document.getElementById('sec-solicitar'),
    lista: document.getElementById('sec-lista'),
    desembolso: document.getElementById('sec-desembolso')
  };
  tabs.forEach(btn=>{
    btn.addEventListener('click', ()=>{
      tabs.forEach(b=>b.classList.add('outline'));
      btn.classList.remove('outline');
      const tab = btn.dataset.tab;
      Object.entries(sections).forEach(([k,el])=> el.classList.toggle('hidden', k!==tab));
    });
  });

  /** Catálogos y moneda */
  const $selMoneda = document.getElementById('selMoneda');
  let MONEDAS = [];
  let PERIODOS = [];
  async function cargarCatalogos(){
    const js = await jsonFetch(API, new URLSearchParams({action:'catalogos'}));
    MONEDAS = js.data?.monedas || [];
    PERIODOS = js.data?.periodos || [];
    $selMoneda.innerHTML = MONEDAS.map(m=>`<option value="${m.id}">${m.txt}</option>`).join('');
    document.getElementById('per_personal').innerHTML = PERIODOS.map(p=>`<option value="${p.id}">${p.txt}</option>`).join('');
    document.getElementById('per_hipo').innerHTML     = PERIODOS.map(p=>`<option value="${p.id}">${p.txt}</option>`).join('');
    // mínimos por defecto (desde tipo_prestamo)
    const defs = js.data?.defaults || [];
    const pers = defs.find(d=>+d.id_tipo_prestamo===1);
    const hipo = defs.find(d=>+d.id_tipo_prestamo===2);
    if(pers){ document.getElementById('tasa_personal').value = pers.tasa_interes; document.getElementById('monto_personal').placeholder = `≥ ${(+pers.monto_minimo).toFixed(2)} DOP`; }
    if(hipo){ document.getElementById('tasa_hipo').value     = hipo.tasa_interes; document.getElementById('monto_hipo').placeholder     = `≥ ${(+hipo.monto_minimo).toFixed(2)} DOP`; }
  }

  /** Buscar/seleccionar cliente */
  const $qC = document.getElementById('qCliente');
  const $btnBuscarC = document.getElementById('btnBuscarCliente');
  const $resC = document.getElementById('resClientes');
  const $boxInfoC = document.getElementById('boxInfoCliente');
  const $infoGrid  = document.getElementById('infoClienteGrid');
  let CLIENTE = null;

  $btnBuscarC.addEventListener('click', async ()=>{
    const q = $qC.value.trim();
    const js = await jsonFetch(API, new URLSearchParams({action:'buscar_cliente', q}));
    $resC.innerHTML = `
      <table class="table-simple"><thead><tr>
        <th>ID</th><th>Nombre</th><th>Documento</th><th>Contacto</th><th>Ingresos</th><th></th>
      </tr></thead><tbody>
      ${js.data.map(r=>`
        <tr>
          <td>${r.id_cliente}</td>
          <td>${r.nombre} ${r.apellido}</td>
          <td>${r.numero_documento ?? '-'}</td>
          <td>${r.email ?? '-'}<small>${r.telefono ?? ''}</small></td>
          <td>$${(+r.ingresos_mensuales||0).toFixed(2)}</td>
          <td><button class="btn btn-light" data-sel="${r.id_cliente}">Seleccionar</button></td>
        </tr>`).join('')}
      </tbody></table>
    `;
  });

  document.getElementById('resClientes').addEventListener('click', (e)=>{
    const b = e.target.closest('[data-sel]'); if(!b) return;
    const tr = b.closest('tr'); // simple para pintar
    const tds = tr.querySelectorAll('td');
    CLIENTE = {
      id_cliente: +tds[0].textContent,
      nombre: tds[1].textContent,
      documento: tds[2].textContent,
      contacto: tds[3].textContent,
      ingresos: tds[4].textContent
    };
    $boxInfoC.classList.remove('hidden');
    $infoGrid.innerHTML = `
      <div><b>Nombre</b><div>${CLIENTE.nombre}</div></div>
      <div><b>Documento</b><div>${CLIENTE.documento}</div></div>
      <div><b>Dirección</b><div>—</div></div>
      <div><b>Teléfono / Email</b><div>${CLIENTE.contacto}</div></div>
      <div><b>Ingresos mensuales</b><div>${CLIENTE.ingresos}</div></div>
      <div><b>Ocupación</b><div>—</div></div>
    `;
    document.getElementById('id_cliente_personal').value = CLIENTE.id_cliente;
    document.getElementById('id_cliente_hipo').value     = CLIENTE.id_cliente;
  });

  // Crear cliente rápido
  document.getElementById('btnAbrirCrearCliente').addEventListener('click', ()=> openModal(document.getElementById('modalCrearCliente')));
  document.getElementById('frmClienteQuick').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const js = await jsonFetch(API_CLIENTES, new FormData(e.target));
    if(!js.ok) return alert(js.msg || 'Error');
    closeModal(document.getElementById('modalCrearCliente'));
    $btnBuscarC.click();
  });

  // Abrir modales de solicitud
  document.getElementById('btnPrestamoPersonal').addEventListener('click', ()=> openModal(document.getElementById('modalPersonal')));
  document.getElementById('btnPrestamoHipotecario').addEventListener('click', ()=> openModal(document.getElementById('modalHipotecario')));
  document.getElementById('btnMinPersonal').addEventListener('click', ()=> {/* placeholder si luego guardas override */});
  document.getElementById('btnMinHipotecario').addEventListener('click', ()=> {/* placeholder */});

  // Envío solicitudes
  function withMoneda(fd){
    // Inyecta id_tipo_moneda seleccionado
    fd.set('id_tipo_moneda', $selMoneda.value || '1');
    return fd;
  }
  document.getElementById('frmPersonal').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const js = await jsonFetch(API, withMoneda(new FormData(e.target)));
    if(!js.ok) return alert(js.msg || 'Error');
    closeModal(document.getElementById('modalPersonal'));
    alert('Préstamo creado #'+js.id_prestamo);
  });
  document.getElementById('frmHipotecario').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const porc = parseFloat(document.getElementById('porc_fin').value||'0');
    if(porc>80) return alert('El porcentaje no puede exceder 80%');
    const js = await jsonFetch(API, withMoneda(new FormData(e.target)));
    if(!js.ok) return alert(js.msg || 'Error');
    closeModal(document.getElementById('modalHipotecario'));
    alert('Préstamo creado #'+js.id_prestamo);
  });

  /** LISTA */
  const PAGE = {cur:1, size:10};
  const $tblP = document.querySelector('#tablaPrestamos tbody');
  const $pager = document.getElementById('pagPrestamos');
  async function cargarPrestamos(page=1){
    PAGE.cur = page;
    const fd = new URLSearchParams({
      action:'list',
      q: document.getElementById('qPrestamo').value.trim(),
      tipo: document.getElementById('fTipoPrestamo').value,
      page, size: PAGE.size
    });
    const js = await jsonFetch(API, fd);
    $tblP.innerHTML = (js.data||[]).map(r => `
      <tr>
        <td>${r.id_prestamo}</td>
        <td>${r.nombre} ${r.apellido}</td>
        <td>${r.tipo_prestamo}</td>
        <td>$${(+r.monto_solicitado).toFixed(2)}</td>
        <td>${(+r.tasa_interes||0).toFixed(2)}%</td>
        <td>${r.plazo_meses} m</td>
        <td>${r.estado_prestamo || '-'}</td>
        <td>${r.proximo_pago || '-'}</td>
        <td><button class="btn btn-light" data-verp="${r.id_prestamo}">Ver</button></td>
      </tr>
    `).join('');
    const total = +js.total || 0, pages = Math.max(1, Math.ceil(total/PAGE.size));
    $pager.innerHTML = Array.from({length:pages}, (_,i)=>`<button ${i+1===page?'class="active"':''} data-p="${i+1}">${i+1}</button>`).join('');
  }
  document.getElementById('btnBuscarPrestamo').addEventListener('click', ()=> cargarPrestamos(1));
  $pager.addEventListener('click', e=>{ const b=e.target.closest('[data-p]'); if(b) cargarPrestamos(+b.dataset.p); });
  document.addEventListener('click', async (e)=>{
    const b = e.target.closest('[data-verp]'); if(!b) return;
    const id = b.dataset.verp;
    const js = await jsonFetch(API, new URLSearchParams({action:'get', id_prestamo:id}));
    const p = js.data||{};
    const cron = js.cronograma||[];
    const html = `
      <div class="grid-2">
        <div>
          <h4>Resumen</h4>
          <p><b>Código:</b> ${p.id_prestamo}</p>
          <p><b>Cliente:</b> ${p.nombre} ${p.apellido}</p>
          <p><b>Tipo:</b> ${p.tipo_prestamo}</p>
          <p><b>Monto:</b> $${(+p.monto_solicitado||0).toFixed(2)}</p>
          <p><b>Tasa:</b> ${(+p.tasa_interes||0).toFixed(2)}% anual</p>
          <p><b>Plazo:</b> ${p.plazo_meses} meses</p>
          <p><b>Frecuencia:</b> ${p.periodo_txt||'-'}</p>
        </div>
        <div>
          <h4>Cronograma</h4>
          <div class="table-responsive" style="max-height:320px; overflow:auto;">
            <table class="table-simple">
              <thead><tr><th>#</th><th>Vence</th><th>Capital</th><th>Interés</th><th>Cuota</th><th>Estado</th></tr></thead>
              <tbody>
                ${cron.map(c=>`
                <tr class="estado-${(c.estado_cuota||'').toLowerCase()}">
                  <td>${c.numero_cuota}</td>
                  <td>${c.fecha_vencimiento}</td>
                  <td>$${(+c.capital_cuota).toFixed(2)}</td>
                  <td>$${(+c.interes_cuota).toFixed(2)}</td>
                  <td>$${(+c.total_monto).toFixed(2)}</td>
                  <td>${c.estado_cuota}</td>
                </tr>`).join('')}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    `;
    document.getElementById('verPrestamoContenido').innerHTML = html;
    openModal(document.getElementById('modalVerPrestamo'));
  });

  /** Desembolso */
  const $qDes = document.getElementById('qDesembolso');
  const $btnDes = document.getElementById('btnBuscarDesembolso');
  const $boxDes = document.getElementById('boxDesembolso');
  const $met = document.getElementById('metodo_entrega');
  async function cargarMetodos(){
    const js = await jsonFetch(API, new URLSearchParams({action:'metodos'}));
    $met.innerHTML = (js.data||[]).map(m=>`<option value="${m.id}">${m.txt}</option>`).join('');
  }
  $btnDes.addEventListener('click', async ()=>{
    const js = await jsonFetch(API, new URLSearchParams({action:'buscar_prestamo', q:$qDes.value.trim()}));
    if(!(js.data||[]).length) { alert('Sin resultados'); return; }
    // usa el primero (simple). Puedes mejorar para mostrar lista
    const p = js.data[0];
    document.getElementById('desResumen').value = `${p.cliente} · ${p.tipo} · #${p.id_prestamo} · $${(+p.monto_solicitado).toFixed(2)}`;
    document.getElementById('id_prestamo_des').value = p.id_prestamo;
    $boxDes.classList.remove('hidden');
  });
  document.getElementById('frmDesembolso').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const js = await jsonFetch(API, new FormData(e.target));
    if(!js.ok) return alert(js.msg || 'Error');
    alert('Desembolso registrado');
  });

  // Recibo (HTML listo para imprimir/guardar PDF)
  const $btnRecibo = document.getElementById('btnRecibo');
  $btnRecibo.addEventListener('click', async ()=>{
    const id = document.getElementById('id_prestamo_des').value;
    const url = API + '?action=recibo_html&id_prestamo=' + encodeURIComponent(id);
    const html = await fetch(url).then(r=>r.text());
    document.getElementById('reciboHTML').innerHTML = html;
    openModal(document.getElementById('modalRecibo'));
  });
  document.getElementById('btnReciboImprimir').addEventListener('click', ()=>{
    const w = window.open('', '_blank'); w.document.write(document.getElementById('reciboHTML').innerHTML); w.document.close(); w.focus(); w.print();
  });
  document.getElementById('btnReciboDescargar').addEventListener('click', ()=>{
    // sin librerías externas: usa el cuadro de impresión para "Guardar como PDF"
    const w = window.open('', '_blank'); w.document.write(document.getElementById('reciboHTML').innerHTML); w.document.close(); w.focus(); w.print();
  });

  // Cargar inicial
  (async ()=>{ await cargarCatalogos(); await cargarMetodos(); cargarPrestamos(1); })();
})();
