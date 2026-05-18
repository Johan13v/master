@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Verdeling per stad — {{ $import->title }}
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

        <div class="flex items-center gap-4">
            <a href="{{ route('imports.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Terug naar imports</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                <div class="text-xs uppercase text-gray-400">Totaal rijen</div>
                <div class="text-xl font-semibold text-gray-800">{{ $import->total_rows ?? 0 }}</div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                <div class="text-xs uppercase text-gray-400">Geimporteerd</div>
                <div class="text-xl font-semibold text-gray-800">{{ $import->imported_count ?? $import->commissions()->count() }}</div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                <div class="text-xs uppercase text-gray-400">Duplicates</div>
                <div class="text-xl font-semibold text-gray-800">{{ $import->duplicate_count ?? 0 }}</div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                <div class="text-xs uppercase text-gray-400">Revoked</div>
                <div class="text-xl font-semibold text-gray-800">{{ $import->revoked_count ?? 0 }}</div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                <div class="text-xs uppercase text-gray-400">Unmatched</div>
                <div class="text-xl font-semibold {{ ($import->unmatched_count ?? 0) > 0 ? 'text-amber-600' : 'text-gray-800' }}">{{ $import->unmatched_count ?? 0 }}</div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-700 mb-1">Commissies per stad</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Totaal {{ $import->commissions()->count() }} commissies.
                    Gebruik "Verplaats alles" om alle commissies van een stad te verplaatsen, of verplaats individuele hotels via de uitklaplijst.
                </p>

                @if($byCity->isEmpty())
                    <p class="text-sm text-gray-500">Geen commissies gevonden voor deze import.</p>
                @else
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-gray-400 uppercase text-xs">
                            <th class="pb-2 pr-6">Stad</th>
                            <th class="pb-2 pr-6">Aantal</th>
                            <th class="pb-2 pr-6">Bedrag</th>
                            <th class="pb-2 pr-6">Hotels</th>
                            <th class="pb-2">Verplaats alles naar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($byCity as $cityId => $group)
                        @php $idx = $loop->index; @endphp
                        <tr>
                            <td class="py-3 pr-6 font-medium text-gray-800 align-top">
                                {{ $group['city']?->title ?? '— onbekend —' }}
                            </td>
                            <td class="py-3 pr-6 text-gray-600 align-top">{{ $group['count'] }}</td>
                            <td class="py-3 pr-6 text-gray-600 align-top">€{{ number_format($group['amount'], 2, ',', '.') }}</td>
                            <td class="py-3 pr-6 text-gray-400 text-xs align-top">
                                {{-- First 5 hotels --}}
                                @foreach($group['commissions']->take(5) as $commission)
                                    <div class="flex items-center gap-2 py-0.5">
                                        <span>{{ $commission->title }}</span>
                                        <button type="button"
                                                onclick="toggleSingleForm('form-{{ $commission->id }}')"
                                                class="text-indigo-400 hover:text-indigo-600 text-xs underline shrink-0">
                                            verplaats
                                        </button>
                                        <form id="form-{{ $commission->id }}"
                                              action="{{ route('commissions.reassign', $commission) }}"
                                              method="POST"
                                              class="hidden flex items-center gap-1 mt-1">
                                            @csrf
                                            <select name="city_id"
                                                    class="border-gray-300 rounded text-xs py-0.5 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                <option value="">— kies stad —</option>
                                                @foreach($cities as $city)
                                                    @if($city->id !== $cityId)
                                                        <option value="{{ $city->id }}">{{ $city->title }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                            <button type="submit"
                                                    class="bg-indigo-100 hover:bg-indigo-200 text-indigo-800 text-xs px-2 py-0.5 rounded border border-indigo-300">
                                                OK
                                            </button>
                                        </form>
                                    </div>
                                @endforeach

                                {{-- Remaining hotels, hidden by default --}}
                                @if($group['commissions']->count() > 5)
                                    <div class="hidden" id="extra-{{ $idx }}">
                                        @foreach($group['commissions']->slice(5) as $commission)
                                            <div class="flex items-center gap-2 py-0.5">
                                                <span>{{ $commission->title }}</span>
                                                <button type="button"
                                                        onclick="toggleSingleForm('form-{{ $commission->id }}')"
                                                        class="text-indigo-400 hover:text-indigo-600 text-xs underline shrink-0">
                                                    verplaats
                                                </button>
                                                <form id="form-{{ $commission->id }}"
                                                      action="{{ route('commissions.reassign', $commission) }}"
                                                      method="POST"
                                                      class="hidden flex items-center gap-1 mt-1">
                                                    @csrf
                                                    <select name="city_id"
                                                            class="border-gray-300 rounded text-xs py-0.5 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                        <option value="">— kies stad —</option>
                                                        @foreach($cities as $city)
                                                            @if($city->id !== $cityId)
                                                                <option value="{{ $city->id }}">{{ $city->title }}</option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                    <button type="submit"
                                                            class="bg-indigo-100 hover:bg-indigo-200 text-indigo-800 text-xs px-2 py-0.5 rounded border border-indigo-300">
                                                        OK
                                                    </button>
                                                </form>
                                            </div>
                                        @endforeach
                                    </div>
                                    <button type="button"
                                            onclick="toggleExtra({{ $idx }}, this)"
                                            class="mt-1 text-indigo-500 hover:text-indigo-700 text-xs underline">
                                        +{{ $group['commissions']->count() - 5 }} meer
                                    </button>
                                @endif
                            </td>
                            <td class="py-3 align-top">
                                @if($group['city'])
                                <form action="{{ route('imports.reassign', $import) }}" method="POST" class="flex items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="from_city_id" value="{{ $cityId }}">
                                    <select name="to_city_id"
                                            class="border-gray-300 rounded-md shadow-sm text-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="">— kies stad —</option>
                                        @foreach($cities as $city)
                                            @if($city->id !== $cityId)
                                                <option value="{{ $city->id }}">{{ $city->title }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <button type="submit"
                                            class="bg-indigo-100 hover:bg-indigo-200 text-indigo-800 text-xs font-medium px-3 py-1 rounded border border-indigo-300">
                                        Verplaats alles
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-700 mb-1">Geimporteerde commissies</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Volledige lijst van alle commissies die aan deze import gekoppeld zijn.
                </p>

                @if($commissions->isEmpty())
                    <p class="text-sm text-gray-500">Geen commissies gevonden voor deze import.</p>
                @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-400 uppercase text-xs">
                                <th class="pb-2 pr-4">Datum</th>
                                <th class="pb-2 pr-4">Product</th>
                                <th class="pb-2 pr-4">Stad</th>
                                <th class="pb-2 pr-4">Website</th>
                                <th class="pb-2 pr-4">Status</th>
                                <th class="pb-2 pr-4">Referentie</th>
                                <th class="pb-2 text-right">Bedrag</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($commissions as $commission)
                            <tr>
                                <td class="py-2 pr-4 text-gray-500 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($commission->order_date)->format('d-m-Y H:i') }}
                                </td>
                                <td class="py-2 pr-4 text-gray-700 max-w-md">
                                    <div class="truncate" title="{{ $commission->title }}">{{ $commission->title }}</div>
                                </td>
                                <td class="py-2 pr-4 text-gray-600 whitespace-nowrap">{{ $commission->city?->title ?? '—' }}</td>
                                <td class="py-2 pr-4 text-gray-600 whitespace-nowrap">{{ $commission->website?->title ?? '—' }}</td>
                                <td class="py-2 pr-4 text-gray-500 whitespace-nowrap">{{ $commission->status }}</td>
                                <td class="py-2 pr-4 text-gray-500 font-mono text-xs whitespace-nowrap">{{ $commission->reference_id }}</td>
                                <td class="py-2 text-right text-gray-800 whitespace-nowrap">€{{ number_format($commission->amount, 2, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
function toggleExtra(index, btn) {
    const el = document.getElementById('extra-' + index);
    const hidden = el.classList.toggle('hidden');
    btn.textContent = hidden ? '+' + el.querySelectorAll('.flex').length + ' meer' : 'Minder';
}

function toggleSingleForm(id) {
    const form = document.getElementById(id);
    form.classList.toggle('hidden');
}
</script>
@endpush
@endsection
