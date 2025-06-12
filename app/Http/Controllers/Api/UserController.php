<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function getMediaStats(Request $request)
    {
        $user = $request->user();
        
        $stats = DB::table('user_media')
            ->where('user_id', $user->id)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'watching' THEN 1 ELSE 0 END) as watching"),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status = 'planned' THEN 1 ELSE 0 END) as planned"),
                DB::raw("SUM(CASE WHEN type = 'movie' THEN 1 ELSE 0 END) as movies"),
                DB::raw("SUM(CASE WHEN type = 'tv' THEN 1 ELSE 0 END) as tv_shows"),
                DB::raw('SUM(CASE WHEN recommended = true THEN 1 ELSE 0 END) as recommended')
            )
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Media statistics retrieved successfully',
            'data' => $stats
        ]);
    }
    
    public function subscribeToPlatform(Request $request, $platformId)
    {
        $user = $request->user();
        
        $platformExists = DB::table('platforms')->where('id', $platformId)->exists();
        
        if (!$platformExists) {
            return response()->json([
                'success' => false,
                'message' => 'Platform not found',
                'data' => null
            ], 404);
        }
        
        $existingSubscription = DB::table('user_platform')
            ->where('user_id', $user->id)
            ->where('platform_id', $platformId)
            ->first();
            
        if ($existingSubscription) {
            DB::table('user_platform')
                ->where('user_id', $user->id)
                ->where('platform_id', $platformId)
                ->update(['is_active' => true, 'updated_at' => now()]);
                
            return response()->json([
                'success' => true,
                'message' => 'Subscription activated successfully',
                'data' => null
            ]);
        }
        
        DB::table('user_platform')->insert([
            'user_id' => $user->id,
            'platform_id' => $platformId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Subscribed to platform successfully',
            'data' => null
        ], 201);
    }
    
    public function getSubscribedPlatforms(Request $request)
    {
        $user = $request->user();
        
        $platforms = DB::table('user_platform')
            ->join('platforms', 'user_platform.platform_id', '=', 'platforms.id')
            ->where('user_platform.user_id', $user->id)
            ->where('user_platform.is_active', true)
            ->select('platforms.id', 'platforms.name', 'user_platform.username')
            ->get();
            
        return response()->json([
            'success' => true,
            'message' => 'Subscribed platforms retrieved successfully',
            'data' => $platforms
        ]);
    }
    
    public function unsubscribeFromPlatform(Request $request, $platformId)
    {
        $user = $request->user();
        
        $subscription = DB::table('user_platform')
            ->where('user_id', $user->id)
            ->where('platform_id', $platformId)
            ->where('is_active', true)
            ->first();
            
        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found or already inactive',
                'data' => null
            ], 404);
        }
        
        DB::table('user_platform')
            ->where('user_id', $user->id)
            ->where('platform_id', $platformId)
            ->update(['is_active' => false, 'updated_at' => now()]);
            
        return response()->json([
            'success' => true,
            'message' => 'Unsubscribed from platform successfully',
            'data' => null
        ]);
    }
    
    public function sendFriendRequest(Request $request)
    {
        $request->validate([
            'friend_email' => 'required|email|exists:users,email'
        ]);
        
        $user = $request->user();
        $friendEmail = $request->friend_email;
        
        $friend = DB::table('users')
            ->where('email', $friendEmail)
            ->first();
            
        if (!$friend) {
            return response()->json([
                'success' => false,
                'message' => 'User with this email not found',
                'data' => null
            ], 404);
        }
        
        $friendId = $friend->id;
        
        if ($user->id === $friendId) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot send friend request to yourself',
                'data' => null
            ], 400);
        }
        
        $existingFriendship = DB::table('friendships')
            ->where(function($query) use ($user, $friendId) {
                $query->where('user_id', $user->id)->where('friend_id', $friendId);
            })
            ->orWhere(function($query) use ($user, $friendId) {
                $query->where('user_id', $friendId)->where('friend_id', $user->id);
            })
            ->first();
            
        if ($existingFriendship) {
            if ($existingFriendship->status === 'accepted') {
                return response()->json([
                    'success' => false,
                    'message' => 'You are already friends with this user',
                    'data' => null
                ], 400);
            } elseif ($existingFriendship->status === 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Friend request already pending',
                    'data' => null
                ], 400);
            } elseif ($existingFriendship->status === 'blocked') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot send friend request to this user',
                    'data' => null
                ], 403);
            }
        }
        
        $friendshipId = DB::table('friendships')->insertGetId([
            'user_id' => $user->id,
            'friend_id' => $friendId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Friend request sent successfully',
            'data' => [
                'friendship_id' => $friendshipId,
                'friend' => [
                    'id' => $friend->id,
                    'name' => $friend->name,
                    'email' => $friend->email
                ]
            ]
        ], 201);
    }
    
    public function respondToFriendRequest(Request $request, $friendshipId)
    {
        $request->validate([
            'action' => 'required|string|in:accept,decline'
        ]);
        
        $user = $request->user();
        $action = $request->action;
        
        $friendship = DB::table('friendships')
            ->where('id', $friendshipId)
            ->where('friend_id', $user->id)
            ->where('status', 'pending')
            ->first();
            
        if (!$friendship) {
            return response()->json([
                'success' => false,
                'message' => 'Friend request not found or not pending',
                'data' => null
            ], 404);
        }
        
        $newStatus = $action === 'accept' ? 'accepted' : 'declined';
        $acceptedAt = $action === 'accept' ? now() : null;
        
        DB::table('friendships')
            ->where('id', $friendshipId)
            ->update([
                'status' => $newStatus,
                'accepted_at' => $acceptedAt,
                'updated_at' => now()
            ]);
        
        $message = $action === 'accept' 
            ? 'Friend request accepted successfully' 
            : 'Friend request declined successfully';
            
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'friendship_id' => $friendshipId,
                'status' => $newStatus
            ]
        ]);
    }
    
    public function sendWatchInvitation(Request $request)
    {
        $request->validate([
            'friend_id' => 'required|integer|exists:users,id',
            'tmdb_id' => 'required|integer',
            'type' => 'required|string|in:movie,tv'
        ]);
        
        $user = $request->user();
        $friendId = $request->friend_id;
        
        if ($user->id === $friendId) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot send watch invitation to yourself',
                'data' => null
            ], 400);
        }
        
        $friendship = DB::table('friendships')
            ->where(function($query) use ($user, $friendId) {
                $query->where('user_id', $user->id)->where('friend_id', $friendId);
            })
            ->orWhere(function($query) use ($user, $friendId) {
                $query->where('user_id', $friendId)->where('friend_id', $user->id);
            })
            ->where('status', 'accepted')
            ->first();
            
        if (!$friendship) {
            return response()->json([
                'success' => false,
                'message' => 'You can only send watch invitations to friends',
                'data' => null
            ], 403);
        }
        
        $existingInvitation = DB::table('watch_invitations')
            ->where('friendship_id', $friendship->id)
            ->where('tmdb_id', $request->tmdb_id)
            ->where('type', $request->type)
            ->first();
            
        if ($existingInvitation) {
            $statusMessage = $existingInvitation->status === 'pending' 
                ? 'You already have a pending invitation for this content with this user'
                : 'An invitation for this content already exists with this user';
            
            return response()->json([
                'success' => false,
                'message' => $statusMessage,
                'data' => null
            ], 400);
        }
        
        $invitationId = DB::table('watch_invitations')->insertGetId([
            'friendship_id' => $friendship->id,
            'sender_id' => $user->id,
            'tmdb_id' => $request->tmdb_id,
            'type' => $request->type,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Watch invitation sent successfully',
            'data' => [
                'invitation_id' => $invitationId,
                'friendship_id' => $friendship->id,
                'tmdb_id' => $request->tmdb_id,
                'type' => $request->type
            ]
        ], 201);
    }
    
    public function respondToWatchInvitation(Request $request, $invitationId)
    {
        $request->validate([
            'action' => 'required|string|in:accept,decline'
        ]);
        
        $user = $request->user();
        $action = $request->action;
        
        $invitation = DB::table('watch_invitations')
            ->join('friendships', 'watch_invitations.friendship_id', '=', 'friendships.id')
            ->where('watch_invitations.id', $invitationId)
            ->where('watch_invitations.status', 'pending')
            ->where(function($query) use ($user) {
                $query->where('friendships.user_id', $user->id)
                      ->orWhere('friendships.friend_id', $user->id);
            })
            ->select('watch_invitations.*', 'friendships.user_id as friendship_user_id', 'friendships.friend_id as friendship_friend_id')
            ->first();
            
        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Watch invitation not found, not pending, or you cannot respond to it',
                'data' => null
            ], 404);
        }
        
        $newStatus = $action === 'accept' ? 'accepted' : 'declined';
        
        DB::table('watch_invitations')
            ->where('id', $invitationId)
            ->update([
                'status' => $newStatus,
                'updated_at' => now()
            ]);
        
        $message = $action === 'accept' 
            ? 'Watch invitation accepted successfully' 
            : 'Watch invitation declined successfully';            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'invitation_id' => $invitationId,
                    'friendship_id' => $invitation->friendship_id,
                    'tmdb_id' => $invitation->tmdb_id,
                    'type' => $invitation->type,
                    'status' => $newStatus
                ]
            ]);
    }
    
    public function getReceivedWatchInvitations(Request $request, $status = 'pending')
    {
        $validStatuses = ['pending', 'accepted', 'declined'];
        
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Valid statuses are: ' . implode(', ', $validStatuses),
                'data' => null
            ], 400);
        }
        
        $user = $request->user();
        
        $invitations = DB::table('watch_invitations')
            ->join('friendships', 'watch_invitations.friendship_id', '=', 'friendships.id')
            ->join('users as friend', function($join) use ($user) {
                $join->on(function($query) use ($user) {
                    $query->where('friendships.user_id', $user->id)
                          ->whereColumn('friend.id', 'friendships.friend_id');
                })->orOn(function($query) use ($user) {
                    $query->where('friendships.friend_id', $user->id)
                          ->whereColumn('friend.id', 'friendships.user_id');
                });
            })
            ->where('watch_invitations.status', $status)
            ->where('watch_invitations.sender_id', '!=', $user->id)
            ->where(function($query) use ($user) {
                $query->where('friendships.user_id', $user->id)
                      ->orWhere('friendships.friend_id', $user->id);
            })
            ->select(
                'watch_invitations.id',
                'watch_invitations.friendship_id',
                'watch_invitations.tmdb_id',
                'watch_invitations.type',
                'watch_invitations.status',
                'watch_invitations.created_at',
                'friend.name as friend_name',
                'friend.email as friend_email'
            )
            ->orderBy('watch_invitations.created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'message' => "Watch invitations with status '{$status}' retrieved successfully",
            'data' => $invitations
        ]);
    }
    
    public function getSentWatchInvitations(Request $request, $status = 'pending')
    {
        $validStatuses = ['pending', 'accepted', 'declined'];
        
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Valid statuses are: ' . implode(', ', $validStatuses),
                'data' => null
            ], 400);
        }
        
        $user = $request->user();
        
        $invitations = DB::table('watch_invitations')
            ->join('friendships', 'watch_invitations.friendship_id', '=', 'friendships.id')
            ->join('users as friend', function($join) use ($user) {
                $join->on(function($query) use ($user) {
                    $query->where('friendships.user_id', $user->id)
                          ->whereColumn('friend.id', 'friendships.friend_id');
                })->orOn(function($query) use ($user) {
                    $query->where('friendships.friend_id', $user->id)
                          ->whereColumn('friend.id', 'friendships.user_id');
                });
            })
            ->where('watch_invitations.status', $status)
            ->where('watch_invitations.sender_id', $user->id)
            ->where(function($query) use ($user) {
                $query->where('friendships.user_id', $user->id)
                      ->orWhere('friendships.friend_id', $user->id);
            })
            ->select(
                'watch_invitations.id',
                'watch_invitations.friendship_id',
                'watch_invitations.tmdb_id',
                'watch_invitations.type',
                'watch_invitations.status',
                'watch_invitations.created_at',
                'friend.name as friend_name',
                'friend.email as friend_email'
            )
            ->orderBy('watch_invitations.created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'message' => "Watch invitations with status '{$status}' retrieved successfully",
            'data' => $invitations
        ]);
    }
    
    public function getSocialData(Request $request)
    {
        $user = $request->user();
        
        $friendships = DB::table('friendships')
            ->join('users as friend', function($join) use ($user) {
                $join->on(function($query) use ($user) {
                    $query->where('friendships.user_id', $user->id)
                          ->whereColumn('friend.id', 'friendships.friend_id');
                })->orOn(function($query) use ($user) {
                    $query->where('friendships.friend_id', $user->id)
                          ->whereColumn('friend.id', 'friendships.user_id');
                });
            })
            ->where('friendships.status', 'accepted')
            ->select(
                'friendships.id as friendship_id',
                'friend.id as friend_id',
                'friend.name as friend_name',
                'friend.email as friend_email',
                'friend.avatar as friend_avatar',
                'friendships.created_at as friendship_since'
            )
            ->get();
        
        $socialData = [];
        
        foreach ($friendships as $friendship) {
            $completedTogether = DB::table('watch_invitations')
                ->where('friendship_id', $friendship->friendship_id)
                ->where('status', 'accepted')
                ->select('tmdb_id', 'type')
                ->get()
                ->toArray();
            
            $watchingTogether = DB::table('watch_invitations')
                ->where('friendship_id', $friendship->friendship_id)
                ->where('status', 'pending')
                ->select('tmdb_id', 'type')
                ->get()
                ->toArray();
            
            $friendData = [
                'friendship_id' => $friendship->friendship_id,
                'friend' => [
                    'id' => $friendship->friend_id,
                    'name' => $friendship->friend_name,
                    'email' => $friendship->friend_email,
                    'avatar' => $friendship->friend_avatar
                ],
                'friendship_since' => $friendship->friendship_since,
                'series_completed_together' => $completedTogether,
                'series_watching_together' => $watchingTogether,
                'total_completed' => count($completedTogether),
                'total_watching' => count($watchingTogether)
            ];
            
            $socialData[] = $friendData;
        }
        
        $totalFriends = count($socialData);
        $totalSeriesCompleted = array_sum(array_column($socialData, 'total_completed'));
        $totalSeriesWatching = array_sum(array_column($socialData, 'total_watching'));
        
        return response()->json([
            'success' => true,
            'message' => 'Social data retrieved successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar
                ],
                'stats' => [
                    'total_friends' => $totalFriends,
                    'total_series_completed_together' => $totalSeriesCompleted,
                    'total_series_watching_together' => $totalSeriesWatching
                ],
                'friends' => $socialData
            ]
        ]);
    }
    
    public function getFriendsRecommendations(Request $request)
    {
        $user = $request->user();
        
        $friendIds = DB::table('friendships')
            ->where(function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('friend_id', $user->id);
            })
            ->where('status', 'accepted')
            ->get()
            ->map(function($friendship) use ($user) {
                return $friendship->user_id === $user->id ? $friendship->friend_id : $friendship->user_id;
            })
            ->toArray();
        
        if (empty($friendIds)) {
            return response()->json([
                'success' => true,
                'message' => 'No friends found',
                'data' => []
            ]);
        }
        
        $recommendedMedia = DB::table('user_media')
            ->whereIn('user_id', $friendIds)
            ->where('recommended', true)
            ->distinct()
            ->pluck('tmdb_id', 'type')
            ->toArray();
        
        return response()->json([
            'success' => true,
            'message' => 'Friends recommendations retrieved successfully',
            'data' => $recommendedMedia
        ]);
    }
    
    public function getProfile(Request $request)
    {
        $user = $request->user();
        
        $mediaStats = DB::table('user_media')
            ->where('user_id', $user->id)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'watching' THEN 1 ELSE 0 END) as watching"),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status = 'planned' THEN 1 ELSE 0 END) as planned"),
                DB::raw("SUM(CASE WHEN type = 'movie' THEN 1 ELSE 0 END) as movies"),
                DB::raw("SUM(CASE WHEN type = 'tv' THEN 1 ELSE 0 END) as tv_shows")
            )
            ->first();
        
        $friendsCount = DB::table('friendships')
            ->where(function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('friend_id', $user->id);
            })
            ->where('status', 'accepted')
            ->count();
        
        $platforms = DB::table('user_platform')
            ->join('platforms', 'user_platform.platform_id', '=', 'platforms.id')
            ->where('user_platform.user_id', $user->id)
            ->select('platforms.id', 'platforms.name')
            ->get();
        
        return response()->json([
            'success' => true,
            'message' => 'User profile retrieved successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'provider' => $user->provider,
                'avatar' => $user->avatar,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'stats' => [
                    'total_media' => $mediaStats->total ?? 0,
                    'watching' => $mediaStats->watching ?? 0,
                    'completed' => $mediaStats->completed ?? 0,
                    'planned' => $mediaStats->planned ?? 0,
                    'movies' => $mediaStats->movies ?? 0,
                    'tv_shows' => $mediaStats->tv_shows ?? 0,
                    'friends' => $friendsCount,
                    'seriesVistas' => $mediaStats->tv_shows ?? 0,
                    'peliculasVistas' => $mediaStats->movies ?? 0,
                    'episodiosVistos' => 0,
                    'amigos' => $friendsCount
                ],
                'platforms' => $platforms->toArray(),
                'preferences' => [
                    'language' => 'es',
                    'notifications' => true,
                    'autoplay' => true
                ],
                'subscription' => [
                    'platforms' => $platforms->pluck('name')->toArray(),
                    'plan' => 'basic'
                ]
            ]
        ]);
    }
    
    public function getFriendsWantToSee(Request $request)
    {
        $user = $request->user();
        
        $friendIds = DB::table('friendships')
            ->where(function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('friend_id', $user->id);
            })
            ->where('status', 'accepted')
            ->get()
            ->map(function($friendship) use ($user) {
                return $friendship->user_id === $user->id ? $friendship->friend_id : $friendship->user_id;
            })
            ->toArray();
        
        if (empty($friendIds)) {
            return response()->json([
                'success' => true,
                'message' => 'No friends found',
                'data' => []
            ]);
        }
        
        $friendsWantToSee = DB::table('user_media')
            ->join('users', 'user_media.user_id', '=', 'users.id')
            ->whereIn('user_media.user_id', $friendIds)
            ->where('user_media.status', 'planned')
            ->select(
                'user_media.tmdb_id',
                'user_media.type',
                'users.id as user_id',
                'users.name as user_name',
                'user_media.created_at as added_at'
            )
            ->orderBy('user_media.created_at', 'desc')
            ->get()
            ->groupBy('tmdb_id')
            ->map(function($mediaGroup) {
                $firstMedia = $mediaGroup->first();
                return [
                    'tmdb_id' => $firstMedia->tmdb_id,
                    'type' => $firstMedia->type,
                    'users_who_want_to_see' => $mediaGroup->map(function($media) {
                        return [
                            'user_id' => $media->user_id,
                            'user_name' => $media->user_name,
                            'added_at' => $media->added_at
                        ];
                    })->toArray()
                ];
            })
            ->values()
            ->toArray();
        
        return response()->json([
            'success' => true,
            'message' => 'Friends want to see media retrieved successfully',
            'data' => $friendsWantToSee
        ]);
    }
    
    public function getMediaByStatus(Request $request, $status)
    {
        $user = $request->user();
        
        $validStatuses = ['watching', 'completed', 'planned'];
        
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Valid statuses are: ' . implode(', ', $validStatuses),
                'data' => null
            ], 400);
        }
        
        $media = DB::table('user_media')
            ->where('user_id', $user->id)
            ->where('status', $status)
            ->select(
                'id',
                'tmdb_id',
                'type',
                'status',
                'recommended',
                'liked',
                'episode',
                'watching_with',
                'invitation_accepted',
                'created_at',
                'updated_at'
            )
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'message' => "Media with status '{$status}' retrieved successfully",
            'data' => $media
        ]);
    }
    
    public function getLikedMedia(Request $request)
    {
        $user = $request->user();
        
        $likedMedia = DB::table('user_media')
            ->where('user_id', $user->id)
            ->where('liked', true)
            ->select(
                'id',
                'tmdb_id',
                'type',
                'status',
                'recommended',
                'liked',
                'episode',
                'watching_with',
                'invitation_accepted',
                'created_at',
                'updated_at'
            )
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Liked media retrieved successfully',
            'data' => $likedMedia
        ]);
    }
    
    public function createOrUpdateUserMedia(Request $request)
    {
        $request->validate([
            'tmdb_id' => 'required|integer',
            'recommended' => 'required|boolean',
            'liked' => 'nullable|boolean',
            'type' => 'required|string|in:movie,tv',
            'status' => 'required|string|in:watching,completed,planned',
            'episode' => 'nullable|string'
        ]);
        
        $user = $request->user();
        
        $existingMedia = DB::table('user_media')
            ->where('user_id', $user->id)
            ->where('tmdb_id', $request->tmdb_id)
            ->first();
        
        $mediaData = [
            'user_id' => $user->id,
            'tmdb_id' => $request->tmdb_id,
            'recommended' => $request->recommended,
            'liked' => $request->liked ?? false,
            'type' => $request->type,
            'status' => $request->status,
            'episode' => $request->episode,
            'updated_at' => now()
        ];
        
        if ($existingMedia) {
            DB::table('user_media')
                ->where('id', $existingMedia->id)
                ->update($mediaData);
            
            $mediaData['id'] = $existingMedia->id;
            $mediaData['created_at'] = $existingMedia->created_at;
            $message = 'User media updated successfully';
        } else {
            $mediaData['created_at'] = now();
            $mediaId = DB::table('user_media')->insertGetId($mediaData);
            $mediaData['id'] = $mediaId;
            $message = 'User media created successfully';
        }
        
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $mediaData
        ], $existingMedia ? 200 : 201);
    }
    
    public function getUserMediaByTmdbId(Request $request, $tmdbId, $type)
    {
        $user = $request->user();
        
        if (!in_array($type, ['movie', 'tv'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid type. Valid types are: movie, tv',
                'data' => null
            ], 400);
        }
        
        $userMedia = DB::table('user_media')
            ->where('user_id', $user->id)
            ->where('tmdb_id', $tmdbId)
            ->where('type', $type)
            ->select(
                'id',
                'tmdb_id',
                'recommended',
                'liked',
                'type',
                'status',
                'episode',
                'watching_with',
                'invitation_accepted',
                'created_at',
                'updated_at'
            )
            ->first();
        
        if (!$userMedia) {
            return response()->json([
                'success' => false,
                'message' => 'Media not found for this user',
                'data' => null
            ], 404);
        }
        
        $watchingWithUser = null;
        if ($userMedia->watching_with) {
            $watchingWithUser = DB::table('users')
                ->where('id', $userMedia->watching_with)
                ->select('id', 'name', 'email')
                ->first();
        }
        
        $response = [
            'id' => $userMedia->id,
            'tmdb_id' => $userMedia->tmdb_id,
            'recommended' => (bool) $userMedia->recommended,
            'liked' => (bool) $userMedia->liked,
            'type' => $userMedia->type,
            'status' => $userMedia->status,
            'episode' => $userMedia->episode,
            'watching_with' => $userMedia->watching_with,
            'watching_with_user' => $watchingWithUser,
            'invitation_accepted' => (bool) $userMedia->invitation_accepted,
            'created_at' => $userMedia->created_at,
            'updated_at' => $userMedia->updated_at
        ];
        
        return response()->json([
            'success' => true,
            'message' => 'User media retrieved successfully',
            'data' => $response
        ]);
    }
    
    public function getFriendRequests(Request $request, $status = 'pending')
    {
        $validStatuses = ['pending', 'accepted', 'declined'];
        
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Valid statuses are: ' . implode(', ', $validStatuses),
                'data' => null
            ], 400);
        }
        
        $user = $request->user();
        
        $friendRequests = DB::table('friendships')
            ->join('users as sender', 'friendships.user_id', '=', 'sender.id')
            ->where('friendships.friend_id', $user->id)
            ->where('friendships.status', $status)
            ->select(
                'friendships.id as friendship_id',
                'friendships.status',
                'friendships.created_at',
                'friendships.updated_at',
                'friendships.accepted_at',
                'sender.id as sender_id',
                'sender.name as sender_name',
                'sender.email as sender_email',
                'sender.avatar as sender_avatar'
            )
            ->orderBy('friendships.created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'message' => "Friend requests with status '{$status}' retrieved successfully",
            'data' => $friendRequests
        ]);
    }
    
    public function getSentFriendRequests(Request $request, $status = 'pending')
    {
        $validStatuses = ['pending', 'accepted', 'declined'];
        
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Valid statuses are: ' . implode(', ', $validStatuses),
                'data' => null
            ], 400);
        }
        
        $user = $request->user();
        
        $sentRequests = DB::table('friendships')
            ->join('users as recipient', 'friendships.friend_id', '=', 'recipient.id')
            ->where('friendships.user_id', $user->id)
            ->where('friendships.status', $status)
            ->select(
                'friendships.id as friendship_id',
                'friendships.status',
                'friendships.created_at',
                'friendships.updated_at',
                'friendships.accepted_at',
                'recipient.id as recipient_id',
                'recipient.name as recipient_name',
                'recipient.email as recipient_email',
                'recipient.avatar as recipient_avatar'
            )
            ->orderBy('friendships.created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'message' => "Sent friend requests with status '{$status}' retrieved successfully",
            'data' => $sentRequests
        ]);
    }
}
