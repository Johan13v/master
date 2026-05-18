<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\City;
use App\Models\Import;
use App\Models\Website;
use App\Models\Commission;
use Illuminate\Http\Request;
use App\Models\RevenueStream;
use Illuminate\Support\Facades\DB;

class ImportController extends Controller
{

    public function index()
    {
        $imports = Import::with(['revenueStream', 'commissions'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('revenueStream.title')
            ->map(function ($streamImports) {
                $isApiStream = (bool) preg_match('/\d{4}-\d{2}-\d{2}/', $streamImports->first()->title);

                if ($isApiStream) {
                    return $streamImports->groupBy(function ($i) {
                        $year = preg_match('/(\d{4})-\d{2}-\d{2}/', $i->title, $m)
                            ? $m[1]
                            : $i->created_at->format('Y');
                        return 'year:' . $year;
                    })->map(fn($yearImports) => $yearImports->groupBy(function ($i) {
                        return preg_match('/(\d{4}-\d{2})-\d{2}/', $i->title, $m)
                            ? $m[1]
                            : $i->created_at->format('Y-m');
                    }));
                }

                return $streamImports->groupBy(fn($i) => 'id:' . $i->id);
            });

        return view('imports.index', compact('imports'));
    }

    public function showImportForm(RevenueStream $revenueStream)
    {
        return view('imports.form', compact('revenueStream'));
    }

    public function import(Request $request, RevenueStream $revenueStream)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
            'title' => 'required|string|max:255',
        ]);

        $path = $request->file('csv_file')->getRealPath();

        $fileContent = file_get_contents($path);
        $encoding = mb_detect_encoding($fileContent, ['UTF-8', 'UTF-16', 'ISO-8859-1'], true);

        // Convert to UTF-8 only if it's not already in UTF-8
        if ($encoding !== 'UTF-8') {
            $fileContent = mb_convert_encoding($fileContent, 'UTF-8', $encoding);
        }

        // Replace commas within quotes to avoid splitting on them
        // $fileContent = preg_replace_callback(
        //     '/"(.*?)"/',
        //     function ($matches) {
        //         return str_replace(',', '.', $matches[0]);
        //     },
        //     $fileContent
        // );

        $data = [];
        $tempFilePath = 'temp.csv';
        file_put_contents($tempFilePath, $fileContent);

