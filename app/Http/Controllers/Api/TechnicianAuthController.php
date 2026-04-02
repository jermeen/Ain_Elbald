<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Technician;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TechnicianAuthController extends Controller
{
    // 1. تسجيل دخول الفني
    public function login(Request $request)
    {
        // التأكد من صحة البيانات المدخلة
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // البحث عن الفني بواسطة البريد الإلكتروني
        $tech = Technician::where('email', $request->email)->first();

        // التحقق من وجود الحساب وصحة كلمة المرور
        if (!$tech || !Hash::check($request->password, $tech->password)) {
            return response()->json([
                'status'  => false,
                'message' => 'بيانات الدخول غير صحيحة'
            ], 401);
        }

        // إنشاء توكن الجلسة باستخدام Sanctum
        $token = $tech->createToken('tech_token')->plainTextToken;

        return response()->json([
            'status'  => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'token'   => $token,
            'data'    => [
                'id'        => $tech->technician_id,
                'full_name' => $tech->first_name . ' ' . $tech->last_name,
                'email'     => $tech->email,
                'image'     => $tech->profile_image ? url('storage/' . $tech->profile_image) : null,
            ]
        ]);
    }

    // 2. عرض بيانات البروفايل الخاص بالفني
    public function getProfile()
    {
        $tech = Auth::user(); 
        
        return response()->json([
            'status' => true,
            'data'   => [
                'id'             => $tech->technician_id,
                'first_name'     => $tech->first_name,
                'last_name'      => $tech->last_name,
                'email'          => $tech->email,
                'phone'          => $tech->phone,
                'address'        => $tech->address,
                'job_title'      => $tech->job_title,
                'specialization' => $tech->specialization,
                'image'          => $tech->profile_image ? url('storage/' . $tech->profile_image) : null,
            ]
        ]);
    }

    // 3. تحديث صورة البروفايل (رفع وحذف القديمة)
    public function updatePhoto(Request $request)
    {
        // التحقق من الملف المرفوع
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $tech = Auth::user();

        // حذف الصورة القديمة من التخزين إذا وجدت لتوفير المساحة
        if ($tech->profile_image) {
            Storage::disk('public')->delete($tech->profile_image);
        }

        // تخزين الصورة الجديدة في مجلد ملفات الفنيين
        $path = $request->file('photo')->store('technicians/profiles', 'public');
        
        // تحديث مسار الصورة في قاعدة البيانات
        $tech->update([
            'profile_image' => $path
        ]);

        return response()->json([
            'status'            => true,
            'message'           => 'تم تحديث الصورة بنجاح',
            'profile_image_url' => url('storage/' . $path)
        ]);
    }

    // 4. تسجيل خروج الفني (إبطال التوكن)
    public function logout(Request $request)
    {
        // مسح التوكن الحالي المستخدم في الطلب
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => true,
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }
}
