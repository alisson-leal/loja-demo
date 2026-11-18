import '@fontsource/poppins/400.css';
import '@fontsource/poppins/500.css';
import '@fontsource/poppins/600.css';
import '@fontsource/poppins/700.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import 'bootstrap';
import { Offcanvas } from 'bootstrap';
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

/* Ajuda: F1 abre ou fecha a ajuda da tela atual (no lugar da ajuda do navegador). */
document.addEventListener('keydown', (event) => {
    if (event.key !== 'F1') {
        return;
    }

    const panel = document.getElementById('help-panel');

    if (!panel) {
        return;
    }

    event.preventDefault();
    Offcanvas.getOrCreateInstance(panel).toggle();
});

/* Busca da central de ajuda, sem diferenciar acentos e maiúsculas. */
const normalize = (text) => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
const helpSearch = document.querySelector('[data-help-search]');

if (helpSearch) {
    const items = [...document.querySelectorAll('[data-help-item]')].map((item) => ({ item, text: normalize(item.dataset.helpText) }));
    const sections = document.querySelectorAll('[data-help-section]');
    const empty = document.querySelector('[data-help-empty]');

    helpSearch.addEventListener('input', () => {
        const terms = normalize(helpSearch.value).split(/\s+/).filter(Boolean);

        items.forEach(({ item, text }) => {
            item.hidden = !terms.every((term) => text.includes(term));
        });

        let visible = 0;
        sections.forEach((section) => {
            const any = [...section.querySelectorAll('[data-help-item]')].some((item) => !item.hidden);
            section.hidden = !any;
            visible += any ? 1 : 0;
        });

        empty.hidden = visible > 0;
    });
}