        if (($handle = fopen($tempFilePath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
                // Replace placeholder back to commas
                $data[] = $row;
            }
            fclose($handle);
        }

        unlink($tempFilePath); // Clean up the temporary file


        $header = array_shift($data);

        $unmatchedRows = [];
        $duplicateRows = [];
        $stats = [
            'total_rows' => 0,
            'imported_count' => 0,
            'duplicate_count' => 0,
            'revoked_count' => 0,
        ];

        $import = Import::create([
            'revenue_stream_id' => $revenueStream->id,
            'title' => $request->title,
        ]);
        $cities = City::all();
        $websites = Website::all();

        DB::transaction(function () use ($data, $request, $header, $revenueStream, &$unmatchedRows, &$duplicateRows, &$stats, $import, $cities, $websites) {

            foreach ($data as $row) {

                if (count($row) !== count($header)) {
                    continue;
                }

                $stats['total_rows']++;

                $rowData = array_combine($header, $row);

                $commission = array(
                    'website' => null,
                    'city' => null,
                );

                if($request->csv_type == 'tiqets') {
                    $commission['referenceId'] = $rowData['reference_id'];
                    $commission['product'] = $rowData['product'];
                    $commission['amount'] = explode(' ', trim($rowData['commission']))[0];
                    $commission['orderDate'] = Carbon::parse($rowData['order_date'])->format('Y-m-d H:i:s ');
                    $commission['customerLanguage'] = $rowData['customer_language'];
                    $commission['status'] = $rowData['status'];
                    $commission['sitebrand'] = $rowData['sitebrand'];
                } elseif ($request->csv_type == 'booking') {
                    $commission['referenceId'] = $rowData['Booking number'];
                    $commission['product'] = $rowData['Property name'];
                    $commission['amount'] = explode(' ', trim($rowData['Your commission']))[0];
                    $commission['orderDate'] = Carbon::parse($rowData['Booking date'])->format('Y-m-d H:i:s');
                    $commission['customerLanguage'] = $rowData['Booker language'];
                    $commission['cityName'] = $rowData['City'];
                    $commission['country'] = $rowData['Country'];
                    $commission['ufi'] = $rowData['UFI'] ?? null;
                    $commission['affiliateId'] = $rowData['Affiliate ID'] ?? null;
                    $commission['status'] = $this->processStatus($rowData['Status']);
                } elseif ($request->csv_type == 'tradetracker') {
                    $commission['referenceId'] = $rowData['ID'];
                    $commission['product'] = $rowData['Campagne'];
                    $commission['amount'] = str_replace('€ ', '', str_replace(',', '.', $rowData['Commissie']));
                    $commission['orderDate'] = Carbon::parse($rowData['Registratiedatum'])->format('Y-m-d H:i:s');
                    $commission['customerLanguage'] = $rowData['Land'];
                    $commission['sitebrand'] = $rowData['Affiliatesite'];
                    $commission['status'] = $this->processStatus($rowData['Status']);
                } elseif ($request->csv_type == 'bajabikes') {
                    $commission['referenceId'] = $rowData['ID'];
                    $commission['product'] = $rowData['Product ID'];
                    $commission['amount'] = $rowData['Commission'];
                    $commission['orderDate'] = Carbon::parse($rowData['Created'])->format('Y-m-d H:i:s');
                    $commission['customerLanguage'] = 'NL';
                    // $commission['sitebrand'] = $rowData['Affiliatesite'];
                    $commission['status'] = $this->processStatus($rowData['Status']);
                } elseif ($request->csv_type == 'getyourguide') {
                    $commission['referenceId'] = $rowData['Booking Reference'];
                    $commission['product'] = $rowData['Activity'];
                    $commission['amount'] = str_replace(',', '', $rowData['Potential income']);
                    $commission['orderDate'] = Carbon::parse($rowData['Booking date'])->format('Y-m-d H:i:s');
                    $commission['customerLanguage'] = $rowData['Traveler origin'];
                    $commission['cityName'] = $rowData['City'];
                    $commission['status'] = $this->processStatus($rowData['Status']);
                } elseif ($request->csv_type == 'googleadsense') {
                    $commission['referenceId'] = $this->generateRandomString(15);
                    $commission['product'] = $rowData['Site'];
                    $commission['amount'] = $rowData['Geschatte inkomsten (EUR)'];
                    $commission['orderDate'] = Carbon::parse($rowData['Datum'])->format('Y-m-d H:i:s');
                    $commission['customerLanguage'] = '';
                    $commission['sitebrand'] = $rowData['Site'];
                    $commission['status'] = $this->processStatus('fulfilled');
                }

                if (Commission::where('reference_id', $commission['referenceId'])->exists()) {
                    $duplicateRows[] = $rowData;
                    $stats['duplicate_count']++;
                    continue;
                }

                if ($request->csv_type == 'tiqets') {
                    $commission = $this->matchTiqets($commission, $cities, $websites);
                } else if ($request->csv_type == 'booking') {
                    $commission = $this->matchBooking($commission, $cities, $websites);
                } elseif ($request->csv_type == 'tradetracker') {
                    $commission = $this->matchTradeTracker($commission, $cities, $websites);
                } elseif ($request->csv_type == 'bajabikes') {
                    $commission = $this->matchBajaBikes($commission, $cities, $websites);
                } elseif ($request->csv_type == 'getyourguide') {
                    $commission = $this->matchGetYourGuide($commission, $cities, $websites);
                } elseif ($request->csv_type == 'googleadsense') {
                    $commission = $this->matchGoogleAdsense($commission, $cities, $websites);
                }


                if ($commission['status'] == 'revoked') {
                    $stats['revoked_count']++;
                    continue;
                }

                [$unmatchedCity, $unmatchedWebsite] = $this->getUnmatchedFlags($commission);

                if (!$unmatchedCity && !$unmatchedWebsite) {
                    if (!isset($commission['website']->id)) {
                        dd($commission);
                    }
                    $website_id = $commission['website']->id;


                    if ($commission['city']->id == '15') {
                        $website_id = 8;
                    }

                    Commission::create([
                        'title' => $commission['product'],
                        'amount' => $commission['amount'],
                        'city_id' => $commission['city']->id,
                        'revenue_stream_id' => $revenueStream->id,
                        'website_id' => $website_id,
                        'import_id' => $import->id,
                        'order_date' => $commission['orderDate'],
                        'status' => $commission['status'],
                        'customer_language' => $commission['customerLanguage'],
                        'reference_id' => $commission['referenceId'],
                        'affiliate_id' => $commission['affiliateId'] ?? null,
                    ]);
                    $stats['imported_count']++;
                } else {
                    $unmatchedRows[] = [
                        'commission' => $commission,
                        'unmatchedCity' => $unmatchedCity,
                        'unmatchedWebsite' => $unmatchedWebsite,
                        'reason' => $this->buildUnmatchedReason($commission, $unmatchedCity, $unmatchedWebsite),
                    ];
                }
            }
        });

        $import->update([
            'total_rows' => $stats['total_rows'],
            'imported_count' => $stats['imported_count'],
            'duplicate_count' => $stats['duplicate_count'],
            'revoked_count' => $stats['revoked_count'],
            'unmatched_count' => count($unmatchedRows),
        ]);

        if (count($unmatchedRows) > 0) {
            $cities = City::all();
            $websites = Website::all();
            return view('imports.unmatched', compact('unmatchedRows', 'duplicateRows', 'revenueStream', 'import', 'cities', 'websites', 'stats'));
        }

        return redirect()->route('imports.index')->with('success', $this->buildImportSuccessMessage($stats));
    }

