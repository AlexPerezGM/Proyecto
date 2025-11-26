// public/js/dashboard.js
(function () {
  const ready = (fn) =>
    document.readyState !== "loading"
      ? fn()
      : document.addEventListener("DOMContentLoaded", fn);

  // Utilidades
  const toCurrency = (v) =>
    typeof v === "number"
      ? "$" + v.toLocaleString(undefined, { maximumFractionDigits: 2 })
      : v;

  const getJSON = (el, attr, fallback = {}) => {
    try {
      const raw = el.getAttribute(attr) || "";
      return raw ? JSON.parse(raw) : fallback;
    } catch (e) {
      console.error("JSON inválido en", attr, e);
      return fallback;
    }
  };

  // Render línea (tendencias)
  const renderTendencia = () => {
    const canvas = document.getElementById("chartTendencia30d");
    if (!canvas) return;

    if (typeof Chart === "undefined") {
      console.error("Chart.js no cargó. Verifica el <script> del CDN.");
      return;
    }

    const ds = getJSON(canvas, "data-series", {
      labels: [],
      prestamos: [],
      pagos: [],
      castigos: [],
    });

    const ctx = canvas.getContext("2d");
    new Chart(ctx, {
      type: "line",
      data: {
        labels: ds.labels || [],
        datasets: [
          {
            label: "Desembolsos",
            data: ds.prestamos || [],
            borderWidth: 2,
            fill: false,
            tension: 0.25,
          },
          {
            label: "Cobros",
            data: ds.pagos || [],
            borderWidth: 2,
            fill: false,
            tension: 0.25,
          },
          {
            label: "Castigos",
            data: ds.castigos || [],
            borderWidth: 2,
            fill: false,
            tension: 0.25,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: "index", intersect: false },
        plugins: {
          legend: { position: "bottom" },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${toCurrency(ctx.parsed.y)}`,
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { callback: (val) => toCurrency(val) },
          },
        },
      },
    });
  };

  // Render barras (ingresos por interés)
  const renderIngresosInteres = () => {
    const canvas = document.getElementById("chartIngresosInteres");
    if (!canvas) return;

    if (typeof Chart === "undefined") {
      console.error("Chart.js no cargó. Verifica el <script> del CDN.");
      return;
    }

    const ds = getJSON(canvas, "data-series", {
      labels: [],
      values: [],
    });

    const ctx = canvas.getContext("2d");
    new Chart(ctx, {
      type: "bar",
      data: {
        labels: ds.labels || [],
        datasets: [
          {
            label: "Intereses cobrados",
            data: ds.values || [],
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) => ` ${toCurrency(ctx.parsed.y)}`,
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { callback: (val) => toCurrency(val) },
          },
        },
      },
    });
  };

  // Init
  ready(() => {
    renderTendencia();
    renderIngresosInteres();
  });
})();
