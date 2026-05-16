<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\City;
use App\Models\Import;
use App\Models\Website;
use App\Models\Commission;
use App\Models\RevenueStream;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AdSenseApiService
{
    private string $tokenPath;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->tokenPath    = storage_path('app/google_token.json');
        $this->clientId     = config('services.google.client_id');
        $this->clientSecret = config('services.google.client_secret');
        $this->redirectUri  = config('services.google.redirect_uri');
    }

    public function isConnected(): bool
    {
        if (!file_exists($this->tokenPath)) return false;
        $token = json_decode(file_get_contents($this->tokenPath), true);
        return !empty($token['refresh_token']);
    }

    public function getAuthUrl(): string
    {
        $params = http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/adsense.readonly',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);

        return "https://accounts.google.com/o/oauth2/v2/auth?{$params}";
    }

    public function handleCallback(string $code): void
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Google token uitwisseling mislukt: ' . $response->body());
        }

        file_put_contents($this->tokenPath, json_encode($response->json()));
    }

    public function disconnect(): void
    {
        if (file_exists($this->tokenPath)) {
            unlink($this->tokenPath);
        }
    }

    private function getAccessToken(): string
    {
        $token = json_decode(file_get_contents($this->tokenPath), true);

        $expiresAt = $token['created'] + $token['expires_in'] - 60;
        if (time() < $expiresAt && !empty($token['access_token'])) {
            return $token['access_token'];
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $token['refresh_token'],
            'grant_type'    => 'refresh_token',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Google token refresh mislukt: ' . $response->body());
        }

        $new = array_merge($token, $response->json(), ['created' => time()]);
        file_put_contents($this->tokenPath, json_encode($new));

        return $new['access_token'];
    }

    private function getAccountId(): string
    {
        return Cache::remember('adsense_account_id', now()->addDays(7), function () {
            $response = Http::withToken($this->getAccessToken())
                ->get('https://adsense.googleapis.com/v2/accounts');

            if ($response->failed()) {
                throw new \RuntimeException('AdSense accounts ophalen mislukt: ' . $response->body());
            }

            $accounts = $response->json('accounts');
            if (empty($accounts)) {
                throw new \RuntimeException('Geen AdSense account gevonden.');
            }

            return $accounts[0]['name'];
        });
    }

    /**
     * Sync AdSense earnings for a single date.
     * Returns ['created' => int, 'skipped' => int, 'already_exists' => bool, 'import' => Import|null]
     */
    public function syncDate(string $date, int $revenueStreamId): array
    {
        $title = 'AdSense API - ' . $date;

        if (Import::where('title', $title)->exists()) {
            return ['created' => 0, 'skipped' => 0, 'already_exists' => true, 'unmatched' => [], 'import' => null];
        }

        $rows = $this->fetchReport($date, $date);

        $import = Import::create([
            'revenue_stream_id' => $revenueStreamId,
            'title'             => $title,
        ]);

        $cities   = City::all();
        $websites = Website::all();
        $created  = 0;
        $skipped  = 0;
        $unmatchedRows = [];

        DB::transaction(function () use ($rows, $revenueStreamId, $import, $cities, $websites, &$created, &$skipped, &$unmatchedRows) {
            foreach ($rows as $row) {
                $reportDate = $row['date'];
                $site       = $row['domain'];
                $amount     = $row['earnings'];

                $referenceId = md5("adsense-{$reportDate}-{$site}");

                if (Commission::where('reference_id', $referenceId)->exists()) {
                    $skipped++;
                    continue;
                }

                $commission = [
                    'website'         => null,
                    'city'            => null,
                    'referenceId'     => $referenceId,
                    'product'         => $site,
                    'amount'          => $amount,
                    'orderDate'       => Carbon::parse($reportDate)->format('Y-m-d H:i:s'),
                    'customerLanguage' => '',
                    'status'          => 'fulfilled',
                    'sitebrand'       => $site,
                ];

                $commission = $this->matchAdSense($commission, $cities, $websites);

                if ($commission['city'] && $commission['website']) {
                    Commission::create([
                        'title'             => $commission['product'],
                        'amount'            => $commission['amount'],
                        'city_id'           => $commission['city']->id,
                        'revenue_stream_id' => $revenueStreamId,
                        'website_id'        => $commission['website']->id,
                        'import_id'         => $import->id,
                        'order_date'        => $commission['orderDate'],
                        'status'            => $commission['status'],
                        'customer_language' => '',
                        'reference_id'      => $commission['referenceId'],
                    ]);
                    $created++;
                } else {
                    $unmatchedRows[] = [
                        'commission'       => $commission,
                        'unmatchedCity'    => !$commission['city'],
                        'unmatchedWebsite' => !$commission['website'],
                    ];
                }
            }
        });

        return [
            'created'      => $created,
            'skipped'      => $skipped,
            'unmatched'    => $unmatchedRows,
            'import'       => $import,
            'already_exists' => false,
        ];
    }

    public function fetchReport(string $startDate, string $endDate): array
    {
        $accountId   = $this->getAccountId();
        $accessToken = $this->getAccessToken();

        $start = Carbon::parse($startDate);
        $end   = Carbon::parse($endDate);

        $response = Http::withToken($accessToken)
            ->get("https://adsense.googleapis.com/v2/{$accountId}/reports:generate", [
                'dateRange'              => 'CUSTOM',
                'startDate.year'         => $start->year,
                'startDate.month'        => $start->month,
                'startDate.day'          => $start->day,
                'endDate.year'           => $end->year,
                'endDate.month'          => $end->month,
                'endDate.day'            => $end->day,
                'metrics'                => 'ESTIMATED_EARNINGS',
                'dimensions'             => ['DATE', 'DOMAIN_NAME'],
                'currencyCode'           => 'EUR',
            ]);

        if ($response->failed()) {
            Log::error('AdSense report mislukt', ['status' => $response->status(), 'body' => $response->body()]);
            return [];
        }

        $rows = $response->json('rows') ?? [];

        return array_map(fn($row) => [
            'date'     => $row['cells'][0]['value'],
            'domain'   => $row['cells'][1]['value'],
            'earnings' => (float) $row['cells'][2]['value'],
        ], $rows);
    }

    private function matchAdSense(array $commission, $cities, $websites): array
    {
        foreach ($websites as $web) {
            foreach ($web->matchers as $matcher) {
                if (strpos($commission['sitebrand'], $matcher) !== false) {
                    $commission['website'] = $web;
                    break 2;
                }
            }
        }

        if ($commission['website'] !== null) {
            if ($commission['website']->title == 'Wegwijs naar Parijs') {
                $commission['city'] = City::whereJsonContains('matchers', 'Parijs')->first();
            } elseif ($commission['website']->title == 'NachParis') {
                $commission['city'] = City::whereJsonContains('matchers', 'Parijs')->first();
            } elseif ($commission['website']->title == 'De Azoren') {
                $commission['city'] = City::whereJsonContains('matchers', 'Azoren')->first();
            } elseif ($commission['website']->title == 'Azoren DE') {
                $commission['city'] = City::whereJsonContains('matchers', 'Azoren')->first();
            } else {
                $commission['city'] = City::whereJsonContains('matchers', 'Onbekend..')->first();
            }
        }

        return $commission;
    }
}