    public function updateMatchers(Request $request, RevenueStream $revenueStream)
    {
        $rows = $request->input('rows');
        $cityMatches = $request->input('city_matches');
        $cityNewMatchers = $request->input('city_new_matchers');
        $websiteMatches = $request->input('website_matches');
        $websiteNewMatchers = $request->input('website_new_matchers');
        $importId = $request->input('import_id');
        $remainingUnmatchedRows = [];
        $createdCount = 0;

        DB::transaction(function () use ($rows, $cityMatches, $cityNewMatchers, $websiteMatches, $websiteNewMatchers, $revenueStream, $importId, &$remainingUnmatchedRows, &$createdCount) {
            foreach ($rows as $index => $encodedRow) {
                $commission = json_decode(base64_decode($encodedRow), true);

                $city = null;
                if (isset($cityMatches[$index])) {
                    $city = City::find($cityMatches[$index]);
                    if ($city) {
                        $commission['city'] = $city;
                        $existingMatchers = $city->matchers ?? [];
                        if (isset($cityNewMatchers[$index]) && !in_array($cityNewMatchers[$index], $existingMatchers)) {
                            $city->matchers = array_merge($existingMatchers, [$cityNewMatchers[$index]]);
                            $city->save();
                        }
                    }
                }

                $website = null;
                if (isset($websiteMatches[$index])) {
                    $website = Website::find($websiteMatches[$index]);
                    if ($website) {
                        $commission['website'] = $website;
                        $existingMatchers = $website->matchers ?? [];
                        if (isset($websiteNewMatchers[$index]) && !in_array($websiteNewMatchers[$index], $existingMatchers)) {
                            $website->matchers = array_merge($existingMatchers, [$websiteNewMatchers[$index]]);
                            $website->save();
                        }
                    }
                }

                [$unmatchedCity, $unmatchedWebsite] = $this->getUnmatchedFlags($commission);

                if ($unmatchedCity || $unmatchedWebsite) {
                    $remainingUnmatchedRows[] = [
                        'commission' => $commission,
                        'unmatchedCity' => $unmatchedCity,
                        'unmatchedWebsite' => $unmatchedWebsite,
                        'reason' => $this->buildUnmatchedReason($commission, $unmatchedCity, $unmatchedWebsite),
                    ];
                    continue;
                }

                if ($commission['city'] && $commission['website']) {
                    $website_id = $commission['website']['id'];
                    if($commission['city']['id'] == '15') {
                        $website_id = 8;
                    }

                    Commission::create([
                        'title' => $commission['product'],
                        'amount' => $commission['amount'],
                        'city_id' => $commission['city']['id'],
                        'revenue_stream_id' => $revenueStream->id,
                        'website_id' => $website_id,
                        'import_id' => $importId,
                        'order_date' => $commission['orderDate'],
                        'status' => $commission['status'],
                        'customer_language' => $commission['customerLanguage'],
                        'reference_id' => $commission['referenceId'],
                        'affiliate_id' => $commission['affiliateId'] ?? null,
                    ]);
                    $createdCount++;
                }
            }
        });

        if ($importId) {
            $import = \App\Models\Import::find($importId);
            if ($import) {
                $import->update([
                    'unmatched_count' => count($remainingUnmatchedRows),
                    'imported_count' => $import->imported_count + $createdCount,
                ]);
            }
        }

        if (count($remainingUnmatchedRows) > 0) {
            $import = Import::findOrFail($importId);
            $cities = City::all();
            $websites = Website::all();
            $stats = [
                'total_rows' => $import->total_rows,
                'imported_count' => $import->imported_count,
                'duplicate_count' => $import->duplicate_count,
                'revoked_count' => $import->revoked_count,
            ];

            return view('imports.unmatched', [
                'unmatchedRows' => $remainingUnmatchedRows,
                'duplicateRows' => [],
                'revenueStream' => $revenueStream,
                'import' => $import,
                'cities' => $cities,
                'websites' => $websites,
                'stats' => $stats,
            ]);
        }

        $returnTo = $request->input('return_to');
        if ($returnTo && str_starts_with($returnTo, url('/'))) {
            return redirect($returnTo)->with('success', "Matchers opgeslagen en {$createdCount} commissies geïmporteerd.");
        }

        return redirect()->route('imports.index')->with('success', "Matchers opgeslagen en {$createdCount} commissies geïmporteerd.");
    }


