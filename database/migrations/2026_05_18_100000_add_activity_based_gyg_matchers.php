<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Sardinië: Golfo Aranci is a northern Sardinian town used by GYG as a city name
        // for dolphin watching / kayak tours.
        $this->addMatchers('Sardinië', ['Golfo Aranci']);

        // Azoren: GYG uses island/town names instead of "Azores" for many activities.
        // Ponta Delgada  — capital of São Miguel (GYG city for canyoning, whale watching)
        // São Miguel     — largest island; activity prefix e.g. "São Miguel: WaterPark Canyoning"
        // Vila Franca do Campo — small town on São Miguel; used as GYG city for snorkeling islet tours
        // Terceira       — island; GYG city / activity prefix for Algar do Carvão cave tours
        // Pico           — island; activity prefix for whale watching and volcano climb
        // Ribeira dos Caldeirões — São Miguel canyon; appears in activity titles without a GYG city match
        // Algar do Carvão — Terceira lava cave; appears in activity titles
        $this->addMatchers('Azoren', [
            'Ponta Delgada',
            'São Miguel',
            'Vila Franca do Campo',
            'Terceira',
            'Pico',
            'Pico Island',
            'Ribeira dos Caldeirões',
            'Algar do Carvão',
        ]);

        // IJsland: Vik is a southern Iceland village used by GYG as a city for Katla/Reynisfjara tours.
        // Katla is the glacier volcano featured in "Katla Ice Cave" activities.
        $this->addMatchers('Ijsland', ['Vik', 'Katla']);

        // Madeira: Funchal is the island capital — GYG city column for most Madeira activities.
        // Caniço is a coastal village on Madeira used as location prefix in activity titles.
        $this->addMatchers('Madeira', ['Funchal', 'Caniço']);
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
