<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSupervisor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. التأكد إن فيه مستخدم عامل Login أصلاً
        // 2. التأكد إن الـ Object بتاع المستخدم هو نسخة من موديل الـ Supervisor
        if ($request->user() && $request->user() instanceof \App\Models\Supervisor) {
            return $next($request); // لو هو مشرف فعلاً، كمل الطلب عادي
        }

        // لو طلع يوزر عادي أو شخص غير مصرح له
        return response()->json([
            'status' => false,
            'message' => 'Access Denied! This area is for supervisors only.'
        ], 403); // كود 403 يعني "ممنوع الدخول"
    }
}