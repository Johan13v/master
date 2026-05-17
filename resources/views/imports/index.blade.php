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

        @forelse($imports as $streamName => $groups)
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-700">{{ $streamName }}</h3>
                    <span class="text-sm text-gray-400">
                        {{ $groups->flatten()->count() }} import(s)
                        &middot;
                        {{ $groups->flatten()->sum(fn($i) => $i->commissions->count()) }} commissies
                    </span>
                </div>

                <div class="space-y-4">
                    @foreach($groups as $groupKey => $groupContent)
                    @php $isApiGroup = (bool) preg_match('/^\d{4}$/', $groupKey); @endphp

                    @if($isApiGroup)
                        {{-- API stream: year → months --}}
                        <div>
                            <div class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-2">{{ $groupKey }}</div>
                            <div class="space-y-2 pl-2">
                                @foreach($groupContent as $month => $monthImports)
                                @php
                                    $label = \Carbon\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y');
                                    $importIds = $monthImports->pluck('id')->toArray();
                                    $totalCommissions = $monthImports->sum(fn($i) => $i->commissions->count());
                                    $totalAmount = $monthImports->sum(fn($i) => $i->commissions->sum('amount'));
                                @endphp
                                <div class="flex items-center justify-between bg-gray-50 rounded px-4 py-2">
                                    <div class="flex items-center gap-6 text-sm text-gray-600">
                                        <span class="font-medium text-gray-700 w-32">{{ $label }}</span>
                                        <span>{{ $monthImports->count() }} dag(en)</span>
                                        <span>{{ $totalCommissions }} commissies</span>
                                        <span>€{{ number_format($totalAmount, 2, ',', '.') }}</span>
                                    </div>
                                    <form action="{{ route('imports.destroyByMonth') }}" method="POST"
                                          onsubmit="return confirm('Alle imports van {{ $label }} verwijderen?')">
                                        @csrf
                                        @method('DELETE')
                                        @foreach($importIds as $id)
                                            <input type="hidden" name="import_ids[]" value="{{ $id }}">
                                        @endforeach
                                        <button type="submit" class="text-red-400 hover:text-red-600 text-xs">
                                            Verwijder
                                        </button>
                                    </form>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        {{-- Manual import: individual row --}}
                        @php
                            $import = $groupContent->first();
                            $isBooking = stripos($streamName, 'booking') !== false;
                        @endphp
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                            <div class="flex items-center gap-6">
                                <span class="text-gray-800 text-sm">{{ $import->title }}</span>
                                <span class="text-gray-400 text-xs">{{ $import->created_at->format('d-m-Y') }}</span>
                                <span class="text-sm">
                                    @if($import->commissions->count() === 0)
                                        <span class="text-orange-500">0 — onbekend</span>
                                    @else
                                        <span class="text-gray-500">{{ $import->commissions->count() }} commissies</span>
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center gap-4">
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
                            </div>
                        </div>
                    @endif
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
