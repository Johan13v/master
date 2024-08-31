@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Import Commissions') }}
    </h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-700 mb-4">Import Commissions for {{ $revenueStream->title }}</h3>
                    <form action="{{ route('imports.import', $revenueStream) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <label class="block text-gray-700">Title</label>
                            <input type="text" name="title" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700">CSV File</label>
                            <input type="file" name="csv_file" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700">CSV Type</label>
                            <select name="csv_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>
                                <option value="tiqets">Tiqets</option>
                                <option value="booking">Booking</option>
                                <option value="tradetracker">TradeTracker</option>
                                <option value="bajabikes">BajaBikes</option>
                                <option value="getyourguide">GetYourGuide</option>
                                <option value="googleadsense">Google Adsense</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Import</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
