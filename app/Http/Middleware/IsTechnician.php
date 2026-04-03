<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsTechnician
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // بنتأكد إن اللي داخل نوعه فني (Technician)
        if (auth()->check() && auth()->user() instanceof \App\Models\Technician) {
        return $next($request);
        }
        return response()->json(['status' => false, 'message' => 'Access Denied: Technicians Only'], 403);
    }
}
