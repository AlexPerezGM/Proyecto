(() => {
  const API = (window.APP_BASE || '/') + 'api/clientes.php';
  const $q = document.getElementById('q');
  const $btnBuscar = document.getElementById('btnBuscar');
  const $tabla = document.getElementById('tablaClientes').querySelector('tbody');
  const $paginacion = document.getElementById('paginacion');
  const $err = document.getElementById('errorBox');

  // Modales
  const $modalForm = document.getElementById('modalForm');
  const $modalVer  = document.getElementById('modalVer');
  const $frm = document.getElementById('frmCliente');
  const $btnAbrirCrear = document.getElementById('btnAbrirCrear');
  const $modalTitulo = document.getElementById('modalTitulo');

  let currentPage = 1;
  const PAGE_SIZE = 10;

  const openModal = (el) => el.classList.add('show');
  const closeModal = (el) => el.classList.remove('show');
  document.querySelectorAll('[data-close]').forEach(b => {
    b.addEventListener('click', () => closeModal(b.closest('.modal')));
  });
  window.addEventListener('keydown', e => {
    if (e.key === 'Escape'){ document.querySelectorAll('.modal.show').forEach(m=>closeModal(m)); }
  });

  async function jsonFetch(url, body){
    $err.hidden = true;
    try{
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body
      });
      const text = await res.text();
      try {
        return JSON.parse(text);
      } catch(parseErr){
        // Si la API devolvió HTML (Warning/Fatal/404), mostrarlo
        $err.hidden = false;
        $err.textContent = 'Respuesta no-JSON de la API:\n' + text.slice(0, 2000);
        throw parseErr;
      }
    }catch(e){
      if ($err.hidden) {
        $err.hidden = false;
        $err.textContent = 'Error consultando API:\n' + (e.message || e);
      }
      throw e;
    }
  }
  
  async function cargarCatalogos(){
  const cats = await jsonFetch(API, new URLSearchParams({action:'catalogos'}));
  const $gen = document.getElementById('genero');
  const $td  = document.getElementById('id_tipo_documento');
  if (Array.isArray(cats.generos)){
    $gen.innerHTML = '<option value="">Seleccione…</option>' +
      cats.generos.map(g=>`<option value="${g.id_genero}">${g.genero}</option>`).join('');
  }
  if (Array.isArray(cats.tipos_documento)){
    $td.innerHTML = '<option value="">Seleccione…</option>' +
      cats.tipos_documento.map(t=>`<option value="${t.id_tipo_documento}">${t.tipo_documento}</option>`).join('');
  }
}
document.addEventListener('DOMContentLoaded', cargarCatalogos);


  async function cargar(page=1){
    currentPage = page;
    const params = new URLSearchParams({ action:'list', q:$q.value.trim(), page, size: PAGE_SIZE });
    const json = await jsonFetch(API, params);

    $tabla.innerHTML = (json.data || []).map(r => `
      <tr>
        <td>${r.id_cliente}</td>
        <td>${r.nombre} ${r.apellido}</td>
        <td>${r.numero_documento ?? '-'}</td>
        <td>${r.email ?? '-'}</td>
        <td>${r.telefono ?? '-'}</td>
        <td>${r.estado_prestamo}</td>
        <td>${(r.creado_en || '').slice(0,10)}</td>
        <td>
          <button class="btn btn-light" data-ver="${r.id_cliente}">Ver</button>
          <button class="btn" data-editar="${r.id_cliente}">Modificar</button>
          <button class="btn btn-danger" data-borrar="${r.id_cliente}">Eliminar</button>
        </td>

      </tr>
    `).join('');

    const total = +json.total || 0;
    const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    let html = '';
    for (let p=1; p<=pages; p++){
      html += `<button ${p===page?'class="active"':''} data-page="${p}">${p}</button>`;
    }
    $paginacion.innerHTML = html;
  }

  $paginacion.addEventListener('click', e => {
    const b = e.target.closest('button[data-page]'); if (!b) return;
    cargar(+b.dataset.page);
  });
  $btnBuscar.addEventListener('click', () => cargar(1));
  $q.addEventListener('keydown', e => { if (e.key === 'Enter') cargar(1); });

  $btnAbrirCrear.addEventListener('click', () => {
    $frm.reset();
    $frm.action.value = 'create';
    document.getElementById('id_cliente').value = '';
    $modalTitulo.textContent = 'Registrar cliente';
    openModal($modalForm);
  });

  document.addEventListener('click', async (e) => {
    const v   = e.target.closest('[data-ver]');
    const ed  = e.target.closest('[data-editar]');
    const del = e.target.closest('[data-borrar]');
    if (!v && !ed && !del) return;

const id = v ? v.dataset.ver : (ed ? ed.dataset.editar : del.dataset.borrar);
    const params = new URLSearchParams({ action:'get', id_cliente: id });
    const c = await jsonFetch(API, params);

    if (v){
      const html = `
        <div class="grid-2">
          <div>
            <h4>Información personal</h4>
            <p><b>Nombre:</b> ${c.nombre} ${c.apellido}</p>
            <p><b>Fecha nac.:</b> ${c.fecha_nacimiento || ''}</p>
            <p><b>Género:</b> ${c.genero_txt || ''}</p>
            <p><b>Documento:</b> ${c.tipo_documento || ''} ${c.numero_documento || ''}</p>
            <p><b>Estado:</b> ${c.estado_prestamo || ''}</p>
          </div>
          <div>
            <h4>Contacto</h4>
            <p><b>Email:</b> ${c.email || '-'}</p>
            <p><b>Teléfono:</b> ${c.telefono || '-'}</p>

            <h4>Dirección</h4>
            <p>${c.ciudad || ''}, ${c.sector || ''}, ${c.calle || ''} #${c.numero_casa || ''}</p>
          </div>
          <div>
            <h4>Información financiera</h4>
            <p><b>Ingresos:</b> $${(+c.ingresos_mensuales||0).toFixed(2)}</p>
            <p><b>Egresos:</b> $${(+c.egresos_mensuales||0).toFixed(2)}</p>
            <p><b>Fuente:</b> ${c.fuente || '-'}</p>
            <p><b>Ocupación:</b> ${c.ocupacion || '-'} (${c.empresa || '-'})</p>
          </div>
        </div>
      `;
      document.getElementById('verContenido').innerHTML = html;
      openModal($modalVer);
    }

    if (ed){
      $frm.reset();
      $frm.action.value = 'update';
      $modalTitulo.textContent = 'Modificar cliente';
      const map = {
        id_cliente:'id_cliente', nombre:'nombre', apellido:'apellido',
        fecha_nacimiento:'fecha_nacimiento', genero:'genero',
        id_tipo_documento:'id_tipo_documento', numero_documento:'numero_documento',
        telefono:'telefono', email:'email',
        ciudad:'ciudad', sector:'sector', calle:'calle', numero_casa:'numero_casa',
        ingresos_mensuales:'ingresos_mensuales', fuente:'fuente_ingresos',
        egresos_mensuales:'egresos_mensuales', ocupacion:'ocupacion', empresa:'empresa'
      };
      for (const k in map){
        const el = document.getElementById(map[k]); if (el) el.value = c[k] ?? '';
      }
      openModal($modalForm);
    }
    
    if (del){
  if (!confirm('¿Eliminar este cliente? Esta acción no se puede deshacer.')) return;
  const params = new URLSearchParams({ action:'delete', id_cliente: id });
  const r = await jsonFetch(API, params);
  if (!r.ok) return alert(r.error || r.msg || 'Error al eliminar');
  cargar(currentPage);
  return;
}
  });

  function esMayorDeEdad(fechaStr){
    const hoy = new Date(), f = new Date(fechaStr);
    if (isNaN(f)) return false;
    let edad = hoy.getFullYear() - f.getFullYear();
    const m = hoy.getMonth() - f.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < f.getDate())) edad--;
    return edad >= 18;
  }

  $frm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fn = document.getElementById('fecha_nacimiento').value;
    if (!esMayorDeEdad(fn)) return alert('El cliente debe ser mayor de edad.');
    const em = document.getElementById('email').value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) return alert('Email no válido.');

    const ing = +document.getElementById('ingresos_mensuales').value;
const egr = +document.getElementById('egresos_mensuales').value;
if (isNaN(ing) || isNaN(egr) || ing < 10000 || ing <= egr) {
  return alert('Verifica ingresos/egresos (ingresos ≥ 10,000 y mayores que egresos).');
}


    const json = await jsonFetch(API, new FormData($frm));
    if (!json.ok) return alert(json.msg || 'Error en la operación');
    closeModal($modalForm);
    cargar(currentPage);
  });

  cargar(1);
})();
