<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Commission;
use App\Models\Website;
use App\Models\City;
use App\Models\RevenueStream;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $interval = $request->get('interval', 'day');
        $viewMode = $request->get('view_mode', 'website');
        $startDate = $request->get('start_date', Carbon::now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->endOfYear()->format('Y-m-d'));

        $revenueStreams = RevenueStream::all();
        $websites = Website::all();
        $cities = City::all();

        $totalRevenueData = $this->getTotalRevenueData($interval, $viewMode, $startDate, $endDate);
        $revenueDataByStream = $this->getRevenueDataByStream($revenueStreams, $interval, $viewMode, $startDate, $endDate);

        return view('dashboard', compact('totalRevenueData', 'revenueDataByStream', 'revenueStreams', 'interval', 'viewMode', 'startDate', 'endDate'));
    }

    private function getTotalRevenueData($interval, $viewMode, $startDate, $endDate)
    {
        $dateFormat = $this->getDateFormat($interval);
        $dates = $this->getAllDates($interval, $startDate, $endDate);

        $commissions = Commission::selectRaw("DATE_FORMAT(order_date, '{$dateFormat}') as date, {$viewMode}_id, SUM(amount) as total_revenue")
            ->whereBetween('order_date', [$startDate, $endDate])
            ->groupBy('date', "{$viewMode}_id")
            ->orderBy('date')
            ->get();

        $totalRevenueData = [];
        $entities = $viewMode === 'website' ? Website::all() : City::all();
        foreach ($entities as $entity) {
            $totalRevenueData[$entity->id] = ['entity' => $entity->title, 'data' => []];
            foreach ($dates as $date) {
                $totalRevenueData[$entity->id]['data'][$date] = [
                    'date' => $date,
                    'total_revenue' => 0
                ];
            }
        }

        foreach ($commissions as $commission) {
            $date = $commission->date;
            $entityId = $commission->{$viewMode . '_id'};
            $totalRevenueData[$entityId]['data'][$date] = [
                'date' => $date,
                'total_revenue' => $commission->total_revenue
            ];
        }

        foreach ($totalRevenueData as &$entityData) {
            $entityData['data'] = array_values($entityData['data']);
        }

        return $totalRevenueData;
    }

    private function getRevenueDataByStream($revenueStreams, $interval, $viewMode, $startDate, $endDate)
    {
        $dateFormat = $this->getDateFormat($interval);
        $dates = $this->getAllDates($interval, $startDate, $endDate);

        $revenueDataByStream = [];

        foreach ($revenueStreams as $stream) {
            $revenueDataByStream[$stream->id] = [];
            $entities = $viewMode === 'website' ? Website::all() : City::all();
            foreach ($entities as $entity) {
                $revenueDataByStream[$stream->id][$entity->id] = ['entity' => $entity->title, 'data' => []];
                foreach ($dates as $date) {
                    $revenueDataByStream[$stream->id][$entity->id]['data'][$date] = [
                        'date' => $date,
                        'total_revenue' => 0
                    ];
                }
            }

            $commissions = Commission::where('revenue_stream_id', $stream->id)
                ->whereBetween('order_date', [$startDate, $endDate])
                ->selectRaw("DATE_FORMAT(order_date, '{$dateFormat}') as date, {$viewMode}_id, SUM(amount) as total_revenue")
                ->groupBy('date', "{$viewMode}_id")
                ->orderBy('date')
                ->get();

            foreach ($commissions as $commission) {
                $date = $commission->date;
                $entityId = $commission->{$viewMode . '_id'};
                $revenueDataByStream[$stream->id][$entityId]['data'][$date] = [
                    'date' => $date,
                    'total_revenue' => $commission->total_revenue
                ];
            }

            foreach ($revenueDataByStream[$stream->id] as &$entityData) {
                $entityData['data'] = array_values($entityData['data']);
            }
        }

        return $revenueDataByStream;
    }

    private function getDateFormat($interval)
    {
        switch ($interval) {
            case 'year':
                return '%Y';
            case 'month':
                return '%Y-%m';
            default:
                return '%Y-%m-%d';
        }
    }

    private function getAllDates($interval, $startDate, $endDate)
    {
        switch ($interval) {
            case 'year':
                return collect(CarbonPeriod::create($startDate, '1 year', $endDate)->toArray())->map(function ($date) {
                    return $date->format('Y');
                })->toArray();
            case 'month':
                return collect(CarbonPeriod::create($startDate, '1 month', $endDate)->toArray())->map(function ($date) {
                    return $date->format('Y-m');
                })->toArray();
            default:
                return collect(CarbonPeriod::create($startDate, '1 day', $endDate)->toArray())->map(function ($date) {
                    return $date->format('Y-m-d');
                })->toArray();
        }
    }
}