    public function destroyByMonth(Request $request)
    {
        $request->validate([
            'import_ids'   => 'required|array',
            'import_ids.*' => 'integer|exists:imports,id',
        ]);

        $count = count($request->import_ids);

        DB::transaction(function () use ($request) {
            Import::whereIn('id', $request->import_ids)
                ->get()
                ->each(function ($import) {
                    $import->commissions()->delete();
                    $import->delete();
                });
        });

        return redirect()->route('imports.index')
            ->with('success', "{$count} import(s) verwijderd.");
    }

    public function backfillAffiliateIds(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt']);

        $path        = $request->file('csv_file')->getRealPath();
        $fileContent = file_get_contents($path);
        $encoding    = mb_detect_encoding($fileContent, ['UTF-8', 'UTF-16', 'ISO-8859-1'], true);
        if ($encoding !== 'UTF-8') {
            $fileContent = mb_convert_encoding($fileContent, 'UTF-8', $encoding);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'bkng_');
        file_put_contents($tempPath, $fileContent);

        // Auto-detect delimiter (Booking.com exports can be comma or tab separated)
        $firstLine = strtok($fileContent, "\n");
        $delimiter = substr_count($firstLine, "\t") > substr_count($firstLine, ',') ? "\t" : ',';

        $data = [];
        if (($handle = fopen($tempPath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 0, $delimiter, '"')) !== false) {
                $data[] = $row;
            }
            fclose($handle);
        }
        unlink($tempPath);

