<?php

// USERRRR

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB; 
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;

class AuthController extends Controller
{
    // ------------------------------------------------------------------
    // 1. SIGN UP (التسجيل) - API: /api/user/register
    // ------------------------------------------------------------------
    public function register(Request $request) 
    {
        try {
            $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed', 
                'phone' => 'nullable|string|unique:users',
                'location' => 'nullable|string',
                'date_of_birth' => 'nullable|date',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => false, 'message' => 'Validation Failed', 'errors' => $e->errors()], 422);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password), 
            'phone' => $request->phone,
            'location' => $request->location,
            'date_of_birth' => $request->date_of_birth,
            'is_verified' => true, // تفعيل تلقائي
        ]);

        $token = $user->createToken("API_TOKEN")->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'تم التسجيل بنجاح.',
            'user' => $user->only('first_name', 'email'),
            'token' => $token
        ], 201);
    }

    // ------------------------------------------------------------------
    // 2. LOGIN (تسجيل الدخول) - API: /api/user/login
    // ------------------------------------------------------------------
    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => false,
                'message' => 'بيانات الدخول غير صحيحة.',
            ], 401);
        }

        $user = Auth::user();
        $token = $user->createToken("API_TOKEN")->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'تم تسجيل الدخول بنجاح.',
            'user' => $user->only('first_name', 'email'),
            'token' => $token
        ]);  
    }

    // ------------------------------------------------------------------
    // 3. FORGOT PASSWORD (طلب الكود) - API: /api/user/password/forgot
    // ------------------------------------------------------------------
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Password reset instructions sent.'], 200);
        }
        
        $token = (string) random_int(1000, 9999); 
        
        // حفظ الرمز المشفر في قاعدة البيانات (password_reset_tokens)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => Hash::make($token), 
                'created_at' => now()
            ]
        );
        
        // *** لغرض الاختبار: إرجاع الكود في الـ Response ***
        return response()->json([
            'status' => true,
            'message' => 'تم إرسال رمز إعادة التعيين بنجاح.',
            'email' => $user->email,
            'reset_token' => $token // هذا الكود سيُستخدم في الخطوة التالية
        ], 200);
    }

    // ------------------------------------------------------------------
    // 4. VERIFY CODE (التحقق من الكود) - API: /api/user/password/verify
    // ------------------------------------------------------------------
    public function verifyCode(Request $request)
    {
        $request->validate(['email' => 'required|email', 'code' => 'required|numeric']);
        
        $resetData = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        // التحقق من وجود الرمز ومطابقته (باستخدام Hash::check لأنه مشفر)
        if (!$resetData || !Hash::check($request->code, $resetData->token)) {
            return response()->json(['status' => false, 'message' => 'كود التحقق غير صحيح.'], 400);
        }
        
        // التحقق من انتهاء الصلاحية (افتراضياً 60 دقيقة)
        $expiryTime = 60 * 60; 
        if (Carbon::parse($resetData->created_at)->addSeconds($expiryTime)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['status' => false, 'message' => 'كود التحقق منتهي الصلاحية. يرجى طلب كود جديد.'], 400);
        }

        return response()->json([
            'status' => true, 
            'message' => 'تم التحقق بنجاح. يمكنك تعيين كلمة المرور الجديدة الآن.'
        ], 200);
    }
    
    // ------------------------------------------------------------------
    // 5. RESET PASSWORD (تعيين كلمة مرور جديدة) - API: /api/user/password/reset
    // ------------------------------------------------------------------
    public function resetPassword(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required|min:8|confirmed', 'token' => 'required|numeric']);

        $resetData = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        // التحقق من الرمز المشفر مرة أخرى
        if (!$resetData || !Hash::check($request->token, $resetData->token)) {
            return response()->json(['status' => false, 'message' => 'رمز إعادة التعيين غير صالح.'], 400);
        }
        
        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->forceFill(['password' => Hash::make($request->password)])->save();
            DB::table('password_reset_tokens')->where('email', $request->email)->delete(); // حذف الرمز
            
            return response()->json([
                'status' => true,
                'message' => 'تم تحديث كلمة المرور بنجاح!',
            ], 200);
        }
        
        return response()->json(['status' => false, 'message' => 'حدث خطأ. لم يتم العثور على المستخدم.'], 404);
    }

    // ------------------------------------------------------------------
    // 6. ME (الحصول على بيانات المستخدم الحالي) - API: /api/user
    // ------------------------------------------------------------------
    public function me(Request $request)
    {
        return response()->json([
            'status' => true,
            'user' => $request->user(),
        ]);
    }
}