@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Edit Website') }}
    </h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="POST" action="{{ route('websites.update', $website) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-4">
                            <label class="block text-gray-700">Title</label>
                            <input type="text" name="title" value="{{ $website->title }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700">Website Address</label>
                            <input type="text" name="website_address" value="{{ $website->website_address }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700">Matchers</label>
                            <div id="matchers-container" class="flex flex-wrap border border-gray-300 rounded-md p-2">
                                @foreach($website->matchers as $matcher)
                                    <span class="bg-gray-200 rounded-full px-3 py-1 mr-2 mb-2 inline-block">
                                        {{ $matcher }}
                                        <button type="button" class="ml-2 text-red-500 hover:text-red-700" onclick="removeMatcher(this)">x</button>
                                    </span>
                                @endforeach
                                <input type="text" id="matcher-input" class="flex-grow focus:outline-none" placeholder="Add matcher">
                            </div>
                            <input type="hidden" name="matchers" id="matchers" value="{{ implode(',', $website->matchers) }}">
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700">Wordpress APP username</label>
                            <input type="text" name="username" value="{{ $website->username }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700">Wordpress APP password</label>
                            <input type="text" name="application_password" value="{{ $website->application_password }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                        <div>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const matchersContainer = document.getElementById('matchers-container');
            const matcherInput = document.getElementById('matcher-input');
            const matchersField = document.getElementById('matchers');

            matcherInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addMatcher(matcherInput.value);
                    matcherInput.value = '';
                }
            });

            function addMatcher(matcher) {
                if (matcher.trim() === '') return;

                const span = document.createElement('span');
                span.className = 'bg-gray-200 rounded-full px-3 py-1 mr-2 mb-2 inline-block';
                span.innerHTML = `${matcher} <button type="button" class="ml-2 text-red-500 hover:text-red-700" onclick="removeMatcher(this)">x</button>`;
                matchersContainer.insertBefore(span, matcherInput);

                updateMatchersField();
            }

            window.removeMatcher = function(button) {
                button.parentElement.remove();
                updateMatchersField();
            };

            function updateMatchersField() {
                const matchers = [];
                matchersContainer.querySelectorAll('span').forEach(span => {
                    matchers.push(span.firstChild.textContent.trim());
                });
                matchersField.value = matchers.join(',');
            }
        });
    </script>
@endsection
