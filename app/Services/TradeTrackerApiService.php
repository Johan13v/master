<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\City;
use App\Models\Import;
use App\Models\Website;
use App\Models\Commission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TradeTrackerApiService
{
    private int $customerId;
    private string $passphrase;
    private string $wsdl;

    public function __construct()
    {
        $this->customerId = (int) config('services.tradetracker.customer_id');
        $this->passphrase = config('services.tradetracker.passphrase');
        $this->wsdl       = config('services.tradetracker.wsdl');
    }

    public function isConfigured(): bool
    {
        return !empty($this->customerId) && !empty($this->passphrase);
    }

    private function client(): \SoapClient
    {
        $client = new \SoapClient($this->wsdl, [
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
            'cache_wsdl'  => WSDL_CACHE_BOTH,
            'trace'       => true,
        ]);

        $client->authenticate($this->customerId, $this->passphrase, 'nl_NL', false);

        return $client;
    }

    /**
     * Sync transactions for a single date.
     */
    public function syncDate(string $date, int $revenueStreamId): array
    {
        $title = 'TradeTracker API - ' . $date;

        if (Import::where('title', $title)->exists()) {
            return ['created' => 0, 'skipped' => 0, 'unmatched' => [], 'import' => null, 'already_exists' => true];
        }

        $transactions = $this->fetchTransactions($date, $date);

        $import = Import::create([
            'revenue_stream_id' => $revenueStreamId,
            'title'             => $title,
        ]);

        $cities        = City::all();
        $websites      = Website::all();
        $created       = 0;
        $skipped       = 0;
        $unmatchedRows = [];

        DB::transaction(function () use ($transactions, $revenueStreamId, $import, $cities, $websites, &$created, &$skipped, &$unmatchedRows) {
            foreach ($transactions as $tx) {
                if (Commission::where('reference_id', $tx['referenceId'])->exists()) {
                    $skipped++;
                    continue;
                }

                $commission = array_merge($tx, ['website' => null, 'city' => null]);
                $commission = $this->matchTradeTracker($commission, $cities, $websites);

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
                        'customer_language' => $commission['customerLanguage'],
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
            'created'       => $created,
            'skipped'       => $skipped,
            'unmatched'     => $unmatchedRows,
            'import'        => $import,
            'already_exists' => false,
        ];
    }

    public function fetchTransactions(string $startDate, string $endDate): array
    {
        try {
            $client = $this->client();

            $options = [
                'registrationDateFrom' => Carbon::parse($startDate)->startOfDay()->timestamp,
                'registrationDateTo'   => Carbon::parse($endDate)->endOfDay()->timestamp,
            ];

            $result = $client->getConversionTransactions(null, $options);

            Log::debug('TradeTracker API response', [
                'start'  => $startDate,
                'end'    => $endDate,
                'count'  => is_array($result) ? count($result) : 0,
            ]);

            if (empty($result)) {
                return [];
            }

            return array_map(function ($tx) {
                $status = $this->mapStatus($tx->transactionStatus ?? '');

                Log::debug('TradeTracker transactie', [
                    'id'       => $tx->ID ?? '',
                    'campaign' => $tx->campaign->name ?? '',
                    'site'     => $tx->affiliateSite->name ?? '',
                    'status'   => $tx->transactionStatus ?? '',
                    'amount'   => $tx->commission ?? 0,
                ]);

                return [
                    'referenceId'      => (string) ($tx->ID ?? ''),
                    'product'          => $tx->campaign->name ?? '',
                    'amount'           => (float) ($tx->commission ?? 0),
                    'orderDate'        => Carbon::createFromTimestamp($tx->registrationDate ?? time())->format('Y-m-d H:i:s'),
                    'customerLanguage' => $tx->country ?? '',
                    'sitebrand'        => $tx->affiliateSite->name ?? '',
                    'status'           => $status,
                ];
            }, (array) $result);

        } catch (\Exception $e) {
            Log::error('TradeTracker SOAP fout', ['message' => $e->getMessage()]);
            return [];
        }
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'accepted'  => 'fulfilled',
            'rejected',
            'cancelled' => 'revoked',
            'pending'   => 'pending',
            default     => 'pending',
        };
    }

    private function matchTradeTracker(array $commission, $cities, $websites): array
    {
        $commission['website'] = Website::whereJsonContains('matchers', $commission['sitebrand'])->first();

        if ($commission['website'] !== null && $commission['website']->title == 'De Azoren') {
            $commission['city'] = City::whereJsonContains('matchers', 'Azoren')->first();
        } elseif ($commission['website'] !== null && $commission['website']->title == 'Wegwijs naar Parijs') {
            $commission['city'] = City::whereJsonContains('matchers', 'Paris')->first();
        } else {
            foreach ($websites as $web) {
                foreach ($web->matchers as $matcher) {
                    if (strpos($commission['product'], $matcher) !== false) {
                        $commission['website'] = $web;
                        break 2;
                    }
                }
            }

            foreach ($cities as $c) {
                foreach ($c->matchers as $matcher) {
                    if (strpos($commission['product'], $matcher) !== false) {
                        $commission['city'] = $c;
                        break 2;
                    }
                }
            }
        }

        Log::debug('TradeTracker matching', [
            'sitebrand' => $commission['sitebrand'],
            'product'   => $commission['product'],
            'website'   => $commission['website']?->title ?? 'geen match',
            'city'      => $commission['city']?->title ?? 'geen match',
        ]);

        return $commission;
    }
}
