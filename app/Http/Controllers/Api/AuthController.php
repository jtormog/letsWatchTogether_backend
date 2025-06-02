<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Handle API login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Las credenciales proporcionadas no son válidas.',
                'errors' => [
                    'email' => ['Las credenciales proporcionadas no coinciden con nuestros registros.']
                ]
            ], 401);
        }

        // Revoke all existing tokens for this user (optional - for single session)
        // $user->tokens()->delete();

        // Create token for API authentication
        $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'provider' => $user->provider,
                'avatar' => $user->avatar,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    /**
     * Handle API logout
     */
    public function logout(Request $request)
    {
        // Delete current token only
        $request->user()->currentAccessToken()->delete();
        
        // Or delete all tokens for the user (uncomment if you want to logout from all devices)
        // $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logout exitoso',
        ], 200);
    }

    /**
     * Handle API registration
     */
    public function register(Request $request)
    {
        $validator = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(), // Auto-verify for API registration
        ]);

        // Create token for API authentication
        $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'message' => 'Registro exitoso',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request)
    {
        // Delete all tokens for the authenticated user
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logout de todos los dispositivos exitoso',
        ], 200);
    }

    /**
     * Refresh token (create new token and delete old one)
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        
        // Delete current token
        $request->user()->currentAccessToken()->delete();
        
        // Create new token
        $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'message' => 'Token renovado exitosamente',
            'token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }
}
