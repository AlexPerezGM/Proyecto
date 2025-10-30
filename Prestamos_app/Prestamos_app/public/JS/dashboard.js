// public/js/dashboard.js
window.addEventListener("DOMContentLoaded", () => {

    // ====== Gráfico de línea (tendencia 30 días) ======
    const tendenciaCanvas = document.getElementById("chartTendencia30d");
    if (tendenciaCanvas) {
        const dataset = JSON.parse(tendenciaCanvas.getAttribute("data-series") || "{}");
        const ctx = tendenciaCanvas.getContext("2d");

        new Chart(ctx, {
            type: "line",
            data: {
                labels: dataset.labels || [],
                datasets: [
                    {
                        label: "Préstamos",
                        data: dataset.prestamos || [],
                        fill: true,
                        borderColor: "#3b82f6",
                        backgroundColor: "rgba(59,130,246,0.15)",
                        tension: 0.3
                    },
                    {
                        label: "Pagos",
                        data: dataset.pagos || [],
                        fill: true,
                        borderColor: "#10b981",
                        backgroundColor: "rgba(16,185,129,0.15)",
                        tension: 0.3
                    },
                    {
                        label: "Mora",
                        data: dataset.mora || [],
                        fill: true,
                        borderColor: "#ef4444",
                        backgroundColor: "rgba(239,68,68,0.15)",
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: "bottom" }
                },
                scales: {
                    y: {
                        ticks: {
                            callback: (val) => "$" + val
                        }
                    }
                }
            }
        });
    }

    // ====== Gráfico de barras (ingresos por interés mensual) ======
    const interesCanvas = document.getElementById("chartIngresosInteres");
    if (interesCanvas) {
        const dataset = JSON.parse(interesCanvas.getAttribute("data-series") || "{}");
        const ctx2 = interesCanvas.getContext("2d");

        new Chart(ctx2, {
            type: "bar",
            data: {
                labels: dataset.labels || [],
                datasets: [
                    {
                        label: "Interés cobrado",
                        data: dataset.values || [],
                        backgroundColor: "#4f46e5"
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: "bottom" }
                },
                scales: {
                    y: {
                        ticks: {
                            callback: (val) => "$" + val
                        }
                    }
                }
            }
        });
    }

});
