@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Websites') }}
    </h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <a href="{{ route('websites.create') }}" class="bg-blue-500 hover:bg-blue-700 font-bold py-2 px-4 rounded">Add Website</a>
                    <ul class="mt-6">
                        @foreach($websites as $website)
                            <li class="py-4 border-b flex justify-between items-center">
                                <div>
                                    <span class="font-medium">{{ $website->title }}</span> -
                                    <span class="text-gray-500">{{ $website->website_address }}</span>
                                </div>
                                <div>
                                    <a href="{{ route('websites.edit', $website) }}" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded mr-2">Edit</a>
                                    <form action="{{ route('websites.destroy', $website) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">Delete</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
