<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\UserController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/{provider}/redirect', [SocialAuthController::class, 'getRedirectUrl']);
    Route::get('/callback/google', [SocialAuthController::class, 'handleCallback'])->defaults('provider', 'google');
    Route::get('/callback/facebook', [SocialAuthController::class, 'handleCallback'])->defaults('provider', 'facebook');
    Route::get('/{provider}/callback/process', [SocialAuthController::class, 'processCallback'])
        ->where('provider', 'google|facebook');
    Route::post('/{provider}/token', [SocialAuthController::class, 'loginWithToken'])
        ->where('provider', 'google|facebook');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/profile', [UserController::class, 'getProfile']);
    Route::get('/user/media/{status}', [UserController::class, 'getMediaByStatus'])
        ->where('status', 'watching|completed|planned');
    Route::get('/user/media/liked', [UserController::class, 'getLikedMedia']);
    Route::get('/user/media-stats', [UserController::class, 'getMediaStats']);
    Route::get('/user/media/tmdb/{tmdbId}/{type}', [UserController::class, 'getUserMediaByTmdbId'])
        ->where('tmdbId', '[0-9]+')
        ->where('type', 'movie|tv');
    Route::post('/user/media', [UserController::class, 'createOrUpdateUserMedia']);
    Route::post('/user/platforms/{platformId}/subscribe', [UserController::class, 'subscribeToPlatform'])
        ->where('platformId', '[0-9]+');
    Route::get('/user/platforms/subscribed', [UserController::class, 'getSubscribedPlatforms']);
    Route::delete('/user/platforms/{platformId}/unsubscribe', [UserController::class, 'unsubscribeFromPlatform'])
        ->where('platformId', '[0-9]+');
    Route::post('/user/friend-request', [UserController::class, 'sendFriendRequest']);
    Route::get('/user/friend-requests/received/{status?}', [UserController::class, 'getFriendRequests'])
        ->where('status', 'pending|accepted|declined');
    Route::get('/user/friend-requests/sent/{status?}', [UserController::class, 'getSentFriendRequests'])
        ->where('status', 'pending|accepted|declined');
    Route::patch('/user/friend-request/{friendshipId}/respond', [UserController::class, 'respondToFriendRequest'])
        ->where('friendshipId', '[0-9]+');
    Route::post('/user/watch-invitation', [UserController::class, 'sendWatchInvitation']);
    Route::patch('/user/watch-invitation/{invitationId}/respond', [UserController::class, 'respondToWatchInvitation'])
        ->where('invitationId', '[0-9]+');
    Route::get('/user/watch-invitations/received/{status}', [UserController::class, 'getReceivedWatchInvitations'])
        ->where('status', 'pending|accepted|declined');
    Route::get('/user/watch-invitations/sent/{status}', [UserController::class, 'getSentWatchInvitations'])
        ->where('status', 'pending|accepted|declined');
    Route::get('/user/social', [UserController::class, 'getSocialData']);
    Route::get('/user/friends/recommendations', [UserController::class, 'getFriendsRecommendations']);
    Route::get('/user/friends/want-to-see', [UserController::class, 'getFriendsWantToSee']);
});
