<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Import;
use App\Models\RevenueStream;
use App\Services\AdSenseApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdSenseSyncController extends Controller
{
    public function __construct(private AdSenseApiService $adSense) {}

    public function index()
    {
        $revenueStream = RevenueStream::where('title', 'like', '%adsense%')
            ->orWhere('title', 'like', '%google%')
            ->first();

        $monthlyStats = Import::join('commissions', 'imports.id', '=', 'commissions.import_id')
            ->where('imports.title', 'like', 'AdSense API - %')
            ->selectRaw("DATE_FORMAT(STR_TO_DATE(SUBSTRING(imports.title, 14), '%Y-%m-%d'), '%Y-%m') as month")
            ->selectRaw('COUNT(DISTINCT imports.id) as days')
            ->selectRaw('COUNT(commissions.id) as commissions')
            ->selectRaw('SUM(commissions.amount) as total')
            ->groupByRaw("DATE_FORMAT(STR_TO_DATE(SUBSTRING(imports.title, 14), '%Y-%m-%d'), '%Y-%m')")
            ->orderByRaw("month DESC")
            ->get();

        $unmatchedDays = Import::where('title', 'like', 'AdSense API - %')
            ->where(fn($q) => $q->whereDoesntHave('commissions')->orWhere('unmatched_count', '>', 0))
            ->orderByDesc('title')
            ->get()
            ->map(fn($i) => substr($i->title, 14));

        return view('adsense.sync', [
            'connected'     => $this->adSense->isConnected(),
            'authUrl'       => $this->adSense->isConnected() ? null : $this->adSense->getAuthUrl(),
            'revenueStream' => $revenueStream,
            'monthlyStats'  => $monthlyStats,
            'unmatchedDays' => $unmatchedDays,
        ]);
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('adsense.sync')->with('error', 'Google koppeling geannuleerd.');
        }

        try {
            $this->adSense->handleCallback($request->get('code'));
        } catch (\Exception $e) {
            return redirect()->route('adsense.sync')->with('error', 'Koppeling mislukt: ' . $e->getMessage());
        }

        return redirect()->route('adsense.sync')->with('success', 'Google AdSense succesvol gekoppeld!');
    }

    public function disconnect()
    {
        $this->adSense->disconnect();
        return redirect()->route('adsense.sync')->with('success', 'Google AdSense ontkoppeld.');
    }

    public function sync(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        $revenueStream = $this->getRevenueStream();
        $result = $this->adSense->syncDate($request->date, $revenueStream->id);

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

        return redirect()->route('adsense.sync')->with('success', "{$result['created']} commissies aangemaakt voor {$request->date}.");
    }

    public function syncDay(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        $revenueStream = $this->getRevenueStream();
        if (!$revenueStream) {
            return response()->json(['error' => 'Geen AdSense revenue stream gevonden.'], 422);
        }

        try {
            $result = $this->adSense->syncDate($request->date, $revenueStream->id);
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

        $title = 'AdSense API - ' . $request->date;
        $import = Import::where('title', $title)->first();

        if ($import) {
            DB::transaction(function () use ($import) {
                $import->commissions()->delete();
                $import->delete();
            });
        }

        $revenueStream = $this->getRevenueStream();
        $result = $this->adSense->syncDate($request->date, $revenueStream->id);

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

        return redirect()->route('adsense.sync')->with('success', "{$result['created']} commissies aangemaakt.");
    }

    private function getRevenueStream(): ?RevenueStream
    {
        return RevenueStream::where('title', 'like', '%adsense%')
            ->orWhere('title', 'like', '%google%')
            ->first();
    }
}
