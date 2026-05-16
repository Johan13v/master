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
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
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
                    <button type="submit"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
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
                <form action="{{ route('tiqets.sync.run') }}" method="POST" class="flex items-end gap-4 flex-wrap">
                    @csrf
                    <input type="hidden" name="mode" value="range">
                    <div>
                        <label class="block text-sm text-gray-700 mb-1">Van</label>
                        <input type="date" name="from"
                               value="{{ old('from') }}"
                               class="border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-700 mb-1">Tot</label>
                        <input type="date" name="to"
                               value="{{ old('to', now()->subDay()->toDateString()) }}"
                               class="border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    </div>
                    <button type="submit"
                            class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                        Start backfill
                    </button>
                </form>
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

        <!-- Recente API-imports -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-700 mb-4">Recente API-imports</h3>
                @if($recentImports->isEmpty())
                    <p class="text-sm text-gray-500">Nog geen API-imports gevonden.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 uppercase text-xs">
                                <th class="pb-2 pr-6">Titel</th>
                                <th class="pb-2 pr-6">Aangemaakt op</th>
                                <th class="pb-2">Commissies</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($recentImports as $import)
                                <tr>
                                    <td class="py-2 pr-6 text-gray-800">{{ $import->title }}</td>
                                    <td class="py-2 pr-6 text-gray-600">{{ $import->created_at->format('d-m-Y H:i') }}</td>
                                    <td class="py-2 text-gray-600">{{ $import->commissions->count() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
