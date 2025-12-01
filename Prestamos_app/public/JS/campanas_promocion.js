document.addEventListener('DOMContentLoaded', function () {
    const API = window.APP_BASE + 'api/campanas_promocion.php';

    // Tabs
    const tabProm = document.getElementById('tabPromociones');
    const tabCamp = document.getElementById('tabCampanas');
    const secProm = document.getElementById('secPromociones');
    const secCamp = document.getElementById('secCampanas');

    tabProm.addEventListener('click', () => {
        tabProm.classList.remove('btn-light');
        tabCamp.classList.add('btn-light');
        secProm.style.display = '';
        secCamp.style.display = 'none';
        loadPromociones();
    });

    tabCamp.addEventListener('click', () => {
        tabCamp.classList.remove('btn-light');
        tabProm.classList.add('btn-light');
        secCamp.style.display = '';
        secProm.style.display = 'none';
        loadCampanas();
    });

    // Helpers (más robustos)
    const openModal = id => {
        const m = document.getElementById(id);
        if (!m) { console.warn('openModal: modal no encontrado', id); return; }
        m.classList.add('open');
    };
    const closeModal = el => {
        try {
            const modal = el && el.closest ? el.closest('.modal') : document.querySelector('.modal.open');
            if (modal) modal.classList.remove('open');
            else console.warn('closeModal: modal no encontrado para', el);
        } catch (err) { console.warn('closeModal error', err); }
    };
    document.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', e => closeModal(e.target)));

    // ===== PROMOCIONES =====
    async function loadPromociones() {
        const res = await fetch(`${API}?action=promociones_listar`);
        const json = await res.json();
        const tbody = document.querySelector('#tablaPromociones tbody');
        tbody.innerHTML = '';
        if (json.ok && json.data.length) {
            json.data.forEach(p => {
                const tr = document.createElement('tr');
                // guardar la promoción completa en data para facilitar edición
                tr.dataset.promo = JSON.stringify(p);
                tr.innerHTML = `
                        <td>${p.id_promocion}</td>
                        <td>${p.nombre}</td>
                        <td>${p.tipo}</td>
                        <td>${p.puntos}</td>
                        <td>${p.inicio}</td>
                        <td>${p.fin}</td>
                        <td>${p.estado}</td>
                        <td>
                          <button class="btn btn-sm btn-light" data-edit="${p.id_promocion}">Editar</button>
                        </td>`;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center">No hay promociones</td></tr>`;
        }
    }

    // Delegación de eventos para editar (se añade SOLO UNA vez)
    const tablaProm = document.getElementById('tablaPromociones');
    tablaProm.addEventListener('click', function (e) {
        const btn = e.target.closest('button');
        if (!btn) return;
        if (!btn.dataset.edit) return;
        const tr = btn.closest('tr');
        if (!tr) return;
        const p = tr.dataset.promo ? JSON.parse(tr.dataset.promo) : null;
        if (!p) return;

        document.getElementById('modalPromTitulo').textContent = 'Editar promoción';
        const form = document.getElementById('frmPromocion');
        // usar querySelector para evitar conflictos con form.action
        const actionInput = form.querySelector('input[name="action"]');
        if (actionInput) actionInput.value = 'promocion_update';
        document.getElementById('id_promocion').value = p.id_promocion || '';
        document.getElementById('prom_nombre').value = p.nombre || '';
        document.getElementById('prom_tipo').value = p.tipo || '';
        document.getElementById('prom_puntos').value = p.puntos || '';
        document.getElementById('prom_estado').value = p.estado || '';
        document.getElementById('prom_inicio').value = p.inicio || '';
        document.getElementById('prom_fin').value = p.fin || '';
        document.getElementById('prom_descripcion').value = p.descripcion || '';
        openModal('modalProm');
    });

    // Crear nueva promoción
    document.getElementById('btnNuevoProm').addEventListener('click', () => {
        document.getElementById('frmPromocion').reset();
        document.getElementById('modalPromTitulo').textContent = 'Nueva promoción';
        openModal('modalProm');
    });

    document.getElementById('frmPromocion').addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        const res = await fetch(API, { method: 'POST', body: fd });
        const json = await res.json();
        alert(json.msg);
        if (json.ok) {
            closeModal(e.target);
            loadPromociones();
            loadMetrics();
        }
    });

    // Asignar promoción a clientes
    document.getElementById('btnAsignarProm').addEventListener('click', async () => {
        const selClients = document.getElementById('selClientesAsign');
        const selPromos = document.getElementById('selPromosAsign');
        selClients.innerHTML = '<option>Cargando...</option>';
        selPromos.innerHTML = '<option value="">Cargando...</option>';

        // Cargar clientes y promociones en paralelo
        const [resClients, resPromos] = await Promise.all([
            fetch(`${API}?action=clientes_listar`).then(r => r.json()).catch(() => ({ ok: false })),
            fetch(`${API}?action=promociones_listar`).then(r => r.json()).catch(() => ({ ok: false }))
        ]);

        // Poblar clientes
        selClients.innerHTML = '';
        if (resClients.ok && resClients.data.length) {
            resClients.data.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id_cliente;
                opt.textContent = c.nombre;
                selClients.appendChild(opt);
            });
        } else {
            selClients.innerHTML = '<option>No hay clientes activos</option>';
        }

        // Poblar promociones
        selPromos.innerHTML = '';
        if (resPromos.ok && resPromos.data.length) {
            selPromos.appendChild(new Option('-- Seleccione una promoción --', ''));
            resPromos.data.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id_promocion;
                opt.textContent = `${p.nombre} (${p.tipo || ''})`;
                selPromos.appendChild(opt);
            });
        } else {
            selPromos.innerHTML = '<option value="">No hay promociones</option>';
        }

        openModal('modalAsignarProm');
    });

    document.getElementById('frmAsignarProm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('action', 'promocion_asignar');
        // obtener id_promocion del select del modal
        const selProm = document.getElementById('selPromosAsign');
        const idp = selProm ? selProm.value : document.getElementById('asign_prom_id')?.value;
        if (!idp) { alert('Seleccione una promoción antes de asignar'); return; }
        fd.append('id_promocion', idp);
        const res = await fetch(API, { method: 'POST', body: fd });
        const json = await res.json();
        alert(json.msg);
        if (json.ok) closeModal(e.target);
    });

    // ===== CAMPAÑAS =====
    async function loadCampanas() {
        const res = await fetch(`${API}?action=campana_lista`);
        const json = await res.json();
        const tbody = document.querySelector('#tablaCampanas tbody');
        tbody.innerHTML = '';
        if (json.ok && json.data.length) {
            json.data.forEach(c => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${c.id_campana}</td>
                    <td>${c.nombre_campana}</td>
                    <td>${c.fecha_inicio}</td>
                    <td>${c.fecha_fin}</td>
                    <td>${c.estado_campana}</td>
                    <td><button class="btn btn-sm btn-light" data-edit="${c.id_campana}">Editar</button>
                    <button class="btn btn-sm btn-light" data-view-promos="${c.id_campana}">Promociones</button></td>`;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center">No hay campañas</td></tr>`;
        }
    }

    // Delegación para acciones sobre campañas (editar / ver promociones)
    const tablaCamp = document.getElementById('tablaCampanas');
    tablaCamp.addEventListener('click', function (e) {
        const btn = e.target.closest('button');
        if (!btn) return;

        // EDITAR CAMPAÑA
        if (btn.dataset.edit) {
            const tr = btn.closest('tr');
            if (!tr) return;
            const cells = tr.querySelectorAll('td');
            const id = btn.dataset.edit;
            const nombre = cells[1] ? cells[1].textContent.trim() : '';
            const inicio = cells[2] ? cells[2].textContent.trim() : '';
            const fin = cells[3] ? cells[3].textContent.trim() : '';
            const estado = cells[4] ? cells[4].textContent.trim() : '';

            document.getElementById('modalCampTitulo').textContent = 'Editar campaña';
            const form = document.getElementById('frmCampana');
            const actionInput = form.querySelector('input[name="action"]');
            if (actionInput) actionInput.value = 'campana_update';
            const idInput = document.getElementById('id_campana');
            if (idInput) idInput.value = id || '';
            document.getElementById('camp_nombre').value = nombre || '';
            document.getElementById('camp_inicio').value = inicio || '';
            document.getElementById('camp_fin').value = fin || '';
            document.getElementById('camp_estado').value = estado.toLowerCase() === 'activa' ? 'activa' : 'inactiva';
            document.getElementById('camp_descripcion').value = '';
            openModal('modalCamp');
            return;
        }

        // VER PROMOCIONES de la campaña
        if (btn.dataset.viewPromos) {
            console.log('Ver promociones click - idCampana:', btn.dataset.viewPromos);
            const idCampana = btn.dataset.viewPromos;
            verPromocionesDeCampana(idCampana);
        }
    });


    document.getElementById('btnNuevaCamp').addEventListener('click', () => {
        const form = document.getElementById('frmCampana');
        form.reset();
        // asegurar action e id limpios
        const actionInput = form.querySelector('input[name="action"]');
        if (actionInput) actionInput.value = 'campana_create';
        const idInput = form.querySelector('input[name="id_campana"]');
        if (idInput) idInput.value = '';
        document.getElementById('modalCampTitulo').textContent = 'Nueva campaña';
        openModal('modalCamp');
    });

    document.getElementById('frmCampana').addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        const res = await fetch(API, { method: 'POST', body: fd });
        const json = await res.json();
        alert(json.msg);
        if (json.ok) {
            closeModal(e.target);
            loadCampanas();
        }
    });

    // Asignar promociones a campaña (botón global)
    document.getElementById('btnAsignPromCamp').addEventListener('click', async () => {
        const selCamp = document.getElementById('selCampanasAsign');
        const selProm = document.getElementById('selPromosParaCamp');
        if (!selCamp || !selProm) {
            alert('Modal de asignación no encontrado en la vista.');
            return;
        }
        selCamp.innerHTML = '<option>Cargando...</option>';
        selProm.innerHTML = '<option>Cargando...</option>';

        // Cargar campañas y promociones en paralelo
        const [resCamp, resPromos] = await Promise.all([
            fetch(`${API}?action=campana_lista`).then(r => r.json()).catch(() => ({ ok: false })),
            fetch(`${API}?action=promociones_listar`).then(r => r.json()).catch(() => ({ ok: false }))
        ]);

        // Poblar campañas
        selCamp.innerHTML = '';
        if (resCamp.ok && resCamp.data.length) {
            selCamp.appendChild(new Option('-- Seleccione campaña --', ''));
            resCamp.data.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id_campana;
                opt.textContent = c.nombre_campana;
                selCamp.appendChild(opt);
            });
        } else {
            selCamp.innerHTML = '<option value="">No hay campañas</option>';
        }

        // Poblar promociones
        selProm.innerHTML = '';
        if (resPromos.ok && resPromos.data.length) {
            resPromos.data.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id_promocion;
                opt.textContent = `${p.nombre} (${p.tipo || ''})`;
                selProm.appendChild(opt);
            });
        } else {
            selProm.innerHTML = '<option>No hay promociones</option>';
        }

        openModal('modalAsignPromCamp');
    });

    // Envío del formulario de vinculación promociones->campaña
    document.getElementById('frmAsignPromCamp').addEventListener('submit', async function (e) {
        e.preventDefault();
        const selCamp = document.getElementById('selCampanasAsign');
        const selProm = document.getElementById('selPromosParaCamp');
        if (!selCamp || !selProm) { alert('Formulario mal configurado'); return; }
        const idCamp = selCamp.value;
        if (!idCamp) { alert('Seleccione una campaña'); return; }
        const selected = Array.from(selProm.selectedOptions).map(o => o.value);
        // preparar FormData
        const fd = new FormData();
        fd.append('action', 'campana_vinc_promocion');
        fd.append('id_campana', idCamp);
        selected.forEach(p => fd.append('promociones[]', p));

        const res = await fetch(API, { method: 'POST', body: fd });
        const json = await res.json();
        alert(json.msg);
        if (json.ok) {
            closeModal(e.target);
        }
    });
    async function verPromocionesDeCampana(idCampana) {
        console.log('verPromocionesDeCampana llamado con id:', idCampana);
        try {
            const res = await fetch(`${API}?action=campana_promociones_listar&id_campana=${idCampana}`);
            const json = await res.json();
            console.log('respuesta campana_promociones_listar:', json);

            const tbody = document.querySelector('#tablaPromosCamp tbody');
            if (!tbody) { console.warn('tablaPromosCamp tbody no encontrado'); }
            else tbody.innerHTML = '';

            if (json.ok && json.data && json.data.length) {
                json.data.forEach(p => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                <td>${p.id_promocion}</td>
                <td>${p.nombre}</td>
                <td>${p.tipo}</td>
                <td>${p.puntos}</td>
                <td>${p.estado}</td>
                <td>${p.inicio}</td>
                <td>${p.fin}</td>
            `;
                    tbody.appendChild(tr);
                });
            } else if (tbody) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center">No hay promociones vinculadas</td></tr>`;
            }

            // Mostrar modal (verificar existencia)
            const modalEl = document.getElementById('modalVerPromosCamp');
            if (!modalEl) {
                console.warn('modalVerPromosCamp no encontrado en DOM');
            } else {
                openModal('modalVerPromosCamp');
            }
        } catch (err) {
            console.error('Error al obtener promociones de campaña:', err);
            alert('Error al cargar promociones de la campaña. Revisa la consola para más detalles.');
        }
    }
    async function loadMetrics() {
        try {
            const res = await fetch(`${API}?action=metricas_campana`);
            const json = await res.json();
            if (json.ok && json.data) {
                document.getElementById('m_prom_activas').textContent = json.data.activas ?? 0;
                document.getElementById('m_prom_usadas').textContent = json.data.inactivas ?? 0;
                document.getElementById('m_prom_vencidas').textContent = json.data.vencidas ?? 0;
            }
        } catch (err) {
            console.error('Error al cargar métricas:', err);
        }
    }


    // Inicialización
    loadPromociones();
    loadCampanas();
    loadMetrics();
});

