<?php

namespace App\Http\Controllers\api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            if (! $user) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            } elseif (! Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'password' => ['The provided credentials are incorrect.'],
                ]);
            }
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('fishing_crew_api_token')->plainTextToken;

        return response()->json([
            'user' => $user,
        ], Response::HTTP_OK)
            ->cookie('auth_token', $token, 60 * 24 * 30, '/', null, true, true, false, 'Lax');
    }
}
