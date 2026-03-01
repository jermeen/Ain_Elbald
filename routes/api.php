<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController; 
use App\Http\Controllers\Api\ReportController; // تأكدي من إضافة هذا السطر

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// المسارات المحمية (تحتاج Token)
Route::middleware('auth:sanctum')->group(function () {
    
    // بيانات المستخدم الحالي
    Route::get('/user', [AuthController::class, 'me']); 

    // --- مسارات البلاغات (Reports) ---
    Route::post('/reports/create', [ReportController::class, 'store']);     // لإنشاء بلاغ جديد (زرار Create)
    Route::get('/reports/my-tickets', [ReportController::class, 'index']);  // لعرض كل بلاغاتي (زرار My Tickets)
    Route::get('/reports/track/{id}', [ReportController::class, 'show']);   // لتتبع بلاغ معين (زرار Track)

});

// مسار التجربة (اختياري)
Route::get('/test-admins', function () {
    return App\Models\Admin::all();
});

// مسارات المصادقة (Auth)
Route::controller(AuthController::class)->group(function () {
    Route::post('/user/register', 'register');          
    Route::post('/user/login', 'login');           

    Route::post('/user/password/forgot', 'forgotPassword');  
    Route::post('/user/password/verify', 'verifyCode');    
    Route::post('/user/password/reset', 'resetPassword');   
});