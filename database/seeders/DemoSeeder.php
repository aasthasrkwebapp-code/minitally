<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Entry;
use App\Services\SampleFiles;
use Illuminate\Database\Seeder;

/** Optional dummy data: 8 clients and 50 entries for the current month. php artisan db:seed --class=DemoSeeder */
class DemoSeeder extends Seeder
{
    public function run(SampleFiles $samples): void
    {
        foreach (SampleFiles::CLIENTS as $c) {
            Client::firstOrCreate(['name' => $c['name']], $c);
        }
        $clients = Client::pluck('id', 'name');

        foreach ($samples->dummyEntries() as $row) {
            Entry::create($row + ['client_id' => $clients[$row['party_name']]]);
        }
    }
}
