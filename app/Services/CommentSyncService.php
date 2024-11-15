<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Website;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;


class CommentSyncService
{
    protected $client;

    public function __construct()
    {
    }

    public function fetchCommentsFromWebsite(Website $website, $sinceDate)
    {
        $allComments = [];

        if($website->application_password == '')
            return;

        $statuses = ['approve', 'hold']; // The different statuses to fetch
        foreach ($statuses as $status) {
            $page = 1;

            do {
                // Fetch comments from the WordPress site, including unapproved ones
                $response = Http::withBasicAuth($website->username, $website->application_password)
                    ->get("{$website->website_address}/wp-json/wp/v2/comments", [
                        'per_page' => 100, // Fetch up to 100 comments per page
                        'page' => $page, // Specify the page number for pagination
                        'after' => $sinceDate, // Only fetch comments after a specific date
                        'status' => $status, // Fetch all statuses
                        'orderby' => 'date',
                        'order' => 'asc',
                    ]);


                if ($response->successful()) {
                    $comments = $response->json();

                    $allComments = array_merge($allComments, $comments);
                    $page++;
                } else {
                    break; // Break the loop if the request is unsuccessful
                }
            } while (count($comments) > 0); // Continue fetching until no more comments
        }

        // Now that we have all the comments, process and store them
        foreach ($allComments as $commentData) {
            // Check if the comment contains the specific alphabet 'подробности'
            if ($commentData['status'] != 'approved') {
                if($this->isSpam($commentData)) {
                    // Delete the comment from the original WordPress site
                    Http::withBasicAuth($website->username, $website->application_password)
                        ->delete("{$website->website_address}/wp-json/wp/v2/comments/{$commentData['id']}");

                    continue;
                }
            }

            // Check if the comment already exists in the database using the reference_id
            $existingComment = Comment::where('reference_id', $website->id . '_' . $commentData['id'])->first();
            $existingTranslatedComment = Comment::where('translated_comment_id', $website->id . '_' . $commentData['id'])->first();

            if (!$existingComment && !$existingTranslatedComment) {
                $parent = null;

                if($commentData['parent'] != '' && $commentData['parent'] != '0') {
                    $parent = Comment::where('reference_id', $website->id . '_' . $commentData['parent'])
                        ->first();  // Retrieve only the first result
                    if (is_null($parent)) {
                        continue;
                    }

                    $parent = $parent->id;
                }

                $language = 'NL';
                if($this->getToLanguage($website) == 'Dutch') {
                    $language = 'DE';
                }

                $target_post_id = $this->retrievePostId($website, $commentData['post']);
                    // If the comment doesn't exist, save it to the database
                Comment::create([
                    'website_id' => $website->id,
                    'author' => $commentData['author_name'],
                    'content' => $commentData['content']['rendered'],
                    'reference_id' => $website->id . '_' . $commentData['id'], // Store the WordPress comment ID as reference_id
                    'status' => $commentData['status'] == 'approved' ? 'approved' : 'pending',
                    'post_id' => $commentData['post'],
                    'target_post_id' => $target_post_id,
                    'created_at' => $commentData['date'],
                    'original_language' => $language,
                    'url' => $commentData['link'],
                    'parent_id' => $parent
                ]);
            }
        }
    }

