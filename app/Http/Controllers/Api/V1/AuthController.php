<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = User::query()->where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken($request->string('device_name', 'flutter'))->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->loadMissing(['roles', 'student', 'teacher'])),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
