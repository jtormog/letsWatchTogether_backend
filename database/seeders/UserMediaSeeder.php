<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserMedia;

class UserMediaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        
        // Popular movies and TV shows from TMDB
        $mediaData = [
            // Movies
            ['tmdb_id' => 550, 'type' => 'movie', 'title' => 'Fight Club'],
            ['tmdb_id' => 238, 'type' => 'movie', 'title' => 'The Godfather'],
            ['tmdb_id' => 424, 'type' => 'movie', 'title' => 'Schindlers List'],
            ['tmdb_id' => 680, 'type' => 'movie', 'title' => 'Pulp Fiction'],
            ['tmdb_id' => 155, 'type' => 'movie', 'title' => 'The Dark Knight'],
            ['tmdb_id' => 13, 'type' => 'movie', 'title' => 'Forrest Gump'],
            ['tmdb_id' => 769, 'type' => 'movie', 'title' => 'GoodFellas'],
            ['tmdb_id' => 19404, 'type' => 'movie', 'title' => 'Dilwale Dulhania Le Jayenge'],
            ['tmdb_id' => 278, 'type' => 'movie', 'title' => 'The Shawshank Redemption'],
            ['tmdb_id' => 429, 'type' => 'movie', 'title' => 'The Good, the Bad and the Ugly'],
            
            // TV Shows
            ['tmdb_id' => 1399, 'type' => 'tv', 'title' => 'Game of Thrones'],
            ['tmdb_id' => 1396, 'type' => 'tv', 'title' => 'Breaking Bad'],
            ['tmdb_id' => 94605, 'type' => 'tv', 'title' => 'Arcane'],
            ['tmdb_id' => 31917, 'type' => 'tv', 'title' => 'Pretty Little Liars'],
            ['tmdb_id' => 1402, 'type' => 'tv', 'title' => 'The Walking Dead'],
            ['tmdb_id' => 456, 'type' => 'tv', 'title' => 'The Simpsons'],
            ['tmdb_id' => 1668, 'type' => 'tv', 'title' => 'Friends'],
            ['tmdb_id' => 66732, 'type' => 'tv', 'title' => 'Stranger Things'],
            ['tmdb_id' => 85552, 'type' => 'tv', 'title' => 'Euphoria'],
            ['tmdb_id' => 130392, 'type' => 'tv', 'title' => 'The Boys'],
        ];

        $statuses = ['watching', 'completed', 'planned'];

        foreach ($users as $user) {
            // Each user gets 3-8 random media entries
            $userMediaCount = rand(3, 8);
            $selectedMedia = collect($mediaData)->random($userMediaCount);

            foreach ($selectedMedia as $media) {
                $status = $statuses[array_rand($statuses)];
                
                UserMedia::create([
                    'user_id' => $user->id,
                    'tmdb_id' => $media['tmdb_id'],
                    'type' => $media['type'],
                    'status' => $status,
                    'recommended' => rand(0, 1) == 1,
                    'episode' => $media['type'] === 'tv' && $status === 'watching' ? 'S' . rand(1, 3) . 'E' . rand(1, 12) : null,
                    'watching_with' => null, // We'll populate this later with friendships
                    'invitation_accepted' => false,
                ]);
            }
        }
    }
}
