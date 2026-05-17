<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Azoren: Azores island towns seen in GYG exports
        $this->addMatchers('Azoren', ['Sete Cidades', 'Faial', 'Horta', 'Calheta', 'Lajes', 'Angra']);

        // Marrakech
        $this->addMatchers('Marrakech', ['Marrakech', 'Marrakesh', 'Marokko', 'Morocco']);

        // Stockholm & Oslo: add city name so GYG "City" column matches
        $this->addMatchers('Stockholm', ['Stockholm']);
        $this->addMatchers('Oslo', ['Oslo']);

        // Amsterdam
        $amsterdam = DB::table('cities')->where('title', 'Amsterdam')->first();
        if (!$amsterdam) {
            DB::table('cities')->insert([
                'title'        => 'Amsterdam',
                'matchers'     => json_encode(['Amsterdam']),
                'country_codes'=> json_encode([]),
                'ufis'         => json_encode([]),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        } else {
            $this->addMatchers('Amsterdam', ['Amsterdam']);
        }

        // Helsinki
        $helsinki = DB::table('cities')->where('title', 'Helsinki')->first();
        if (!$helsinki) {
            DB::table('cities')->insert([
                'title'        => 'Helsinki',
                'matchers'     => json_encode(['Helsinki']),
                'country_codes'=> json_encode(['FI']),
                'ufis'         => json_encode([]),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        } else {
            $this->addMatchers('Helsinki', ['Helsinki']);
        }
    }

    private function addMatchers(string $cityTitle, array $newMatchers): void
    {
        $city = DB::table('cities')->where('title', $cityTitle)->first();
        if (!$city) {
            return;
        }
        $existing = json_decode($city->matchers ?? '[]', true);
        $merged   = array_values(array_unique(array_merge($existing, $newMatchers)));
        DB::table('cities')->where('id', $city->id)->update(['matchers' => json_encode($merged)]);
    }

    public function down(): void {}
};
