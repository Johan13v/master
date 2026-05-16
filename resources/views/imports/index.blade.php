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

        @foreach($imports as $streamName => $streamImports)
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-700">{{ $streamName }}</h3>
                    <span class="text-sm text-gray-400">{{ $streamImports->count() }} import(s) &middot; {{ $streamImports->sum(fn($i) => $i->commissions->count()) }} commissies</span>
                </div>

                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 uppercase text-xs">
                            <th class="pb-2 pr-6">Titel</th>
                            <th class="pb-2 pr-6">Datum</th>
                            <th class="pb-2 pr-6">Commissies</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($streamImports as $import)
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
                            <td class="py-2 text-right">
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
            </div>
        </div>
        @endforeach

        @if($imports->isEmpty())
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-sm text-gray-500">Nog geen imports gevonden.</div>
            </div>
        @endif

    </div>
</div>
@endsection
