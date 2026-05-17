@extends('layouts.navbar')

@section('content')
    <div class="analytics-page">

        <div class="analytics-container">

            <div class="analytics-filter card">

                <div class="filter-left">
                    @php
                        $currentType = request('type', 'daily');
                    @endphp
                    <button class="filter-btn {{ $currentType == 'daily' ? 'active-filter' : '' }}" data-type="daily">Daily</button>
                    <button class="filter-btn {{ $currentType == 'weekly' ? 'active-filter' : '' }}" data-type="weekly">Weekly</button>
                    <button class="filter-btn {{ $currentType == 'monthly' ? 'active-filter' : '' }}" data-type="monthly">Monthly</button>
                </div>

                <div class="filter-divider"></div>

            </div>
            <!-- TOP ANALYTICS -->
            <div class="analytics-top">
                <!-- BAR CHART -->
                <div class="analytics-chart card">
                    <h3> Emotion Trend</h3>
                    <div class="chart-wrapper" style="flex: 1; min-height: 0; position: relative;">
                        <canvas id="myChart"></canvas>
                    </div>
                </div>

                <!-- DONUT CHART -->
                <div class="analytics-donut card">

                    <h3>Statisfaction Share</h3>

                    <div class="donut-wrapper">

                        <canvas id="donutChart"></canvas>

                    </div>

                    <div class="emotion-list">

                        @php
                            $colorMap = [
                                'happy' => '#22c55e',   
                                'neutral' => '#f59e0b', 
                                'sad' => '#3b82f6',     
                                'angry' => '#ef4444'  
                            ];
                        @endphp

                        @php
                            $puas = 0;
                            $tidakPuas = 0;

                            foreach ($labels as $index => $label) {
                                $count = $data[$index] ?? 0;

                                if ($label === 'happy' || $label === 'neutral') {
                                    $puas += $count;
                                } else if ($label === 'sad' || $label === 'angry') {
                                    $tidakPuas += $count;
                                }
                            }

                            $puasPercent = $total > 0 ? round(($puas / $total) * 100) : 0;
                            $tidakPuasPercent = $total > 0 ? round(($tidakPuas / $total) * 100) : 0;
                        @endphp

                        <!-- PUAS -->
                        <div class="emotion-row">
                            <div class="emotion-left" style="display: flex; align-items: center; gap: 10px;">
                                
                                <span style="
                                    width: 12px;
                                    height: 12px;
                                    border-radius: 50%;
                                    display: inline-block;
                                    background-color: #22c55e;
                                "></span>

                                <span>Puas</span>
                            </div>

                            <span>{{ $puasPercent }}%</span>
                        </div>

                        <!-- TIDAK PUAS -->
                        <div class="emotion-row">
                            <div class="emotion-left" style="display: flex; align-items: center; gap: 10px;">
                                
                                <span style="
                                    width: 12px;
                                    height: 12px;
                                    border-radius: 50%;
                                    display: inline-block;
                                    background-color: #ef4444;
                                "></span>

                                <span>Tidak Puas</span>
                            </div>

                            <span>{{ $tidakPuasPercent }}%</span>
                        </div>

                    </div>

                </div>

            </div>

            <!-- TABLE -->
            <div class="analytics-table card">

                <div class="table-header">

                    <h2>Historical Analytics Log</h2>

                    <form method="GET" action="/analytics">
                        <input 
                            type="text" 
                            name="search" 
                            placeholder="🔍 Search logs..." 
                            value="{{ request('search') }}"
                        />
                    </form>

                </div>

                <table>

                    <thead>
                        <tr>
                            <th>DETECTION ID</th>
                            <th>TIMESTAMP</th>
                            <th>EMOTION</th>
                            <th>SATISFACTION</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->id }}</td>
                            
                            <!-- FORMAT TIMESTAMP -->
                            <td>{{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}</td>

                            <!-- EMOTION -->
                            <td>{{ ucfirst($log->emotion) }}</td>

                            <!-- SATISFACTION -->
                            <td>{{ ucfirst($log->satisfaction) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" style="text-align:center;">No data available</td>
                        </tr>
                        @endforelse

                    </tbody>

                </table>

                <div class="table-footer">

                    @if($logs->count())
                        <span class="showing-text">
                            Showing {{ $logs->firstItem() }}-{{ $logs->lastItem() }} 
                            of {{ $logs->total() }} reports
                        </span>
                    @else
                        <span>No data available</span>
                    @endif

                    <div class="pagination-wrapper">
                        {{ $logs->links('pagination::simple-bootstrap-4') }}
                    </div>

                </div>

            </div>

        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const type = this.dataset.type;

                // ambil search biar ga ilang
                const searchParams = new URLSearchParams(window.location.search);
                searchParams.set('type', type);

                window.location.search = searchParams.toString();
            });
        });
        document.addEventListener('DOMContentLoaded', function () {

            const barCanvas = document.getElementById('myChart');
            const donutCanvas = document.getElementById('donutChart');

            if (!barCanvas || !donutCanvas) {
                console.error("Canvas tidak ditemukan!");
                return;
            }

        // =========================
        // DATA DARI LARAVEL
        // =========================
        const rawLabels = @json($labels ?? []);
        const rawData = @json($data ?? []);

        // =========================
        // BAR CHART (4 EMOSI)
        // =========================
        const barLabels = rawLabels;
        const barData = rawData;

        // =========================
        // DONUT (PUAS / TIDAK)
        // =========================
        let puas = 0;
        let tidakPuas = 0;

        rawLabels.forEach((label, index) => {
            if (label === 'happy' || label === 'neutral') {
                puas += rawData[index];
            } else if (label === 'sad' || label === 'angry') {
                tidakPuas += rawData[index];
            }
        });

        const donutLabels = ['Puas', 'Tidak Puas'];
        const donutData = [puas, tidakPuas];

        const totalEmotion = {{ $total ?? 0 }};

        // =========================
        // BAR CHART
        // =========================
        new Chart(barCanvas, {
        type: 'bar',
        data: {
            labels: barLabels,
            datasets: [{
                data: barData,
                backgroundColor: [
                    '#22c55e', 
                    '#f59e0b', 
                    '#3b82f6', 
                    '#ef4444'  
                ],
                borderRadius: 8,
                borderSkipped: false
            }]
        },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        bottom: 10
                    }
                },
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            padding: 5
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // =========================
        // DONUT CHART
        // =========================
        new Chart(donutCanvas, {
        type: 'doughnut',
        data: {
            labels: donutLabels,
            datasets: [{
                data: donutData,
                backgroundColor: [
                    '#22c55e',
                    '#ef4444' 
                ],
                borderWidth: 0
            }]
        },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false }
                }
            },

            // CENTER TEXT
            plugins: [
                {
                    id: 'centerText',
                    beforeDraw(chart) {
                        const { width, height, ctx } = chart;

                        ctx.save();

                        ctx.font = "bold 16px Arial";
                        ctx.fillStyle = "#111";
                        ctx.textAlign = "center";
                        ctx.textBaseline = "middle";

                        ctx.fillText(totalEmotion, width / 2, height / 2);

                        ctx.restore();
                    }
                }
            ]
        });

    });

    </script>
@endsection