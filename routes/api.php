<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController; 
// تم حذف: use App\Http\Controllers\Api\PasswordResetController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider and all routes are
| assigned to the "api" middleware group. Enjoy building your API!
|
*/

// المسار المحمي للحصول على بيانات المستخدم الحالي 
Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'me']); 
// تم تعديل هذا المسار لاستخدام دالة me() في AuthController بدلاً من الـ Closure.

Route::get('/test-admins', function () {
    // لعرض جميع بيانات جدول Admins (للتجربة فقط)
    return App\Models\Admin::all();
});



Route::controller(AuthController::class)->group(function () {

   
    Route::post('/user/register', 'register');          
    Route::post('/user/login', 'login');           

    
    Route::post('/user/password/forgot', 'forgotPassword');  
    Route::post('/user/password/verify', 'verifyCode');    
    Route::post('/user/password/reset', 'resetPassword');   
    
    // تم حذف: Route::post('/user/verify', [AuthController::class, 'verifyAccount']);
});