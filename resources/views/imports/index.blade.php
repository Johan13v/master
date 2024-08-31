@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Imports') }}
    </h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-700 mb-4">Imports</h3>
                    <div class="space-y-4">
                        @foreach($imports as $import)
                            <div class="p-4 bg-gray-100 rounded-md shadow">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h4 class="text-md font-medium text-gray-700">{{ $import->title }}</h4>
                                        <p class="text-sm text-gray-600">Date: {{ $import->created_at->format('Y-m-d') }}</p>
                                        <p class="text-sm text-gray-600">Revenue Stream: {{ $import->revenueStream->title }}</p>
                                    </div>
                                    <form action="{{ route('imports.destroy', $import) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
