@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Tiqets API Sync') }}
    </h2>
@endsection

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if(!$revenueStream)
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded">
                Geen Tiqets revenue stream gevonden. Maak er eerst een aan onder Inkomsten bronnen.
            </div>
        @else

        <!-- Snelle sync -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-700 mb-4">Snelle sync</h3>
                <p class="text-sm text-gray-500 mb-4">Haal orders op voor één specifieke dag.</p>
                <form action="{{ route('tiqets.sync.run') }}" method="POST" class="flex items-end gap-4">
                    @csrf
                    <input type="hidden" name="mode" value="single">
                    <div>
                        <label class="block text-sm text-gray-700 mb-1">Datum</label>
                        <input type="date" name="date"
                               value="{{ old('date', now()->subDay()->toDateString()) }}"
                               class="border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    </div>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Sync
                    </button>
                </form>
            </div>
        </div>

        <!-- Backfill -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-700 mb-4">Backfill</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Haal orders op voor een periode. Per dag wordt één importrecord aangemaakt.
                    Al geïmporteerde dagen worden automatisch overgeslagen.
                </p>
                <div class="flex items-end gap-4 flex-wrap mb-6">
                    <div>
                        <label class="block text-sm text-gray-700 mb-1">Van</label>
                        <input type="date" id="backfill-from"
                               class="border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-700 mb-1">Tot</label>
                        <input type="date" id="backfill-to"
                               value="{{ now()->subDay()->toDateString() }}"
                               class="border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    </div>
                    <button id="backfill-btn" onclick="startBackfill()"
                            class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                        Start backfill
                    </button>
                </div>

                <!-- Voortgang -->
                <div id="backfill-progress" class="hidden">
                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                        <span id="backfill-status">Bezig...</span>
                        <span id="backfill-counter"></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 mb-3">
                        <div id="backfill-bar" class="bg-indigo-500 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <div id="backfill-log" class="text-xs text-gray-500 space-y-1 max-h-40 overflow-y-auto mb-4"></div>

                    <!-- Ongematchte dagen -->
                    <div id="unmatched-section" class="hidden">
                        <p class="text-sm font-medium text-orange-700 mb-2">Ongematchte orders gevonden — klik op een datum om matchers in te stellen:</p>
                        <div id="unmatched-days" class="flex flex-wrap gap-2 mb-3"></div>
                        <button onclick="window.location.reload()" class="text-sm text-gray-500 hover:text-gray-700 underline">
                            Pagina vernieuwen
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @endif

        <!-- Dagen met ongematchte orders -->
        @if($unmatchedDays->isNotEmpty())
        <div class="bg-orange-50 border border-orange-200 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-orange-700 mb-2">Ongematchte orders — actie vereist</h3>
                <p class="text-sm text-orange-600 mb-4">
                    Voor de onderstaande dagen zijn orders binnengekomen die niet automatisch gekoppeld konden worden.
                    Klik op een datum om matchers in te stellen.
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach($unmatchedDays as $date)
                        <form action="{{ route('tiqets.sync.fix-day') }}" method="POST">
                            @csrf
                            <input type="hidden" name="date" value="{{ $date }}">
                            <button type="submit"
                                    class="bg-orange-100 hover:bg-orange-200 text-orange-800 text-sm font-medium px-4 py-2 rounded border border-orange-300">
                                {{ $date }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Cache wissen -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium text-gray-700">Productnamen cache</h3>
                    <p class="text-xs text-gray-500 mt-1">Wis de cache als productnamen als ID worden getoond.</p>
                </div>
                <form action="{{ route('tiqets.sync.clear-cache') }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium py-2 px-4 rounded">
                        Cache wissen
                    </button>
                </form>
            </div>
        </div>

        <!-- Overzicht per maand -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-700 mb-4">Overzicht per maand</h3>
                @if($monthlyStats->isEmpty())
                    <p class="text-sm text-gray-500">Nog geen API-imports gevonden.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 uppercase text-xs">
                                <th class="pb-2 pr-8">Maand</th>
                                <th class="pb-2 pr-8">Dagen geïmporteerd</th>
                                <th class="pb-2 pr-8">Commissies</th>
                                <th class="pb-2">Totaal bedrag</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($monthlyStats as $stat)
                                <tr>
                                    <td class="py-2 pr-8 font-medium text-gray-800">{{ $stat->month }}</td>
                                    <td class="py-2 pr-8 text-gray-600">{{ $stat->days }}</td>
                                    <td class="py-2 pr-8 text-gray-600">{{ $stat->commissions }}</td>
                                    <td class="py-2 text-gray-600">€{{ number_format($stat->total, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

    </div>
</div>

<!-- Verborgen fix-day form -->
<form id="fix-day-form" action="{{ route('tiqets.sync.fix-day') }}" method="POST">
    @csrf
    <input type="hidden" id="fix-day-input" name="date" value="">
</form>

@push('scripts')
<script>
async function startBackfill() {
    const from = document.getElementById('backfill-from').value;
    const to   = document.getElementById('backfill-to').value;

    if (!from || !to) { alert('Vul beide datums in.'); return; }

    const days = getDaysBetween(from, to);
    if (days.length === 0) return;

    const btn             = document.getElementById('backfill-btn');
    const progress        = document.getElementById('backfill-progress');
    const bar             = document.getElementById('backfill-bar');
    const status          = document.getElementById('backfill-status');
    const counter         = document.getElementById('backfill-counter');
    const log             = document.getElementById('backfill-log');
    const unmatchedSection = document.getElementById('unmatched-section');
    const unmatchedDays   = document.getElementById('unmatched-days');

    btn.disabled = true;
    btn.textContent = 'Bezig...';
    progress.classList.remove('hidden');
    unmatchedSection.classList.add('hidden');
    unmatchedDays.innerHTML = '';
    log.innerHTML = '';
    bar.classList.replace('bg-green-500', 'bg-indigo-500');

    let created = 0, skipped = 0, existing = 0, daysWithUnmatched = [];

    for (let i = 0; i < days.length; i++) {
        const date = days[i];
        counter.textContent = `${i + 1} / ${days.length}`;
        status.textContent  = `Verwerken: ${date}`;
        bar.style.width     = `${Math.round((i / days.length) * 100)}%`;

        try {
            const response = await fetch('{{ route('tiqets.sync.day') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ date }),
            });

            const result = await response.json();

            let logLine = `${date}: `;
            if (result.already_exists) {
                logLine += 'al geïmporteerd';
                existing++;
            } else {
                logLine += `${result.created} aangemaakt`;
                if (result.skipped)         logLine += `, ${result.skipped} overgeslagen`;
                if (result.unmatched_count) logLine += `, ${result.unmatched_count} onbekend`;
                created += result.created;
                skipped += result.skipped;
                if (result.unmatched_count) daysWithUnmatched.push(date);
            }

            const line = document.createElement('div');
            line.textContent = logLine;
            if (result.unmatched_count) line.classList.add('text-orange-500');
            log.appendChild(line);
            log.scrollTop = log.scrollHeight;

        } catch (e) {
            const line = document.createElement('div');
            line.textContent = `${date}: fout — ${e.message}`;
            line.classList.add('text-red-500');
            log.appendChild(line);
        }
    }

    bar.style.width = '100%';
    bar.classList.replace('bg-indigo-500', 'bg-green-500');
    status.textContent = `Klaar — ${created} aangemaakt, ${skipped} overgeslagen, ${existing} al bestond.`;
    counter.textContent = '';
    btn.disabled = false;
    btn.textContent = 'Start backfill';

    if (daysWithUnmatched.length > 0) {
        unmatchedSection.classList.remove('hidden');
        daysWithUnmatched.forEach(date => {
            const b = document.createElement('button');
            b.textContent = date;
            b.className = 'bg-orange-100 hover:bg-orange-200 text-orange-800 text-xs font-medium px-3 py-1 rounded border border-orange-300';
            b.onclick = () => fixDay(date);
            unmatchedDays.appendChild(b);
        });
    } else {
        setTimeout(() => window.location.reload(), 1500);
    }
}

function fixDay(date) {
    document.getElementById('fix-day-input').value = date;
    document.getElementById('fix-day-form').submit();
}

function getDaysBetween(from, to) {
    const days = [];
    const current = new Date(from);
    const end     = new Date(to);
    while (current <= end) {
        days.push(current.toISOString().split('T')[0]);
        current.setDate(current.getDate() + 1);
    }
    return days;
}
</script>
@endpush
@endsection
