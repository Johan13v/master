<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rabaçal is a hiking/levada area on Madeira — GYG uses it as the city name.
        $this->addMatchers('Madeira', ['Rabaçal', 'Rabacal']);

        // Seyðisfjörður is an East Iceland fjord town covered by Iceland content.
        $this->addMatchers('Ijsland', ['Seyðisfjörður', 'Seydisfjordur']);

        // Potsdam is a Berlin day-trip destination; group it under Berlijn.
        $this->addMatchers('Berlijn', ['Potsdam']);
    }

    public function down(): void {}

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
};
