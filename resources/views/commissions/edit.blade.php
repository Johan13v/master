@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Edit Commission') }}
    </h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="POST" action="{{ route('commissions.update', $commission) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-4">
                            <label class="block text-gray-700">Title</label>
                            <input type="text" name="title" value="{{ $commission->title }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700">Amount</label>
                            <input type="text" name="amount" value="{{ $commission->amount }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700">City</label>
                            <select name="city_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                @foreach($cities as $city)
                                    <option value="{{ $city->id }}" {{ $commission->city_id == $city->id ? 'selected' : '' }}>{{ $city->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700">Revenue Stream</label>
                            <select name="revenue_stream_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                @foreach($revenueStreams as $revenueStream)
                                    <option value="{{ $revenueStream->id }}" {{ $commission->revenue_stream_id == $revenueStream->id ? 'selected' : '' }}>{{ $revenueStream->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700">Website</label>
                            <select name="website_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                @foreach($websites as $website)
                                    <option value="{{ $website->id }}" {{ $commission->website_id == $website->id ? 'selected' : '' }}>{{ $website->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
