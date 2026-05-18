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
                    @if(isset($stats))
                        <div class="mb-6 grid grid-cols-2 md:grid-cols-5 gap-3 text-sm">
                            <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2">
                                <div class="text-gray-400 text-xs uppercase">Totaal</div>
                                <div class="text-gray-800 font-medium">{{ $stats['total_rows'] ?? 0 }}</div>
                            </div>
                            <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2">
                                <div class="text-gray-400 text-xs uppercase">Geimporteerd</div>
                                <div class="text-gray-800 font-medium">{{ $stats['imported_count'] ?? 0 }}</div>
                            </div>
                            <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2">
                                <div class="text-gray-400 text-xs uppercase">Duplicates</div>
                                <div class="text-gray-800 font-medium">{{ $stats['duplicate_count'] ?? 0 }}</div>
                            </div>
                            <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2">
                                <div class="text-gray-400 text-xs uppercase">Revoked</div>
                                <div class="text-gray-800 font-medium">{{ $stats['revoked_count'] ?? 0 }}</div>
                            </div>
                            <div class="rounded border border-amber-200 bg-amber-50 px-3 py-2">
                                <div class="text-amber-600 text-xs uppercase">Nog te matchen</div>
                                <div class="text-amber-800 font-medium">{{ count($unmatchedRows) }}</div>
                            </div>
                        </div>
                    @endif
                    <form action="{{ route('imports.updateMatchers', $revenueStream) }}" method="POST">
                        @csrf
                        <input type="hidden" name="import_id" value="{{ $import->id }}">
                        @foreach ($unmatchedRows as $index => $unmatched)
                            <div class="mb-6">
                                <h4 class="text-md font-medium text-gray-600 mb-2">Row {{ $index + 1 }}</h4>
                                @if(!empty($unmatched['reason']))
                                    <div class="mb-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                                        {{ $unmatched['reason'] }}
                                    </div>
                                @endif
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
                                <div class="mb-2 text-sm text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">
                                    {{ $row['reason'] ?? 'Duplicate' }}
                                    @if(!empty($row['reference_field']) && !empty($row['reference_value']))
                                        · {{ $row['reference_field'] }} = {{ $row['reference_value'] }}
                                    @endif
                                </div>
                                @if(!empty($row['existing']))
                                    <div class="mb-2 bg-gray-50 border border-gray-200 rounded px-4 py-3 text-sm text-gray-700">
                                        <div class="font-medium text-gray-800 mb-2">Botst met bestaande commission</div>
                                        <div>ID: {{ $row['existing']['id'] }}</div>
                                        <div>Import: {{ $row['existing']['import_title'] ?? '—' }} @if(!empty($row['existing']['import_id']))(#{{ $row['existing']['import_id'] }})@endif</div>
                                        <div>Bron: {{ $row['existing']['revenue_stream'] ?? '—' }}</div>
                                        <div>Product: {{ $row['existing']['title'] ?? '—' }}</div>
                                        <div>Datum: {{ $row['existing']['order_date'] ?? '—' }}</div>
                                        <div>Status: {{ $row['existing']['status'] ?? '—' }}</div>
                                        <div>Stad: {{ $row['existing']['city'] ?? '—' }}</div>
                                        <div>Website: {{ $row['existing']['website'] ?? '—' }}</div>
                                        <div>Bedrag: €{{ number_format((float) ($row['existing']['amount'] ?? 0), 2, ',', '.') }}</div>
                                        <div>Reference ID: {{ $row['existing']['reference_id'] ?? '—' }}</div>
                                    </div>
                                @endif
                                <pre class="bg-red-100 p-4 rounded mb-2">{{ json_encode($row['row'] ?? $row, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
