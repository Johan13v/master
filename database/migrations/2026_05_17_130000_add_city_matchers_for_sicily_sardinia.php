<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Sicilian cities — matched on Booking.com "City" column
        $sicilyMatchers = [
            'Palermo', 'Catania', 'Taormina', 'Siracusa', 'Syracuse',
            'Agrigento', 'Trapani', 'Marsala', 'Ragusa', 'Messina',
            'Noto', 'Cefalù', 'Cefalu', 'Sciacca', 'Enna',
            'Caltagirone', 'Modica', 'Scicli', 'Mazara', 'Erice',
            'Monreale', 'Bagheria', 'Acireale', 'Giardini Naxos',
            'Castellammare del Golfo', 'San Vito Lo Capo', 'Licata', 'Gela',
            'Porto Empedocle', 'Milazzo', 'Patti', 'Tindari', 'Sambuca',
            'Menfi', 'Selinunte', 'Segesta', 'Piazza Armerina',
            'Caltanissetta', 'Vittoria', 'Comiso', 'Pachino', 'Marzamemi',
            'Pozzallo', 'Ispica', 'Avola', 'Lentini', 'Augusta',
            'Aci Trezza', 'Aci Castello', 'Nicolosi', 'Linguaglossa',
            'Lipari', 'Stromboli', 'Vulcano', 'Panarea', 'Salina',
            'Pantelleria', 'Lampedusa', 'Favignana', 'Marettimo',
        ];

        // Sardinian cities — matched on Booking.com "City" column
        $sardiniaMatchers = [
            'Cagliari', 'Olbia', 'Sassari', 'Alghero', 'Nuoro',
            'Oristano', 'Porto Cervo', 'Villasimius', 'Pula',
            'Arbatax', 'Bosa', 'Arzachena', 'San Teodoro', 'Palau',
            'La Maddalena', 'Carloforte', 'Dorgali', 'Cala Gonone',
            'Santa Teresa Gallura', 'Tortolì', 'Costa Smeralda',
            'Stintino', 'Castelsardo', 'Porto Torres', 'Siniscola',
            'Posada', 'Orosei', 'Budoni', 'Valledoria', 'Trinità d\'Agultu',
            'Trinità d\'Agultu', 'Santa Margherita di Pula', 'Chia',
            'Teulada', 'Sant\'Antioco', 'Carbonia', 'Iglesias',
            'Muravera', 'Castiadas', 'Quartucciu', 'Quartu Sant\'Elena',
            'Porto Pino', 'Bari Sardo', 'Lotzorai',
            'Lanusei', 'Jerzu', 'Orgosolo', 'Mamoiada', 'Aritzo',
            'Sardinien', 'Sardegna', 'Sardinië',
        ];

        $this->addMatchers('Sicilië', $sicilyMatchers);
        $this->addMatchers('Sardinië', $sardiniaMatchers);
    }

    private function addMatchers(string $cityTitle, array $newMatchers): void
    {
        $city = DB::table('cities')->where('title', $cityTitle)->first();
        if (!$city) {
            return;
        }

        $existing = json_decode($city->matchers ?? '[]', true);
        $merged   = array_values(array_unique(array_merge($existing, $newMatchers)));

        DB::table('cities')
            ->where('id', $city->id)
            ->update(['matchers' => json_encode($merged)]);
    }

    public function down(): void
    {
        // No rollback needed — removing specific matchers is not worth the complexity
    }
};
