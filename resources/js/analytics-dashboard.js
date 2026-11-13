import ApexCharts from 'apexcharts';

/** @type {Record<string, ApexCharts>} */
const charts = {};

function readPayload() {
    const node = document.getElementById('analytics-payload');
    if (!node?.textContent) {
        return null;
    }
    try {
        return JSON.parse(node.textContent);
    } catch {
        return null;
    }
}

function destroyCharts() {
    Object.values(charts).forEach((c) => {
        try {
            c.destroy();
        } catch {
            /* noop */
        }
    });
    Object.keys(charts).forEach((k) => delete charts[k]);
}

function baseOptions(theme, rtl) {
    return {
        chart: {
            fontFamily: 'inherit',
            toolbar: { show: false },
            animations: { enabled: true, speed: 400 },
        },
        colors: [theme.primary, theme.accent, theme.mocha, theme.latte, theme.espresso],
        grid: { borderColor: 'rgba(92, 61, 46, 0.12)', strokeDashArray: 4 },
        dataLabels: { enabled: false },
        legend: { position: 'bottom', horizontalAlign: rtl ? 'left' : 'center' },
        tooltip: { theme: 'light' },
        yaxis: {
            labels: {
                formatter: (v) => (Number.isFinite(v) ? v.toFixed(0) : v),
            },
        },
    };
}

function mountChart(elId, options) {
    const el = document.getElementById(elId);
    if (!el) {
        return;
    }
    if (charts[elId]) {
        charts[elId].destroy();
    }
    charts[elId] = new ApexCharts(el, options);
    charts[elId].render();
}

export function initOwnerAnalytics() {
    const payload = readPayload();
    if (!payload?.report) {
        return;
    }

    destroyCharts();

    const { report, theme, rtl, labels, paymentLabels } = payload;
    const common = baseOptions(theme, rtl);

    const seriesLabels = report.sales_series.map((r) => r.label);
    const seriesTotals = report.sales_series.map((r) => r.total);

    mountChart('chart-sales-series', {
        ...common,
        series: [{ name: labels.sales, data: seriesTotals }],
        chart: { ...common.chart, type: 'area', height: 280 },
        stroke: { curve: 'smooth', width: 2.5 },
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 0.4, opacityFrom: 0.55, opacityTo: 0.05 },
        },
        xaxis: {
            categories: seriesLabels,
            labels: { rotate: rtl ? 45 : -45, style: { fontSize: '11px' } },
        },
    });

    const dow = labels.dow ?? [];
    const heatSeries = dow.map((name, dowIndex) => {
        const cells = [];
        for (let hour = 0; hour < 24; hour += 1) {
            const hit = report.hourly_heatmap.find((c) => c.dow === dowIndex && c.hour === hour);
            cells.push({ x: `${hour}:00`, y: hit?.total ?? 0 });
        }
        return { name, data: cells };
    });

    mountChart('chart-heatmap', {
        ...common,
        chart: { ...common.chart, type: 'heatmap', height: 300 },
        plotOptions: {
            heatmap: {
                shadeIntensity: 0.5,
                colorScale: {
                    ranges: [
                        { from: 0, to: 0, color: theme.latte, name: '0' },
                        { from: 0.01, to: 50, color: theme.accent, name: 'low' },
                        { from: 50.01, to: 500, color: theme.primary, name: 'mid' },
                        { from: 500.01, to: 100000, color: theme.espresso, name: 'high' },
                    ],
                },
            },
        },
        series: heatSeries,
        dataLabels: { enabled: false },
        xaxis: { type: 'category' },
    });

    const catLabels = report.category_mix.map((c) => c.label);
    const catTotals = report.category_mix.map((c) => c.total);
    mountChart('chart-category-donut', {
        ...common,
        chart: { ...common.chart, type: 'donut', height: 260 },
        labels: catLabels,
        series: catTotals.length ? catTotals : [1],
        plotOptions: { pie: { donut: { size: '62%' } } },
        legend: { show: catTotals.length > 0 },
        noData: { text: '—' },
    });

    const payLabels = report.payment_split.map((p) => paymentLabels?.[p.method] ?? p.method);
    const payTotals = report.payment_split.map((p) => p.total);
    mountChart('chart-payment-donut', {
        ...common,
        chart: { ...common.chart, type: 'donut', height: 260 },
        labels: payLabels,
        series: payTotals.length ? payTotals : [1],
        plotOptions: { pie: { donut: { size: '62%' } } },
    });

    mountChart('chart-top-products', {
        ...common,
        chart: { ...common.chart, type: 'bar', height: 280 },
        series: [{ name: labels.sar, data: report.top_products.map((p) => p.total) }],
        plotOptions: { bar: { borderRadius: 6, horizontal: true, barHeight: '70%' } },
        xaxis: {
            categories: report.top_products.map((p) => p.name),
            labels: { style: { fontSize: '11px' } },
        },
    });

    if (report.branch_comparison?.length) {
        mountChart('chart-branches', {
            ...common,
            chart: { ...common.chart, type: 'bar', height: 260 },
            series: [{ name: labels.sar, data: report.branch_comparison.map((b) => b.total) }],
            plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
            xaxis: { categories: report.branch_comparison.map((b) => b.name) },
        });
    }

    mountChart('chart-cashiers', {
        ...common,
        chart: { ...common.chart, type: 'bar', height: 280 },
        series: [
            { name: labels.sar, data: report.cashier_performance.map((c) => c.total) },
            { name: labels.orders ?? 'Orders', data: report.cashier_performance.map((c) => c.orders) },
        ],
        plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
        xaxis: { categories: report.cashier_performance.map((c) => c.name) },
    });
}

window.initOwnerAnalytics = initOwnerAnalytics;

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('analytics-payload')) {
        initOwnerAnalytics();
    }
});

document.addEventListener('livewire:navigated', () => {
    if (document.getElementById('analytics-payload')) {
        initOwnerAnalytics();
    }
});

document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.updated', ({ el }) => {
        if (el.querySelector?.('#analytics-payload') || document.getElementById('analytics-payload')) {
            initOwnerAnalytics();
        }
    });
});
