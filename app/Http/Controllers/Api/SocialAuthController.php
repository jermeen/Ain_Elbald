<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class SocialAuthController extends Controller
{
    public function handleMobileSocialLogin(Request $request)
    {
        // 1. التأكد من البيانات اللي جاية من تيم الفلاتر
        $request->validate([
            'email' => 'required|email',
            'social_id' => 'required',
            'social_type' => 'required|in:google,facebook,apple',
            'first_name' => 'required',
            'last_name' => 'nullable',
        ]);

        // 2. البحث عن اليوزر (نشوفه سجل قبل كدة ولا لا)
        // بندور بالـ social_id عشان نضمن إنه هو نفس الشخص
        $user = User::where('social_id', $request->social_id)
                    ->orWhere('email', $request->email)
                    ->first();

        if (!$user) {
            // 3. لو مش موجود، بنكريت حساب جديد
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name ?? '',
                'email' => $request->email,
                'social_id' => $request->social_id,
                'social_type' => $request->social_type,
                'password' => Hash::make(Str::random(16)), // باسورد عشوائي للأمان
                'is_verified' => true, // بنعتبره موثق لأن جوجل/فيسبوك وثقوه
            ]);
        } else {
            // لو اليوزر موجود بس أول مرة يدخل بالسوشيال، نحدث بياناته
            if (empty($user->social_id)) {
                $user->update([
                    'social_id' => $request->social_id,
                    'social_type' => $request->social_type,
                ]);
            }
        }

        // 4. إصدار Token لليوزر عشان يقدر يستخدم الـ APIs المحمية
        $token = $user->createToken('SocialLoginToken')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Social login successful',
            'token' => $token,
            'user' => $user
        ]);
    }
}