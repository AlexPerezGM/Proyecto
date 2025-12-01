function formatDateLong(dateISO) {
    const [y, m, d] = dateISO.split("-").map(Number);
    const dateObj = new Date(y, m - 1, d);
    const formatter = new Intl.DateTimeFormat("es-DO", {
        weekday: "long",
        day: "2-digit",
        month: "long",
        year: "numeric",
    });
    return formatter.format(dateObj);
}

function printCalendarDay(backdrop) {
    const dateText = backdrop.querySelector(".calendar-modal-date").textContent || "";
    const table = backdrop.querySelector(".calendar-modal-table");
    if (!table) return;

    const rowsHtml = table.outerHTML;

    const w = window.open("", "_blank");
    w.document.write(`
        <html>
        <head>
            <meta charset="utf-8" />
            <title>Agenda del día</title>
            <style>
                body {
                    font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
                    padding: 16px;
                }
                h1 {
                    font-size: 1.2rem;
                    margin-bottom: 0.5rem;
                }
                p {
                    margin: 0 0 1rem 0;
                    font-size: 0.9rem;
                    color: #4b5563;
                }
                table {
                    border-collapse: collapse;
                    width: 100%;
                    font-size: 0.85rem;
                }
                th, td {
                    border: 1px solid #d1d5db;
                    padding: 6px 8px;
                    text-align: left;
                }
                thead {
                    background: #f3f4f6;
                }
            </style>
        </head>
        <body>
            <h1>Agenda del día</h1>
            <p>${dateText}</p>
            ${rowsHtml}
        </body>
        </html>
    `);
    w.document.close();
    w.focus();
    w.print();
}

function ensureCalendarModal() {
    let backdrop = document.getElementById("calendar-day-modal");
    if (backdrop) return backdrop;

    backdrop = document.createElement("div");
    backdrop.id = "calendar-day-modal";
    backdrop.className = "calendar-modal-backdrop hidden";

    backdrop.innerHTML = `
        <div class="calendar-modal">
            <div class="calendar-modal-header">
                <div>
                    <h3>Agenda del día</h3>
                    <p class="calendar-modal-date"></p>
                </div>
                <button type="button" class="calendar-modal-close calendar-modal-close-icon">&times;</button>
            </div>
            <div class="calendar-modal-body">
                <table class="table-simple calendar-modal-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Descripción</th>
                            <th>Monto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- filas dinámicas -->
                    </tbody>
                </table>
            </div>
            <div class="calendar-modal-footer">
                <button type="button" class="btn-secondary calendar-modal-close">Cerrar</button>
                <button type="button" class="btn-primary calendar-modal-print">Imprimir lista</button>
            </div>
        </div>
    `;

    document.body.appendChild(backdrop);

    function closeModal() {
        backdrop.classList.add("hidden");
    }
    backdrop.addEventListener("click", (e) => {
        if (e.target === backdrop) {
            closeModal();
        }
    });

    backdrop.querySelectorAll(".calendar-modal-close").forEach((btn) => {
        btn.addEventListener("click", closeModal);
    });

    const printBtn = backdrop.querySelector(".calendar-modal-print");
    printBtn.addEventListener("click", () => {
        printCalendarDay(backdrop);
    });

    return backdrop;
}

function openDayDetail(dateISO, eventsForDay) {
    const backdrop = ensureCalendarModal();
    const dateLabel = backdrop.querySelector(".calendar-modal-date");
    const tbody = backdrop.querySelector("tbody");

    if (!tbody) return;

    const total = eventsForDay.length;
    dateLabel.textContent = `${formatDateLong(dateISO)} (${total} evento${total !== 1 ? "s" : ""})`;

    tbody.innerHTML = "";

    const estadoLabels = {
        vencida: "En mora",
        pendiente: "Pendiente",
        pagada: "Pagada",
    };

    eventsForDay.forEach((ev, idx) => {
        const tr = document.createElement("tr");
        tr.className = ev.type ? `estado-${ev.type}` : "";

        const montoTexto =
            ev.monto !== undefined && ev.monto !== null && ev.monto !== ""
                ? "$" +
                Number(ev.monto).toLocaleString("es-DO", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                })
                : "-";

        tr.innerHTML = `
            <td>${idx + 1}</td>
            <td>${ev.title || ""}</td>
            <td>${montoTexto}</td>
            <td>${estadoLabels[ev.type] || ev.type || "-"}</td>
        `;

        tbody.appendChild(tr);
    });

    backdrop.classList.remove("hidden");
}

function buildCalendar(container, eventos) {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth(); // 0-11

    const firstDay = new Date(year, month, 1);
    const startWeekday = firstDay.getDay();

    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();

    const monthNames = [
        "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
        "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
    ];

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

    const eventosPorDia = {};
    eventos.forEach(ev => {
        if (!eventosPorDia[ev.date]) {
            eventosPorDia[ev.date] = [];
        }
        eventosPorDia[ev.date].push(ev);
    });

    for (let i = 0; i < startWeekday; i++) {
        const emptyCell = document.createElement("div");
        emptyCell.className = "calendar-day-cell";
        grid.appendChild(emptyCell);
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const fechaISO = `${year}-${String(month + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`;

        const cell = document.createElement("div");
        cell.className = "calendar-day-cell";

        const dayNumber = document.createElement("div");
        dayNumber.className = "day-number";
        dayNumber.textContent = day;
        cell.appendChild(dayNumber);

        const eventosDia = eventosPorDia[fechaISO];

        if (eventosDia && eventosDia.length > 0) {
            const counts = { vencida: 0, pendiente: 0, pagada: 0 };
            eventosDia.forEach(ev => {
                if (counts[ev.type] !== undefined) {
                    counts[ev.type]++;
                }
            });

            const total = eventosDia.length;

            const summaryBtn = document.createElement("button");
            summaryBtn.type = "button";
            summaryBtn.className = "day-events-summary";

            summaryBtn.innerHTML = `
                <span class="summary-count">
                    ${total} tarea${total !== 1 ? "s" : ""}
                </span>
                <span class="summary-badges">
                    ${counts.vencida ? `<span class="badge badge-vencida">${counts.vencida} 🔴</span>` : ""}
                    ${counts.pendiente ? `<span class="badge badge-pendiente">${counts.pendiente} 🟡</span>` : ""}
                    ${counts.pagada ? `<span class="badge badge-pagada">${counts.pagada} 🟢</span>` : ""}
                </span>
            `;

            summaryBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                openDayDetail(fechaISO, eventosDia);
            });

            cell.appendChild(summaryBtn);
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
