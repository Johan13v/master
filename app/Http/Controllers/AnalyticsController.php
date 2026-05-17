<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Commission;
use App\Models\RevenueStream;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $currentYear = (int) $request->get('year', now()->year);
        $compareYear = $currentYear - 1;

        $commissions = Commission::with(['city', 'revenueStream'])
            ->whereIn(\DB::raw('YEAR(order_date)'), [$currentYear, $compareYear])
            ->where('status', '!=', 'revoked')
            ->get();

        $months = collect(range(1, 12))->map(fn($m) => str_pad($m, 2, '0', STR_PAD_LEFT));

        // 1. YoY per destination
        $byCity = $commissions
            ->groupBy('city_id')
            ->map(fn($items) => $this->cityStats($items, $currentYear, $compareYear, $months))
            ->filter(fn($d) => $d['city'] !== null)
            ->sortByDesc('current_total');

        // 2. YoY per destination per source
        $byCitySource = $commissions
            ->groupBy('city_id')
            ->map(function ($items) use ($currentYear, $compareYear) {
                return [
                    'city'    => $items->first()->city,
                    'sources' => $items
                        ->groupBy('revenue_stream_id')
                        ->map(fn($src) => [
                            'stream'   => $src->first()->revenueStream,
                            'current'  => $src->filter(fn($c) => $this->year($c) === $currentYear)->sum('amount'),
                            'previous' => $src->filter(fn($c) => $this->year($c) === $compareYear)->sum('amount'),
                        ])
                        ->sortByDesc('current'),
                ];
            })
            ->filter(fn($d) => $d['city'] !== null)
            ->sortByDesc(fn($d) => $d['sources']->sum('current'));

        // 3. Paris × Tiqets per product
        $parisCity    = City::whereJsonContains('matchers', 'Paris')->first();
        $tiqetsStream = RevenueStream::where('title', 'like', '%iqets%')->first();
        $parisTiqets  = collect();

        if ($parisCity && $tiqetsStream) {
            $parisTiqets = $commissions
                ->where('city_id', $parisCity->id)
                ->where('revenue_stream_id', $tiqetsStream->id)
                ->groupBy('title')
                ->map(function ($items) use ($currentYear, $compareYear) {
                    $cur  = $items->filter(fn($c) => $this->year($c) === $currentYear);
                    $prev = $items->filter(fn($c) => $this->year($c) === $compareYear);
                    return [
                        'product'          => $items->first()->title,
                        'current_amount'   => $cur->sum('amount'),
                        'previous_amount'  => $prev->sum('amount'),
                        'current_count'    => $cur->count(),
                        'previous_count'   => $prev->count(),
                    ];
                })
                ->sortByDesc('current_amount');
        }

        // 4. Booking.com per affiliate ID (campaign)
        $bookingStream = RevenueStream::where('title', 'like', '%ooking%')->first();
        $bookingAffiliate = collect();

        if ($bookingStream) {
            $bookingAffiliate = $commissions
                ->where('revenue_stream_id', $bookingStream->id)
                ->filter(fn($c) => !empty($c->affiliate_id))
                ->groupBy('affiliate_id')
                ->map(function ($items) use ($currentYear, $compareYear) {
                    $cur  = $items->filter(fn($c) => $this->year($c) === $currentYear);
                    $prev = $items->filter(fn($c) => $this->year($c) === $compareYear);
                    return [
                        'affiliate_id'     => $items->first()->affiliate_id,
                        'current_amount'   => $cur->sum('amount'),
                        'previous_amount'  => $prev->sum('amount'),
                        'current_count'    => $cur->count(),
                        'previous_count'   => $prev->count(),
                    ];
                })
                ->sortByDesc('current_amount');
        }

        // Available years for the year picker
        $availableYears = Commission::selectRaw('YEAR(order_date) as year')
            ->where('status', '!=', 'revoked')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        return view('analytics.index', compact(
            'byCity', 'byCitySource', 'parisTiqets', 'bookingAffiliate',
            'currentYear', 'compareYear', 'months', 'availableYears'
        ));
    }

    private function cityStats($items, int $currentYear, int $compareYear, $months): array
    {
        $cur  = $items->filter(fn($c) => $this->year($c) === $currentYear);
        $prev = $items->filter(fn($c) => $this->year($c) === $compareYear);

        return [
            'city'           => $items->first()->city,
            'current_total'  => $cur->sum('amount'),
            'previous_total' => $prev->sum('amount'),
            'by_month'       => $months->mapWithKeys(fn($m) => [$m => [
                'current'  => $cur->filter(fn($c)  => substr($c->order_date, 5, 2) === $m)->sum('amount'),
                'previous' => $prev->filter(fn($c) => substr($c->order_date, 5, 2) === $m)->sum('amount'),
            ]]),
        ];
    }

    private function year(Commission $c): int
    {
        return (int) substr($c->order_date, 0, 4);
    }

    public static function growth(float $current, float $previous): string
    {
        if ($previous == 0) return $current > 0 ? '—' : '—';
        $pct = (($current - $previous) / $previous) * 100;
        return ($pct >= 0 ? '+' : '') . number_format($pct, 1) . '%';
    }
}
