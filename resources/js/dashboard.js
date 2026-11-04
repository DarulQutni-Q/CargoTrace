import Chart from 'chart.js/auto';

// Dashboard charts follow the design rules: muted grid, mono-ish labels,
// neutral ramp for the series and one accent on the leading value [S9].
Chart.defaults.color = '#9ba0aa';
Chart.defaults.font.family = "'IBM Plex Sans', sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.borderColor = 'rgba(235, 238, 245, 0.06)';

const ACCENT = '#3b82f6';
const NEUTRAL = ['rgba(235, 238, 245, 0.28)', 'rgba(235, 238, 245, 0.22)', 'rgba(235, 238, 245, 0.16)', 'rgba(235, 238, 245, 0.12)', 'rgba(235, 238, 245, 0.08)', 'rgba(235, 238, 245, 0.06)'];

function renderBars(id, labels, values, { indexAxis = 'y' } = {}) {
    const canvas = document.getElementById(id);

    if (!canvas) {
        return;
    }

    const maxValue = Math.max(...values, 0);
    const colors = values.map((value) => (value === maxValue && maxValue > 0 ? ACCENT : NEUTRAL[Math.min(values.indexOf(value), NEUTRAL.length - 1)]));

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{ data: values, backgroundColor: colors, borderRadius: 4, barThickness: 16 }],
        },
        options: {
            indexAxis,
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: indexAxis === 'y' }, ticks: { precision: 0 } },
                y: { grid: { display: indexAxis === 'x' } },
            },
        },
    });
}

const byStatus = document.getElementById('chart-by-status');
const byCarrier = document.getElementById('chart-by-carrier');

if (byStatus) {
    const data = JSON.parse(byStatus.dataset.chart);
    renderBars('chart-by-status', data.labels, data.values, { indexAxis: 'x' });
}

if (byCarrier) {
    const data = JSON.parse(byCarrier.dataset.chart);
    renderBars('chart-by-carrier', data.labels, data.values);
}