        $header = array_shift($data);
        // Strip BOM / whitespace from header field names
        $header = array_map(fn($h) => trim($h, " \t\r\n\xEF\xBB\xBF"), $header);

        if (!in_array('Booking number', $header) || !in_array('Affiliate ID', $header)) {
            return back()->withErrors(['csv_file' => 'CSV moet kolommen "Booking number" en "Affiliate ID" bevatten. Gevonden: ' . implode(', ', array_slice($header, 0, 5))]);
        }

        // Build reference_id → affiliate_id mapping from CSV (one pass, no DB queries yet)
        $mapping = [];
        foreach ($data as $row) {
            if (count($row) !== count($header)) continue;
            $rowData     = array_combine($header, $row);
            // Strip leading # that Booking.com sometimes adds to both fields
            $bookingNr   = ltrim(trim($rowData['Booking number'] ?? ''), '#');
            $affiliateId = ltrim(trim($rowData['Affiliate ID'] ?? ''), '#');
            if ($bookingNr && $affiliateId) {
                $mapping[$bookingNr] = $affiliateId;
            }
        }

        $updated  = 0;
        $notFound = 0;
        $skipped  = 0;

        // Group reference_ids by affiliate_id so we can do one bulk update per affiliate.
        // PHP converts pure-numeric string array keys to integers; cast back to string
        // so values stay as strings through array_chunk and into the PDO binding.
        $byAffiliate = [];
        foreach ($mapping as $refId => $affId) {
            $byAffiliate[(string) $affId][] = (string) $refId;
        }

        foreach ($byAffiliate as $affiliateId => $refIds) {
            // Chunk to keep individual WHERE IN clauses manageable.
            // Cast to string so PDO sends quoted values — prevents MySQL from casting the
            // varchar reference_id column to DOUBLE (which fails on values like '#8952520322').
            foreach (array_chunk($refIds, 500) as $chunk) {
                $chunk = array_map('strval', $chunk);

                $updated += DB::table('commissions')
                    ->whereIn('reference_id', $chunk)
                    ->whereNull('affiliate_id')
                    ->update(['affiliate_id' => (string) $affiliateId]);

                $skipped += DB::table('commissions')
                    ->whereIn('reference_id', $chunk)
                    ->whereNotNull('affiliate_id')
                    ->count();

                $found = DB::table('commissions')
                    ->whereIn('reference_id', $chunk)
                    ->count();

                $notFound += count($chunk) - $found;
            }
        }

