import '@fontsource/poppins/400.css';
import '@fontsource/poppins/500.css';
import '@fontsource/poppins/600.css';
import '@fontsource/poppins/700.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import 'bootstrap';
import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);
Chart.defaults.color = '#9fb3d1';

/** Degradê vertical das barras, calculado sobre a área real do gráfico. */
function barGradient({ chart }) {
    const { ctx, chartArea } = chart;

    if (!chartArea) {
        return '#1677ff';
    }

    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
    gradient.addColorStop(0, '#00d4ff');
    gradient.addColorStop(0.55, '#1677ff');
    gradient.addColorStop(1, '#0b3fbf');

    return gradient;
}

const charts = new WeakMap();

/**
 * Desenha ou atualiza cada gráfico a partir do atributo data-chart do contêiner.
 * O Livewire atualiza esse atributo; o canvas fica protegido com wire:ignore.
 */
function renderCharts() {
    document.querySelectorAll('[data-chart]').forEach((box) => {
        const raw = box.dataset.chart;
        const current = charts.get(box);

        if (current?.raw === raw) {
            return;
        }

        const { labels, values } = JSON.parse(raw);

        if (current) {
            current.chart.data.labels = labels;
            current.chart.data.datasets[0].data = values;
            current.chart.update();
            current.raw = raw;

            return;
        }

        const chart = new Chart(box.querySelector('canvas'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: box.dataset.chartLabel,
                    data: values,
                    backgroundColor: barGradient,
                    borderRadius: 4,
                    maxBarThickness: 28,
                }],
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(255, 255, 255, 0.08)' } },
                },
            },
        });

        charts.set(box, { chart, raw });
    });
}

renderCharts();

new MutationObserver(renderCharts).observe(document.body, {
    subtree: true,
    childList: true,
    attributes: true,
    attributeFilter: ['data-chart'],
});
