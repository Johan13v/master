<?php

namespace App\Http\Controllers;

use App\Models\Website;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    public function index()
    {
        $websites = Website::all();
        return view('websites.index', compact('websites'));
    }

    public function create()
    {
        return view('websites.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'website_address' => 'required|url',
            'matchers' => 'nullable|string',
        ]);

        Website::create([
            'title' => $request->title,
            'website_address' => $request->website_address,
            'matchers' => explode(',', $request->matchers),
        ]);

        return redirect()->route('websites.index');
    }

    public function edit(Website $website)
    {
        return view('websites.edit', compact('website'));
    }

    public function update(Request $request, Website $website)
    {
        $request->validate([
            'title' => 'required',
            'website_address' => 'required|url',
            'matchers' => 'nullable|string',
        ]);

        $website->update([
            'title' => $request->title,
            'website_address' => $request->website_address,
            'matchers' => explode(',', $request->matchers),
        ]);

        return redirect()->route('websites.index');
    }

    public function destroy(Website $website)
    {
        $website->delete();
        return redirect()->route('websites.index');
    }
}
