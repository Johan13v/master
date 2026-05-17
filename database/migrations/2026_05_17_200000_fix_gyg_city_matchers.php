<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // "Palau" in Barcelona's matchers conflicts with GYG city "Palau" (Sardinian coastal town).
        // Replace the bare "Palau" matcher with a more specific substring so Booking.com product
        // titles like "Palau de la Música" still match, but the GYG city "Palau" does not.
        $barcelona = DB::table('cities')->where('title', 'Barcelona')->first();
        if ($barcelona) {
            $matchers = json_decode($barcelona->matchers ?? '[]', true);
            $matchers = array_values(array_map(
                fn($m) => $m === 'Palau' ? 'Palau de' : $m,
                $matchers
            ));
            DB::table('cities')->where('id', $barcelona->id)->update(['matchers' => json_encode($matchers)]);
        }

        // Ensure Sardinia has the "Palau" matcher for GYG (should already be there, but be safe).
        $this->addMatchers('Sardinië', ['Palau']);

        // GYG uses "Lajes Do Pico" as city name — Azoren already has "Lajes" which catches it via
        // strpos, but add the full form too for exact matching.
        $this->addMatchers('Azoren', ['Lajes Do Pico', 'Lajes do Pico']);

        // GYG uses "Marrakesh" (the English spelling) — already covered by Marrakech city matchers.
        // Add "Marrakesh" just in case it was missing.
        $this->addMatchers('Marrakech', ['Marrakesh']);
    }

    public function down(): void
    {
        // Restore "Palau" in Barcelona (reverting the specificity change)
        $barcelona = DB::table('cities')->where('title', 'Barcelona')->first();
        if ($barcelona) {
            $matchers = json_decode($barcelona->matchers ?? '[]', true);
            $matchers = array_values(array_map(
                fn($m) => $m === 'Palau de' ? 'Palau' : $m,
                $matchers
            ));
            DB::table('cities')->where('id', $barcelona->id)->update(['matchers' => json_encode($matchers)]);
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
};
