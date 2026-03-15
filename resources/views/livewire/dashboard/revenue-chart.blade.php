<div
    wire:ignore
    x-data="revenueChart(@js($chartData))"
    x-init="initChart()"
    class="w-full"
>
    <canvas x-ref="canvas" class="w-full" style="height: 220px;"></canvas>
</div>

@script
<script>
Alpine.data('revenueChart', (initialData) => ({
    chart: null,
    data: initialData,

    initChart() {
        const ctx = this.$refs.canvas.getContext('2d');

        this.chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: this.data.dates.map(d => {
                    const date = new Date(d + 'T00:00:00');
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }),
                datasets: [
                    {
                        label: 'Revenue',
                        data: this.data.revenue,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.06)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        borderWidth: 2.5,
                    },
                    {
                        label: 'Cost',
                        data: this.data.cost,
                        borderColor: '#94a3b8',
                        backgroundColor: 'transparent',
                        fill: false,
                        tension: 0.3,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        borderWidth: 2,
                        borderDash: [6, 4],
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' +
                                    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 10, family: 'IBM Plex Mono, monospace' },
                            color: '#94a3b8',
                            maxTicksLimit: 7,
                        },
                    },
                    y: {
                        grid: {
                            color: document.documentElement.classList.contains('dark') ? 'rgba(148, 163, 184, 0.1)' : 'rgba(148, 163, 184, 0.2)',
                            drawBorder: false,
                        },
                        ticks: {
                            font: { size: 10, family: 'IBM Plex Mono, monospace' },
                            color: '#94a3b8',
                            callback: function(value) {
                                if (value >= 1000) return '$' + (value / 1000) + 'k';
                                return '$' + value;
                            }
                        },
                        beginAtZero: true,
                    }
                }
            }
        });

        // Dark mode observer
        const observer = new MutationObserver(() => {
            if (this.chart) {
                this.chart.options.scales.y.grid.color =
                    document.documentElement.classList.contains('dark')
                        ? 'rgba(148, 163, 184, 0.1)'
                        : 'rgba(148, 163, 184, 0.2)';
                this.chart.update();
            }
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        // Listen for Livewire updates
        Livewire.on('date-range-changed', () => {
            this.$nextTick(() => {
                const newData = this.$wire.chartData;
                if (newData && this.chart) {
                    this.chart.data.labels = newData.dates.map(d => {
                        const date = new Date(d + 'T00:00:00');
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    });
                    this.chart.data.datasets[0].data = newData.revenue;
                    this.chart.data.datasets[1].data = newData.cost;
                    this.chart.update();
                }
            });
        });
    }
}));
</script>
@endscript
