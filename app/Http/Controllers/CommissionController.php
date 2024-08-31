<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\City;
use App\Models\RevenueStream;
use App\Models\Website;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function index()
    {
        $commissions = Commission::with(['city', 'revenueStream', 'website'])->get();
        return view('commissions.index', compact('commissions'));
    }

    public function create()
    {
        $cities = City::all();
        $revenueStreams = RevenueStream::all();
        $websites = Website::all();
        return view('commissions.create', compact('cities', 'revenueStreams', 'websites'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'amount' => 'required|numeric',
            'city_id' => 'required|exists:cities,id',
            'revenue_stream_id' => 'required|exists:revenue_streams,id',
            'website_id' => 'required|exists:websites,id',
        ]);

        Commission::create($request->only(['title', 'amount', 'city_id', 'revenue_stream_id', 'website_id']));

        return redirect()->route('commissions.index');
    }

    public function edit(Commission $commission)
    {
        $cities = City::all();
        $revenueStreams = RevenueStream::all();
        $websites = Website::all();
        return view('commissions.edit', compact('commission', 'cities', 'revenueStreams', 'websites'));
    }

    public function update(Request $request, Commission $commission)
    {
        $request->validate([
            'title' => 'required',
            'amount' => 'required|numeric',
            'city_id' => 'required|exists:cities,id',
            'revenue_stream_id' => 'required|exists:revenue_streams,id',
            'website_id' => 'required|exists:websites,id',
        ]);

        $commission->update($request->only(['title', 'amount', 'city_id', 'revenue_stream_id', 'website_id']));

        return redirect()->route('commissions.index');
    }

    public function destroy(Commission $commission)
    {
        $commission->delete();
        return redirect()->route('commissions.index');
    }
}
