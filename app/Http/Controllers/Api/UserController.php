<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Report;

class UserController extends Controller
{
    // 1. عرض بيانات البروفايل
    public function showProfile()
    {
        $user = auth()->user();
        return response()->json([
            'status' => true,
            'data' => [
                'full_name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'photo' => $user->photo ? asset('storage/' . $user->photo) : asset('default-avatar.png'),
            ]
        ]);
    }

    // 2. تحديث الصورة الشخصية (اللي في الـ UI بـ Gallery/Camera)
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = auth()->user();

        // مسح الصورة القديمة لو موجودة
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        $path = $request->file('photo')->store('profile_photos', 'public');
        $user->update(['photo' => $path]);

        return response()->json([
            'status' => true,
            'message' => 'Profile photo updated successfully',
            'photo_url' => asset('storage/' . $path)
        ]);
    }

    // 3. الإشعارات (Notifications) - بناءً على سكرينة الـ Reports اللي بعتيها
    public function notifications()
    {
        $user = auth()->user();
        
        // هنجيب البلاغات ونقسمها "جديد" و "قديم" زي التصميم
        $newNotifications = Report::where('user_id', $user->User_id)
            ->where('updated_at', '>=', now()->subDays(2))
            ->orderBy('updated_at', 'desc')
            ->get();

        $lastWeekNotifications = Report::where('user_id', $user->User_id)
            ->where('updated_at', '<', now()->subDays(2))
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'new' => $newNotifications,
                'last_week' => $lastWeekNotifications
            ]
        ]);
    }
}