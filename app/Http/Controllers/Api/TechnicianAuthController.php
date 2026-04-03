<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Technician;
use App\Models\Report;
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


    // 5. عرض قائمة المهام الخاصة بالفني (My Tasks)
    public function myTasks()
    {
        $techId = auth()->user()->technician_id;

        // جلب البلاغات المسندة لهذا الفني مع بيانات السوبر فايزر
        $tasks = Report::where('technician_id', $techId)
            ->with('supervisor') // لجلب بيانات السوبر فايزر ومنها القسم
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $tasks->map(function ($task) {
            return [
                'report_id' => $task->report_id,
                'description' => $task->description,
                'location'  => $task->location_address,
                'status' => ($task->current_status == 'Assigned') ? 'New Task' : $task->current_status,
                // سحب القسم من السوبر فايزر مباشرة
                'category'  => $task->supervisor ? $task->supervisor->department_name : 'General',
                'image' => $task->photo_url ? (str_starts_with($task->photo_url, 'http') ? $task->photo_url : url('storage/' . $task->photo_url)) : null,
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => $data
        ]);
    }

    // 6. عرض تفاصيل مهمة محددة (Task Details)
    public function taskDetails($id)
    {
        $techId = auth()->user()->technician_id;

        // التأكد أن البلاغ يخص هذا الفني وجلب بيانات السوبر فايزر معه
        $task = Report::where('technician_id', $techId)
            ->where('report_id', $id)
            ->with('supervisor')
            ->first();

        if (!$task) {
            return response()->json([
                'status'  => false,
                'message' => 'المهمة غير موجودة أو غير مسندة إليك'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => [
                'report_id'   => $task->report_id,
                'description' => $task->description,
                'location'    => $task->location_address,
                'status'      => ($task->current_status == 'Assigned') ? 'New Task' : $task->current_status,
                'category'    => $task->supervisor ? $task->supervisor->department_name : 'General',
                'priority'    => $task->priority_level,
                // تنسيق التاريخ زي ما في صورة الـ UI
                'issue_date'  => $task->report_date ? $task->report_date->format('d M Y - h:i A') : null,
                'image' => $task->photo_url ? (str_starts_with($task->photo_url, 'http') ? $task->photo_url : url('storage/' . $task->photo_url)) : null,
                'latitude_longitude' => $task->latitude_longitude,
                'supervisor_comment' => $task->supervisor_comment,
            ]
        ]);
    }
}
