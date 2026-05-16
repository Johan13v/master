@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Tiqets – Ongematchte orders') }}
    </h2>
@endsection

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded">
            {{ $summary }} — onderstaande orders konden niet automatisch worden gekoppeld.
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-700 mb-1">Ongematchte orders</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Koppel elke order handmatig aan een stad en/of website. Voeg optioneel een nieuwe matcher toe
                    zodat toekomstige orders automatisch worden herkend.
                </p>

                <form action="{{ route('imports.updateMatchers', $revenueStream) }}" method="POST">
                    <input type="hidden" name="return_to" value="{{ route('tiqets.sync') }}">
                    @csrf
                    <input type="hidden" name="import_id" value="{{ $import->id }}">

                    @foreach ($unmatchedRows as $index => $unmatched)
                        @php $c = $unmatched['commission']; @endphp
                        <div class="mb-8 p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <h4 class="font-medium text-gray-700 mb-3">
                                Order {{ $index + 1 }}
                                <span class="text-sm font-normal text-gray-500 ml-2">
                                    ref: {{ $c['referenceId'] }} &middot; {{ $c['orderDate'] }}
                                </span>
                            </h4>

                            <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                                <div>
                                    <span class="text-gray-500">Product:</span>
                                    <span class="ml-1 text-gray-800">{{ $c['product'] }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Sitebrand:</span>
                                    <span class="ml-1 text-gray-800">{{ $c['sitebrand'] }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Taal:</span>
                                    <span class="ml-1 text-gray-800">{{ $c['customerLanguage'] }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Commissie:</span>
                                    <span class="ml-1 text-gray-800">€{{ $c['amount'] }}</span>
                                </div>
                            </div>

                            @if ($unmatched['unmatchedCity'])
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Stad</label>
                                    <select name="city_matches[{{ $index }}]"
                                            class="block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm">
                                        <option value="">— selecteer stad —</option>
                                        @foreach($cities as $city)
                                            <option value="{{ $city->id }}" {{ $city->id == 15 ? 'selected' : '' }}>
                                                {{ $city->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="city_new_matchers[{{ $index }}]"
                                           class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm"
                                           placeholder="Nieuwe matcher voor stad (bijv. '{{ $c['product'] }}')">
                                </div>
                            @endif

                            @if ($unmatched['unmatchedWebsite'])
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                                    <select name="website_matches[{{ $index }}]"
                                            class="block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm">
                                        <option value="">— selecteer website —</option>
                                        @foreach($websites as $website)
                                            <option value="{{ $website->id }}" {{ $website->id == 8 ? 'selected' : '' }}>
                                                {{ $website->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="website_new_matchers[{{ $index }}]"
                                           class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm"
                                           placeholder="Nieuwe matcher voor website (bijv. '{{ $c['sitebrand'] }}')">
                                </div>
                            @endif

                            <input type="hidden" name="rows[{{ $index }}]"
                                   value="{{ base64_encode(json_encode($c)) }}">
                        </div>
                    @endforeach

                    <div class="flex gap-4">
                        <button type="submit"
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Matchers opslaan &amp; importeren
                        </button>
                        <a href="{{ route('tiqets.sync') }}"
                           class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded">
                            Overslaan
                        </a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection
