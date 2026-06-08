let chartInstance = null;
let reportChartInstance = null;
let currentView = "monthly";

window.addEventListener("load", function () {
  if (window.monthlyData && window.monthlyData.labels.length > 0) {
    renderChart(window.monthlyData, "monthly");
  }
});

function renderChart(data, viewType) {
  currentView = viewType;
  const canvas = document.getElementById("myChart");
  if (!canvas) return;

  if (chartInstance) {
    chartInstance.destroy();
    chartInstance = null;
  }

  const ctx = canvas.getContext("2d");

  chartInstance = new Chart(ctx, {
    type: "bar",
    data: {
      labels: data.labels,
      datasets: [
        {
          label: "Revenue (₱)",
          data: data.revenue,
          backgroundColor: "rgba(76, 175, 80, 0.8)",
          borderColor: "#4CAF50",
          borderWidth: 1,
          borderRadius: 4,
        },
        {
          label: "Transactions",
          data: data.transactions,
          backgroundColor: "rgba(99, 102, 241, 0.8)",
          borderColor: "#6366F1",
          borderWidth: 1,
          borderRadius: 4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: true,
          position: "top",
        },
      },
      scales: {
        y: {
          beginAtZero: true,
        },
        x: {
          stacked: false,
        },
      },
    },
  });
}

function toggleView(view) {
  currentView = view;
  const data = view === "daily" ? window.dailyData : window.monthlyData;
  if (data) renderChart(data, view);
}

function openReport() {
  document.getElementById("reportModal").style.display = "flex";

  const data = currentView === "daily" ? window.dailyData : window.monthlyData;

  // ===== TABLE =====
  const tableBody = document.getElementById("reportTableBody");
  const totalEl = document.getElementById("reportTotal");

  tableBody.innerHTML = "";

  let total = 0;

  data.labels.forEach((label, index) => {
    const revenue = data.revenue[index] || 0;

    total += revenue;

    const row = document.createElement("tr");

    row.innerHTML = `
      <td style="padding: 8px 0;">${label}</td>
      <td style="text-align:right; padding: 8px 0;">
        ₱${revenue.toLocaleString()}
      </td>
    `;

    tableBody.appendChild(row);
  });

  totalEl.textContent = `₱${total.toLocaleString()}`;

  // ===== CHART =====
  const ctx = document.getElementById("reportChart").getContext("2d");

  if (reportChartInstance) {
    reportChartInstance.destroy();
  }

  reportChartInstance = new Chart(ctx, {
    type: "line",
    data: {
      labels: data.labels,
      datasets: [
        {
          label: "Income",
          data: data.revenue,
          borderColor: "#4CAF50",
          backgroundColor: "rgba(76,175,80,0.2)",
          fill: true,
          tension: 0.3,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
    },
  });
}

function closeReport() {
  document.getElementById("reportModal").style.display = "none";
}
