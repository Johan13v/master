@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-semibold mb-6">Linkbuilding Results</h1>

    @if(session('success'))
        <div class="bg-green-500 text-white p-4 mb-4">
            {{ session('success') }}
        </div>
    @endif

    <table class="min-w-full bg-white">
        <thead>
            <tr>
                <th class="py-2 px-4 border-b border-gray-200">Title</th>
                <th class="py-2 px-4 border-b border-gray-200">URL</th>
                <th class="py-2 px-4 border-b border-gray-200">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($results as $result)
                <tr>
                    <td class="py-2 px-4 border-b border-gray-200">{{ $result['title'] }}</td>
                    <td class="py-2 px-4 border-b border-gray-200">
                        <a href="{{ $result['link'] }}" target="_blank" class="text-blue-500 hover:underline">{{ $result['link'] }}</a>
                    </td>
                    <td class="py-2 px-4 border-b border-gray-200">
                        <!-- Form to add the URL to the blacklist -->
                        <form method="POST" action="{{ route('blacklist.add') }}">
                            @csrf
                            <input type="hidden" name="url" value="{{ $result['link'] }}">
                            <button type="submit" class="bg-red-500 text-white py-1 px-4 rounded hover:bg-red-600">Blacklist</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
