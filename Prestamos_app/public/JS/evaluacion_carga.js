let pct = 0;
const bar = document.getElementById('progressBar');
const pctText = document.getElementById('progressPct');
const steps = [
    document.getElementById('step-1'), 
    document.getElementById('step-2'), 
    document.getElementById('step-3'), 
    document.getElementById('step-4')
];
const icons = [
    document.getElementById('icon-1'), 
    document.getElementById('icon-2'), 
    document.getElementById('icon-3'), 
    document.getElementById('icon-4')
];

steps[0].classList.add('running');
icons[0].innerHTML = '<div class="ev-step-spin"></div>';

// Variable para guardar el resultado de la API
let resultadoApi = null;
let evaluacionTerminada = false;

// 1. Iniciar la animación visual
const interval = setInterval(() => {
    if(pct < 95 || evaluacionTerminada) {
        pct += (evaluacionTerminada ? 5 : 2); // Si terminó la API, acelerar al final
    }
    if(pct > 100) pct = 100;
    
    bar.style.width = pct + '%';
    pctText.innerText = pct + '% Completado';

    if (pct >= 25 && pct < 50 && steps[0].classList.contains('running')) { 
        steps[0].classList.replace('running', 'done'); icons[0].innerHTML = '✔️';
        steps[1].classList.add('running'); icons[1].innerHTML = '<div class="ev-step-spin"></div>';
    }
    if (pct >= 50 && pct < 75 && steps[1].classList.contains('running')) { 
        steps[1].classList.replace('running', 'done'); icons[1].innerHTML = '✔️';
        steps[2].classList.add('running'); icons[2].innerHTML = '<div class="ev-step-spin"></div>';
    }
    if (pct >= 75 && pct < 100 && steps[2].classList.contains('running')) { 
        steps[2].classList.replace('running', 'done'); icons[2].innerHTML = '✔️';
        steps[3].classList.add('running'); icons[3].innerHTML = '<div class="ev-step-spin"></div>';
    }
    
    // Cuando llega al 100% y la API ya respondió
    if (pct === 100 && evaluacionTerminada) {
        clearInterval(interval);
        steps[3].classList.replace('running', 'done'); icons[3].innerHTML = '✔️';
        document.getElementById('mainSpinner').style.animation = 'none';
        
        // Mostrar dictamen
        const dictamenLabel = document.getElementById('mainLabel');
        if (resultadoApi.decision === 'Contrapropuesta') {
            document.getElementById('mainSpinner').style.borderColor = '#f59e0b';
            dictamenLabel.innerText = 'Dictamen: CONTRAPROPUESTA';
            dictamenLabel.style.color = '#d97706';
            
            // Redirigir a la vista de contrapropuesta tras un instante
            setTimeout(() => {
                window.location.href = APP_BASE + 'views/contrapropuesta.php?id_prestamo=' + ID_PRESTAMO;
            }, 1200);

        } else if (resultadoApi.decision === 'Aprobado') {
            document.getElementById('mainSpinner').style.borderColor = '#16a34a';
            dictamenLabel.innerText = 'Dictamen: APROBADO';
            dictamenLabel.style.color = '#16a34a';
            
            setTimeout(() => {
                window.location.href = APP_BASE + 'views/resultado_v.php?id_prestamo=' + ID_PRESTAMO;
            }, 1200);

        } else {
            document.getElementById('mainSpinner').style.borderColor = '#dc2626';
            dictamenLabel.innerText = 'Dictamen: RECHAZADO';
            dictamenLabel.style.color = '#dc2626';
            
            setTimeout(() => {
                window.location.href = APP_BASE + 'views/resultado_v.php?id_prestamo=' + ID_PRESTAMO;
            }, 1200);
        }
    }
}, 70);

// 2. Ejecutar el análisis real en la API
async function procesarEvaluacion() {
    try {
        const params = new URLSearchParams({ id_prestamo: ID_PRESTAMO });
        // Aquí llamamos a la API de evaluación que acabamos de crear
        const res = await fetch(APP_BASE + 'api/Evaluar_prestamo_propuesta.php', {
            method: 'POST',
            body: params
        });
        
        const data = await res.json();
        
        if (data.ok) {
            resultadoApi = data;
        } else {
            alert("Error en el análisis: " + data.msg);
            resultadoApi = { decision: 'REVISION_MANUAL' }; // Fallback de seguridad
        }
    } catch (e) {
        console.error("Error en Fetch:", e);
        resultadoApi = { decision: 'REVISION_MANUAL' };
    } finally {
        evaluacionTerminada = true; // Avisamos al timer visual que puede finalizar
    }
}

// Iniciar el fetch inmediatamente al cargar la pantalla
procesarEvaluacion();