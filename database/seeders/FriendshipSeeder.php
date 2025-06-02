<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Friendship;

class FriendshipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $statuses = ['pending', 'accepted', 'declined'];

        // Create friendships between users
        foreach ($users as $user) {
            // Each user will have 2-5 friendships
            $friendshipCount = rand(2, 5);
            $potentialFriends = $users->where('id', '!=', $user->id)->random(min($friendshipCount, $users->count() - 1));

            foreach ($potentialFriends as $friend) {
                // Check if friendship already exists (in either direction)
                $existingFriendship = Friendship::where(function ($query) use ($user, $friend) {
                    $query->where('user_id', $user->id)->where('friend_id', $friend->id);
                })->orWhere(function ($query) use ($user, $friend) {
                    $query->where('user_id', $friend->id)->where('friend_id', $user->id);
                })->first();

                if (!$existingFriendship) {
                    $status = $statuses[array_rand($statuses)];
                    
                    Friendship::create([
                        'user_id' => $user->id,
                        'friend_id' => $friend->id,
                        'status' => $status,
                        'accepted_at' => $status === 'accepted' ? now()->subDays(rand(1, 30)) : null,
                    ]);
                }
            }
        }

        // Create some specific test friendships for known users
        $testUser = User::where('email', 'test@example.com')->first();
        $adminUser = User::where('email', 'admin@example.com')->first();
        $googleUser = User::where('email', 'google@example.com')->first();

        if ($testUser && $adminUser) {
            Friendship::firstOrCreate([
                'user_id' => $testUser->id,
                'friend_id' => $adminUser->id,
            ], [
                'status' => 'accepted',
                'accepted_at' => now()->subDays(5),
            ]);
        }

        if ($testUser && $googleUser) {
            Friendship::firstOrCreate([
                'user_id' => $testUser->id,
                'friend_id' => $googleUser->id,
            ], [
                'status' => 'accepted',
                'accepted_at' => now()->subDays(10),
            ]);
        }
    }
}
