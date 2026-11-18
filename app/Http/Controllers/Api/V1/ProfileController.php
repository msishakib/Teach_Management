<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->loadMissing('roles', 'student', 'teacher');

        return new UserResource($user);
    }
}
