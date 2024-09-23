<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Website;
use App\Services\CommentSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;

class CommentController extends Controller
{
    protected $service;

    public function __construct(CommentSyncService $service)
    {
        $this->service = $service;
    }

    public function fetchComments()
    {

        $websites = Website::all();
        foreach ($websites as $website) {
            if($website->website_address != 'https://www.wegwijsnaarparijs.nl' && $website->website_address != 'https://www.nachparis.de') {
                $sinceDate = '2020-01-01T00:00:00'; // Example: Fetch comments after January 1, 2024
            } else {
                $sinceDate = '2024-01-01T00:00:00'; // Example: Fetch comments after January 1, 2024
            }

            if($website->application_password == '' || $website->application_password == null){
                continue;
            }

            $comment = Comment::where('website_id', $website->id)  // Filter by website_id
            ->orderBy('created_at', 'desc')  // Order by creation date ascending
            ->first();  // Retrieve only the first result

            if($comment != null) {
                $sinceDate = $comment->created_at;
            }

            $this->service->fetchCommentsFromWebsite($website, $sinceDate);
        }
        return response()->json(['message' => 'Comments fetched successfully']);
    }

    public function translateAndSync(Comment $comment, Request $request)
    {
        $this->service->translateComment($comment, $request->target_language);
        $this->service->syncCommentsAcrossWebsites($comment);
        return response()->json(['message' => 'Comments translated and synced']);
    }

    public function translate()
    {
        $websites = Website::all();
        foreach ($websites as $website) {
            $this->service->translateComments($website);
        }

        return response()->json(['message' => 'Comments translated']);
    }

    public function generateResponse()
    {
        $websites = Website::all();
        foreach ($websites as $website) {
            $this->service->generateResponse($website);
        }

        return response()->json(['message' => 'Responses generated']);
    }


    public function index(Request $request)
    {
        // Fetch all websites for the filter dropdown
        $websites = Website::all();

        // Get the selected website from the request
        $selectedWebsite = $request->input('website_id');

        // Build the query
        $query = Comment::query();

        // If a website is selected, filter by website_id
        if ($selectedWebsite) {
            $query->where('website_id', $selectedWebsite);
        }

        // Paginate the results (10 comments per page)
        $comments = $query->whereNull('parent_id')->with('website')->orderBy('created_at', 'desc')->paginate(25);

        // Pass the comments, websites, and selected website to the view
        return view('comments.index', compact('comments', 'websites', 'selectedWebsite'));
    }


    public function show(Comment $comment)
    {

        // Fetch the parent comment and its replies
        $replies = $comment->replies;

        return view('comments.show', compact('comment', 'replies'));
    }

    public function approve(Comment $comment)
    {
        $comment->status = 'approved';
        $comment->save();

        $comment_id = str_replace($comment->website->id . '_', '', $comment->reference_id);

        Http::withBasicAuth($comment->website->username, $comment->website->application_password)
        ->put("{$comment->website->website_address}/wp-json/wp/v2/comments/{$comment_id}", [
            'status' => 'approved'
        ]);

        return redirect()->route('comments.index')->with('success', 'Comment approved and synced.');
    }

