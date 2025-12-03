(() => {
  const API = (window.APP_BASE || '/') + 'api/seguridad.php';

  const $err = document.getElementById('errorBox');

  const $tabRoles = document.getElementById('tabRoles');
  const $tabUsuarios = document.getElementById('tabUsuarios');
  const $panelRoles = document.getElementById('panelRoles');
  const $panelUsuarios = document.getElementById('panelUsuarios');

  const $tablaRoles = document.querySelector('#tablaRoles tbody');
  const $tablaUsers = document.querySelector('#tablaUsuarios tbody');

  const $modalRol = document.getElementById('modalRol');
  const $frmRol = document.getElementById('frmRol');
  const $modalRolTitulo = document.getElementById('modalRolTitulo');
  const $checksPermisos = document.getElementById('checksPermisos');
  const $idRol = document.getElementById('id_rol');
  const $rolNombre = document.getElementById('rol_nombre');

  const $modalUsuario = document.getElementById('modalUsuario');
  const $frmUsuario = document.getElementById('frmUsuario');
  const $modalUsuarioTitulo = document.getElementById('modalUsuarioTitulo');
  const $idUsuario = document.getElementById('id_usuario');
  const $usuario = document.getElementById('usuario');
  const $contrasena = document.getElementById('contrasena');
  const $idRolUser = document.getElementById('id_rol_user');

  const $btnNuevoRol = document.getElementById('btnNuevoRol');
  const $btnNuevoUsuario = document.getElementById('btnNuevoUsuario');

  const $idEmpleadoUser = document.getElementById('id_datos_persona');

  const openModal = (el) => el.classList.add('show');
  const closeModal = (el) => el.classList.remove('show');
  document.querySelectorAll('[data-close]').forEach(b => {
    b.addEventListener('click', () => closeModal(b.closest('.modal')));
  });
  window.addEventListener('keydown', e => {
    if (e.key === 'Escape') { document.querySelectorAll('.modal.show').forEach(m => closeModal(m)); }
  });

  async function jsonFetch(url, body) {
    $err.hidden = true;
    try {
      const res = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body });
      const text = await res.text();
      try { return JSON.parse(text); }
      catch (parseErr) { $err.hidden = false; $err.textContent = 'Respuesta no-JSON de la API:\n' + text.slice(0, 2000); throw parseErr; }
    } catch (e) { if ($err.hidden) { $err.hidden = false; $err.textContent = 'Error consultando API:\n' + (e.message || e); } throw e; }
  }

  // ---- Tabs ----
  $tabRoles.addEventListener('click', () => {
    $tabRoles.classList.replace('btn-light', 'btn');
    $tabUsuarios.classList.replace('btn', 'btn-light');
    $panelRoles.style.display = 'block';
    $panelUsuarios.style.display = 'none';
  });
  $tabUsuarios.addEventListener('click', () => {
    $tabUsuarios.classList.replace('btn-light', 'btn');
    $tabRoles.classList.replace('btn', 'btn-light');
    $panelUsuarios.style.display = 'block';
    $panelRoles.style.display = 'none';
  });

  // ---- Cargas ----
  async function cargarPermisos() {
    const json = await jsonFetch(API, new URLSearchParams({ action: 'permisos_list' }));
    $checksPermisos.innerHTML = (json.data || []).map(p => `
      <label><input type="checkbox" name="permisos[]" value="${p.clave}"> ${p.nombre}</label>
    `).join('');
  }

  async function cargarRoles() {
    const json = await jsonFetch(API, new URLSearchParams({ action: 'roles_list' }));
    $tablaRoles.innerHTML = (json.data || []).map(r => `
      <tr>
        <td>${r.id_rol}</td>
        <td>${r.nombre}</td>
        <td>${r.permisos || '-'}</td>
        <td>
          <button class="btn" data-editar-rol="${r.id_rol}">Modificar</button>
        </td>
      </tr>
    `).join('');
    cargarRolesSelect(); // para el formulario de usuario
  }

  async function cargarUsers() {
    const json = await jsonFetch(API, new URLSearchParams({ action: 'users_list' }));
    $tablaUsers.innerHTML = (json.data || []).map(u => `
    <tr>
      <td>${u.id_usuario}</td>
      <td>${u.nombre_usuario}</td>
      <td>${u.roles || '-'}</td>      <!-- primero el rol -->
      <td>${u.empleado || '-'}</td>   <!-- luego el empleado -->
      <td><button class="btn" data-editar-user="${u.id_usuario}">Modificar</button></td>
    </tr>
  `).join('');
  }

  async function cargarRolesSelect() {
    const json = await jsonFetch(API, new URLSearchParams({ action: 'roles_list' }));
    $idRolUser.innerHTML = (json.data || []).map(r => `<option value="${r.id_rol}">${r.nombre}</option>`).join('');
  }

  async function cargarEmpleadosSelect() {
    const json = await jsonFetch(API, new URLSearchParams({ action: 'empleados_list' }));
    $idEmpleadoUser.innerHTML = (json.data || []).map(e => `
    <option value="${e.id_datos_persona}">${e.nombre_completo}</option>
  `).join('');
  }


  // ---- Nuevo Rol ----
  document.getElementById('btnNuevoRol').addEventListener('click', async () => {
    $frmRol.reset();
    $frmRol.action.value = 'role_create';
    $idRol.value = '';
    $modalRolTitulo.textContent = 'Nuevo rol';
    await cargarPermisos();
    openModal($modalRol);
  });

  // ---- Editar Rol ----
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-editar-rol]');
    if (!btn) return;
    const id = btn.dataset.editarRol;
    const json = await jsonFetch(API, new URLSearchParams({ action: 'role_get', id_rol: id }));
    $frmRol.reset();
    $frmRol.action.value = 'role_update';
    $idRol.value = json.data.id_rol;
    $rolNombre.value = json.data.nombre;
    await cargarPermisos();
    // marcar checks
    (json.data.permisos || []).forEach(clave => {
      const el = $checksPermisos.querySelector(`input[value="${clave}"]`);
      if (el) el.checked = true;
    });
    $modalRolTitulo.textContent = 'Modificar rol';
    openModal($modalRol);
  });

  $frmRol.addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await jsonFetch(API, new FormData($frmRol));
    if (!json.ok) return alert(json.msg || 'Error');
    closeModal($modalRol);
    cargarRoles();
  });

  // ---- Nuevo Usuario ----
  document.getElementById('btnNuevoUsuario').addEventListener('click', async () => {
    $frmUsuario.reset();
    $frmUsuario.action.value = 'user_create';
    $idUsuario.value = '';
    $modalUsuarioTitulo.textContent = 'Nuevo usuario';
    await cargarRolesSelect();
    await cargarEmpleadosSelect();
    openModal($modalUsuario);
  });

  // ---- Editar Usuario ----
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-editar-user]');
    if (!btn) return;
    const id = btn.dataset.editarUser;
    const json = await jsonFetch(API, new URLSearchParams({ action: 'user_get', id_usuario: id }));
    $frmUsuario.reset();
    $frmUsuario.action.value = 'user_update';
    $idUsuario.value = json.data.id_usuario;
    $usuario.value = json.data.nombre_usuario;
    await cargarRolesSelect();
    await cargarEmpleadosSelect();
    if (json.data.id_rol) $idRolUser.value = json.data.id_rol;
    $contrasena.value = ''; // opcional
    $modalUsuarioTitulo.textContent = 'Modificar usuario';
    openModal($modalUsuario);
  });

  $frmUsuario.addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await jsonFetch(API, new FormData($frmUsuario));
    if (!json.ok) return alert(json.msg || 'Error');
    closeModal($modalUsuario);
    cargarUsers();
  });

  // Primera carga
  cargarRoles();
  cargarUsers();
})();
