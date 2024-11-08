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
        $imports = Import::with('revenueStream')->get();
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

        $import = Import::create([
            'revenue_stream_id' => $revenueStream->id,
            'title' => $request->title,
        ]);
        $cities = City::all();
        $websites = Website::all();

        DB::transaction(function () use ($data, $request, $header, $revenueStream, &$unmatchedRows, $import, $cities, $websites) {

            foreach ($data as $row) {

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
                    $commission['amount'] = $rowData['Potential income'];
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
                    continue;
                }

                if ($commission['city'] && $commission['website']) {
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
                    ]);
                } else {
                    $unmatchedRows[] = [
                        'commission' => $commission,
                        'unmatchedCity' => !$commission['city'],
                        'unmatchedWebsite' => !$commission['website'],
                    ];
                }
            }
        });

        if (count($unmatchedRows) > 0) {
            $cities = City::all();
            $websites = Website::all();
            return view('imports.unmatched', compact('unmatchedRows', 'duplicateRows', 'revenueStream', 'import', 'cities', 'websites'));
        }

        return redirect()->route('imports.index')->with('success', 'Commissions imported successfully.');
    }

    public function updateMatchers(Request $request, RevenueStream $revenueStream)
    {
        $rows = $request->input('rows');
        $cityMatches = $request->input('city_matches');
        $cityNewMatchers = $request->input('city_new_matchers');
        $websiteMatches = $request->input('website_matches');
        $websiteNewMatchers = $request->input('website_new_matchers');
        $importId = $request->input('import_id');

        DB::transaction(function () use ($rows, $cityMatches, $cityNewMatchers, $websiteMatches, $websiteNewMatchers, $revenueStream, $importId) {
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
                    ]);
                }
            }
        });

        return redirect()->route('imports.index')->with('success', 'Matchers updated and commissions imported successfully.');
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
        // Special case for sitebrand 'Wegwijsnaarparijs'
        if ($commission['sitebrand'] == 'Wegwijsnaarparijs' && $commission['customerLanguage'] == 'de') {
            $commission['website'] = Website::whereJsonContains('matchers', 'Nachparis')->first();
        } elseif ($commission['sitebrand'] == 'De-azoren' && $commission['customerLanguage'] == 'de') {
            $commission['website'] = Website::whereJsonContains('matchers', 'AzorenPortugalDE')->first();
        } else {
            // Check for website matchers
            foreach ($websites as $web) {
                foreach ($web->matchers as $matcher) {
                    if (strpos($commission['sitebrand'], $matcher) !== false) {
                        $commission['website'] = $web;
                        break 2;
                    }
                }
            }
        }

        // Special case for product 'Parijs'
        if ($commission['sitebrand'] == 'Wegwijsnaarparijs') {
            $commission['city'] = City::whereJsonContains('matchers', 'Parijs')->first();
        } else {
            // Check for city matchers
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


    public function matchBooking($commission, $cities, $websites)
    {
        // Special cases for cities
        if ($commission['cityName'] == 'Paris') {
            $commission['city'] = City::whereJsonContains('matchers', 'Paris')->first();
            if ($commission['customerLanguage'] == 'DE') {
                $commission['website'] = Website::whereJsonContains('matchers', 'Nachparis')->first();
            } else {
                $commission['website'] = Website::whereJsonContains('matchers', 'Wegwijsnaarparijs')->first();
            }
        } elseif ($commission['country'] == 'PT' && !in_array($commission['cityName'], ['Lisboa', 'Sintra'])) {
            $commission['city'] = City::whereJsonContains('matchers', 'Azoren')->first();
            if ($commission['customerLanguage'] == 'DE') {
                $commission['website'] = Website::whereJsonContains('matchers', 'AzorenPortugalDE')->first();
            } else {
                $commission['website'] = Website::whereJsonContains('matchers', 'De-azoren')->first();
            }
        } elseif ($commission['country'] == 'IS') {
            $commission['city'] = City::whereJsonContains('matchers', 'IJsland')->first();
            $commission['website'] = Website::whereJsonContains('matchers', 'Wegwijsnaar')->first();
        } else {
            // Default case for other city matchers
            foreach ($cities as $c) {
                foreach ($c->matchers as $matcher) {
                    if (strpos($commission['cityName'], $matcher) !== false) {
                        $commission['city'] = $c;
                        break 2;
                    }
                }
            }

            // Default case for website matchers
            if (!$commission['website']) {
                foreach ($websites as $web) {
                    foreach ($web->matchers as $matcher) {
                        if (strpos($commission['product'], $matcher) !== false) {
                            $commission['website'] = $web;
                            break 2;
                        }
                    }
                }
            }
        }

        // Ensure website defaults to Wegwijsnaar if still null
        if($commission['country'] == 'FR' && $commission['city'] == null) {
            $commission['city'] = City::whereJsonContains('matchers', 'Paris')->first();
            if ($commission['customerLanguage'] == 'DE') {
                $commission['website'] = Website::whereJsonContains('matchers', 'Nachparis')->first();
            } else {
                $commission['website'] = Website::whereJsonContains('matchers', 'Wegwijsnaarparijs')->first();
            }
        } else if (!$commission['website']) {
            if ($commission['customerLanguage'] == 'DE') {
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
        } elseif ($commission['website'] != null && $commission['website']->title == 'Wegwijs naar Parijs') {
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
        foreach ($cities as $c) {
            foreach ($c->matchers as $matcher) {
                if (strpos($commission['cityName'], $matcher) !== false) {
                    $commission['city'] = $c;
                    break 2;
                }
            }
        }

        if($commission['city'] == null) {
            foreach ($cities as $c) {
                foreach ($c->matchers as $matcher) {
                    if (strpos($commission['product'], $matcher) !== false) {
                        $commission['city'] = $c;
                        break 2;
                    }
                }
            }
        }

        if ($commission['city'] != null && $commission['city']->title == 'Azoren') {
            if($commission['customerLanguage'] == 'Switzerland' || $commission['customerLanguage'] == 'Austria' || $commission['customerLanguage'] == 'Germany'){
                $commission['website'] = Website::whereJsonContains('matchers', 'AzorenPortugalDE')->first();
            } else {
                $commission['website'] = Website::whereJsonContains('matchers', 'De-azoren')->first();
            }
        } else if ($commission['city'] != null && $commission['city']->title == 'Parijs') {
            if ($commission['customerLanguage'] == 'Switzerland' || $commission['customerLanguage'] == 'Austria' || $commission['customerLanguage'] == 'Germany') {
                $commission['website'] = Website::whereJsonContains('matchers', 'NachParis')->first();
            } else {
                $commission['website'] = Website::whereJsonContains('matchers', 'WegwijsnaarParijs')->first();
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
