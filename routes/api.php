<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController; 
use App\Http\Controllers\Api\ReportController; 
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\SupervisorAuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// =============================================================
// 🔓 المسارات العامة (بدون حماية)
// =============================================================

// تسجيل دخول المشرف (الداش بورد)
Route::post('/supervisor/login', [SupervisorAuthController::class, 'login']);

// مسارات المصادقة للمستخدم العادي (الموبايل)
Route::controller(AuthController::class)->group(function () {
    Route::post('/user/register', 'register');          
    // [إضافة جديدة]: مسار تفعيل الحساب بالكود (4 أرقام) بعد التسجيل
    Route::post('/user/verify-email', 'verifyEmail'); 
    Route::post('/user/login', 'login');           
    Route::post('/user/password/forgot', 'forgotPassword');  
    Route::post('/user/password/verify', 'verifyCode');    
    Route::post('/user/password/reset', 'resetPassword');   
});

// السوشيال لوجن
Route::post('/user/social-login', [SocialAuthController::class, 'handleMobileSocialLogin']);


// =============================================================
// 🔒 المسارات المحمية (تحتاج Token)
// =============================================================

Route::middleware('auth:sanctum')->group(function () {

    // --- (1) مسارات تخص أي مستخدم (يوزر أو مشرف) ---
    Route::post('/logout-all', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['status' => true, 'message' => 'Logged out successfully']);
    });


    // --- (2) مسارات المستخدم العادي فقط (الموبايل) ---
    // هنا ممكن مستقبلاً تضيفي مدي ل وير يحمي اليوزر فقط لو حبتِ
    Route::get('/user', [AuthController::class, 'me']); 
    Route::post('/reports/create', [ReportController::class, 'store']);     
    Route::get('/reports/my-tickets', [ReportController::class, 'index']);  
    Route::get('/reports/track/{id}', [ReportController::class, 'show']);   
    Route::get('/user/profile', [UserController::class, 'showProfile']);
    Route::post('/user/profile/update-photo', [UserController::class, 'updatePhoto']);
    Route::get('/user/notifications', [UserController::class, 'notifications']);
    Route::post('/user/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['status' => true, 'message' => 'User logged out']);
    });


    // --- (3) مسارات المشرف فقط (الداش بورد) ---
    Route::middleware('is_supervisor')->group(function () {
        Route::post('/supervisor/logout', [SupervisorAuthController::class, 'logout']);
        
        // هنا هنضيف مسارات إضافة الفنيين بكرة بإذن الله
        // Route::post('/supervisor/add-technician', [TechnicianController::class, 'store']);
    });

});

// مسار التجربة
Route::get('/test-admins', function () {
    return App\Models\Admin::all();
});