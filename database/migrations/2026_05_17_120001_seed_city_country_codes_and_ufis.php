<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // UFI values extracted from Booking.com export data.
    // country_codes are used for destinations that cover an entire country.
    // ufis are used for regional matching within a country (e.g. Sardinia vs Sicily vs Rome).
    private array $cityData = [
        // Dutch title => [country_codes, ufis]
        'Parijs'         => [[], ['-1456928']],
        'Rome'           => [[], ['-126693']],
        'IJsland'        => [['IS'], []],
        'Londen'         => [[], ['-2601889', '-2606778']],
        'Berlijn'        => [[], ['-1746443']],
        'Athene'         => [[], ['-814876']],
        'Oslo'           => [['NO'], ['-273837']],
        'Stockholm'      => [['SE'], ['-2524279']],
        'Barcelona'      => [[], ['-372490']],
        'Valencia'       => [[], ['-406131']],
        'Praag'          => [[], ['-2142714']],
        'Tallinn'        => [['EE'], []],
        'Marrakech'      => [[], ['-38833']],
        'Madeira'        => [[], ['-2166199', '-2174445']],
        'Sicilië'        => [[], ['-123045', '-111031', '-110017', '-118368', '-129266', '-114419']],
        'Sardinië'       => [[], ['-110765', '-113171', '-116905', '-127667']],
        'Japan'          => [['JP'], []],
        'Sri lanka'      => [['LK'], []],
        'Oman'           => [['OM'], []],
        'De Seychellen'  => [['SC'], []],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->cityData as $title => [$countryCodes, $ufis]) {
            $payload = [
                'country_codes' => $countryCodes ? json_encode($countryCodes) : null,
                'ufis'          => $ufis ? json_encode($ufis) : null,
                'updated_at'    => $now,
            ];

            // Try exact title first, then case-insensitive fallback.
            $affected = DB::table('cities')->where('title', $title)->update($payload);

            if (!$affected) {
                $affected = DB::table('cities')
                    ->whereRaw('LOWER(title) = ?', [strtolower($title)])
                    ->update($payload);
            }

            // City does not exist yet — create it with empty matchers so it is ready to use.
            if (!$affected) {
                DB::table('cities')->insert(array_merge($payload, [
                    'title'      => $title,
                    'matchers'   => json_encode([]),
                    'created_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('cities')->update([
            'country_codes' => null,
            'ufis'          => null,
        ]);
    }
};
