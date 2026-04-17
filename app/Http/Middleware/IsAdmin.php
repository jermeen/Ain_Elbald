<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // بنشيك لو المستخدم مسجل دخول (auth) وبنشيك لو هو موجود في جدول الـ admins
        if (Auth::check() && Auth::user() instanceof \App\Models\Admin) {
        return $next($request);
        }

         return response()->json([
        'status' => false,
        'message' => 'عذراً، هذه الصلاحية مخصصة للأدمن فقط.'
         ], 403);
    }
}
