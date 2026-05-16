<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\City;
use App\Models\Import;
use App\Models\Website;
use App\Models\Commission;
use App\Models\RevenueStream;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TiqetsApiService
{
    private string $baseUrl;
    private ?string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.tiqets.base_url');
        $this->token = config('services.tiqets.token');
    }

    /**
     * Sync orders for a single date. Returns ['created' => int, 'skipped' => int, 'title' => string].
     * Skips if an Import with the same title already exists (idempotent).
     */
    public function syncDate(string $date, int $revenueStreamId): array
    {
        $title = 'Tiqets API - ' . $date;

        if (Import::where('title', $title)->exists()) {
            return ['created' => 0, 'skipped' => 0, 'title' => $title, 'already_exists' => true];
        }

        $orders = $this->fetchAllOrders($date, $date);

        $import = Import::create([
            'revenue_stream_id' => $revenueStreamId,
            'title' => $title,
        ]);

        $cities = City::all();
        $websites = Website::all();
        $created = 0;
        $skipped = 0;
        $unmatchedRows = [];

        DB::transaction(function () use ($orders, $revenueStreamId, $import, $cities, $websites, &$created, &$skipped, &$unmatchedRows) {
            foreach ($orders as $order) {
                $referenceId = $order['order_reference_id'];

                if (Commission::where('reference_id', $referenceId)->exists()) {
                    $skipped++;
                    continue;
                }

                $commission = [
                    'website'          => null,
                    'city'             => null,
                    'referenceId'      => $referenceId,
                    'product'          => $this->getProductTitle((int) $order['product_id']),
                    'amount'           => $order['commission_excl_vat'],
                    'orderDate'        => Carbon::parse($order['order_fulfilled_at'])->format('Y-m-d H:i:s'),
                    'customerLanguage' => $order['customer_booking_language'] ?? '',
                    'status'           => 'fulfilled',
                    'sitebrand'        => $order['sitebrand_shortname'] ?? '',
                ];

                $commission = $this->matchTiqets($commission, $cities, $websites);

                if ($commission['city'] && $commission['website']) {
                    $websiteId = $commission['website']->id;

                    if ($commission['city']->id == '15') {
                        $websiteId = 8;
                    }

                    Commission::create([
                        'title'            => $commission['product'],
                        'amount'           => $commission['amount'],
                        'city_id'          => $commission['city']->id,
                        'revenue_stream_id' => $revenueStreamId,
                        'website_id'       => $websiteId,
                        'import_id'        => $import->id,
                        'order_date'       => $commission['orderDate'],
                        'status'           => $commission['status'],
                        'customer_language' => $commission['customerLanguage'],
                        'reference_id'     => $commission['referenceId'],
                    ]);

                    $created++;
                } else {
                    $unmatchedRows[] = [
                        'commission'      => $commission,
                        'unmatchedCity'   => !$commission['city'],
                        'unmatchedWebsite' => !$commission['website'],
                    ];
                }
            }
        });

        return [
            'created'       => $created,
            'skipped'       => $skipped,
            'unmatched'     => $unmatchedRows,
            'import'        => $import,
            'title'         => $title,
            'already_exists' => false,
        ];
    }

    /**
     * Fetch all pages of orders for the given date range.
     */
    private function fetchAllOrders(string $startDate, string $endDate): array
    {
        $all = [];
        $page = 1;

        do {
            $response = $this->fetchOrders($startDate, $endDate, $page);
            $orders = $response['orders'] ?? [];
            $all = array_merge($all, $orders);

            $total = $response['pagination']['total'] ?? 0;
            $pageSize = $response['pagination']['page_size'] ?? 100;
            $page++;
        } while (count($all) < $total && count($orders) > 0);

        return $all;
    }

    public function fetchOrders(string $startDate, string $endDate, int $page = 1): array
    {
        $response = Http::withToken($this->token)
            ->get("{$this->baseUrl}/reports/orders", [
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'page'       => $page,
                'page_size'  => 100,
            ]);

        if ($response->failed()) {
            Log::error('Tiqets API error', ['status' => $response->status(), 'body' => $response->body()]);
            return ['orders' => [], 'pagination' => ['total' => 0]];
        }

        return $response->json();
    }

    public function getProductTitle(int $productId): string
    {
        return Cache::remember("tiqets_product_{$productId}", now()->addDays(30), function () use ($productId) {
            $response = Http::withToken($this->token)
                ->get("{$this->baseUrl}/products/{$productId}");

            if ($response->failed()) {
                Log::warning("Tiqets: kon productnaam niet ophalen voor id {$productId}");
                return (string) $productId;
            }

            return $response->json('title') ?? (string) $productId;
        });
    }

    private function matchTiqets(array $commission, $cities, $websites): array
    {
        if ($commission['sitebrand'] == 'Wegwijsnaarparijs' && $commission['customerLanguage'] == 'de') {
            $commission['website'] = Website::whereJsonContains('matchers', 'Nachparis')->first();
        } elseif ($commission['sitebrand'] == 'De-azoren' && $commission['customerLanguage'] == 'de') {
            $commission['website'] = Website::whereJsonContains('matchers', 'AzorenPortugalDE')->first();
        } else {
            foreach ($websites as $web) {
                foreach ($web->matchers as $matcher) {
                    if (strpos($commission['sitebrand'], $matcher) !== false) {
                        $commission['website'] = $web;
                        break 2;
                    }
                }
            }
        }

        if ($commission['sitebrand'] == 'Wegwijsnaarparijs') {
            $commission['city'] = City::whereJsonContains('matchers', 'Parijs')->first();
        } else {
            foreach ($cities as $c) {
                foreach ($c->matchers as $matcher) {
                    if (strpos($commission['product'], $matcher) !== false) {
                        $commission['city'] = $c;
                        break 2;
                    }
                }
            }
        }

        return $commission;
    }
}
