@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Imports') }}
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

        @forelse($imports as $streamName => $monthGroups)
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-700">{{ $streamName }}</h3>
                    <span class="text-sm text-gray-400">
                        {{ $monthGroups->flatten()->count() }} import(s)
                        &middot;
                        {{ $monthGroups->flatten()->sum(fn($i) => $i->commissions->count()) }} commissies
                    </span>
                </div>

                <div class="space-y-6">
                    @foreach($monthGroups as $month => $monthImports)
                    @php
                        $label = \Carbon\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y');
                        $importIds = $monthImports->pluck('id')->toArray();
                        $isApiImport = (bool) preg_match('/\d{4}-\d{2}-\d{2}/', $monthImports->first()->title);
                        $totalCommissions = $monthImports->sum(fn($i) => $i->commissions->count());
                        $totalAmount = $monthImports->sum(fn($i) => $i->commissions->sum('amount'));
                        $isBooking = stripos($streamName, 'booking') !== false;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-semibold text-gray-600 uppercase tracking-wide">{{ $label }}</span>
                            <form action="{{ route('imports.destroyByMonth') }}" method="POST"
                                  onsubmit="return confirm('Alle imports van {{ $label }} en bijbehorende commissies verwijderen?')">
                                @csrf
                                @method('DELETE')
                                @foreach($importIds as $id)
                                    <input type="hidden" name="import_ids[]" value="{{ $id }}">
                                @endforeach
                                <button type="submit" class="text-red-400 hover:text-red-600 text-xs">
                                    Verwijder maand
                                </button>
                            </form>
                        </div>

                        @if($isApiImport)
                            {{-- API imports: show only month summary --}}
                            <div class="text-sm text-gray-600 bg-gray-50 rounded px-4 py-3 flex items-center gap-8">
                                <span>{{ $monthImports->count() }} dag(en) geïmporteerd</span>
                                <span>{{ $totalCommissions }} commissies</span>
                                <span>€{{ number_format($totalAmount, 2, ',', '.') }}</span>
                            </div>
                        @else
                            {{-- Manual imports: show individual rows --}}
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead>
                                    <tr class="text-left text-gray-400 uppercase text-xs">
                                        <th class="pb-2 pr-6">Titel</th>
                                        <th class="pb-2 pr-6">Datum</th>
                                        <th class="pb-2 pr-6">Commissies</th>
                                        <th class="pb-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($monthImports as $import)
                                    <tr>
                                        <td class="py-2 pr-6 text-gray-800">{{ $import->title }}</td>
                                        <td class="py-2 pr-6 text-gray-500">{{ $import->created_at->format('d-m-Y') }}</td>
                                        <td class="py-2 pr-6 text-gray-500">
                                            @if($import->commissions->count() === 0)
                                                <span class="text-orange-500">0 — onbekend</span>
                                            @else
                                                {{ $import->commissions->count() }}
                                            @endif
                                        </td>
                                        <td class="py-2 text-right flex items-center justify-end gap-3">
                                            @if($isBooking)
                                                <a href="{{ route('imports.breakdown', $import) }}"
                                                   class="text-indigo-500 hover:text-indigo-700 text-xs">
                                                    Bekijk verdeling
                                                </a>
                                            @endif
                                            <form action="{{ route('imports.destroy', $import) }}" method="POST"
                                                  onsubmit="return confirm('Import en bijbehorende commissies verwijderen?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-400 hover:text-red-600 text-xs">
                                                    Verwijderen
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @empty
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-sm text-gray-500">Nog geen imports gevonden.</div>
            </div>
        @endforelse

    </div>
</div>
@endsection
