<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Platform;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            ['name' => 'Netflix', 'code' => 1796],
            ['name' => 'HBO Max', 'code' => 384],
            ['name' => 'Disney+', 'code' => 337],
            ['name' => 'Amazon Prime Video', 'code' => 2100],
            ['name' => 'Apple TV+', 'code' => 350],
            ['name' => 'Crunchyroll', 'code' => 283],
            ['name' => 'Paramount+', 'code' => 531],
            ['name' => 'Hulu', 'code' => 15],
            ['name' => 'Peacock', 'code' => 386],
            ['name' => 'Discovery+', 'code' => 524],
        ];

        foreach ($platforms as $platform) {
            Platform::firstOrCreate(
                ['code' => $platform['code']],
                $platform
            );
        }
    }
}
