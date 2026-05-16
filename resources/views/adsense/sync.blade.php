@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Google AdSense Sync') }}
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
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded">
                {{ session('error') }}
            </div>
        @endif

        @if(!$connected)
        {{-- ===== SETUP WIZARD ===== --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-700 mb-2">Eenmalige instelling — Google koppelen</h3>
                <p class="text-sm text-gray-500 mb-6">Volg de stappen hieronder. Je hoeft dit maar één keer te doen.</p>

                <!-- Stap 1 -->
                <div class="flex gap-4 mb-6">
                    <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center font-bold text-sm">1</div>
                    <div>
                        <p class="font-medium text-gray-700 mb-1">Google Cloud project aanmaken</p>
                        <ol class="text-sm text-gray-600 space-y-1 list-decimal list-inside">
                            <li>Ga naar <strong>console.cloud.google.com</strong> en log in met je Google-account</li>
                            <li>Klik bovenaan op het project-dropdown → <strong>Nieuw project</strong></li>
                            <li>Geef het een naam (bijv. <em>Master Commissies</em>) en klik <strong>Maken</strong></li>
                        </ol>
                    </div>
                </div>

                <!-- Stap 2 -->
                <div class="flex gap-4 mb-6">
                    <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center font-bold text-sm">2</div>
                    <div>
                        <p class="font-medium text-gray-700 mb-1">AdSense API inschakelen</p>
                        <ol class="text-sm text-gray-600 space-y-1 list-decimal list-inside">
                            <li>Ga in het menu naar <strong>API's en services → Bibliotheek</strong></li>
                            <li>Zoek op <strong>AdSense Management API</strong></li>
                            <li>Klik op de API en klik dan op <strong>Inschakelen</strong></li>
                        </ol>
                    </div>
                </div>

                <!-- Stap 3 -->
                <div class="flex gap-4 mb-6">
                    <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center font-bold text-sm">3</div>
                    <div>
                        <p class="font-medium text-gray-700 mb-1">OAuth-inloggegevens aanmaken</p>
                        <ol class="text-sm text-gray-600 space-y-1 list-decimal list-inside">
                            <li>Ga naar <strong>API's en services → Inloggegevens</strong></li>
                            <li>Klik op <strong>+ Inloggegevens aanmaken → OAuth-client-ID</strong></li>
                            <li>Kies applicatietype: <strong>Webtoepassing</strong></li>
                            <li>Voeg bij "Geautoriseerde redirect-URI's" het volgende toe:<br>
                                <code class="bg-gray-100 px-2 py-0.5 rounded text-xs select-all">{{ route('adsense.callback') }}</code>
                            </li>
                            <li>Klik <strong>Maken</strong> — je krijgt een <strong>Client-ID</strong> en <strong>Clientgeheim</strong></li>
                        </ol>
                    </div>
                </div>

                <!-- Stap 4 -->
                <div class="flex gap-4 mb-6">
                    <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center font-bold text-sm">4</div>
                    <div>
                        <p class="font-medium text-gray-700 mb-1">Inloggegevens in Forge instellen</p>
                        <ol class="text-sm text-gray-600 space-y-1 list-decimal list-inside">
                            <li>Ga naar <strong>Forge → jouw site → Environment</strong></li>
                            <li>Voeg de volgende regels toe:
                                <pre class="bg-gray-100 rounded p-3 text-xs mt-2 select-all">GOOGLE_CLIENT_ID=jouw-client-id-hier
GOOGLE_CLIENT_SECRET=jouw-clientgeheim-hier
GOOGLE_REDIRECT_URI={{ route('adsense.callback') }}</pre>
                            </li>
                            <li>Sla op en wacht tot Forge de site herstart</li>
                            <li>Herlaad daarna deze pagina</li>
                        </ol>
                    </div>
                </div>

                <!-- Stap 5 -->
                <div class="flex gap-4 mb-2">
                    <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center font-bold text-sm">5</div>
                    <div>
                        <p class="font-medium text-gray-700 mb-2">Koppelen met Google</p>
                        <p class="text-sm text-gray-600 mb-3">Als je de stappen hierboven hebt afgerond, klik dan op de knop hieronder om in te loggen met Google:</p>
                        @if(config('services.google.client_id'))
                            <a href="{{ $authUrl }}"
                               class="inline-block bg-blue-600 hover:bg-blue-800 text-white font-bold py-2 px-6 rounded">
                                Koppelen met Google
                            </a>
                        @else
                            <p class="text-sm text-orange-600 font-medium">Stel eerst stap 4 in — de Google credentials ontbreken nog in Forge.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @else
        {{-- ===== VERBONDEN ===== --}}

        <!-- Status + ontkoppelen -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span class="text-sm font-medium text-gray-700">Verbonden met Google AdSense</span>
                </div>
                <form action="{{ route('adsense.disconnect') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-sm text-red-500 hover:text-red-700">Ontkoppelen</button>
                </form>
            </div>
        </div>

        @if(!$revenueStream)
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded text-sm">
                Geen AdSense revenue stream gevonden. Maak er een aan onder <strong>Inkomsten bronnen</strong> met "adsense" of "google" in de naam.
            </div>
        @else

        <!-- Snelle sync -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-700 mb-4">Snelle sync</h3>
                <p class="text-sm text-gray-500 mb-4">Haal inkomsten op voor één specifieke dag.</p>
                <form action="{{ route('adsense.sync.run') }}" method="POST" class="flex items-end gap-4">
                    @csrf
                    <div>
                        <label class="block text-sm text-gray-700 mb-1">Datum</label>
                        <input type="date" name="date"
                               value="{{ now()->subDay()->toDateString() }}"
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
                    Haal inkomsten op voor een periode. Per dag wordt één importrecord aangemaakt.
                    Al geïmporteerde dagen worden overgeslagen.
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

                <div id="backfill-progress" class="hidden">
                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                        <span id="backfill-status">Bezig...</span>
                        <span id="backfill-counter"></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 mb-3">
                        <div id="backfill-bar" class="bg-indigo-500 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <div id="backfill-log" class="text-xs text-gray-500 space-y-1 max-h-40 overflow-y-auto mb-4"></div>
                    <div id="unmatched-section" class="hidden">
                        <p class="text-sm font-medium text-orange-700 mb-2">Ongematchte orders — klik op een datum om matchers in te stellen:</p>
                        <div id="unmatched-days" class="flex flex-wrap gap-2"></div>
                    </div>
                </div>
            </div>
        </div>

        @endif

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
                                <th class="pb-2 pr-8">Dagen</th>
                                <th class="pb-2 pr-8">Regels</th>
                                <th class="pb-2">Totaal</th>
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

        @endif {{-- end connected --}}

    </div>
</div>

<form id="fix-day-form" action="{{ route('adsense.fix-day') }}" method="POST">
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

    const btn              = document.getElementById('backfill-btn');
    const progress         = document.getElementById('backfill-progress');
    const bar              = document.getElementById('backfill-bar');
    const status           = document.getElementById('backfill-status');
    const counter          = document.getElementById('backfill-counter');
    const log              = document.getElementById('backfill-log');
    const unmatchedSection = document.getElementById('unmatched-section');
    const unmatchedDays    = document.getElementById('unmatched-days');

    btn.disabled = true;
    btn.textContent = 'Bezig...';
    progress.classList.remove('hidden');
    unmatchedSection.classList.add('hidden');
    unmatchedDays.innerHTML = '';
    log.innerHTML = '';
    bar.classList.replace('bg-green-500', 'bg-indigo-500');

    let created = 0, existing = 0, daysWithUnmatched = [];

    for (let i = 0; i < days.length; i++) {
        const date = days[i];
        counter.textContent = `${i + 1} / ${days.length}`;
        status.textContent  = `Verwerken: ${date}`;
        bar.style.width     = `${Math.round((i / days.length) * 100)}%`;

        try {
            const response = await fetch('{{ route('adsense.sync.day') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ date }),
            });

            const result = await response.json();

            if (result.error) {
                appendLog(log, `${date}: fout — ${result.error}`, 'text-red-500');
                continue;
            }

            let logLine = result.already_exists
                ? `${date}: al geïmporteerd`
                : `${date}: ${result.created} aangemaakt${result.unmatched_count ? `, ${result.unmatched_count} onbekend` : ''}`;

            appendLog(log, logLine, result.unmatched_count ? 'text-orange-500' : '');

            if (result.already_exists) existing++;
            else {
                created += result.created;
                if (result.unmatched_count) daysWithUnmatched.push(date);
            }
        } catch (e) {
            appendLog(log, `${date}: fout — ${e.message}`, 'text-red-500');
        }
    }

    bar.style.width = '100%';
    bar.classList.replace('bg-indigo-500', 'bg-green-500');
    status.textContent = `Klaar — ${created} aangemaakt, ${existing} al bestond.`;
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

function appendLog(container, text, cls) {
    const line = document.createElement('div');
    line.textContent = text;
    if (cls) line.classList.add(...cls.split(' '));
    container.appendChild(line);
    container.scrollTop = container.scrollHeight;
}

function getDaysBetween(from, to) {
    const days = [], current = new Date(from), end = new Date(to);
    while (current <= end) {
        days.push(current.toISOString().split('T')[0]);
        current.setDate(current.getDate() + 1);
    }
    return days;
}
</script>
@endpush
@endsection
