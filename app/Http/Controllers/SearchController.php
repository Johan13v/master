<?php

namespace App\Http\Controllers;

use App\Models\Blacklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class SearchController extends Controller
{
    public function searchGoogle(Request $request)
    {
        $keywords = $request->input('keywords');
        $language = $request->input('language');
        $apiKey = 'YOUR_GOOGLE_CUSTOM_SEARCH_API_KEY';
        $cx = 'YOUR_CUSTOM_SEARCH_ENGINE_ID';

        // Demo/mock response - simuleer de top 100 Google-resultaten
        $demoResults = [
            [
                'title' => 'Fietstoer door de Gotische wijk van Barcelona',
                'link' => 'https://example1.com/fietstoer-gotische-wijk',
                'snippet' => 'Ontdek de Gotische wijk van Barcelona op de fiets met deze spannende fietstocht.',
            ],
            [
                'title' => 'De beste fietsroutes door Barcelona',
                'link' => 'https://example2.com/beste-fietsroutes-barcelona',
                'snippet' => 'Fiets door de meest iconische plekken van Barcelona, inclusief Parc de la Ciutadella en de stranden.',
            ],
            [
                'title' => 'Fietstocht langs de architectuur van Gaudí',
                'link' => 'https://example3.com/fietstocht-gaudi-barcelona',
                'snippet' => 'Bezoek de meesterwerken van Antoni Gaudí zoals de Sagrada Família en Casa Batlló op de fiets.',
            ],
            [
                'title' => 'Barcelona Beach Bike Tour',
                'link' => 'https://example4.com/beach-bike-tour-barcelona',
                'snippet' => 'Geniet van een relaxte fietstocht langs de prachtige stranden van Barcelona.',
            ],
            [
                'title' => 'Ontdek de groene parken van Barcelona op de fiets',
                'link' => 'https://example5.com/parken-fiets-barcelona',
                'snippet' => 'Een fietstocht door de vele groene parken die Barcelona te bieden heeft.',
            ],
            [
                'title' => 'Familie fietstocht door het hart van Barcelona',
                'link' => 'https://example6.com/familie-fietstocht-barcelona',
                'snippet' => 'Gezinsvriendelijke fietstocht door de historische wijken van Barcelona.',
            ],
            [
                'title' => 'Barcelona Bike Tour: Highlights van de stad',
                'link' => 'https://example7.com/barcelona-highlights-bike-tour',
                'snippet' => 'Bezoek de bekendste bezienswaardigheden van Barcelona tijdens deze uitgebreide fietstoer.',
            ],
            [
                'title' => 'Romantische fietstoer langs de haven van Barcelona',
                'link' => 'https://example8.com/romantische-fietstoer-haven-barcelona',
                'snippet' => 'Een romantische fietstocht langs de haven van Barcelona met prachtige uitzichten over zee.',
            ],
            [
                'title' => 'Fietstocht langs onbekende schatten van Barcelona',
                'link' => 'https://example9.com/verborgen-schatten-fietstocht-barcelona',
                'snippet' => 'Ontdek verborgen parels van de stad op de fiets, weg van de toeristische drukte.',
            ],
            [
                'title' => 'E-bike tour door de heuvels van Barcelona',
                'link' => 'https://example10.com/e-bike-tour-barcelona-heuvels',
                'snippet' => 'Beklim de heuvels van Barcelona met gemak tijdens deze e-bike tour door Montjuïc en Tibidabo.',
            ],
            // Voeg hier meer toe tot je er 50 hebt
        ];

        // Voeg hier meer resultaten toe om het aantal van 50 te halen
        for ($i = 11; $i <= 50; $i++) {
            $demoResults[] = [
                'title' => 'Demo Fietstoer ' . $i . ' door Barcelona',
                'link' => 'https://example' . $i . '.com/fietstoer-' . $i,
                'snippet' => 'Dit is een demo fietstoer door de prachtige straten van Barcelona. Perfect voor de avontuurlijke fietser.',
            ];
        }

        // Als je met een blacklist werkt, kun je deze logica hier laten zoals in de echte API-call
        $blacklist = Blacklist::pluck('url')->toArray();

        // Filter demo-resultaten op basis van de blacklist
        $results = collect($demoResults)->filter(function ($item) use ($blacklist) {
            return !in_array($this->extractDomain($item['link']), $blacklist); // Resultaten die niet op de blacklist staan
        });

        return view('linkbuilding.search', compact('results'));

        // Fetch results from Google Custom Search API
        $response = Http::get('https://www.googleapis.com/customsearch/v1', [
            'key' => $apiKey,
            'cx' => $cx,
            'q' => $keywords,
            'hl' => $language,
            'sort' => 'date:r:now-7d',
            'num' => 100,
        ]);

        if ($response->successful()) {
            // Get the list of blacklisted URLs
            $blacklist = Blacklist::pluck('url')->toArray();

            // Filter out the blacklisted URLs
            $results = collect($response->json()['items'] ?? [])->filter(function ($item) use ($blacklist) {
                return !in_array($item['link'], $blacklist); // Exclude blacklisted URLs
            });

            return view('results.index', compact('results'));
        }

        return response()->json(['error' => 'Failed to fetch results'], 500);
    }

    public function addToBlacklist(Request $request)
    {
        $url = $request->input('url');
        $domain = $this->extractDomain($url);

        // Add the URL to the blacklist
        Blacklist::create(['url' => $domain]);

        return back()->with('success', 'URL added to blacklist');
    }

    public function extractDomain($url) {
        // extract the domain
        $explode = explode('.', $url);
        $first_part = $explode[0];
        $second_part = $explode[1];
        $second_part = explode('/', $second_part);
        $domain = $first_part . '.' . $second_part[0];

        $domain = str_replace('https://', '', $domain);
        $domain = str_replace('http://', '', $domain);
        $domain = str_replace('www.', '', $domain);

        return $domain;
    }
}

// The blacklist option should be selectboxes and when submitting the form all the blacklisted items should be removed from the list. for the rest of the list a call to the openai api should be made with the question to specify the estimated costs of buying a link on website and the autority of website, this should result in a list with 4 values: title, url, costs and authority. This list should be displayed to the user. There should be an option to order the list based on authority and costs. Additionally there should be an option to select multiple pages. If the users submits the list then a call for every selected item should be made to ask openai for an emailaddress and a generated email with subject in the language of the website with some compliment about the page and a question to buy a link on the webpage. Retrieving this details should be async from the frontend with a loader and for every result (email address and generated email) a form should be shown to send the email, fields: title, email address and wysiwyg editor with proposed email. Additionally there should be a send button to send the email. Make sure this is async as well so that multiple emails can be send on the same page.
