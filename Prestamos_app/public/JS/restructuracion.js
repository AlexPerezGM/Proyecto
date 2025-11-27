(() => {
  const API = (window.APP_BASE || '/') + 'api/restructuracion.php';

  const $q = document.getElementById('q');
  const $btnBuscar = document.getElementById('btnBuscar');
  const $tabla = document.querySelector('#tablaRestructuracion tbody');
  const $paginacion = document.getElementById('paginacion');
  const $err = document.getElementById('errorBox');
  const $totalTag = document.getElementById('totalTag');

  const $modalForm = document.getElementById('modalForm');
  const $frm = document.getElementById('frmRestructurar');
  const $errForm = document.getElementById('errorBoxForm');

  const openModal = (el) => el.classList.add('show');
  const closeModal = (el) => el.classList.remove('show');
  document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => closeModal(b.closest('.modal'))));
  window.addEventListener('keydown', e => { if (e.key === 'Escape') { document.querySelectorAll('.modal.show').forEach(m => closeModal(m)); } });
  // paginacion 
  let currentPage = 1;
  const PAGE_SIZE = 10;

  async function jsonFetch(url, body) {
    $err.hidden = true;
    try {
      const res = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body });
      const text = await res.text();
      try { return JSON.parse(text); } catch (parseErr) {
        $err.hidden = false;
        $err.textContent = 'Respuesta no-JSON de la API:\n' + text.slice(0, 2000);
        throw parseErr;
      }
    } catch (e) {
      if ($err.hidden) { $err.hidden = false; $err.textContent = 'Error consultando API:\n' + (e.message || e); }
      throw e;
    }
  }

  async function cargar(page = 1) {
    currentPage = page;
    const params = new URLSearchParams({ action: 'list', q: $q.value.trim(), page, size: PAGE_SIZE });
    const json = await jsonFetch(API, params);
    const data = json.data || [];
    $tabla.innerHTML = data.map(r => `
      <tr>
        <td>${r.id_prestamo}</td>
        <td>${r.cliente}</td>
        <td>${r.documento || '-'}</td>
        <td>$${(+r.monto).toFixed(2)}</td>
        <td>${r.plazo}</td>
        <td><button class="btn" data-editar="${r.id_prestamo}">Reestructurar</button></td>
      </tr>
    `).join('');

    const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    let html = '';
    for (let p = 1; p <= pages; p++) html += `<button ${p === page ? 'class="active"' : ''} data-page="${p}">${p}</button>`;
    $paginacion.innerHTML = html;
  }

  $paginacion.addEventListener('click', e => {
    const b = e.target.closest('button[data-page]'); if (!b) return;
    cargar(+b.dataset.page);
  });
  $btnBuscar.addEventListener('click', () => cargar(1));
  $q.addEventListener('keydown', e => { if (e.key === 'Enter') cargar(1); });

  document.addEventListener('click', async (e) => {
    const ed = e.target.closest('[data-editar]');
    if (!ed) return;
    const id = +ed.dataset.editar;

    const params = new URLSearchParams({ action: 'get', id_prestamo: id });
    const json = await jsonFetch(API, params);
    if (!json.ok) return;

    document.getElementById('id_prestamo').value = json.data.id_prestamo;
    document.getElementById('cliente_lbl').value = json.data.cliente;
    document.getElementById('doc_lbl').value = json.data.documento || '';
    document.getElementById('monto_actual').value = (+json.data.monto_actual).toFixed(2);
    document.getElementById('plazo_actual').value = json.data.plazo_actual;
    document.getElementById('nueva_tasa').value = (json.data.tasa_actual ?? '').toString();
    document.getElementById('nuevo_plazo').value = json.data.plazo_actual;
    document.getElementById('nueva_fecha').value = (json.data.prox_fecha || '').slice(0, 10);
    document.getElementById('motivo').value = '';

    openModal($modalForm);
  });

  $frm.addEventListener('submit', async (e) => {
    e.preventDefault();
    $errForm.hidden = true; $errForm.textContent = '';
    const fd = new FormData($frm);

    const plazo = parseInt(fd.get('nuevo_plazo') || '0', 10);
    if (!(plazo > 0)) { alert('Verifica plazo'); return; }

    const res = await jsonFetch(API, fd);
    if (!res.ok) {
      $errForm.hidden = false;
      $errForm.textContent = res.msg || 'Error aplicando reestructuración';
      return;
    }
    closeModal($modalForm);
    cargar(currentPage);
    alert(res.msg || 'Reestructuración aplicada');
  });

  cargar(1);
})();