    public function translateComments($website)
    {
        $comments = Comment::where('website_id', $website->id)
        ->whereNull('translated_content')
        ->whereNotNull('target_post_id')
        ->get();


        foreach ($comments as $comment) {

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => "Translate the following comment into " . $this->getToLanguage($website) . " with a focus on semantic relevance. Please keep all HTML tags intact."],
                    ['role' => 'user', 'content' => $comment->content],
                ],
            ]);

            $comment->translated_content = $response['choices'][0]['message']['content'];
            $comment->save();
        }

    }

    public function generateResponse($website)
    {
        $comments = Comment::where('website_id', $website->id)
            ->whereNull('generated_response')
            ->where('created_at', '>', '2024-09-15')
            ->whereNull('parent_id')
            ->get();


        foreach ($comments as $comment) {
            $checkIfParent =
                Comment::where('website_id', $website->id)
                ->where('parent_id', $comment->id)
                ->count();

            if($checkIfParent == 0) {
                $response = OpenAI::chat()->create([
                    'model' => 'gpt-4o',
                    'messages' => [
                        ['role' => 'system', 'content' => "Please generate a reponse in " . $this->getLanguage($website) . " on the following comment. Please include html tags for paragraphs. The response should be in Dutch. Could you be as specific as possible in your answer and not get stuck on usually and probably? I'll do a fact check myself. Please include references to the specific pages where the information you provided can be found."],
                        ['role' => 'user', 'content' => $comment->content],
                    ],
                ]);

                $comment->generated_response = $response['choices'][0]['message']['content'];
                $comment->save();
            }
        }
    }

    public function postCommentToWebsite(Comment $comment, Website $website)
    {
        if ($comment->status == 'approved') {
            Http::withBasicAuth($website->username, $website->application_password)
                ->post("{$website->url}/wp-json/wp/v2/comments", [
                    'content' => $comment->translated_content ?? $comment->content,
                    'author_name' => $comment->author,
                ]);
        }
    }

    public function syncCommentsAcrossWebsites(Comment $comment)
    {
        $websites = Website::all();
        foreach ($websites as $website) {
            if ($website->id !== $comment->website_id) {
                $this->postCommentToWebsite($comment, $website);
            }
        }
    }

    function isSpam($comment)
    {
        // Regular expression for Cyrillic characters
        if (preg_match('/[\p{Cyrillic}]/u', $comment['content']['rendered'])) {
            echo 'IS SPAM' . $comment['content']['rendered'];
            return true;
        } else if(! $this->checkAnchor($comment['content']['rendered'])) {
            echo 'IS SPAM' . $comment['content']['rendered'];
            return true;

        } else if ($comment['author_url'] != '') {
            echo 'IS SPAM' . $comment['author_url'];
            return true;
        } else if (strlen($comment['content']['rendered']) < 25) {
            return true;
        } else if (preg_match('/\b(the|and)\b/', $comment['content']['rendered'])) {
            if($this->askOpenAiIfSpam($comment['content']['rendered'])) {
                return true;
            }
        }

        return false;
    }

    function checkAnchor($comment)
    {
        if (strpos($comment, '<a') !== false) {

            // Create a new DOMDocument object
            $dom = new \DOMDocument();

            // Load the HTML (suppress errors with @ in case of invalid HTML)
            @$dom->loadHTML($comment);

            // Find the anchor tag in the DOM
            $anchor = $dom->getElementsByTagName('a')->item(0);

            if ($anchor) {
                // Get the href attribute and the text inside the anchor
                $href = $anchor->getAttribute('href');
                $text = $anchor->nodeValue;

                // Check if the anchor text is not the same as the href
                if ($text !== $href) {
                    return false;
                }
            }
        }
        return true;
    }

    function askOpenAiIfSpam($content) {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => "I got this comment on my travel blog. Do you think it is spam. Please answer yes or no only."],
                ['role' => 'user', 'content' => $content],
            ],
        ]);

        if (ucfirst($response['choices'][0]['message']['content']) == 'Yes') {
            return true;
        }
    }

    function retrievePostId(Website $website, $postid) {
        $response = Http::withBasicAuth($website->username, $website->application_password)
                ->get("{$website->website_address}/wp-json/wp/v2/posts/" . $postid);
        $response = $response->json();

        $url = '';
        if(isset($response['acf']['de_link'])) {
            $url = $response['acf']['de_link'];
        } else if (isset($response['acf']['nl_link'])) {
            $url = $response['acf']['nl_link'];
        }

        if( $url == '' ) {
            return '';
        }
        // Get the HTML content of the post
        $html = file_get_contents($url);

        // Check if the HTML was retrieved successfully
        if ($html === false) {
            return '';
        }

        // Use a regular expression to find the post ID from the body class
        preg_match('/class=["\'].*\bpostid-(\d+)\b.*["\']/', $html, $matches);

        // Check if a match was found
        if (!empty($matches)) {
            $post_id = $matches[1];
            return $post_id;
        }

        return '';
    }

    function getLanguage($website) {
        switch ($website->website_address) {
            case 'https://www.de-azoren.nl':
            case 'https://www.wegwijsnaarparijs.nl':
            case 'https://www.wegwijsnaar.nl':
                return 'Dutch';

            case 'https://www.azoren-portugal.de':
            case 'https://www.nachparis.de':
            case 'https://www.dasfreitheitsgefuhl.de':
                return 'German';
                break;
        }
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
