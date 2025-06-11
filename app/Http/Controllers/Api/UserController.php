<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Obtener medios del usuario por estado
     * 
     * @param Request $request
     * @param string $status
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMediaByStatus(Request $request, $status)
    {
        // Validar que el status sea uno de los valores permitidos
        $validStatuses = ['watching', 'completed', 'planned'];
        
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Valid statuses are: ' . implode(', ', $validStatuses),
                'data' => null
            ], 400);
        }

        // Obtener el usuario autenticado
        $user = $request->user();
        
        // Consultar los medios del usuario con el status especificado
        $media = DB::table('user_media')
            ->where('user_id', $user->id)
            ->where('status', $status)
            ->select('tmdb_id', 'type')
            ->get();

        return response()->json([
            'success' => true,
            'message' => "Media with status '{$status}' retrieved successfully",
            'data' => $media
        ]);
    }
    
    /**
     * Obtener estadísticas de medios del usuario
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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
    
    /**
     * Suscribirse a una plataforma
     * 
     * @param Request $request
     * @param int $platformId
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribeToPlatform(Request $request, $platformId)
    {
        $user = $request->user();
        
        // Verificar si la plataforma existe
        $platformExists = DB::table('platforms')->where('id', $platformId)->exists();
        
        if (!$platformExists) {
            return response()->json([
                'success' => false,
                'message' => 'Platform not found',
                'data' => null
            ], 404);
        }
        
        // Verificar si ya está suscrito
        $existingSubscription = DB::table('user_platform')
            ->where('user_id', $user->id)
            ->where('platform_id', $platformId)
            ->first();
            
        if ($existingSubscription) {
            // Activar la suscripción si estaba desactivada
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
        
        // Crear nueva suscripción
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
    
    /**
     * Obtener plataformas suscritas del usuario
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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
    
    /**
     * Desuscribirse de una plataforma
     * 
     * @param Request $request
     * @param int $platformId
     * @return \Illuminate\Http\JsonResponse
     */
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
        
        // Desactivar la suscripción
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
    
    /**
     * Enviar petición de amistad
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendFriendRequest(Request $request)
    {
        $request->validate([
            'friend_id' => 'required|integer|exists:users,id'
        ]);
        
        $user = $request->user();
        $friendId = $request->friend_id;
        
        // No permitir enviarse petición a sí mismo
        if ($user->id === $friendId) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot send friend request to yourself',
                'data' => null
            ], 400);
        }
        
        // Verificar si ya existe una petición pendiente o amistad activa
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
        
        // Crear nueva petición de amistad
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
            'data' => ['friendship_id' => $friendshipId]
        ], 201);
    }
    
    /**
     * Responder a petición de amistad (aceptar o denegar)
     * 
     * @param Request $request
     * @param int $friendshipId
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondToFriendRequest(Request $request, $friendshipId)
    {
        $request->validate([
            'action' => 'required|string|in:accept,decline'
        ]);
        
        $user = $request->user();
        $action = $request->action;
        
        // Buscar la petición de amistad
        $friendship = DB::table('friendships')
            ->where('id', $friendshipId)
            ->where('friend_id', $user->id) // Solo el receptor puede responder
            ->where('status', 'pending')
            ->first();
            
        if (!$friendship) {
            return response()->json([
                'success' => false,
                'message' => 'Friend request not found or not pending',
                'data' => null
            ], 404);
        }
        
        // Actualizar el estado según la acción
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
    
    /**
     * Enviar invitación para ver contenido juntos
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendWatchInvitation(Request $request)
    {
        $request->validate([
            'friend_id' => 'required|integer|exists:users,id',
            'tmdb_id' => 'required|integer'
        ]);
        
        $user = $request->user();
        $friendId = $request->friend_id;
        
        // No permitir enviarse invitación a sí mismo
        if ($user->id === $friendId) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot send watch invitation to yourself',
                'data' => null
            ], 400);
        }
        
        // Buscar la amistad entre los usuarios
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
        
        // Verificar si ya existe una invitación para el mismo contenido y amistad
        $existingInvitation = DB::table('watch_invitations')
            ->where('friendship_id', $friendship->id)
            ->where('tmdb_id', $request->tmdb_id)
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
        
        // Crear nueva invitación
        $invitationId = DB::table('watch_invitations')->insertGetId([
            'friendship_id' => $friendship->id,
            'tmdb_id' => $request->tmdb_id,
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
                'tmdb_id' => $request->tmdb_id
            ]
        ], 201);
    }
    
    /**
     * Responder a invitación de watch (aceptar o declinar)
     * 
     * @param Request $request
     * @param int $invitationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondToWatchInvitation(Request $request, $invitationId)
    {
        $request->validate([
            'action' => 'required|string|in:accept,decline'
        ]);
        
        $user = $request->user();
        $action = $request->action;
        
        // Buscar la invitación y verificar que el usuario puede responder
        $invitation = DB::table('watch_invitations')
            ->join('friendships', 'watch_invitations.friendship_id', '=', 'friendships.id')
            ->where('watch_invitations.id', $invitationId)
            ->where('watch_invitations.status', 'pending')
            ->where(function($query) use ($user) {
                // El usuario puede responder si es parte de la amistad
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
        
        // Actualizar el estado según la acción
        $newStatus = $action === 'accept' ? 'accepted' : 'declined';
        
        DB::table('watch_invitations')
            ->where('id', $invitationId)
            ->update([
                'status' => $newStatus,
                'updated_at' => now()
            ]);
        
        $message = $action === 'accept' 
            ? 'Watch invitation accepted successfully' 
            : 'Watch invitation declined successfully';
            
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'invitation_id' => $invitationId,
                'friendship_id' => $invitation->friendship_id,
                'tmdb_id' => $invitation->tmdb_id,
                'status' => $newStatus
            ]
        ]);
    }
    
    /**
     * Obtener invitaciones de watch recibidas
     * 
     * @param Request $request
     * @param string $status
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReceivedWatchInvitations(Request $request, $status = 'pending')
    {
        // Validar que el status sea uno de los valores permitidos
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
            ->where(function($query) use ($user) {
                // Solo mostrar invitaciones donde el usuario actual es parte de la amistad
                $query->where('friendships.user_id', $user->id)
                      ->orWhere('friendships.friend_id', $user->id);
            })
            ->select(
                'watch_invitations.id',
                'watch_invitations.friendship_id',
                'watch_invitations.tmdb_id',
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
    
    /**
     * Obtener invitaciones de watch del usuario (todas las de sus amistades)
     * 
     * @param Request $request
     * @param string $status
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSentWatchInvitations(Request $request, $status = 'pending')
    {
        // Validar que el status sea uno de los valores permitidos
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
            ->where(function($query) use ($user) {
                // Solo mostrar invitaciones donde el usuario actual es parte de la amistad
                $query->where('friendships.user_id', $user->id)
                      ->orWhere('friendships.friend_id', $user->id);
            })
            ->select(
                'watch_invitations.id',
                'watch_invitations.friendship_id',
                'watch_invitations.tmdb_id',
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
    
    /**
     * Obtener información social del usuario con sus amigos y actividad compartida
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSocialData(Request $request)
    {
        $user = $request->user();
        
        // Obtener todas las amistades aceptadas del usuario
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
                'friendships.created_at as friendship_since'
            )
            ->get();
        
        $socialData = [];
        
        foreach ($friendships as $friendship) {
            // Obtener series completadas juntos (invitaciones aceptadas)
            $completedTogether = DB::table('watch_invitations')
                ->where('friendship_id', $friendship->friendship_id)
                ->where('status', 'accepted')
                ->pluck('tmdb_id')
                ->toArray();
            
            // Obtener series que están viendo juntos (invitaciones pendientes)
            $watchingTogether = DB::table('watch_invitations')
                ->where('friendship_id', $friendship->friendship_id)
                ->where('status', 'pending')
                ->pluck('tmdb_id')
                ->toArray();
            
            // Construir datos del amigo
            $friendData = [
                'friendship_id' => $friendship->friendship_id,
                'friend' => [
                    'id' => $friendship->friend_id,
                    'name' => $friendship->friend_name,
                    'email' => $friendship->friend_email
                ],
                'friendship_since' => $friendship->friendship_since,
                'series_completed_together' => $completedTogether,
                'series_watching_together' => $watchingTogether,
                'total_completed' => count($completedTogether),
                'total_watching' => count($watchingTogether)
            ];
            
            $socialData[] = $friendData;
        }
        
        // Estadísticas generales
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
                    'email' => $user->email
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
    
    /**
     * Obtener información del perfil del usuario
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        
        // Obtener estadísticas de medios del usuario
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
        
        // Obtener número de amigos
        $friendsCount = DB::table('friendships')
            ->where(function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('friend_id', $user->id);
            })
            ->where('status', 'accepted')
            ->count();
        
        // Obtener plataformas suscritas
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
                    // Campos adicionales para compatibilidad con Next.js
                    'seriesVistas' => $mediaStats->tv_shows ?? 0,
                    'peliculasVistas' => $mediaStats->movies ?? 0,
                    'episodiosVistos' => 0, // Este cálculo podría agregarse después
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
}
