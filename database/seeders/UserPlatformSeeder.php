<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Platform;
use Illuminate\Support\Facades\DB;

class UserPlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $platforms = Platform::all();

        foreach ($users as $user) {
            // Each user subscribes to 2-5 random platforms
            $platformCount = rand(2, 5);
            $selectedPlatforms = $platforms->random(min($platformCount, $platforms->count()));

            foreach ($selectedPlatforms as $platform) {
                // Check if relationship already exists
                $exists = DB::table('user_platform')
                    ->where('user_id', $user->id)
                    ->where('platform_id', $platform->id)
                    ->exists();

                if (!$exists) {
                    DB::table('user_platform')->insert([
                        'user_id' => $user->id,
                        'platform_id' => $platform->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Ensure specific test users have popular platforms
        $testUser = User::where('email', 'test@example.com')->first();
        $adminUser = User::where('email', 'admin@example.com')->first();
        
        $popularPlatforms = Platform::whereIn('name', ['Netflix', 'Disney+', 'Amazon Prime Video'])->get();

        if ($testUser) {
            foreach ($popularPlatforms as $platform) {
                $exists = DB::table('user_platform')
                    ->where('user_id', $testUser->id)
                    ->where('platform_id', $platform->id)
                    ->exists();

                if (!$exists) {
                    DB::table('user_platform')->insert([
                        'user_id' => $testUser->id,
                        'platform_id' => $platform->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if ($adminUser) {
            foreach ($popularPlatforms as $platform) {
                $exists = DB::table('user_platform')
                    ->where('user_id', $adminUser->id)
                    ->where('platform_id', $platform->id)
                    ->exists();

                if (!$exists) {
                    DB::table('user_platform')->insert([
                        'user_id' => $adminUser->id,
                        'platform_id' => $platform->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
