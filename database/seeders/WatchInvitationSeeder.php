<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Friendship;
use App\Models\WatchInvitation;
use App\Models\UserMedia;

class WatchInvitationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $acceptedFriendships = Friendship::where('status', 'accepted')->get();
        $statuses = ['pending', 'accepted', 'declined'];

        // Popular TMDB IDs for invitations
        $popularMedia = [
            550, 238, 424, 680, 155, 13, 769, 19404, 278, 429, // Movies
            1399, 1396, 94605, 31917, 1402, 456, 1668, 66732, 85552, 130392 // TV Shows
        ];

        foreach ($acceptedFriendships as $friendship) {
            // Each friendship gets 1-3 watch invitations
            $invitationCount = rand(1, 3);

            for ($i = 0; $i < $invitationCount; $i++) {
                $tmdbId = $popularMedia[array_rand($popularMedia)];
                
                // Check if invitation already exists for this friendship and media
                $existingInvitation = WatchInvitation::where('friendship_id', $friendship->id)
                    ->where('tmdb_id', $tmdbId)
                    ->first();

                if (!$existingInvitation) {
                    $status = $statuses[array_rand($statuses)];
                    
                    WatchInvitation::create([
                        'friendship_id' => $friendship->id,
                        'tmdb_id' => $tmdbId,
                        'status' => $status,
                    ]);

                    // If invitation is accepted, update UserMedia to reflect they're watching together
                    if ($status === 'accepted') {
                        // Check if both users have this media in their list
                        $userMedia = UserMedia::where('user_id', $friendship->user_id)
                            ->where('tmdb_id', $tmdbId)
                            ->first();

                        $friendMedia = UserMedia::where('user_id', $friendship->friend_id)
                            ->where('tmdb_id', $tmdbId)
                            ->first();

                        if ($userMedia) {
                            $userMedia->update([
                                'watching_with' => $friendship->friend_id,
                                'invitation_accepted' => true,
                            ]);
                        }

                        if ($friendMedia) {
                            $friendMedia->update([
                                'watching_with' => $friendship->user_id,
                                'invitation_accepted' => true,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
