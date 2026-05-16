<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Import;
use App\Models\RevenueStream;
use App\Services\TiqetsApiService;
use Illuminate\Http\Request;

class TiqetsSyncController extends Controller
{
    public function __construct(private TiqetsApiService $tiqetsService) {}

    public function index()
    {
        $revenueStream = RevenueStream::where('title', 'like', '%iqets%')->first();

        $recentImports = Import::with('commissions')
            ->where('title', 'like', 'Tiqets API - %')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('tiqets.sync', compact('revenueStream', 'recentImports'));
    }

    public function sync(Request $request)
    {
        $request->validate([
            'mode'  => 'required|in:single,range',
            'date'  => 'required_if:mode,single|nullable|date',
            'from'  => 'required_if:mode,range|nullable|date',
            'to'    => 'required_if:mode,range|nullable|date|after_or_equal:from',
        ]);

        $revenueStream = RevenueStream::where('title', 'like', '%iqets%')->firstOrFail();

        if ($request->mode === 'single') {
            $result = $this->tiqetsService->syncDate($request->date, $revenueStream->id);
            $message = $this->buildMessage([$result]);
        } else {
            $from = Carbon::parse($request->from);
            $to   = Carbon::parse($request->to);
            $results = [];

            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                $results[] = $this->tiqetsService->syncDate($day->toDateString(), $revenueStream->id);
            }

            $message = $this->buildMessage($results);
        }

        return redirect()->route('tiqets.sync')->with('success', $message);
    }

    private function buildMessage(array $results): string
    {
        $created   = array_sum(array_column($results, 'created'));
        $skipped   = array_sum(array_column($results, 'skipped'));
        $existing  = count(array_filter($results, fn($r) => $r['already_exists']));
        $processed = count(array_filter($results, fn($r) => !$r['already_exists']));

        $parts = [];
        if ($processed > 0) {
            $parts[] = "{$created} commissies aangemaakt over {$processed} dag(en)";
        }
        if ($skipped > 0) {
            $parts[] = "{$skipped} overgeslagen (geen match of duplicaat)";
        }
        if ($existing > 0) {
            $parts[] = "{$existing} dag(en) al geïmporteerd";
        }

        return implode(', ', $parts) ?: 'Geen orders gevonden voor de opgegeven periode.';
    }
}
