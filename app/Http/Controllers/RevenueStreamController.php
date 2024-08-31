<?php

namespace App\Http\Controllers;

use App\Models\RevenueStream;
use Illuminate\Http\Request;

class RevenueStreamController extends Controller
{
    public function index()
    {
        $revenueStreams = RevenueStream::all();
        return view('revenue_streams.index', compact('revenueStreams'));
    }

    public function create()
    {
        return view('revenue_streams.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
        ]);

        RevenueStream::create($request->only(['title']));

        return redirect()->route('revenue-streams.index');
    }

    public function edit(RevenueStream $revenueStream)
    {
        return view('revenue_streams.edit', compact('revenueStream'));
    }

    public function update(Request $request, RevenueStream $revenueStream)
    {
        $request->validate([
            'title' => 'required',
        ]);

        $revenueStream->update($request->only(['title']));

        return redirect()->route('revenue-streams.index');
    }

    public function destroy(RevenueStream $revenueStream)
    {
        $revenueStream->delete();
        return redirect()->route('revenue-streams.index');
    }
}
