<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supervisor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SupervisorAuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        // البحث عن المشرف بالإيميل
        $supervisor = Supervisor::where('email', $request->email)->first();

        // التأكد من الحساب والباسورد
        if (!$supervisor || !Hash::check($request->password, $supervisor->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid email or password.'
            ], 401);
        }

        // إنشاء Token خاص بالمشرف (باستخدام Sanctum)
        $token = $supervisor->createToken('SupervisorToken')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Supervisor logged in successfully',
            'token' => $token,
            'data' => $supervisor
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['status' => true, 'message' => 'Logged out successfully']);
    }
}
