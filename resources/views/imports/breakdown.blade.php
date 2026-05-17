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

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-700 mb-1">Commissies per stad</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Totaal {{ $import->commissions()->count() }} commissies.
                    Klik op "Herindelen" om commissies van een verkeerde stad naar de juiste te verplaatsen.
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
                            <th class="pb-2 pr-6">Voorbeelden</th>
                            <th class="pb-2">Herindelen naar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($byCity as $cityId => $group)
                        <tr>
                            <td class="py-3 pr-6 font-medium text-gray-800 align-top">
                                {{ $group['city']?->title ?? '— onbekend —' }}
                            </td>
                            <td class="py-3 pr-6 text-gray-600 align-top">{{ $group['count'] }}</td>
                            <td class="py-3 pr-6 text-gray-600 align-top">€{{ number_format($group['amount'], 2, ',', '.') }}</td>
                            <td class="py-3 pr-6 text-gray-400 text-xs align-top">
                                @foreach($group['samples']->take(5) as $sample)
                                    <div>{{ $sample }}</div>
                                @endforeach
                                @if($group['samples']->count() > 5)
                                    <div class="hidden" data-extra-{{ $loop->parent->index }}>
                                        @foreach($group['samples']->slice(5) as $sample)
                                            <div>{{ $sample }}</div>
                                        @endforeach
                                    </div>
                                    <button type="button"
                                            onclick="toggleExtra({{ $loop->parent->index }}, this)"
                                            class="mt-1 text-indigo-500 hover:text-indigo-700 text-xs underline">
                                        +{{ $group['samples']->count() - 5 }} meer
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
                                        Verplaats
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

    </div>
</div>

@push('scripts')
<script>
function toggleExtra(index, btn) {
    const el = document.querySelector('[data-extra-' + index + ']');
    const hidden = el.classList.toggle('hidden');
    btn.textContent = hidden ? '+' + el.children.length + ' meer' : 'Minder';
}
</script>
@endpush
@endsection
