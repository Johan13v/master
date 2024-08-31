@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Dashboard') }}
    </h2>
@endsection

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <div class="mb-4">
                    <label for="interval" class="block text-gray-700">Select Interval:</label>
                    <select id="interval" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="day" {{ $interval == 'day' ? 'selected' : '' }}>Day</option>
                        <option value="month" {{ $interval == 'month' ? 'selected' : '' }}>Month</option>
                        <option value="year" {{ $interval == 'year' ? 'selected' : '' }}>Year</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="view_mode" class="block text-gray-700">View Mode:</label>
                    <select id="view_mode" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="website" {{ $viewMode == 'website' ? 'selected' : '' }}>Per Website</option>
                        <option value="city" {{ $viewMode == 'city' ? 'selected' : '' }}>Per City</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="start_date" class="block text-gray-700">Start Date:</label>
                    <input type="date" id="start_date" value="{{ $startDate }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                <div class="mb-4">
                    <label for="end_date" class="block text-gray-700">End Date:</label>
                    <input type="date" id="end_date" value="{{ $endDate }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>

                <div class="chart-container">
                    <h3 class="text-lg font-medium text-gray-700">Total Revenue</h3>
                    <canvas id="totalRevenueChart"></canvas>
                </div>

                @foreach ($revenueStreams as $stream)
                    <div class="chart-container">
                        <h3 class="text-lg font-medium text-gray-700">{{ $stream->title }} Revenue</h3>
                        <canvas id="revenueChart{{ $stream->id }}"></canvas>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const intervalSelect = document.getElementById('interval');
    const viewModeSelect = document.getElementById('view_mode');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');

    function updateDashboard() {
        const selectedInterval = intervalSelect.value;
        const selectedViewMode = viewModeSelect.value;
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        window.location.href = `{{ url('/dashboard') }}?interval=${selectedInterval}&view_mode=${selectedViewMode}&start_date=${startDate}&end_date=${endDate}`;
    }

    intervalSelect.addEventListener('change', updateDashboard);
    viewModeSelect.addEventListener('change', updateDashboard);
    startDateInput.addEventListener('change', updateDashboard);
    endDateInput.addEventListener('change', updateDashboard);

    const totalRevenueData = @json($totalRevenueData);
    const revenueDataByStream = @json($revenueDataByStream);
    const revenueStreams = @json($revenueStreams);

    const colors = [
    'rgba(255, 99, 132, 0.2)',
    'rgba(54, 162, 235, 0.2)',
    'rgba(255, 206, 86, 0.2)',
    'rgba(75, 192, 192, 0.2)',
    'rgba(153, 102, 255, 0.2)',
    'rgba(255, 159, 64, 0.2)',
    'rgba(255, 99, 71, 0.2)',
    'rgba(144, 238, 144, 0.2)',
    'rgba(173, 216, 230, 0.2)',
    'rgba(255, 255, 102, 0.2)',
    'rgba(32, 178, 170, 0.2)',
    'rgba(220, 20, 60, 0.2)',
    'rgba(218, 112, 214, 0.2)',
    'rgba(60, 179, 113, 0.2)',
    'rgba(0, 191, 255, 0.2)',
    'rgba(135, 206, 235, 0.2)',
    'rgba(255, 140, 0, 0.2)',
    'rgba(233, 150, 122, 0.2)',
    'rgba(148, 0, 211, 0.2)',
    'rgba(100, 149, 237, 0.2)',
];

const borderColors = [
    'rgba(255, 99, 132, 1)',
    'rgba(54, 162, 235, 1)',
    'rgba(255, 206, 86, 1)',
    'rgba(75, 192, 192, 1)',
    'rgba(153, 102, 255, 1)',
    'rgba(255, 159, 64, 1)',
    'rgba(255, 99, 71, 1)',
    'rgba(144, 238, 144, 1)',
    'rgba(173, 216, 230, 1)',
    'rgba(255, 255, 102, 1)',
    'rgba(32, 178, 170, 1)',
    'rgba(220, 20, 60, 1)',
    'rgba(218, 112, 214, 1)',
    'rgba(60, 179, 113, 1)',
    'rgba(0, 191, 255, 1)',
    'rgba(135, 206, 235, 1)',
    'rgba(255, 140, 0, 1)',
    'rgba(233, 150, 122, 1)',
    'rgba(148, 0, 211, 1)',
    'rgba(100, 149, 237, 1)',
];


    function createChart(ctx, data, labels) {
        const datasets = Object.keys(data).map((key, index) => {
            return {
                label: data[key].entity,
                data: labels.map(label => {
                    const item = data[key].data.find(d => d.date === label);
                    return item ? item.total_revenue : 0;
                }),
                backgroundColor: colors[index % colors.length],
                borderColor: borderColors[index % borderColors.length],
                borderWidth: 1,
                fill: false
            };
        });

        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: '{{ $interval }}'
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    const labels = Object.keys(totalRevenueData).length > 0 ? totalRevenueData[Object.keys(totalRevenueData)[0]].data.map(d => d.date) : [];

    const totalRevenueCtx = document.getElementById('totalRevenueChart').getContext('2d');
    createChart(totalRevenueCtx, totalRevenueData, labels);

    revenueStreams.forEach(stream => {
        const ctx = document.getElementById(`revenueChart${stream.id}`).getContext('2d');
        createChart(ctx, revenueDataByStream[stream.id], labels);
    });
});
</script>
@endsection
