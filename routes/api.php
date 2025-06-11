<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\UserController;

Route::prefix('auth')->group(function () {
    /**
     * Autenticación con email y contraseña
     * POST /auth/login - Espera: email, password
     * Retorna: token de acceso y datos del usuario
     */
    Route::post('/login', [AuthController::class, 'login']);
    
    /**
     * Registro de nuevo usuario
     * POST /auth/register - Espera: name, email, password, password_confirmation
     * Retorna: token de acceso y datos del usuario creado
     */
    Route::post('/register', [AuthController::class, 'register']);
    
    /**
     * Cerrar sesión del token actual
     * POST /auth/logout - Requiere: autenticación Bearer token
     * Retorna: mensaje de confirmación
     */
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    
    /**
     * Obtener URL de redirección para OAuth
     * GET /auth/{provider}/redirect - Espera: provider (google|facebook)
     * Retorna: URL de autorización del proveedor
     */
    Route::get('/{provider}/redirect', [SocialAuthController::class, 'getRedirectUrl']);
    
    /**
     * Callback de Google OAuth
     * GET /auth/callback/google - Espera: code, state (de Google)
     * Retorna: redirección con token o error
     */
    Route::get('/callback/google', [SocialAuthController::class, 'handleCallback'])->defaults('provider', 'google');
    
    /**
     * Callback de Facebook OAuth
     * GET /auth/callback/facebook - Espera: code, state (de Facebook)
     * Retorna: redirección con token o error
     */
    Route::get('/callback/facebook', [SocialAuthController::class, 'handleCallback'])->defaults('provider', 'facebook');
    
    /**
     * Procesar código de autorización OAuth
     * GET /auth/{provider}/callback/process - Espera: code del proveedor
     * Retorna: token de acceso y datos del usuario
     */
    Route::get('/{provider}/callback/process', [SocialAuthController::class, 'processCallback'])
        ->where('provider', 'google|facebook');
    
    /**
     * Login con token de acceso del proveedor (para SPAs)
     * POST /auth/{provider}/token - Espera: access_token del proveedor
     * Retorna: token de acceso de la aplicación y datos del usuario
     */
    Route::post('/{provider}/token', [SocialAuthController::class, 'loginWithToken'])
        ->where('provider', 'google|facebook');
});

Route::middleware('auth:sanctum')->group(function () {
    /**
     * Obtener información del perfil del usuario
     * GET /user/profile - Requiere: autenticación Bearer token
     * Retorna: información completa del usuario autenticado
     */
    Route::get('/user/profile', [UserController::class, 'getProfile']);
    
    /**
     * Obtener medios del usuario por estado
     * GET /user/media/{status} - Requiere: autenticación Bearer token
     * Parámetros: status (watching|completed|planned)
     * Retorna: array de objetos {tmdb_id, type} del usuario autenticado
     */
    Route::get('/user/media/{status}', [UserController::class, 'getMediaByStatus'])
        ->where('status', 'watching|completed|planned');
    
    /**
     * Obtener estadísticas de medios del usuario
     * GET /user/media/stats - Requiere: autenticación Bearer token
     * Retorna: estadísticas de medios del usuario autenticado
     */
    Route::get('/user/media-stats', [UserController::class, 'getMediaStats']);
    
    /**
     * Suscribirse a una plataforma
     * POST /user/platforms/{platformId}/subscribe - Requiere: autenticación Bearer token
     * Retorna: confirmación de suscripción
     */
    Route::post('/user/platforms/{platformId}/subscribe', [UserController::class, 'subscribeToPlatform'])
        ->where('platformId', '[0-9]+');
    
    /**
     * Obtener plataformas suscritas
     * GET /user/platforms/subscribed - Requiere: autenticación Bearer token
     * Retorna: lista de plataformas a las que está suscrito el usuario
     */
    Route::get('/user/platforms/subscribed', [UserController::class, 'getSubscribedPlatforms']);
    
    /**
     * Desuscribirse de una plataforma
     * DELETE /user/platforms/{platformId}/unsubscribe - Requiere: autenticación Bearer token
     * Retorna: confirmación de desuscripción
     */
    Route::delete('/user/platforms/{platformId}/unsubscribe', [UserController::class, 'unsubscribeFromPlatform'])
        ->where('platformId', '[0-9]+');
    
    /**
     * Enviar petición de amistad
     * POST /user/friend-request - Requiere: autenticación Bearer token
     * Parámetros: friend_id (ID del usuario al que enviar la petición)
     * Retorna: confirmación de envío de petición
     */
    Route::post('/user/friend-request', [UserController::class, 'sendFriendRequest']);
    
    /**
     * Responder a petición de amistad
     * PATCH /user/friend-request/{friendshipId}/respond - Requiere: autenticación Bearer token
     * Parámetros: action (accept|decline)
     * Retorna: confirmación de respuesta a petición
     */
    Route::patch('/user/friend-request/{friendshipId}/respond', [UserController::class, 'respondToFriendRequest'])
        ->where('friendshipId', '[0-9]+');
    
    /**
     * Enviar invitación para ver contenido juntos
     * POST /user/watch-invitation - Requiere: autenticación Bearer token
     * Parámetros: friend_id, tmdb_id
     * Retorna: confirmación de envío de invitación
     */
    Route::post('/user/watch-invitation', [UserController::class, 'sendWatchInvitation']);
    
    /**
     * Responder a invitación de watch
     * PATCH /user/watch-invitation/{invitationId}/respond - Requiere: autenticación Bearer token
     * Parámetros: action (accept|decline)
     * Retorna: confirmación de respuesta a invitación
     */
    Route::patch('/user/watch-invitation/{invitationId}/respond', [UserController::class, 'respondToWatchInvitation'])
        ->where('invitationId', '[0-9]+');
    
    /**
     * Obtener invitaciones de watch recibidas
     * GET /user/watch-invitations/received/{status?} - Requiere: autenticación Bearer token
     * Parámetros: status (pending|accepted|declined) - opcional, por defecto 'pending'
     * Retorna: lista de invitaciones recibidas
     */
    Route::get('/user/watch-invitations/received/{status?}', [UserController::class, 'getReceivedWatchInvitations'])
        ->where('status', 'pending|accepted|declined');
    
    /**
     * Obtener invitaciones de watch del usuario
     * GET /user/watch-invitations/sent/{status?} - Requiere: autenticación Bearer token
     * Parámetros: status (pending|accepted|declined) - opcional, por defecto 'pending'
     * Retorna: lista de invitaciones de las amistades del usuario
     */
    Route::get('/user/watch-invitations/sent/{status?}', [UserController::class, 'getSentWatchInvitations'])
        ->where('status', 'pending|accepted|declined');
    
    /**
     * Obtener información social del usuario
     * GET /user/social - Requiere: autenticación Bearer token
     * Retorna: amigos, series completadas juntos y series viendo juntos
     */
    Route::get('/user/social', [UserController::class, 'getSocialData']);
    
    /**
     * Obtener recomendaciones de amigos
     * GET /user/friends/recommendations - Requiere: autenticación Bearer token
     * Retorna: array de tmdb_id recomendados por amigos
     */
    Route::get('/user/friends/recommendations', [UserController::class, 'getFriendsRecommendations']);
});
