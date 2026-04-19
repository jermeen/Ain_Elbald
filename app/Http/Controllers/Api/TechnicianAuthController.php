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
    
    // 5. عرض قائمة "كل المهام" (My Tasks) - تظهر فيها كل الحالات
    public function myTasks()
    {
        $techId = auth()->user()->technician_id;

        $tasks = Report::where('technician_id', $techId)
            ->with('supervisor')
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $tasks->map(function ($task) {
            return $this->formatTaskData($task); // دالة موحدة لتنسيق البيانات
        });

        return response()->json(['status' => true, 'data' => $data]);
    }

    // 6. [دالة مساعدة]: لتنسيق البيانات وتوحيدها في الـ APIs المختلفة
    private function formatTaskData($task)
    {
        $uiStatus = $task->current_status;
        if ($task->current_status === 'Assigned') $uiStatus = 'New Task';
        if ($task->current_status === 'Completed') $uiStatus = 'Fixed';

        return [
            'report_id'   => $task->report_id,
            'description' => $task->description,
            'location'    => $task->location_address,
            'status'      => $uiStatus, 
            'category'    => $task->supervisor ? $task->supervisor->department_name : 'General',
            'image'       => $task->photo_url ? (str_starts_with($task->photo_url, 'http') ? $task->photo_url : url('storage/' . $task->photo_url)) : null,
            'date'        => $task->report_date ? $task->report_date->format('Y-m-d') : null,
        ];
    }

    // 7. عرض تفاصيل مهمة محددة (Task Details)
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

    // 8. بدء العمل على المهمة (Start Task)
    public function startTask(Request $request, $id)
    {
        $techId = auth()->user()->technician_id;

        $task = Report::where('technician_id', $techId)
            ->where('report_id', $id)
            ->first();

        if (!$task) {
            return response()->json(['status' => false, 'message' => 'المهمة غير موجودة'], 404);
        }

        // 1. تحديث حالة البلاغ
        $task->update([
            'current_status' => 'In Progress'
        ]);

        // 2. التسجيل في الـ Timeline (استخدام القيمة المظبوطة للـ Enum)
        \App\Models\ReportStatusUpdate::create([
            'report_id'   => $task->report_id,
            'new_status'  => 'In Progress',
            'update_type' => 'Status Change', // مطابقة تماماً للميجريشن بتاعك
            'content'     => "الفني " . auth()->user()->first_name . " بدأ العمل على بلاغك الآن.",
            'timestamp'   => now(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'تم بدء العمل على المهمة بنجاح، البلاغ الآن في صفحة In Progress',
            'data'    => [
                'report_id' => $task->report_id,
                'status'    => 'In Progress'
            ]
        ]);
    }

    // 9. [إضافة جديدة]: عرض "المهام الجاري العمل عليها" فقط (In Progress Tasks)
    public function inProgressTasks()
    {
        $techId = auth()->user()->technician_id;

        // بنفلتر هنا على حالة In Progress فقط
        $tasks = Report::where('technician_id', $techId)
            ->where('current_status', 'In Progress')
            ->with('supervisor')
            ->orderBy('updated_at', 'desc') // الأحدث تحديثاً يظهر أولاً
            ->get();

        $data = $tasks->map(function ($task) {
            return [
                'report_id'   => $task->report_id,
                'description' => $task->description,
                'location'    => $task->location_address,
                'status'      => 'In Progress', // الحالة ثابتة هنا لأننا بنفلتر عليها
                'category'    => $task->supervisor ? $task->supervisor->department_name : 'General',
                'image'       => $task->photo_url ? (str_starts_with($task->photo_url, 'http') ? $task->photo_url : url('storage/' . $task->photo_url)) : null,
            ];
        });

        return response()->json(['status' => true, 'data' => $data]);
    }

    // 10. api بتاع ارسال الابديت 
    public function submitUpdate(Request $request, $id)
{
    // 1. التحقق
    $request->validate([
        'comment' => 'required|string',
        'photo'   => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
    ]);

    $techId = auth()->user()->technician_id;

    // 2. جلب البلاغ
    $report = Report::where('report_id', $id)
        ->where('technician_id', $techId)
        ->first();

    if (!$report) {
        return response()->json(['status' => false, 'message' => 'البلاغ غير موجود'], 404);
    }

    // 3. رفع الصورة
    $photoPath = $report->after_photo_url;
    if ($request->hasFile('photo')) {
        $file = $request->file('photo');
        $filename = time() . '_' . $file->getClientOriginalName();
        $file->move(public_path('storage/reports/after'), $filename);
        $photoPath = url('storage/reports/after/' . $filename);
    }

    // 4. تحديث حالة البلاغ
    $report->update([
        'current_status'        => 'Completed',
        'after_photo_url'       => $photoPath,
        'technician_final_note' => $request->comment,
        'resolution_date'       => now(),
    ]);

    // 5. التايم لاين
    \App\Models\ReportStatusUpdate::create([
        'report_id'   => $report->report_id,
        'new_status'  => 'Completed',
        'update_type' => 'Resolution',
        'content'     => "قام الفني بإنهاء العمل: " . $request->comment,
        'timestamp'   => now(),
    ]);

    // 6. إرسال الإشعار (قبل الـ return)
    if ($report->supervisor) {
        $notificationData = [
            'title'       => $report->supervisor->department_name ?? 'Task Completed', 
            'report_id'   => $report->report_id,
            'description' => "Technician finished work. Note: " . $request->comment,
            'status'      => 'Fixed',
            'photo'       => $photoPath,
        ];
        $report->supervisor->notify(new \App\Notifications\GeneralNotification($notificationData));
    }

    // 7. الرد النهائي
    return response()->json([
        'status'  => true,
        'message' => 'Task submitted successfully and supervisor notified',
        'data'    => [
            'report_id' => $report->report_id,
            'status'    => 'Fixed'
        ]
    ]);
}

    // 11. يجيب صفحه البلاغات المكتمله 
    public function completedTasks()
    {
    $techId = auth()->user()->technician_id;

    // جلب البلاغات اللي حالتها Completed (واللي بنعرضها Fixed في الفرونت)
    $tasks = Report::where('technician_id', $techId)
        ->where('current_status', 'Completed')
        ->orderBy('resolution_date', 'desc') 
        ->get();

    $data = $tasks->map(function ($report) {
        return [
            'report_id'   => $report->report_id,
            'description' => $report->description,
            'done_date'   => $report->resolution_date ? $report->resolution_date->format('j M Y') : $report->updated_at->format('j M Y'),
            'status'      => 'Fixed',
            // التعديل هنا: بنعرض صورة اليوزر الأصلية في القائمة
            'photo'       => $report->photo_url ? url($report->photo_url) : null, 
        ];
    });

    return response()->json([
        'status' => true,
        'data'   => $data
    ]);
    }


    // 12. تفااصيل كل بلاغ بعد الابديت 
    public function completedTaskDetails($id)
    {
    $techId = auth()->user()->technician_id;

    // بنجيب البلاغ مع علاقة السوبر فايزر
    $report = Report::with(['supervisor']) 
        ->where('report_id', $id)
        ->where('technician_id', $techId)
        ->where('current_status', 'Completed')
        ->first();

    if (!$report) {
        return response()->json(['status' => false, 'message' => 'البلاغ غير موجود'], 404);
    }

    // 1. حساب المدة (Duration) ديناميكياً
    $duration = 'N/A';
    if ($report->report_date && $report->resolution_date) {
        $days = $report->report_date->diffInDays($report->resolution_date);
        
        if ($days == 0) {
            $duration = "Less than a day";
        } elseif ($days < 7) {
            $duration = $days . " days";
        } else {
            $weeks = floor($days / 7);
            $remainingDays = $days % 7;
            $duration = $weeks . " week" . ($weeks > 1 ? 's' : '') . ($remainingDays > 0 ? " and $remainingDays days" : "");
        }
    }

    return response()->json([
        'status' => true,
        'data'   => [
            'id'             => "#" . $report->report_id,
            'location'       => $report->location_address ?? 'غير محدد',
            'description'    => $report->description,
            'duration'       => $duration,
            'final_status'   => 'Fixed',
            'category'       => $report->supervisor ? $report->supervisor->department_name : 'General',
            'done_at'        => $report->updated_at ? $report->updated_at->format('j M Y - h:i A') : '',
            'before_photo'   => $report->photo_url ? url($report->photo_url) : null,
            'after_photo'    => $report->after_photo_url,
            'technician_note'=> $report->technician_final_note
            //'photo'       => $report->after_photo_url ?? $report->photo_url,
        ]
    ]);
    }
    
    // 13. [إضافة جديدة]: عرض قائمة الإشعارات للفني (New Task & Supervisor Comment
    public function notifications()
    {
        $tech = auth()->user();

        // 1. جلب البلاغات المسندة للفني (التي بها كومنت أو المسندة حديثاً)
        // ده هيضمن إن أي حاجة قديمة تظهر
        $reports = \App\Models\Report::with('supervisor')
            ->where('technician_id', $tech->technician_id)
            ->where(function($query) {
                $query->whereNotNull('supervisor_comment')
                      ->orWhereIn('current_status', ['Assigned', 'Pending', 'In Progress']);
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        $formatted = $reports->map(function ($report) {
            
            // مسميات الحالة المتفق عليها
            $uiStatus = $report->current_status;
            if ($report->current_status === 'Assigned') $uiStatus = 'New';
            if ($report->current_status === 'Completed') $uiStatus = 'Fixed';

            // تحديد النوع: لو فيه كومنت يبقى Supervisor Comment
            $hasComment = !empty($report->supervisor_comment);
            $type = $hasComment ? 'Supervisor Comment' : 'New Task';

            return [
                'id'          => $report->report_id, // استخدام ID البلاغ
                'report_id'   => "#" . $report->report_id,
                'type'        => $type,
                'title'       => $report->supervisor ? $report->supervisor->department_name : $report->report_type,
                'description' => $hasComment ? $report->supervisor_comment : $report->description,
                'status'      => $uiStatus, 
                'photo'       => $report->photo_url ? (str_starts_with($report->photo_url, 'http') ? $report->photo_url : url('storage/' . $report->photo_url)) : null,
                'time_ago'    => $report->updated_at->diffForHumans(['short' => true]),
                'date'        => $report->updated_at->format('d M Y'),    
                'time'        => $report->updated_at->format('h:i A'),    
                'timestamp'   => $report->updated_at->toIso8601String(),
                'updated_at_raw' => $report->updated_at,
            ];
        });

        // التقسيم الزمني (آخر يومين و القديم)
        $new = $formatted->filter(function($item) {
            return $item['updated_at_raw'] >= now()->subDays(2);
        })->values();

        $lastWeek = $formatted->filter(function($item) {
            return $item['updated_at_raw'] < now()->subDays(2);
        })->values();

        return response()->json([
            'status' => true,
            'data' => [
                'new'       => $new,
                'last_week' => $lastWeek
            ]
        ]);
    }
    
}