    public function reject(Comment $comment)
    {
        $comment->status = 'rejected';
        $comment->delete();

        $comment_id = str_replace($comment->website->id . '_', '', $comment->reference_id);

        Http::withBasicAuth($comment->website->username, $comment->website->application_password)
        ->put("{$comment->website->website_address}/wp-json/wp/v2/comments/{$comment_id}", [
            'status' => 'spam'
        ]);

        return redirect()->route('comments.index')->with('success', 'Comment rejected.');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'content' => 'required|string',
            'parent_id' => 'required|exists:comments,id',
        ]);

        $parent = Comment::find($validatedData['parent_id']);
        $website = $parent->website;
        $parent_comment_id = str_replace($parent->website->id . '_', '', $parent->reference_id);

        $response = Http::withBasicAuth($website->username, $website->application_password)
            ->post("{$website->website_address}/wp-json/wp/v2/comments", [
                'content' => $validatedData['content'],
                'author_name' => 'eline',
                'author_email' => 'eline@wegwijsnaarparijs.nl',
                'post' => $parent->post_id, // The ID of the post to which the comment is being added
                'status' => 'approved',
                'parent' => $parent_comment_id
            ]);

        $comment_id = $response->json();
        $comment_id = $comment_id['id'];

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4-turbo',
            'messages' => [
                ['role' => 'system', 'content' => "Translate the following comment into " . $this->getToLanguage($website) . " with a focus on semantic relevance. Please keep all HTML tags intact."],
                ['role' => 'user', 'content' => $validatedData['content']],
            ],

        ]);

        $translated_content = $response['choices'][0]['message']['content'];

        Comment::create([
            'content' => $validatedData['content'],
            'parent_id' => $validatedData['parent_id'],
            'website_id' => $parent->website_id,
            'translated_content' => $translated_content,
            'reference_id' => $parent->website_id . '_' . $comment_id,
            'post_id' => $parent->post_id,
            'target_post_id' => $parent->target_post_id,
            'status' => 'approved',
            'author' => 'eline', // Example: use authenticated user as author
        ]);

        return back()->with('success', 'Reply added successfully!');
    }

    public function update(Request $request, Comment $comment)
    {
        // Validate the request
        $validatedData = $request->validate([
            'content' => 'required|string',
            'translated_content' => 'nullable|string',
        ]);

        $comment_id = str_replace($comment->website->id . '_', '', $comment->reference_id);

        // dd($validatedData['content']);
        Http::withBasicAuth($comment->website->username, $comment->website->application_password)
        ->put("{$comment->website->website_address}/wp-json/wp/v2/comments/{$comment_id}", [
            'content' => $this->fixCommentLinebreaks($validatedData['content']),
            'author_name' => $comment->author,
        ]);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4-turbo',
            'messages' => [
                ['role' => 'system', 'content' => "Translate the following comment into " . $this->getToLanguage($comment->website) . " with a focus on semantic relevance. Please keep all HTML tags intact."],
                ['role' => 'user', 'content' => $validatedData['content']],
            ],
        ]);

        $validatedData['translated_content'] = $response['choices'][0]['message']['content'];


        // Update the comment
        $comment->update($validatedData);

        return back()->with('success', 'Comment updated successfully.');
    }



    public function submitTranslation(Request $request, Comment $comment)
    {
        // Validate the request
        $validatedData = $request->validate([
            'translated_content' => 'nullable|string',
        ]);

        // Remove lastname
        $author = explode(' ' , $comment->author);
        $author = $author[0];

        $website = $this->getToWebsite($comment->website->website_address);

        $response = Http::withBasicAuth($website->username, $website->application_password)
            ->post("{$website->website_address}/wp-json/wp/v2/comments", [
                'content' => $validatedData['translated_content'],
                'author_name' => $author,
                'author_email' => 'no-reply@bestaatniet.nl',
                'post' => $comment->target_post_id, // The ID of the post to which the comment is being added
                'status' => 'approved'
            ]);

        $translated_comment_id = $response->json();
        $validatedData['translated_comment_id'] = $website->id . '_' . $translated_comment_id['id'];

        // Update the comment
        $comment->update($validatedData);

        return back()->with('success', 'Comment synced successfully.');
    }

    function submitTranslatedReply(Request $request, Comment $comment) {
        // Validate the request
        $validatedData = $request->validate([
            'translated_content' => 'nullable|string',
        ]);

        $parent = Comment::find($comment->parent_id);
        $website = $this->getToWebsite($comment->website->website_address);

        // Remove lastname
        $author = explode(' ', $comment->author);
        $author = $author[0];

        $response = Http::withBasicAuth($website->username, $website->application_password)
            ->post("{$website->website_address}/wp-json/wp/v2/comments", [
                'content' => $validatedData['translated_content'],
                'author_name' => $author,
                'author_email' => 'no-reply@bestaatniet.nl',
                'post' => $comment->target_post_id, // The ID of the post to which the comment is being added
                'status' => 'approved',

            ]);

        $translated_comment_id = $response->json();
        $validatedData['translated_comment_id'] = $translated_comment_id['id'];

        // Update the comment
        $comment->update($validatedData);

        return back()->with('success', 'Comment synced successfully.');
    }

    function fixCommentLinebreaks($comment) {
        $comment = str_replace('</p>', '

        ', $comment);

        $comment = str_replace('<br>', '
        ', $comment);
        $comment = str_replace('<br/>', '
        ', $comment);
        $comment = str_replace('<br />', '
        ', $comment);

        return $comment;
    }

    function getToWebsite($url) {
        $website = '';
        switch ($url) {
            case 'https://www.de-azoren.nl':
                $website = Website::where('website_address', 'https://www.azoren-portugal.de')
                    ->get()
                    ->first();
                break;
            case 'https://www.azoren-portugal.de':
                $website = Website::where('website_address', 'https://www.de-azoren.nl')
                    ->get();
                break;
            case 'https://www.wegwijsnaarparijs.nl':
                $website = Website::where('website_address', 'https://www.nachparis.de')
                    ->get();
                break;
            case 'https://www.nachparis.de':
                $website = Website::where('website_address', 'https://www.wegwijsnaarparijs.nl')
                    ->get();
                break;
            case 'https://www.wegwijsnaar.nl':
                $website = Website::where('website_address', 'https://www.dasfreitheitsgefuhl.de')
                    ->get();
                break;
            case 'https://www.dasfreitheitsgefuhl.de':
                $website = Website::where('website_address', 'https://www.wegwijsnaar.nl')
                    ->get();
                break;
        }

        return $website;
    }

    function getToLanguage($website)
    {
        switch ($website->website_address) {
            case 'https://www.de-azoren.nl':
            case 'https://www.wegwijsnaarparijs.nl':
            case 'https://www.wegwijsnaar.nl':
                return 'German';

            case 'https://www.azoren-portugal.de':
            case 'https://www.nachparis.de':
            case 'https://www.dasfreitheitsgefuhl.de':
                return 'Dutch';
                break;
        }
    }


}
