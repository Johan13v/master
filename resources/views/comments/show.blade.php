@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-semibold mb-6">Review and Edit Comment</h1>


    <div class="flex justify-between">
        <form action="{{ route('comments.approve', $comment->id) }}" method="POST">
            @csrf
            <button type="submit" class="bg-green-500 text-white py-2 px-4 rounded hover:bg-green-600">Approve</button>
        </form>

        <form action="{{ route('comments.reject', $comment->id) }}" method="POST">
            @csrf
            <button type="submit" class="bg-red-500 text-white py-2 px-4 rounded hover:bg-red-600">Reject</button>
        </form>
    </div>


    <form method="POST" action="{{ route('comments.update', $comment->id) }}">
        @csrf
        @method('PUT')

        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <div class="mb-4">
                <p>Website: {{ $comment->website->website_address }}</p>
                <p>Auteur: {{ $comment->author }}</p>
            </div>

            <div class="mb-4">
                <h2 class="font-bold text-lg">Originele Comment</h2>
                <textarea name="content" class="wysiwyg">{{ $comment->content }}</textarea>
            </div>


            <div class="mt-4">
                <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">Comment aanpassen en opnieuw vertalen</button>
            </div>
        </div>
    </form>


    @if($comment->target_post_id != '' && $comment->translated_comment_id == '')
        <form method="POST" action="{{ route('comments.submitTranslation', $comment->id) }}">
            @csrf
            @method('PUT')
            <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                <div class="mb-4 mt-4">
                    <h2 class="font-bold text-lg">Vertaalde Comment</h2>
                    <textarea name="translated_content" class="wysiwyg">{{ $comment->translated_content }}</textarea>
                </div>

                <div class="mt-4">
                    <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">Vertaling doorplaatsen</button>
                </div>
            </div>
        </form>
    @elseif($comment->translated_comment_id != '')
        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2>Vertaling is al doorgeplaatst</h2>
        </div>
    @else
        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2>Geen vertaalde (target) pagina aanwezig</h2>
        </div>
    @endif

    @if($replies != null)
        <h2 class="font-bold text-lg mb-4">Reacties</h2>
        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            @foreach ($replies as $reply)
                <form method="POST" action="{{ route('comments.update', $reply->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="bg-gray-100 p-4 mb-4">
                        <strong>{{ $reply->author }}</strong>
                        <textarea name="content" class="wysiwyg">{{ $reply->content }}</textarea>

                        <div class="mt-4">
                            <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">Save Reply</button>
                        </div>
                    </div>
                </form>

                @if($reply->target_post_id != '' && $reply->translated_comment_id == '')
                    <form method="POST" action="{{ route('comments.submitTranslatedReply', $reply->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                            <div class="mb-4 mt-4">
                                <h2 class="font-bold text-lg">Vertaalde reactie</h2>
                                <textarea name="translated_content" class="wysiwyg">{{ $reply->translated_content }}</textarea>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">Vertaling doorplaatsen</button>
                            </div>
                        </div>
                    </form>
                @elseif($reply->translated_comment_id != '')
                    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                        <h2>Vertaling is al doorgeplaatst</h2>
                    </div>
                @else
                    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                        <h2>Geen vertaalde (target) pagina aanwezig</h2>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    @if($comment->language == 'NL' || $comment->translated_comment_id != '')
        <h2 class="font-bold text-lg mb-4">Nieuwe reactie</h2>
        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <form method="POST" action="{{ route('comments.store') }}">
                @csrf
                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                <textarea name="content" class="wysiwyg" placeholder="Write a reply...">{{ $comment->generated_response}}</textarea>

                <div class="mt-4">
                    <button type="submit" class="bg-green-500 text-white py-2 px-4 rounded hover:bg-green-600">Reageer</button>
                </div>
            </form>
        </div>
    @endif
</div>

<script src="https://cdn.tiny.cloud/1/cudvr6zma9km9xbm4a1eme3d01d5nospqb4v68hoeaa8coh1/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
<script>
   tinymce.init({
       selector: '.wysiwyg',
       menubar: false,
       plugins: 'lists link image table',
       toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist outdent indent | link',
       height: 300
   });
</script>
@endsection


