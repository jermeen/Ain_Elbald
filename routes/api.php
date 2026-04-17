<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController; 
use App\Http\Controllers\Api\ReportController; 
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\SupervisorAuthController;
use App\Http\Controllers\SupervisorController; // استدعاء الكنترولر الجديد
use App\Http\Controllers\Api\TechnicianAuthController; // استدعاء كنترولر الفني
use App\Http\Controllers\Api\AdminAuthController;

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

// تسجيل دخول الفني (الموبايل)
Route::post('/technician/login', [TechnicianAuthController::class, 'login']);

// راوت تسجيل الدخول (خارج الـ Middleware)
Route::post('/admin/login', [AdminAuthController::class, 'login']);

// مسارات المصادقة للمستخدم العادي (الموبايل)
Route::controller(AuthController::class)->group(function () {
    Route::post('/user/register', 'register');          

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

    // --- (1) مسارات تخص أي مستخدم (يوزر أو مشرف أو فني) ---
    Route::post('/logout-all', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['status' => true, 'message' => 'Logged out successfully']);
    });


    // --- (2) مسارات المستخدم العادي فقط (الموبايل) ---
    // [تعديل]: إضافة Middleware لمنع الفني أو المشرف من دخول مسارات اليوزر
    Route::middleware('is_user')->group(function () {
        Route::get('/user', [AuthController::class, 'me']); 
        Route::post('/reports/create', [ReportController::class, 'store']);     
        Route::get('/reports/my-tickets', [ReportController::class, 'index']);  
        Route::get('/reports/track/{id}', [ReportController::class, 'show']);   
        Route::get('/user/profile', [UserController::class, 'showProfile']);
        Route::post('/user/update-profile', [UserController::class, 'updateProfile']);
        Route::post('/user/profile/update-photo', [UserController::class, 'updatePhoto']);
        Route::get('/user/notifications', [UserController::class, 'notifications']);
        Route::post('/user/logout', function (Request $request) {
            $request->user()->currentAccessToken()->delete();
            return response()->json(['status' => true, 'message' => 'User logged out']);
        });
    });


    // --- (3) مسارات المشرف فقط (الداش بورد) ---
    Route::middleware('is_supervisor')->group(function () {
        Route::post('/supervisor/logout', [SupervisorAuthController::class, 'logout']);
        
        // ⬇️ المسارات الجديدة الخاصة بالسكيرنات (إضافة فني وجدول المهام والإحصائيات) ⬇️
        Route::prefix('supervisor')->group(function () {
            Route::post('/add-technician', [SupervisorController::class, 'addTechnician']);
            Route::get('/technicians-list', [SupervisorController::class, 'getTechniciansList']);
            Route::get('/technician-tasks', [SupervisorController::class, 'getTechnicianTasks']);
            Route::post('/add-comment/{report_id}', [SupervisorController::class, 'addComment']);
            
            // المسار الجديد لإحصائيات الداشبورد (الهوم)
            Route::get('/dashboard-stats', [SupervisorController::class, 'getHomeDashboardStats']);
            Route::get('/all-reports', [SupervisorController::class, 'getAllReports']);
            // جلب بيانات الـ Popup (تحتاج رقم البلاغ)
            Route::get('/assign-data/{report_id}', [SupervisorController::class, 'getAssignPageData']);
            // تنفيذ الإرسال (Confirm)
            Route::post('/confirm-assign', [SupervisorController::class, 'confirmAssign']);
            // رفض المشكله 
            Route::post('/reject-report', [SupervisorController::class, 'rejectReport']);
            // معلومات البلاغ للقراءه فقط 
            Route::get('/report-details/{report_id}', [SupervisorController::class, 'getReportDetails']);
            //pending-reports صفحه ال 
            Route::get('/pending-reports', [SupervisorController::class, 'getPendingReports']);
            // خليه كدة (عشان يبقى الـ URL هو /api/supervisor/notifications):
            Route::get('/notifications', [SupervisorController::class, 'getNotifications']);
            // بروفايل السوبر فايز
            Route::get('/profile', [SupervisorController::class, 'getProfile']);
            // تحديث بروفايل السوبر فايزر
            Route::post('/profile/update', [SupervisorController::class, 'updateProfileName']);

        });
    });

    // --- (4) مسارات الفني فقط (الموبايل) ---
    // [تعديل]: إضافة Middleware للتأكد أن المستخدم فني فقط
    Route::middleware('is_technician')->group(function () {
        Route::prefix('technician')->group(function () {
            Route::get('/profile', [TechnicianAuthController::class, 'getProfile']);
            Route::post('/profile/update-photo', [TechnicianAuthController::class, 'updatePhoto']);
            Route::post('/logout', [TechnicianAuthController::class, 'logout']);
            Route::get('/my-tasks', [TechnicianAuthController::class, 'myTasks']);
            Route::get('/task-details/{id}', [TechnicianAuthController::class, 'taskDetails']);
            Route::get('/in-progress-tasks', [TechnicianAuthController::class, 'inProgressTasks']);
            Route::post('/start-task/{id}', [TechnicianAuthController::class, 'startTask']); 
            Route::post('/submit-update/{id}', [TechnicianAuthController::class, 'submitUpdate']);
            Route::get('/completed-tasks', [TechnicianAuthController::class, 'completedTasks']);
            Route::get('/completed-task-details/{id}', [TechnicianAuthController::class, 'completedTaskDetails']);
            Route::get('/notifications', [TechnicianAuthController::class, 'notifications']);
        });
    });


    // --- (5) مسارات الأدمن فقط (الداش بورد الرئيسي) ---
    Route::middleware(['auth:sanctum', 'is_admin'])->prefix('admin')->group(function () {
           Route::get('/profile', [AdminAuthController::class, 'getProfile']);
           Route::post('/profile/update', [AdminAuthController::class, 'updateProfile']);
           Route::post('/logout', [AdminAuthController::class, 'logout']);
        });
    });

// مسار التجربة
Route::get('/test-admins', function () {
    return App\Models\Admin::all();
});


