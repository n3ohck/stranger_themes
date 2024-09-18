<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Log;

class AuthController extends Controller
{
    public function authenticate(Request $request)
    {
        try {
            $user = User::accountIs($request->account)->first();

            if (is_null($user) || !($token = $user->apiLogin($request->password))) {
                return response()->json(['message' => 'Los datos ingresados son invalidos, por favor intenta nuevamente.'], 400);
            }

            if (!$user->hasRole('APP USER')) {
                return response()->json(['message' => 'Este usuario no tiene permisos para acceder al sistema'], 400);
            }
        } catch (JWTException $e) {
            throw $e;
            return response()->json(['message' => 'could_not_create_token'], 500);
        }

        return response()->json(compact('token'));
    }

    public function register(Request $request)
    {

        Log::info($request);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $user = User::create([
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'password' => Hash::make($request->get('password')),
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json(compact('user', 'token'), 201);
    }
}