        return back()->with('success', "{$updated} commissies bijgewerkt, {$skipped} al ingevuld, {$notFound} niet gevonden.");
    }

    public function breakdown(Import $import)
    {
        $commissions = $import->commissions()
            ->with(['city', 'website'])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();

        $byCity = $commissions
            ->groupBy('city_id')
            ->map(function ($group) {
                return [
                    'city'        => $group->first()->city,
                    'count'       => $group->count(),
                    'amount'      => $group->sum('amount'),
                    'commissions' => $group->values(),
                ];
            })
            ->sortByDesc('count');

        $cities = City::orderBy('title')->get();

        return view('imports.breakdown', compact('import', 'byCity', 'cities', 'commissions'));
    }

    private function getUnmatchedFlags(array $commission): array
    {
        return [
            $this->isUnknownEntity($commission['city'] ?? null),
            $this->isUnknownEntity($commission['website'] ?? null),
        ];
    }

    private function isUnknownEntity($entity): bool
    {
        if (!$entity) {
            return true;
        }

        $title = is_array($entity) ? ($entity['title'] ?? null) : ($entity->title ?? null);
        if (!$title) {
            return true;
        }

        return in_array(mb_strtolower(trim($title)), ['onbekend', 'onbekend..'], true);
    }

    private function buildUnmatchedReason(array $commission, bool $unmatchedCity, bool $unmatchedWebsite): string
    {
        $reasons = [];

        if ($unmatchedCity) {
            $reasons[] = 'Stad kon niet betrouwbaar gematcht worden';
        }

        if ($unmatchedWebsite) {
            $reasons[] = 'Website kon niet betrouwbaar gematcht worden';
        }

        if (isset($commission['cityName']) && $commission['cityName'] !== '') {
            $reasons[] = "Bronstad: {$commission['cityName']}";
        }

        return implode(' · ', $reasons);
    }

    private function buildImportSuccessMessage(array $stats): string
    {
        return sprintf(
            'Import verwerkt: %d toegevoegd, %d duplicates, %d revoked, %d unmatched.',
            $stats['imported_count'],
            $stats['duplicate_count'],
            $stats['revoked_count'],
            $stats['total_rows'] - $stats['imported_count'] - $stats['duplicate_count'] - $stats['revoked_count']
        );
    }

    public function reassign(Request $request, Import $import)
    {
        $request->validate([
            'from_city_id' => 'required|integer|exists:cities,id',
            'to_city_id'   => 'required|integer|exists:cities,id|different:from_city_id',
        ]);

        $count = $import->commissions()
            ->where('city_id', $request->from_city_id)
            ->update(['city_id' => $request->to_city_id]);

        return redirect()->route('imports.breakdown', $import)
            ->with('success', "{$count} commissies verplaatst.");
    }

    public function reassignCommission(Request $request, Commission $commission)
    {
        $request->validate([
            'city_id' => 'required|integer|exists:cities,id',
        ]);

        $city = City::find($request->city_id);
        $commission->update(['city_id' => $request->city_id]);

        return redirect()->route('imports.breakdown', $commission->import_id)
            ->with('success', "'{$commission->title}' verplaatst naar {$city->title}.");
    }

    public function destroy(Import $import)
    {
        DB::transaction(function () use ($import) {
            $import->commissions()->delete();
            $import->delete();
        });

        return redirect()->route('imports.index')->with('success', 'Import and associated commissions deleted successfully.');
    }

    public function matchTiqets($commission, $cities, $websites)
    {
        $sitebrand = strtolower($commission['sitebrand'] ?? '');
        $lang      = strtolower($commission['customerLanguage'] ?? '');

        // Website via sitebrand matchers
        foreach ($websites as $web) {
            foreach ($web->matchers as $matcher) {
                if (stripos($commission['sitebrand'], $matcher) !== false) {
                    $commission['website'] = $web;
                    break 2;
                }
            }
        }

        // City: sitebrand 'wegwijsnaarparijs' → always Paris
        if ($sitebrand === 'wegwijsnaarparijs') {
            $commission['city'] = City::whereJsonContains('matchers', 'Parijs')->first();
        } else {
            foreach ($cities as $c) {
                foreach ($c->matchers as $matcher) {
                    if (stripos($commission['product'], $matcher) !== false) {
                        $commission['city'] = $c;
                        break 2;
                    }
                }
            }
        }

        // German override: city=Parijs + DE → NachParis; city=Azoren + DE → AzorenPortugalDE
        if ($commission['city'] && in_array($lang, ['de', 'german', 'germany', 'deutsch'])) {
            if ($commission['city']->title === 'Parijs') {
                $commission['website'] = Website::whereJsonContains('matchers', 'Nachparis')->first();
            } elseif ($commission['city']->title === 'Azoren') {
                $commission['website'] = Website::whereJsonContains('matchers', 'AzorenPortugalDE')->first();
            }
        }

        return $commission;
    }


    public function matchBooking($commission, $cities, $websites)
    {
        $ufi = $commission['ufi'] ?? null;
        $country = $commission['country'] ?? null;
        $cityName = $commission['cityName'] ?? '';
        $isGerman = $commission['customerLanguage'] === 'DE';

        // 1. UFI match — most specific, distinguishes regions within same country
        //    (e.g. Sardinia vs Sicily vs Rome, Madeira vs Azores)
        if ($ufi) {
            foreach ($cities as $c) {
                if (!empty($c->ufis) && in_array((string) $ufi, array_map('strval', $c->ufis))) {
                    $commission['city'] = $c;
                    break;
                }
            }
        }

        // 2. City-name matchers — catches named cities stored in matchers (Paris, Oslo, Lisboa, etc.)
        if (!$commission['city']) {
            foreach ($cities as $c) {
                foreach ($c->matchers ?? [] as $matcher) {
                    if (stripos($cityName, $matcher) !== false) {
                        $commission['city'] = $c;
                        break 2;
                    }
                }
            }
        }

        // 3. Country-code match — for destinations that are a whole country
        //    (Japan → JP, Sri Lanka → LK, Iceland → IS, etc.)
        if (!$commission['city'] && $country) {
            foreach ($cities as $c) {
                if (!empty($c->country_codes) && in_array($country, $c->country_codes)) {
                    $commission['city'] = $c;
                    break;
                }
            }
        }

        // 4. Portugal fallback: anything PT not already matched → Azores
        if (!$commission['city'] && $country === 'PT') {
            $commission['city'] = City::whereJsonContains('matchers', 'Azoren')->first();
        }

        // 5. France fallback: anything FR not already matched → Paris
        if (!$commission['city'] && $country === 'FR') {
            $commission['city'] = City::whereJsonContains('matchers', 'Paris')->first();
        }

        // Website resolution
        $city = $commission['city'];

        if ($city && $city->title === 'Parijs') {
            $commission['website'] = $isGerman
                ? Website::whereJsonContains('matchers', 'Nachparis')->first()
                : Website::whereJsonContains('matchers', 'Wegwijsnaarparijs')->first();
        } elseif ($city && $city->title === 'Azoren') {
            $commission['website'] = $isGerman
                ? Website::whereJsonContains('matchers', 'AzorenPortugalDE')->first()
                : Website::whereJsonContains('matchers', 'De-azoren')->first();
        } elseif (!$commission['website']) {
            if ($isGerman) {
                $commission['website'] = Website::whereJsonContains('matchers', 'dasfreiheitsgefuhl')->first();
            } else {
                $commission['website'] = Website::whereJsonContains('matchers', 'Wegwijsnaar')->first();
            }
        }

        return $commission;
    }


    public function matchTradeTracker($commission, $cities, $websites)
    {
        $commission['website'] = Website::whereJsonContains('matchers', $commission['sitebrand'])->first();

        if ($commission['website'] != null && $commission['website']->title == 'De Azoren') {
            $commission['city'] = City::whereJsonContains('matchers', 'Azoren')->first();
            if ($commission['referenceId'] == 'mietwagenAzoren') {
                $commission['website'] = Website::whereJsonContains('matchers', 'AzorenPortugalDE')->first();
            }
        } elseif ($commission['website'] != null && in_array($commission['website']->title, ['Wegwijs naar Parijs', 'NachParis'])) {
            $commission['city'] = City::whereJsonContains('matchers', 'Paris')->first();
        } else {
            // Default case for other matchers
            foreach ($websites as $web) {
                foreach ($web->matchers as $matcher) {
                    if (strpos($commission['product'], $matcher) !== false) {
                        $commission['website'] = $web;
                        break 2;
                    }
                }
            }

            foreach ($cities as $c) {
                foreach ($c->matchers as $matcher) {
                    if (strpos($commission['product'], $matcher) !== false) {
                        $commission['city'] = $c;
                        break 2;
                    }
                }
            }
        }

        return $commission;
    }

    public function matchBajaBikes($commission, $cities, $websites)
    {
        foreach ($cities as $c) {
            foreach ($c->matchers as $matcher) {
                if (strpos($commission['product'], $matcher) !== false) {
                    $commission['city'] = $c;
                    break 2;
                }
            }
        }

        if ($commission['city'] != null && $commission['city']->title == 'Parijs') {
            $commission['website'] = Website::whereJsonContains('matchers', 'WegwijsnaarParijs')->first();
        } else {
            $commission['website'] = Website::whereJsonContains('matchers', 'Wegwijsnaar')->first();
        }

        return $commission;
    }

    public function matchGoogleAdsense($commission, $cities, $websites)
    {
        foreach ($websites as $c) {
            foreach ($c->matchers as $matcher) {
                if (strpos($commission['sitebrand'], $matcher) !== false) {
                    $commission['website'] = $c;
                    break 2;
                }
            }
        }

        if ($commission['website'] != null && $commission['website']->title == 'Wegwijs naar Parijs') {
            $commission['city'] = City::whereJsonContains('matchers', 'Parijs')->first();
        } else if ($commission['website'] != null && $commission['website']->title == 'NachParis') {
            $commission['city'] = City::whereJsonContains('matchers', 'Parijs')->first();
        } else if ($commission['website'] != null && $commission['website']->title == 'De Azoren') {
            $commission['city'] = City::whereJsonContains('matchers', 'Azoren')->first();
        } else if ($commission['website'] != null && $commission['website']->title == 'Azoren DE') {
            $commission['city'] = City::whereJsonContains('matchers', 'Azoren')->first();
        } else {
            $commission['city'] = City::whereJsonContains('matchers', 'Onbekend..')->first();
        }

        return $commission;
    }

    public function matchGetYourGuide($commission, $cities, $websites)
    {
        // GYG provides exact city names — use exact case-insensitive match to avoid
        // substring false positives (e.g. "Vienna" falsely matching the "Enna" Sicily matcher).
        $gygCity = strtolower(trim($commission['cityName'] ?? ''));
        foreach ($cities as $c) {
            foreach ($c->matchers as $matcher) {
                if ($gygCity === strtolower(trim($matcher))) {
                    $commission['city'] = $c;
                    break 2;
                }
            }
        }

        // Only fall back to title matching when GYG did not provide a city name at all.
        if ($commission['city'] == null && $gygCity === '') {
            foreach ($cities as $c) {
                foreach ($c->matchers as $matcher) {
                    if (stripos($commission['product'], $matcher) !== false) {
                        $commission['city'] = $c;
                        break 2;
                    }
                }
            }
        }

        if ($commission['city'] != null && ($commission['city']->title == 'Azoren' || $commission['city'] == 'Azores' || $commission['city'] == 'Santa Cruz Das Flores')) {
            if($commission['customerLanguage'] == 'Switzerland' || $commission['customerLanguage'] == 'Austria' || $commission['customerLanguage'] == 'Germany'){
                $commission['website'] = Website::whereJsonContains('matchers', 'AzorenPortugalDE')->first();
            } else {
                $commission['website'] = Website::whereJsonContains('matchers', 'De-azoren')->first();
            }
        } else if ($commission['city'] != null && ($commission['city']->title == 'Parijs' | $commission['city']->title == 'Paris')) {
            if ($commission['customerLanguage'] == 'Switzerland' || $commission['customerLanguage'] == 'Austria' || $commission['customerLanguage'] == 'Germany') {
                $commission['website'] = Website::whereJsonContains('matchers', 'Nachparis')->first();
            } else {
                $commission['website'] = Website::whereJsonContains('matchers', 'Wegwijsnaarparijs')->first();
            }
        } else {
            $commission['website'] = Website::whereJsonContains('matchers', 'Wegwijsnaar')->first();
        }

        return $commission;
    }


    function processStatus($status) {
        switch ($status) {
            case 'A':
            case 'Stayed':
            case 'Geaccepteerd':
            case 'Completed':
                return 'fulfilled';
            case 'Cancelled by guest':
            case 'Cancelled by property':
            case 'No-show':
            case 'Cancelled':
            case 'Afgewezen':
            case 'revoked':
            case 'D':
            case 'Canceled':
                return 'revoked';
            case 'Booked':
            case 'Onder beoordeling':
            case 'P':
            case 'Pending':
                return 'pending';
        }

        return 'fulfilled';
    }

    function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
