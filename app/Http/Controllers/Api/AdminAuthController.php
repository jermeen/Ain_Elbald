<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AdminAuthController extends Controller
{
    // 1. تسجيل دخول الأدمن
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // البحث باستخدام الحقل 'Email' كما هو في الموديل
        $admin = Admin::where('Email', $request->email)->first();

        // التحقق من الباسورد باستخدام الحقل 'Password'
        if (!$admin || !Hash::check($request->password, $admin->Password)) {
            return response()->json([
                'status'  => false,
                'message' => 'بيانات الدخول غير صحيحة الخاصة بالأدمن'
            ], 401);
        }

        $token = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'status'  => true,
            'message' => 'تم تسجيل دخول الأدمن بنجاح',
            'token'   => $token,
            'data'    => [
                'id'         => $admin->Admin_id,
                'full_name'  => $admin->First_Name . ' ' . $admin->Last_Name,
                'email'      => $admin->Email,
            ]
        ]);
    }

    // 2. عرض بيانات البروفايل
    public function getProfile()
    {
        $admin = Auth::user(); 
        
        return response()->json([
            'status' => true,
            'data'   => [

                // دمج الاسم الأول والتاني في خانة واحدة
                'full_name'  => $admin->First_Name . ' ' . $admin->Last_Name, 
                // كلمة ثابته تظهر تحت الاسم في الـ UI
                'role'       => 'Admin', 
                'email'      => $admin->Email,
                'phone'      => $admin->Phone_Number,
            ]
        ]);
    }

    // 3. تحديث بيانات البروفايل (الاسم )
    public function updateProfile(Request $request)
    {
        $admin = Auth::user();

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
        ]);

        $admin->update([
            'First_Name' => $request->first_name,
            'Last_Name'  => $request->last_name,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'تم تحديث الاسم بنجاح',
            'data'    => [
                'full_name' => $admin->First_Name . ' ' . $admin->Last_Name
            ]
        ]);
    }

    // 4. تسجيل الخروج
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => true,
            'message' => 'تم تسجيل خروج الأدمن بنجاح'
        ]);
    }
}