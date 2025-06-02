<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    // Supported OAuth providers
    private const SUPPORTED_PROVIDERS = ['google', 'facebook'];

    // Common user response structure
    private function formatUserResponse($user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'provider' => $user->provider,
            'provider_id' => $user->provider_id,
            'avatar' => $user->avatar,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    // Get Next.js URL from config
    private function getNextjsUrl()
    {
        return config('services.nextjs_url', 'http://localhost:3000');
    }

    // Build Next.js redirect URL
    private function buildNextjsRedirectUrl($provider, $params)
    {
        $nextjsUrl = $this->getNextjsUrl();
        return $nextjsUrl . '/api/auth/callback/' . $provider . '?' . http_build_query($params);
    }

    // Log successful social authentication
    private function logSuccessfulAuth($provider, $user, $request, $wasRecentlyCreated = null)
    {
        \Log::info('LOGIN_SOCIAL_EXITOSO', [
            'provider' => $provider,
            'user_id' => $user->id,
            'email' => $user->email,
            'was_recently_created' => $wasRecentlyCreated ?? $user->wasRecentlyCreated,
            'ip' => $request->ip(),
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    // Standard success response with token
    private function successResponseWithToken($user, $token, $message = 'Autenticación social exitosa')
    {
        return response()->json([
            'message' => $message,
            'user' => $this->formatUserResponse($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    // Create standard auth token
    private function createAuthToken($user)
    {
        return $user->createToken('social-auth-token', ['*'], now()->addDays(30))->plainTextToken;
    }

    /**
     * Get the redirect URL for social provider
     */
    public function getRedirectUrl($provider)
    {
        $this->validateProvider($provider);

        try {
            $driver = Socialite::driver($provider)->stateless();
            
            if ($provider === 'google') {
                $driver = $driver->scopes(['openid', 'profile', 'email'])
                    ->with([
                        'access_type' => 'offline',
                        'prompt' => 'consent',
                        'include_granted_scopes' => 'true'
                    ]);
            }

            $redirectUrl = $driver->redirect()->getTargetUrl();

            return response()->json($redirectUrl, 200);

        } catch (\Exception $e) {
            \Log::error('ERROR_REDIRECT_URL', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al generar URL de redirección',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function handleCallback($provider, Request $request)
    {
        $this->validateProvider($provider);

        // Log de entrada para debug
        \Log::info('INICIO_CALLBACK_SOCIAL', [
            'provider' => $provider,
            'request_url' => $request->fullUrl(),
            'query_params' => $request->query(),
            'has_code' => $request->has('code'),
            'has_state' => $request->has('state'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        try {
            // Verificar que tenemos un código de autorización
            if (!$request->has('code')) {
                throw new \Exception('No se recibió código de autorización');
            }

            // Procesar el código OAuth
            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->user();

            $user = $this->findOrCreateUser($socialUser, $provider);

            $token = $this->createAuthToken($user);

            $this->logSuccessfulAuth($provider, $user, $request);

            // Preparar datos del usuario para la redirección
            $userData = $this->formatUserResponse($user);

            // URL de redirección a Next.js
            $redirectUrl = $this->buildNextjsRedirectUrl($provider, [
                'token' => $token,
                'user' => base64_encode(json_encode($userData)),
                'success' => 'true'
            ]);

            return redirect($redirectUrl);

        } catch (\Exception $e) {
            \Log::error('ERROR_CALLBACK_SOCIAL', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            // En caso de error, redirigir a Next.js con el error
            $redirectUrl = $this->buildNextjsRedirectUrl($provider, [
                'error' => 'auth_failed',
                'message' => $e->getMessage(),
                'success' => 'false'
            ]);

            return redirect($redirectUrl);
        }
    }

    public function processCallback($provider, Request $request)
    {
        $this->validateProvider($provider);

        $request->validate([
            'code' => 'required|string',
            'state' => 'sometimes|string',
        ]);

        try {
            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->user();

            $user = $this->findOrCreateUser($socialUser, $provider);

            $token = $this->createAuthToken($user);

            $this->logSuccessfulAuth($provider, $user, $request);

            return $this->successResponseWithToken($user, $token);

        } catch (\Exception $e) {
            \Log::error('ERROR_PROCESS_CALLBACK', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'message' => 'Error procesando callback social',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function loginWithToken($provider, Request $request)
    {
        $this->validateProvider($provider);

        $request->validate([
            'access_token' => 'required|string',
        ]);

        try {
            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->userFromToken($request->access_token);

            $user = $this->findOrCreateUser($socialUser, $provider);

            $token = $this->createAuthToken($user);

            return $this->successResponseWithToken($user, $token);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Token de acceso inválido',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function linkAccount($provider, Request $request)
    {
        $this->validateProvider($provider);

        $request->validate([
            'access_token' => 'required|string',
        ]);

        try {
            $user = $request->user();

            if ($user->provider === $provider && $user->provider_id) {
                return response()->json([
                    'message' => 'Ya tienes este proveedor vinculado a tu cuenta'
                ], 400);
            }

            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->userFromToken($request->access_token);

            $existingUser = User::where('provider', $provider)
                ->where('provider_id', $socialUser->id)
                ->first();

            if ($existingUser && $existingUser->id !== $user->id) {
                return response()->json([
                    'message' => 'Esta cuenta social ya está vinculada a otro usuario'
                ], 400);
            }

            $user->update([
                'provider' => $provider,
                'provider_id' => $socialUser->id,
                'avatar' => $socialUser->avatar ?? $user->avatar,
            ]);

            return response()->json([
                'message' => 'Cuenta social vinculada exitosamente',
                'user' => $this->formatUserResponse($user)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al vincular cuenta social',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function unlinkAccount(Request $request)
    {
        $user = $request->user();

        if (!$user->provider) {
            return response()->json([
                'message' => 'No tienes ninguna cuenta social vinculada'
            ], 400);
        }

        if (!$user->password) {
            return response()->json([
                'message' => 'No puedes desvincular tu cuenta social sin establecer una contraseña primero'
            ], 400);
        }

        $user->update([
            'provider' => null,
            'provider_id' => null,
        ]);

        return response()->json([
            'message' => 'Cuenta social desvinculada exitosamente',
            'user' => $this->formatUserResponse($user)
        ], 200);
    }

    public function getProviders()
    {
        $providers = [];
        
        foreach (self::SUPPORTED_PROVIDERS as $provider) {
            $providers[$provider] = [
                'name' => ucfirst($provider),
                'enabled' => !empty(config("services.{$provider}.client_id")),
            ];
        }

        return response()->json([
            'providers' => $providers
        ], 200);
    }


    private function findOrCreateUser($socialUser, $provider)
    {
        if (!$socialUser->email) {
            throw new \Exception('No se pudo obtener el email del proveedor social');
        }

        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->id)
            ->first();

        if ($user) {
            $user->update([
                'name' => $socialUser->name ?? $user->name,
                'avatar' => $socialUser->avatar ?? $user->avatar,
            ]);
            
            \Log::info('USUARIO_SOCIAL_ENCONTRADO', [
                'user_id' => $user->id,
                'provider' => $provider,
                'email' => $user->email,
                'name' => $user->name
            ]);
            
            return $user;
        }

        $user = User::where('email', $socialUser->email)->first();

        if ($user) {
            if ($user->provider && $user->provider !== $provider) {
                throw new \Exception("Esta cuenta de email ya está vinculada con {$user->provider}");
            }

            $user->update([
                'provider' => $provider,
                'provider_id' => $socialUser->id,
                'avatar' => $socialUser->avatar ?? $user->avatar,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
            
            \Log::info('USUARIO_SOCIAL_VINCULADO', [
                'user_id' => $user->id,
                'provider' => $provider,
                'email' => $user->email,
                'name' => $user->name
            ]);
            
            return $user;
        }

        $newUser = User::create([
            'name' => $socialUser->name ?? 'Usuario Social',
            'email' => $socialUser->email,
            'provider' => $provider,
            'provider_id' => $socialUser->id,
            'avatar' => $socialUser->avatar,
            'password' => null,
            'email_verified_at' => now(),
        ]);

        \Log::info('USUARIO_SOCIAL_CREADO', [
            'user_id' => $newUser->id,
            'provider' => $provider,
            'email' => $newUser->email,
            'name' => $newUser->name
        ]);

        return $newUser;
    }

    private function validateProvider($provider)
    {
        if (!in_array($provider, self::SUPPORTED_PROVIDERS)) {
            abort(404, 'Proveedor no soportado');
        }
    }
}
