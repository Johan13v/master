@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-semibold mb-6">Comments Overview</h1>

    <!-- Website Filter Form -->
    <form method="GET" action="{{ route('comments.index') }}" class="mb-6">
        <label for="website_id" class="block text-sm font-medium text-gray-700">Filter by Website:</label>
        <select name="website_id" id="website_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            <option value="">All Websites</option>
            @foreach ($websites as $website)
                <option value="{{ $website->id }}" {{ $selectedWebsite == $website->id ? 'selected' : '' }}>
                    {{ $website->title }}
                </option>
            @endforeach
        </select>
        <button type="submit" class="mt-3 px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
            Filter
        </button>
    </form>

    <!-- Comments Table -->
    <table class="min-w-full bg-white">
        <thead>
            <tr>
                <th class="py-2 px-4 border-b border-gray-200">Website</th>
                <th class="py-2 px-4 border-b border-gray-200">Author</th>
                <th class="py-2 px-4 border-b border-gray-200">Original Comment</th>
                <th class="py-2 px-4 border-b border-gray-200">Date</th>
                <th class="py-2 px-4 border-b border-gray-200">Status</th>
                <th class="py-2 px-4 border-b border-gray-200">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($comments as $comment)
                <tr>
                    <td class="py-2 px-4 border-b border-gray-200">{{ $comment->website->title }}</td>
                    <td class="py-2 px-4 border-b border-gray-200">{{ $comment->author }}</td>
                    <td class="py-2 px-4 border-b border-gray-200">{{ Str::limit($comment->content, 50) }}</td>
                    <td class="py-2 px-4 border-b border-gray-200">{{ $comment->created_at->format('Y-m-d') }}</td>
                    <td class="py-2 px-4 border-b border-gray-200">
                        @if ($comment->status == 'pending')
                            <span class="text-yellow-500">Pending</span>
                        @elseif ($comment->status == 'approved')
                            <span class="text-green-500">Approved</span>
                        @else
                            <span class="text-red-500">Rejected</span>
                        @endif
                    </td>
                    <td class="py-2 px-4 border-b border-gray-200">
                        <form action="{{ route('comments.reject', $comment->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="text-red hover:underline">Reject</button>
                        </form>
                        <a href="{{ route('comments.show', $comment->id) }}" class="text-blue-500 hover:underline">Review</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Pagination Links -->
    <div class="mt-6">
        {{ $comments->withQueryString()->links() }}
    </div>
</div>
@endsection
