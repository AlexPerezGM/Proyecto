(() => {
  const API = (window.APP_BASE || '/') + 'api/rrhh.php';

  // Tabs
  const $tabEmp = document.getElementById('tabEmpleados');
  const $tabNom = document.getElementById('tabNomina');
  const $secEmp = document.getElementById('secEmpleados');
  const $secNom = document.getElementById('secNomina');

  const showEmp = () => { $secEmp.style.display=''; $secNom.style.display='none'; $tabEmp.classList.remove('btn-light'); $tabNom.classList.add('btn-light'); };
  const showNom = () => { $secEmp.style.display='none'; $secNom.style.display=''; $tabEmp.classList.add('btn-light'); $tabNom.classList.remove('btn-light'); };
  $tabEmp.addEventListener('click', showEmp);
  $tabNom.addEventListener('click', showNom);

  // Empleados (lista)
  const $qEmp = document.getElementById('qEmp');
  const $btnBuscarEmp = document.getElementById('btnBuscarEmp');
  const $btnNuevoEmp = document.getElementById('btnNuevoEmp');
  const $tbEmp = document.querySelector('#tablaEmpleados tbody');
  const $pagEmp = document.getElementById('paginacionEmp');
  const $errEmp = document.getElementById('errorEmp');

  // Modal empleado
  const $modalEmp = document.getElementById('modalEmp');
  const $frmEmp = document.getElementById('frmEmpleado');
  const $modalEmpTitulo = document.getElementById('modalEmpTitulo');
  const $errEmpForm = document.getElementById('errorEmpForm');
  document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', ()=> $modalEmp.classList.remove('show')));
  const openModal = (m)=> m.classList.add('show');

  function esMayorDeEdad(fechaStr){
    const hoy = new Date(), f = new Date(fechaStr);
    if (isNaN(f)) return false;
    let edad = hoy.getFullYear() - f.getFullYear();
    const m = hoy.getMonth() - f.getMonth();
    if (m < 0 || (m===0 && hoy.getDate() < f.getDate())) edad--;
    return edad >= 18;
  }

  async function jsonFetch(url, body){
    $errEmp.hidden = true;
    const res = await fetch(url, { method:'POST', headers:{'Accept':'application/json'}, body });
    const text = await res.text();
    try { return JSON.parse(text); }
    catch(e){ $errEmp.hidden=false; $errEmp.textContent = 'Respuesta no-JSON:\n'+text.slice(0,1500); throw e; }
  }

  // Cargar combos base (género, contratos, empleados para "jefe", y listado para nómina)
  async function cargarOptions(){
    const j = await jsonFetch(API, new URLSearchParams({action:'options'}));
    const sel = (id, arr, k='id', v='txt') => {
      const el = document.getElementById(id);
      el.innerHTML = (arr||[]).map(o=>`<option value="${o[k]}">${o[v]}</option>`).join('');
    };
    sel('genero', j.generos, 'id_genero', 'genero');
    sel('id_tipo_contrato', j.contratos, 'id_tipo_contrato', 'tipo_contrato');
    document.getElementById('id_jefe').innerHTML = '<option value="">—</option>' + (j.jefes||[]).map(o=>`<option value="${o.id_empleado}">${o.nombre}</option>`).join('');
    // Nómina: selector de empleado
    const $selEmp = document.getElementById('selEmpleado');
    $selEmp.innerHTML = '<option value="ALL">Todos</option>' + (j.jefes||[]).map(o=>`<option value="${o.id_empleado}">${o.nombre}</option>`).join('');
  }

  let pageEmp = 1, PAGE=10;
  async function cargarEmpleados(p=1){
    pageEmp = p;
    const params = new URLSearchParams({action:'emp_list', q:$qEmp.value.trim(), page:p, size:PAGE});
    const j = await jsonFetch(API, params);
    $tbEmp.innerHTML = (j.data||[]).map(r=>`
      <tr>
        <td>${r.id_empleado}</td>
        <td>${r.nombre}</td>
        <td>${r.cedula||'-'}</td>
        <td>${r.email||'-'}</td>
        <td>${r.telefono||'-'}</td>
        <td>${r.cargo||'-'}</td>
        <td>$${(+r.salario_base||0).toFixed(2)}</td>
        <td>${r.fecha_contratacion||''}</td>
        <td>
          <button class="btn btn-light" data-ver="${r.id_empleado}">Ver</button>
          <button class="btn" data-editar="${r.id_empleado}">Editar</button>
        </td>
      </tr>
    `).join('');
    const total = +j.total || 0;
    const pages = Math.max(1, Math.ceil(total/PAGE));
    let html=''; for(let i=1;i<=pages;i++) html += `<button ${i===p?'class="active"':''} data-p="${i}">${i}</button>`;
    $pagEmp.innerHTML=html;
  }

  $pagEmp.addEventListener('click', e=>{const b=e.target.closest('button[data-p]'); if(!b) return; cargarEmpleados(+b.dataset.p);});
  $btnBuscarEmp.addEventListener('click', ()=>cargarEmpleados(1));
  $qEmp.addEventListener('keydown', e=>{if(e.key==='Enter') cargarEmpleados(1);});

  $btnNuevoEmp.addEventListener('click', ()=>{
    $frmEmp.reset();
    $frmEmp.action.value='emp_create';
    document.getElementById('id_empleado').value='';
    $modalEmpTitulo.textContent='Nuevo empleado';
    openModal($modalEmp);
  });

  // Ver/Editar
  document.addEventListener('click', async (e)=>{
    const v = e.target.closest('[data-ver]'); const ed = e.target.closest('[data-editar]');
    if(!v && !ed) return;
    const id = (v ? v.dataset.ver : ed.dataset.editar);
    const j = await jsonFetch(API, new URLSearchParams({action:'emp_get', id_empleado:id}));
    if(ed){
      $frmEmp.reset();
      $frmEmp.action.value='emp_update';
      $modalEmpTitulo.textContent='Editar empleado';
      for(const k of ['id_empleado','nombre','apellido','numero_documento','fecha_nacimiento','telefono','email','ciudad','sector','calle','numero_casa','cargo','departamento','fecha_contratacion','salario_base']){
        if(document.getElementById(k)) document.getElementById(k).value = j[k] ?? '';
      }
      document.getElementById('genero').value = j.genero || '';
      document.getElementById('id_tipo_contrato').value = j.id_tipo_contrato || '';
      document.getElementById('id_jefe').value = j.id_jefe || '';
      openModal($modalEmp);
    }else{
      alert(`Empleado: ${j.nombre}\nCargo: ${j.cargo}\nSalario: $${(+j.salario_base||0).toFixed(2)}`);
    }
  });

  // Guardar empleado
  $frmEmp.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fn = document.getElementById('fecha_nacimiento').value;
    if(!esMayorDeEdad(fn)) return alert('El empleado debe ser mayor de edad.');
    const em = document.getElementById('email').value.trim();
    if(em && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) return alert('Email no válido.');
    const res = await jsonFetch(API, new FormData($frmEmp));
    if(!res.ok) { $errEmpForm.hidden=false; $errEmpForm.textContent=res.msg||'Error al guardar'; return; }
    $errEmpForm.hidden=true; $modalEmp.classList.remove('show'); cargarEmpleados(pageEmp); cargarOptions(); // refresca combos de jefe
  });

  // ===== Nómina =====
  const $periodo = document.getElementById('periodo');
  const $selEmpleado = document.getElementById('selEmpleado');
  const $btnCalcular = document.getElementById('btnCalcular');
  const $btnGuardar = document.getElementById('btnGuardarNomina');
  const $btnComprob = document.getElementById('btnComprobantes');
  const $tbNom = document.querySelector('#tablaNomina tbody');
  const $tot = document.getElementById('resumenTotales');
  const $errNom = document.getElementById('errorNomina');

  function rowsToPayload(){
    const rows=[];
    $tbNom.querySelectorAll('tr').forEach(tr=>{
      rows.push({
        id_empleado: +tr.dataset.id,
        salario_base: +tr.querySelector('[data-sal]').textContent.replace(/[^0-9.]/g,''),
        horas_extra: +(tr.querySelector('[name="hx"]').value||0),
        bonificaciones: +(tr.querySelector('[name="bon"]').value||0),
        deducciones: +(tr.querySelector('[name="ded"]').value||0)
      });
    });
    return rows;
  }
  function pintarNomina(list){
    let totBase=0, totHx=0, totBon=0, totDed=0, totNeto=0;
    $tbNom.innerHTML = (list||[]).map(r=>{
      const neto = (+r.salario_base) + (+r.horas_extra) + (+r.bonificaciones) - (+r.deducciones);
      totBase+=+r.salario_base; totHx+=+r.horas_extra; totBon+=+r.bonificaciones; totDed+=+r.deducciones; totNeto+=neto;
      return `<tr data-id="${r.id_empleado}">
        <td>${r.nombre}</td>
        <td data-sal="$${(+r.salario_base).toFixed(2)}">$${(+r.salario_base).toFixed(2)}</td>
        <td><input class="input" name="hx" type="number" step="0.01" value="${r.horas_extra||0}"></td>
        <td><input class="input" name="bon" type="number" step="0.01" value="${r.bonificaciones||0}"></td>
        <td><input class="input" name="ded" type="number" step="0.01" value="${r.deducciones||0}"></td>
        <td>$${neto.toFixed(2)}</td>
      </tr>`;
    }).join('');
    $tot.textContent = `Totales — Salario base: $${totBase.toFixed(2)} | Horas extra: $${totHx.toFixed(2)} | Bonificaciones: $${totBon.toFixed(2)} | Deducciones: $${totDed.toFixed(2)} | Nómina total: $${totNeto.toFixed(2)}`;
  }

  $btnCalcular.addEventListener('click', async ()=>{
    const params = new URLSearchParams({action:'nomina_preview', periodo:$periodo.value.trim(), id_empleado:$selEmpleado.value});
    const j = await jsonFetch(API, params);
    if(!j.ok){ $errNom.hidden=false; $errNom.textContent=j.msg||'Error'; return; }
    $errNom.hidden=true; pintarNomina(j.rows||[]);
  });

  $btnGuardar.addEventListener('click', async ()=>{
    const rows = rowsToPayload();
    const fd = new FormData();
    fd.append('action','nomina_save');
    fd.append('periodo',$periodo.value.trim());
    fd.append('rows', JSON.stringify(rows));
    const j = await jsonFetch(API, fd);
    if(!j.ok){ $errNom.hidden=false; $errNom.textContent=j.msg||'No se pudo guardar.'; return; }
    $errNom.hidden=true; alert('Nómina guardada.');
  });

  $btnComprob.addEventListener('click', async ()=>{
    const j = await jsonFetch(API, new URLSearchParams({action:'nomina_comprobantes', periodo:$periodo.value.trim()}));
    if(!j.ok) return alert(j.msg||'Error generando comprobantes');
    const w = window.open('','_blank');
    w.document.write(j.html||'<p>Sin datos</p>');
    w.document.close();
    w.focus();
  });

  // INIT
  cargarOptions().then(()=>{ cargarEmpleados(1); });
})();
