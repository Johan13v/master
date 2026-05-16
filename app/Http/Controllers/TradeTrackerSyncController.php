<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Import;
use App\Models\RevenueStream;
use App\Services\TradeTrackerApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TradeTrackerSyncController extends Controller
{
    public function __construct(private TradeTrackerApiService $tradeTracker) {}

    public function index()
    {
        $revenueStream = RevenueStream::where('title', 'like', '%radetracker%')->first();

        $monthlyStats = Import::join('commissions', 'imports.id', '=', 'commissions.import_id')
            ->where('imports.title', 'like', 'TradeTracker API - %')
            ->selectRaw("DATE_FORMAT(STR_TO_DATE(SUBSTRING(imports.title, 19), '%Y-%m-%d'), '%Y-%m') as month")
            ->selectRaw('COUNT(DISTINCT imports.id) as days')
            ->selectRaw('COUNT(commissions.id) as commissions')
            ->selectRaw('SUM(commissions.amount) as total')
            ->groupByRaw("DATE_FORMAT(STR_TO_DATE(SUBSTRING(imports.title, 19), '%Y-%m-%d'), '%Y-%m')")
            ->orderByRaw("month DESC")
            ->get();

        $unmatchedDays = Import::where('title', 'like', 'TradeTracker API - %')
            ->whereDoesntHave('commissions')
            ->orderByDesc('title')
            ->get()
            ->map(fn($i) => substr($i->title, 18));

        return view('tradetracker.sync', [
            'configured'    => $this->tradeTracker->isConfigured(),
            'revenueStream' => $revenueStream,
            'monthlyStats'  => $monthlyStats,
            'unmatchedDays' => $unmatchedDays,
        ]);
    }

    public function sync(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        $revenueStream = $this->getRevenueStream();
        $result = $this->tradeTracker->syncDate($request->date, $revenueStream->id);

        if (count($result['unmatched']) > 0) {
            return view('tiqets.unmatched', [
                'unmatchedRows' => $result['unmatched'],
                'revenueStream' => $revenueStream,
                'import'        => $result['import'],
                'cities'        => \App\Models\City::all(),
                'websites'      => \App\Models\Website::all(),
                'summary'       => "{$result['created']} aangemaakt, {$result['skipped']} overgeslagen",
            ]);
        }

        return redirect()->route('tradetracker.sync')->with('success', "{$result['created']} commissies aangemaakt voor {$request->date}.");
    }

    public function syncDay(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        $revenueStream = $this->getRevenueStream();
        if (!$revenueStream) {
            return response()->json(['error' => 'Geen TradeTracker revenue stream gevonden.'], 422);
        }

        try {
            $result = $this->tradeTracker->syncDate($request->date, $revenueStream->id);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json([
            'date'            => $request->date,
            'created'         => $result['created'],
            'skipped'         => $result['skipped'],
            'already_exists'  => $result['already_exists'],
            'unmatched_count' => count($result['unmatched']),
        ]);
    }

    public function fixDay(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        $title = 'TradeTracker API - ' . $request->date;
        $import = Import::where('title', $title)->first();

        if ($import) {
            DB::transaction(function () use ($import) {
                $import->commissions()->delete();
                $import->delete();
            });
        }

        $revenueStream = $this->getRevenueStream();
        $result = $this->tradeTracker->syncDate($request->date, $revenueStream->id);

        if (count($result['unmatched']) > 0) {
            return view('tiqets.unmatched', [
                'unmatchedRows' => $result['unmatched'],
                'revenueStream' => $revenueStream,
                'import'        => $result['import'],
                'cities'        => \App\Models\City::all(),
                'websites'      => \App\Models\Website::all(),
                'summary'       => "{$result['created']} aangemaakt, {$result['skipped']} overgeslagen",
            ]);
        }

        $remaining = Import::where('title', 'like', 'TradeTracker API - %')->whereDoesntHave('commissions')->count();
        $msg = "{$result['created']} commissies aangemaakt.";
        if ($remaining > 0) $msg .= " Nog {$remaining} dag(en) wachten op koppeling.";

        return redirect()->route('tradetracker.sync')->with('success', $msg);
    }

    private function getRevenueStream(): ?RevenueStream
    {
        return RevenueStream::where('title', 'like', '%radetracker%')->first();
    }
}
