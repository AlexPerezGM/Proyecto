// public/js/calendar.js

function buildCalendar(container, eventos) {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth(); // 0-11

    // Primer día del mes
    const firstDay = new Date(year, month, 1);
    const startWeekday = firstDay.getDay(); // 0=Domingo

    // Último día del mes
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();

    const monthNames = [
        "Enero","Febrero","Marzo","Abril","Mayo","Junio",
        "Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"
    ];

    // Header calendario
    const headerHtml = `
        <div class="calendar-header">
            <span>${monthNames[month]} ${year}</span>
            <small style="color:#6b7280;font-size:0.7rem;">
                🔴 Mora | 🟡 Próximo | 🟢 Pagado
            </small>
        </div>
        <div class="calendar-grid">
            <div class="calendar-day-name">Dom</div>
            <div class="calendar-day-name">Lun</div>
            <div class="calendar-day-name">Mar</div>
            <div class="calendar-day-name">Mié</div>
            <div class="calendar-day-name">Jue</div>
            <div class="calendar-day-name">Vie</div>
            <div class="calendar-day-name">Sáb</div>
        </div>
    `;
    container.innerHTML = headerHtml;

    const grid = document.createElement("div");
    grid.className = "calendar-grid";

    // Mapear eventos por fecha YYYY-MM-DD
    const eventosPorDia = {};
    eventos.forEach(ev => {
        if (!eventosPorDia[ev.date]) {
            eventosPorDia[ev.date] = [];
        }
        eventosPorDia[ev.date].push(ev);
    });

    // Celdas vacías antes del día 1
    for (let i = 0; i < startWeekday; i++) {
        const emptyCell = document.createElement("div");
        emptyCell.className = "calendar-day-cell";
        grid.appendChild(emptyCell);
    }

    // Crear las celdas de cada día
    for (let day = 1; day <= daysInMonth; day++) {
        const fechaISO = `${year}-${String(month+1).padStart(2,"0")}-${String(day).padStart(2,"0")}`;

        const cell = document.createElement("div");
        cell.className = "calendar-day-cell";

        const dayNumber = document.createElement("div");
        dayNumber.className = "day-number";
        dayNumber.textContent = day;
        cell.appendChild(dayNumber);

        if (eventosPorDia[fechaISO]) {
            eventosPorDia[fechaISO].forEach(ev => {
                const pill = document.createElement("span");
                pill.className = `event-pill ${ev.type}`;
                pill.textContent = ev.title;
                // opcional tooltip
                pill.title = ev.monto ? ("Monto: $" + ev.monto) : "";
                cell.appendChild(pill);
            });
        }

        grid.appendChild(cell);
    }

    container.appendChild(grid);
}

window.addEventListener("DOMContentLoaded", () => {
    const calDiv = document.getElementById("calendar");
    if (!calDiv) return;

    let eventos = [];
    try {
        eventos = JSON.parse(calDiv.getAttribute("data-events") || "[]");
    } catch (e) {
        eventos = [];
    }

    buildCalendar(calDiv, eventos);
});
