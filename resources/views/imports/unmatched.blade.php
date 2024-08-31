@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Unmatched Rows') }}
    </h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-700 mb-4">Unmatched Rows</h3>
                    <form action="{{ route('imports.updateMatchers', $revenueStream) }}" method="POST">
                        @csrf
                        <input type="hidden" name="import_id" value="{{ $import->id }}">
                        @foreach ($unmatchedRows as $index => $unmatched)
                            <div class="mb-6">
                                <h4 class="text-md font-medium text-gray-600 mb-2">Row {{ $index + 1 }}</h4>
                                <pre class="bg-gray-100 p-4 rounded mb-2">{{ json_encode($unmatched['commission'], JSON_PRETTY_PRINT) }}</pre>
                                @if ($unmatched['unmatchedCity'])
                                    <div class="mb-2">
                                        <label class="block text-gray-700">Select City</label>
                                        <select name="city_matches[{{ $index }}]" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <option value="">Select City</option>
                                            @foreach($cities as $city)
                                                <option value="{{ $city->id }}" {{ $city->id == 15 ? 'selected' : '' }}>{{ $city->title }}</option>
                                            @endforeach
                                        </select>
                                        <label class="block text-gray-700 mt-2">Enter New Matcher for Selected City</label>
                                        <input type="text" name="city_new_matchers[{{ $index }}]" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="Enter new matcher">
                                    </div>
                                @endif
                                @if ($unmatched['unmatchedWebsite'])
                                    <div class="mb-2">
                                        <label class="block text-gray-700">Select Website</label>
                                        <select name="website_matches[{{ $index }}]" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <option value="">Select Website</option>
                                            @foreach($websites as $website)
                                                <option value="{{ $website->id }}" {{ $website->id == 8 ? 'selected' : '' }}>{{ $website->title }}</option>
                                            @endforeach
                                        </select>
                                        <label class="block text-gray-700 mt-2">Enter New Matcher for Selected Website</label>
                                        <input type="text" name="website_new_matchers[{{ $index }}]" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="Enter new matcher">
                                    </div>
                                @endif
                                <input type="hidden" name="rows[{{ $index }}]" value="{{ base64_encode(json_encode($unmatched['commission'])) }}">
                            </div>
                        @endforeach
                        <div>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Update Matchers</button>
                        </div>
                    </form>

                    @if (count($duplicateRows) > 0)
                        <h3 class="text-lg font-medium text-red-700 mb-4 mt-6">Duplicate Rows</h3>
                        @foreach ($duplicateRows as $index => $row)
                            <div class="mb-6">
                                <h4 class="text-md font-medium text-red-600 mb-2">Duplicate Row {{ $index + 1 }}</h4>
                                <pre class="bg-red-100 p-4 rounded mb-2">{{ json_encode($row, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
