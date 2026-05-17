@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Analyses — {{ $currentYear }} vs {{ $compareYear }}
    </h2>
@endsection

@php
    function fmt($amount) { return '€' . number_format($amount, 2, ',', '.'); }
    function growth($cur, $prev) {
        if ($prev == 0) return '<span class="text-gray-400">—</span>';
        $pct = (($cur - $prev) / $prev) * 100;
        $color = $pct >= 0 ? 'text-green-600' : 'text-red-500';
        $sign  = $pct >= 0 ? '+' : '';
        return "<span class=\"{$color} font-medium\">{$sign}" . number_format($pct, 1) . "%</span>";
    }
    $monthNames = ['01'=>'jan','02'=>'feb','03'=>'mrt','04'=>'apr','05'=>'mei','06'=>'jun',
                   '07'=>'jul','08'=>'aug','09'=>'sep','10'=>'okt','11'=>'nov','12'=>'dec'];
@endphp

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

        {{-- Year picker --}}
        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-500">Jaar:</span>
            @foreach($availableYears as $y)
                <a href="{{ route('analytics.index', ['year' => $y]) }}"
                   class="px-3 py-1 rounded text-sm {{ $y == $currentYear ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:border-indigo-400' }}">
                    {{ $y }}
                </a>
            @endforeach
        </div>

        {{-- ─── 1. YoY per bestemming ─────────────────────────────────────── --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-700 mb-1">Jaar-op-jaar per bestemming</h3>
                <p class="text-xs text-gray-400 mb-5">Klik op een rij om maanden uit te klappen.</p>

                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-400 uppercase text-xs border-b border-gray-200">
                            <th class="pb-2 pr-4">Bestemming</th>
                            <th class="pb-2 pr-4 text-right">{{ $compareYear }}</th>
                            <th class="pb-2 pr-4 text-right">{{ $currentYear }}</th>
                            <th class="pb-2 text-right">YoY</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($byCity as $cityId => $data)
                        @php $rowId = 'city-months-' . $cityId; @endphp
                        <tr class="border-b border-gray-100 cursor-pointer hover:bg-gray-50"
                            onclick="document.getElementById('{{ $rowId }}').classList.toggle('hidden')">
                            <td class="py-2 pr-4 font-medium text-gray-800">
                                {{ $data['city']->title }}
                            </td>
                            <td class="py-2 pr-4 text-right text-gray-500">{{ fmt($data['previous_total']) }}</td>
                            <td class="py-2 pr-4 text-right text-gray-800">{{ fmt($data['current_total']) }}</td>
                            <td class="py-2 text-right">{!! growth($data['current_total'], $data['previous_total']) !!}</td>
                        </tr>
                        {{-- Month detail --}}
                        <tr id="{{ $rowId }}" class="hidden bg-gray-50">
                            <td colspan="4" class="pb-3 pt-1 px-4">
                                <table class="w-full text-xs text-gray-500">
                                    <thead>
                                        <tr class="text-gray-400 uppercase">
                                            <th class="pb-1 pr-3 text-left">Maand</th>
                                            <th class="pb-1 pr-3 text-right">{{ $compareYear }}</th>
                                            <th class="pb-1 pr-3 text-right">{{ $currentYear }}</th>
                                            <th class="pb-1 text-right">YoY</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($months as $m)
                                        @php
                                            $cur  = $data['by_month'][$m]['current'];
                                            $prev = $data['by_month'][$m]['previous'];
                                        @endphp
                                        @if($cur > 0 || $prev > 0)
                                        <tr class="border-t border-gray-100">
                                            <td class="py-1 pr-3 uppercase">{{ $monthNames[$m] }}</td>
                                            <td class="py-1 pr-3 text-right">{{ fmt($prev) }}</td>
                                            <td class="py-1 pr-3 text-right">{{ fmt($cur) }}</td>
                                            <td class="py-1 text-right">{!! growth($cur, $prev) !!}</td>
                                        </tr>
                                        @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ─── 2. YoY per bestemming per bron ───────────────────────────── --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-700 mb-5">Jaar-op-jaar per bestemming per bron</h3>

                <div class="space-y-6">
                    @foreach($byCitySource as $cityId => $data)
                    @if($data['sources']->sum('current') > 0 || $data['sources']->sum('previous') > 0)
                    <div>
                        <div class="text-sm font-semibold text-gray-700 mb-2">{{ $data['city']->title }}</div>
                        <table class="min-w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @foreach($data['sources'] as $src)
                                @if($src['current'] > 0 || $src['previous'] > 0)
                                <tr>
                                    <td class="py-1.5 pr-4 text-gray-500 w-48">{{ $src['stream']?->title ?? '—' }}</td>
                                    <td class="py-1.5 pr-4 text-right text-gray-400 w-28">{{ fmt($src['previous']) }}</td>
                                    <td class="py-1.5 pr-4 text-right text-gray-800 w-28">{{ fmt($src['current']) }}</td>
                                    <td class="py-1.5 text-right w-20">{!! growth($src['current'], $src['previous']) !!}</td>
                                </tr>
                                @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ─── 3. Parijs × Tiqets per product ───────────────────────────── --}}
        @if($parisTiqets->isNotEmpty())
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-700 mb-1">Parijs — Tiqets per product</h3>
                <p class="text-xs text-gray-400 mb-5">Aantal boekingen en commissie per tour/activiteit.</p>

                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-400 uppercase text-xs border-b border-gray-200">
                            <th class="pb-2 pr-4">Product</th>
                            <th class="pb-2 pr-4 text-right">{{ $compareYear }} (boek.)</th>
                            <th class="pb-2 pr-4 text-right">{{ $currentYear }} (boek.)</th>
                            <th class="pb-2 pr-4 text-right">{{ $compareYear }}</th>
                            <th class="pb-2 pr-4 text-right">{{ $currentYear }}</th>
                            <th class="pb-2 text-right">YoY</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($parisTiqets as $product)
                        <tr>
                            <td class="py-2 pr-4 text-gray-700 max-w-xs truncate" title="{{ $product['product'] }}">
                                {{ $product['product'] }}
                            </td>
                            <td class="py-2 pr-4 text-right text-gray-400">{{ $product['previous_count'] }}</td>
                            <td class="py-2 pr-4 text-right text-gray-600">{{ $product['current_count'] }}</td>
                            <td class="py-2 pr-4 text-right text-gray-400">{{ fmt($product['previous_amount']) }}</td>
                            <td class="py-2 pr-4 text-right text-gray-800">{{ fmt($product['current_amount']) }}</td>
                            <td class="py-2 text-right">{!! growth($product['current_amount'], $product['previous_amount']) !!}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- ─── 4. Booking.com per affiliate ID (campaign) ────────────────── --}}
        @if($bookingAffiliate->isNotEmpty())
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-700 mb-1">Booking.com per affiliate ID (campaign)</h3>
                <p class="text-xs text-gray-400 mb-5">Gebaseerd op het "Affiliate ID" veld uit de Booking.com export.</p>

                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-400 uppercase text-xs border-b border-gray-200">
                            <th class="pb-2 pr-4">Affiliate ID</th>
                            <th class="pb-2 pr-4 text-right">{{ $compareYear }} (boek.)</th>
                            <th class="pb-2 pr-4 text-right">{{ $currentYear }} (boek.)</th>
                            <th class="pb-2 pr-4 text-right">{{ $compareYear }}</th>
                            <th class="pb-2 pr-4 text-right">{{ $currentYear }}</th>
                            <th class="pb-2 text-right">YoY</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($bookingAffiliate as $row)
                        <tr>
                            <td class="py-2 pr-4 text-gray-700 font-mono text-xs">{{ $row['affiliate_id'] }}</td>
                            <td class="py-2 pr-4 text-right text-gray-400">{{ $row['previous_count'] }}</td>
                            <td class="py-2 pr-4 text-right text-gray-600">{{ $row['current_count'] }}</td>
                            <td class="py-2 pr-4 text-right text-gray-400">{{ fmt($row['previous_amount']) }}</td>
                            <td class="py-2 pr-4 text-right text-gray-800">{{ fmt($row['current_amount']) }}</td>
                            <td class="py-2 text-right">{!! growth($row['current_amount'], $row['previous_amount']) !!}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>
</div>
@endsection
