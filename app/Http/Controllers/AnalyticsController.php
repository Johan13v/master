<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Commission;
use App\Models\RevenueStream;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $currentYear = (int) $request->get('year', now()->year);
        $compareYear = $currentYear - 1;
        $isYearToDate = $currentYear === (int) now()->year;
        $cutoffMonthDay = $isYearToDate ? now()->format('m-d') : null;
        $cutoffLabel = $isYearToDate
            ? Carbon::createFromFormat('m-d', $cutoffMonthDay)->translatedFormat('j F')
            : null;

        $commissions = Commission::with(['city', 'revenueStream'])
            ->whereIn(\DB::raw('YEAR(order_date)'), [$currentYear, $compareYear])
            ->where('status', '!=', 'revoked')
            ->get()
            ->filter(fn($commission) => $this->withinComparisonWindow($commission, $cutoffMonthDay))
            ->values();

        $months = collect(range(1, 12))->map(fn($m) => str_pad($m, 2, '0', STR_PAD_LEFT));

        // 1. YoY per destination
        $byCity = $commissions
            ->groupBy('city_id')
            ->map(fn($items) => $this->cityStats($items, $currentYear, $compareYear, $months))
            ->filter(fn($d) => $d['city'] !== null)
            ->sortByDesc('current_total');

        // 2. YoY per source
        $bySource = $commissions
            ->groupBy('revenue_stream_id')
            ->map(function ($items) use ($currentYear, $compareYear) {
                $cur  = $items->filter(fn($c) => $this->year($c) === $currentYear);
                $prev = $items->filter(fn($c) => $this->year($c) === $compareYear);

                return [
                    'stream'          => $items->first()->revenueStream,
                    'current_amount'  => $cur->sum('amount'),
                    'previous_amount' => $prev->sum('amount'),
                    'current_count'   => $cur->count(),
                    'previous_count'  => $prev->count(),
                ];
            })
            ->filter(fn($row) => $row['stream'] !== null)
            ->sortByDesc('current_amount')
            ->values();

        // 3. YoY per destination per source
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

        // 4. Paris × Tiqets per product
        $parisCity    = City::whereJsonContains('matchers', 'Paris')->first();
        $tiqetsStream = RevenueStream::where('title', 'like', '%iqets%')->first();
        $parisTiqets  = collect();

        if ($parisCity && $tiqetsStream) {
            $parisTiqets = $commissions
                ->where('city_id', $parisCity->id)
                ->where('revenue_stream_id', $tiqetsStream->id)
                ->groupBy(fn($commission) => $this->normalizeTiqetsProduct($commission->title))
                ->map(function ($items, $productGroup) use ($currentYear, $compareYear) {
                    $cur  = $items->filter(fn($c) => $this->year($c) === $currentYear);
                    $prev = $items->filter(fn($c) => $this->year($c) === $compareYear);
                    return [
                        'product'          => $productGroup,
                        'current_amount'   => $cur->sum('amount'),
                        'previous_amount'  => $prev->sum('amount'),
                        'current_count'    => $cur->count(),
                        'previous_count'   => $prev->count(),
                        'variants'         => $items->pluck('title')->filter()->unique()->sort()->values(),
                    ];
                })
                ->sortByDesc('current_amount')
                ->values();
        }

        // 5. Booking.com per affiliate ID (campaign)
        $bookingStream = RevenueStream::where('title', 'like', '%ooking%')->first();
        $bookingAffiliate = collect();

        if ($bookingStream) {
            $affiliateLabels = config('booking_affiliate_labels', []);

            $bookingAffiliate = $commissions
                ->where('revenue_stream_id', $bookingStream->id)
                ->filter(fn($c) => !empty($c->affiliate_id))
                ->groupBy('affiliate_id')
                ->map(function ($items, $affiliateId) use ($currentYear, $compareYear, $affiliateLabels) {
                    $cur  = $items->filter(fn($c) => $this->year($c) === $currentYear);
                    $prev = $items->filter(fn($c) => $this->year($c) === $compareYear);
                    return [
                        'affiliate_id'     => $items->first()->affiliate_id,
                        'affiliate_label'  => $affiliateLabels[(string) $affiliateId] ?? null,
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
            'byCity', 'bySource', 'byCitySource', 'parisTiqets', 'bookingAffiliate',
            'currentYear', 'compareYear', 'months', 'availableYears', 'isYearToDate', 'cutoffLabel'
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

    private function withinComparisonWindow(Commission $commission, ?string $cutoffMonthDay): bool
    {
        if ($cutoffMonthDay === null) {
            return true;
        }

        return substr($commission->order_date, 5, 5) <= $cutoffMonthDay;
    }

    private function normalizeTiqetsProduct(?string $title): string
    {
        $title = trim((string) $title);

        if ($title === '') {
            return 'Onbekend product';
        }

        $title = preg_replace('/\s+/', ' ', str_replace(['®', '™'], '', $title)) ?? $title;
        $lowerTitle = mb_strtolower($title);

        if (str_contains($lowerTitle, 'seine') && str_contains($lowerTitle, 'cruise')) {
            if (str_contains($lowerTitle, 'dinner')) {
                return 'Seine River Cruise - Dinner';
            }

            if (str_contains($lowerTitle, 'lunch')) {
                return 'Seine River Cruise - Lunch';
            }

            return 'Seine River Cruise - Normal';
        }

        if (str_contains($lowerTitle, 'eiffel tower')) {
            return 'Eiffel Tower';
        }

        if (str_contains($lowerTitle, 'louvre')) {
            if (str_contains($lowerTitle, 'guided')) {
                return 'Louvre Museum - Guided Tours';
            }

            return 'Louvre Museum - Standard Entry';
        }

        if (
            str_contains($lowerTitle, 'hop-on hop-off')
            || str_contains($lowerTitle, 'hop on hop off')
            || str_contains($lowerTitle, 'tootbus')
            || str_contains($lowerTitle, 'big bus')
            || str_contains($lowerTitle, 'batobus')
        ) {
            return 'Hop-on Hop-off';
        }

        if (str_contains($lowerTitle, 'versailles') || str_contains($lowerTitle, 'trianon')) {
            return 'Versailles';
        }

        return $title;
    }

    public static function growth(float $current, float $previous): string
    {
        if ($previous == 0) return $current > 0 ? '—' : '—';
        $pct = (($current - $previous) / $previous) * 100;
        return ($pct >= 0 ? '+' : '') . number_format($pct, 1) . '%';
    }
}
