<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Report;
use App\Models\Technician;
use App\Models\Supervisor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;

class AdminAuthController extends Controller
{
    // 1. تسجيل دخول الأدمن
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // البحث باستخدام الحقل 'Email' كما هو في الموديل
        $admin = Admin::where('Email', $request->email)->first();

        // التحقق من الباسورد باستخدام الحقل 'Password'
        if (!$admin || !Hash::check($request->password, $admin->Password)) {
            return response()->json([
                'status'  => false,
                'message' => 'بيانات الدخول غير صحيحة الخاصة بالأدمن'
            ], 401);
        }

        $token = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'status'  => true,
            'message' => 'تم تسجيل دخول الأدمن بنجاح',
            'token'   => $token,
            'data'    => [
                'id'         => $admin->Admin_id,
                'full_name'  => $admin->First_Name . ' ' . $admin->Last_Name,
                'email'      => $admin->Email,
            ]
        ]);
    }

    // 2. عرض بيانات البروفايل
    public function getProfile()
    {
        $admin = Auth::user(); 
        
        return response()->json([
            'status' => true,
            'data'   => [

                // دمج الاسم الأول والتاني في خانة واحدة
                'full_name'  => $admin->First_Name . ' ' . $admin->Last_Name, 
                // كلمة ثابته تظهر تحت الاسم في الـ UI
                'role'       => 'Admin', 
                'email'      => $admin->Email,
                'phone'      => $admin->Phone_Number,
            ]
        ]);
    }

    // 3. تحديث بيانات البروفايل (الاسم )
    public function updateProfile(Request $request)
    {
        $admin = Auth::user();

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
        ]);

        $admin->update([
            'First_Name' => $request->first_name,
            'Last_Name'  => $request->last_name,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'تم تحديث الاسم بنجاح',
            'data'    => [
                'full_name' => $admin->First_Name . ' ' . $admin->Last_Name
            ]
        ]);
    }

    // 4. تسجيل الخروج
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => true,
            'message' => 'تم تسجيل خروج الأدمن بنجاح'
        ]);
    }

    // احصائياتت داش بورد الادمن 
    public function getDashboardAnalysis()
    {
    $now = now();
    $allReports = Report::all();
    $totalReports = $allReports->count();

    // 1. العدادات الأساسية (نفس كودك)
    $stats = [
        'New'         => 0,
        'Pending'     => 0,
        'In Progress' => 0,
        'Fixed'       => 0,
        'Late'        => 0,
        'Rejected'    => 0,
    ];

    foreach ($allReports as $report) {
        $uiStatus = 'New';
        if ($report->current_status === 'Pending') {
            $uiStatus = 'New';
        } 
        elseif ($report->current_status === 'Assigned') {
            $assignedTime = $report->updated_at; 
            $hoursPassed = $assignedTime->diffInHours($now);
            $target = $report->target_hours ?? 24;
            $uiStatus = ($hoursPassed > $target) ? 'Late' : 'Pending';
        } 
        elseif ($report->current_status === 'In Progress') {
            $uiStatus = 'In Progress';
        } 
        elseif ($report->current_status === 'Completed') {
            $uiStatus = 'Fixed';
        } 
        elseif ($report->current_status === 'Canceled') {
            $uiStatus = 'Rejected';
        }

        if (isset($stats[$uiStatus])) {
            $stats[$uiStatus]++;
        }
    }

    // 2. إحصائيات الفنيين (نفس كودك)
    $allTechs = Technician::withCount(['reports as workload' => function($query) {
        $query->whereIn('current_status', ['Assigned', 'In Progress']);
    }])->get();

    $availableTechs = 0; $busyTechs = 0;
    foreach ($allTechs as $tech) {
        if ($tech->status === 'Active') {
            ($tech->workload >= 5) ? $busyTechs++ : $availableTechs++;
        }
    }

    // 3. أداء الإدارات (الجدول اللي عملناه سوا)
    $departmentPerformance = Supervisor::select('department_name', 'supervisor_id')
        ->get()
        ->map(function ($dept) use ($now) {
            $deptReports = Report::where('supervisor_id', $dept->supervisor_id)->get();
            $totalDeptReports = $deptReports->count();
            if ($totalDeptReports == 0) {
                return [
                    'department' => $dept->department_name,
                    'assigned_reports' => 0,
                    'avg_response_time' => '0 hours',
                    'late_percentage' => '0%',
                    'status' => 'Excellent'
                ];
            }

            $lateCount = 0; $totalResponseHours = 0; $respondedCount = 0;
            foreach ($deptReports as $report) {
                if ($report->current_status === 'Assigned') {
                    if ($report->updated_at->diffInHours($now) > ($report->target_hours ?? 24)) {
                        $lateCount++;
                    }
                }
                if (in_array($report->current_status, ['Assigned', 'In Progress', 'Completed'])) {
                    $totalResponseHours += $report->report_date->diffInHours($report->updated_at);
                    $respondedCount++;
                }
            }
            $latePercent = round(($lateCount / $totalDeptReports) * 100, 1);
            $avgResTime = $respondedCount > 0 ? round($totalResponseHours / $respondedCount, 1) : 0;
            $status = ($latePercent > 35) ? 'Alert' : (($latePercent > 15) ? 'Good' : 'Excellent');

            return [
                'department' => $dept->department_name,
                'assigned_reports' => $totalDeptReports,
                'avg_response_time' => $avgResTime . ' hours',
                'late_percentage' => $latePercent . '%',
                'status' => $status
            ];
        });

    // --- 4. الجزء المعدل: النشاطات الأخيرة في آخر 5 دقايق  فقط ---
    $fiveMinutesAgo = now()->subMinutes(5);

    $recentActivity = Report::with('supervisor')
        ->where('updated_at', '>=', $fiveMinutesAgo) //فلترة: البلاغات اللي اتعدلت في آخر 5  دقايق 
        ->orderBy('updated_at', 'desc') // الأحدث أولاً
        ->limit(10) // بحد أقصى 10 بلاغات
        ->get()
        ->map(function ($report) {
            $title = "Action Updated";
            $desc = "Report #{$report->report_id} status changed.";
            $label = "System";

            if ($report->current_status === 'Pending') {
            $title = "New report submitted";
            $dept = $report->supervisor->department_name ?? 'General';
            $loc = $report->location_address ?? 'Area';
            $desc = "New report for {$dept} reported in {$loc}";
            $label = "New";
            }
            elseif ($report->current_status === 'Assigned') {
                $title = "Report Assigned";
                $desc = "Report #{$report->report_id} assigned to " . ($report->supervisor->department_name ?? 'Department');
                $label = "In Progress";
            } 
            elseif ($report->current_status === 'Canceled') {
                $title = "Report Rejected";
                $desc = "Report #{$report->report_id} was rejected by Supervisor " . ($report->supervisor->last_name ?? 'Admin');
                $label = "Pending"; //Rejected
            }

            return [
                'title' => $title,
                'description' => $desc,
                'status' => $label,
                'time' => $report->updated_at->diffForHumans(), // هيظهر "5 mins ago" مثلاً
            ];
        });
    // 5. باقي الإحصائيات (نفس كودك)
    $issueDistribution = Report::join('supervisors', 'reports.supervisor_id', '=', 'supervisors.supervisor_id')
        ->select('supervisors.department_name', DB::raw('count(*) as count'))
        ->groupBy('supervisors.department_name')
        ->get()
        ->map(function ($item) use ($totalReports) {
            return [
                'department' => $item->department_name,
                'count'      => $item->count,
                'percentage' => ($totalReports > 0 ? round(($item->count / $totalReports) * 100, 1) : 0) . '%'
            ];
        });

    $aiAccuracy = Report::whereNotNull('ai_confidence_score')->avg('ai_confidence_score') ?? 0;
    $avgResponseTime = Report::whereIn('current_status', ['Assigned', 'In Progress', 'Completed'])
        ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, report_date, updated_at)) as avg_hours'))
        ->first()->avg_hours ?? 0;

    return response()->json([
        'status' => true,
        'data' => [
            'top_cards' => [
                'total_reports' => $totalReports,
                'pending'       => $stats['Pending'],
                'in_progress'   => $stats['In Progress'],
                'fixed'         => $stats['Fixed'],
                'late'          => $stats['Late'],
                'new'           => $stats['New'],
                'rejected'      => $stats['Rejected'],
                'ai_accuracy'   => round($aiAccuracy * 100, 1) . '%',
                'response_time' => round($avgResponseTime, 1) . ' Hours'
            ],
            'technicians_summary' => ['busy' => $busyTechs, 'available' => $availableTechs],
            'report_status_chart' => [
                ['status' => 'Late', 'percentage' => ($totalReports > 0 ? round(($stats['Late'] / $totalReports) * 100, 1) : 0) . '%'],
                ['status' => 'In Progress', 'percentage' => ($totalReports > 0 ? round(($stats['In Progress'] / $totalReports) * 100, 1) : 0) . '%'],
                ['status' => 'Fixed', 'percentage' => ($totalReports > 0 ? round(($stats['Fixed'] / $totalReports) * 100, 1) : 0) . '%'],
                ['status' => 'Pending', 'percentage' => ($totalReports > 0 ? round(($stats['Pending'] / $totalReports) * 100, 1) : 0) . '%'],
            ],
            'issue_distribution' => $issueDistribution,
            'department_performance' => $departmentPerformance,
            'recent_activity' => $recentActivity 
        ]
    ]);
    }

    //  ف الادمن  AllReports جدول ال 
    public function getAllReports(Request $request)
    {
    $now = now();
    $query = Report::with(['supervisor']);

      // فلاتر البحث (Search Bar)
      if ($request->has('search')) {
        $query->where('report_id', 'like', '%' . $request->search . '%');
      }

      // فلاتر القوائم (Status, Priority, Department)
      if ($request->has('status') && $request->status != 'All') {
        $query->where('current_status', $request->status);
      }

       // [تعديل]: جلب كل التقارير دفعة واحدة بدلاً من التقسيم لصفحات
       $reports = $query->orderBy('created_at', 'desc')->get();

        $formattedReports = $reports->map(function ($report) use ($now) {
        // --- تطبيق المنطق الخاص بكِ لتحديد الـ UI Status (كما هو بدون تغيير) ---
        $uiStatus = 'New';
        if ($report->current_status === 'Pending') {
            $uiStatus = 'New';
        } 
        elseif ($report->current_status === 'Assigned') {
            $hoursPassed = $report->updated_at->diffInHours($now);
            $target = $report->target_hours ?? 24;
            $uiStatus = ($hoursPassed > $target) ? 'Late' : 'Pending';
        } 
        elseif ($report->current_status === 'In Progress') {
            $uiStatus = 'In Progress';
        } 
        elseif ($report->current_status === 'Completed') {
            $uiStatus = 'Fixed';
        } 
        elseif ($report->current_status === 'Canceled') {
            $uiStatus = 'Rejected';
        }

        return [
            'report_id'      => "#" . $report->report_id,
            'department'     => $report->supervisor->department_name ?? 'N/A',
            'submitted_date' => $report->created_at->format('d M Y'),
            'priority'       => $report->priority_level ?? 'Medium',
            'status'         => $uiStatus, 
            'db_id'          => $report->report_id, 
        ];
    });

    return response()->json([
        'status' => true,
        'count'  => $formattedReports->count(), // عدد التقارير الإجمالي
        'data'   => $formattedReports
    ]);
    }

    // زرار ال view ف جدول البلاغات ف الادمن 
    public function getReportDetails($id)
    {
    $now = now();
    $report = Report::with('supervisor')->find($id);

    if (!$report) {
        return response()->json(['status' => false, 'message' => 'Report not found'], 404);
    }

    // توحيد منطق الـ Status هنا أيضاً
    $uiStatus = 'New';
    if ($report->current_status === 'Pending') {
        $uiStatus = 'New';
    } 
    elseif ($report->current_status === 'Assigned') {
        $hoursPassed = $report->updated_at->diffInHours($now);
        $target = $report->target_hours ?? 24;
        $uiStatus = ($hoursPassed > $target) ? 'Late' : 'Pending';
    } 
    elseif ($report->current_status === 'In Progress') {
        $uiStatus = 'In Progress';
    } 
    elseif ($report->current_status === 'Completed') {
        $uiStatus = 'Fixed';
    } 
    elseif ($report->current_status === 'Canceled') {
        $uiStatus = 'Rejected';
    }

    return response()->json([
        'status' => true,
        'data'   => [
            'id'             => "#" . $report->report_id,
            'issue_name'     => $report->description ?? 'N/A', // حقل العنوان
            'location'       => $report->location_address ?? 'N/A', // حقل المنطقة
            'status'         => $uiStatus,
            'category'       => $report->supervisor->department_name ?? 'N/A',
            'priority'       => $report->priority_level ?? 'High',
            'issue_date'     => $report->created_at->format('j M Y - h:i A'),
            'image_url' => $report->photo_url ? (Str::contains($report->photo_url, 'http') ? $report->photo_url           :asset('storage/' . $report->photo_url)) : null,  
        ]
    ]);
    }

    // جلب بيانات كل اليوزر ف الابلكيشن 
    public function getAllUsers(Request $request)
    {
    $query = User::query();

    // البحث بالاسم أو الـ ID
    if ($request->has('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('User_id', 'like', "%$search%")
              ->orWhere('first_name', 'like', "%$search%")
              ->orWhere('last_name', 'like', "%$search%");
        });
    }

    // جلب كل المستخدمين بدون تقسيم لصفحات
    $users = $query->orderBy('created_at', 'desc')->get();

    $formattedUsers = $users->map(function ($user) {
        return [
            'id'           => $user->User_id,
            'name'         => $user->first_name . ' ' . $user->last_name,
            'email'        => $user->email,
            'phone'        => $user->phone ?? 'N/A',
            // تنسيق التاريخ بالمسافات والشرطة المائلة كما في التصميم
    'date'   => $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d / m / Y'):'N/A',
            'created_at'   => $user->created_at->format('d M Y'),
            'status'       => $user->is_active ? 'Active' : 'Blocked',
        ];
    });

    return response()->json([
        'status' => true,
        'count'  => $formattedUsers->count(),
        'data'   => $formattedUsers
    ]);
    }


    //  لحظر اليوزر من استخدام الابلكيشن 
    public function toggleUserStatus($id)
    {
    $user = User::find($id);
    if (!$user) {
        return response()->json(['status' => false, 'message' => 'User not found'], 404);
    }

    // تبديل الحالة
    $user->is_active = !$user->is_active;
    $user->save();

    $statusMessage = $user->is_active ? 'User unblocked successfully' : 'User blocked successfully';

    return response()->json([
        'status'  => true,
        'message' => $statusMessage,
        'current_status' => $user->is_active
    ]);
    }


    // جدول البلاغات المرفوضه عند الادمن 
    public function getAiRejectedReports(Request $request)
    {
    // وبنعمل Eager Loading للمشرف عشان نجيب منه اسم القسم
    $query = Report::with(['supervisor'])
        ->where('current_status', 'Canceled');

    // 1. فلتر البحث بـ ID البلاغ أو الوصف
    if ($request->has('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('report_id', 'like', "%$search%")
              ->orWhere('description', 'like', "%$search%");
        });
    }

    // 2. فلتر القسم (Ai Category)
    if ($request->has('category') && $request->category != 'All') {
        $query->whereHas('supervisor', function($q) use ($request) {
            $q->where('department_name', $request->category);
        });
    }

    // 3. فلتر نسبة الثقة (Confidence)
    if ($request->has('confidence') && $request->confidence != 'All') {
        $threshold = (float)$request->confidence / 100;
        $query->where('ai_confidence_score', '<=', $threshold);
    }

    $reports = $query->orderBy('ai_confidence_score', 'asc')->get();

    $formattedReports = $reports->map(function ($report) {
        return [
            'db_id'         => $report->report_id,
            'report_id'     => "#" . $report->report_id,
            'description'   => $report->description,
            'ai_category'   => $report->supervisor->department_name ?? 'Unassigned',
            'confidence'    => ($report->ai_confidence_score * 100) . "%",
            'status'        => 'Rejected', 
            // --- الحقول الجديدة للـ Pop-up ---
            'photo_url'     => $report->photo_url, // صورة البلاغ
            'location'      => $report->location_address, // الموقع النصي
            'priority'      => $report->priority_level ?? 'Normal', // الأولوية
            'issue_date'    => $report->report_date->format('d M Y - h:i A'), // التاريخ المنسق
        ];
    });

    return response()->json([
        'status' => true,
        'count'  => $formattedReports->count(),
        'data'   => $formattedReports
    ]);
    }


    // approve زرار ال لتاكيد ان التصننيف صحح
    public function approveReport(Request $request, $id)
    {
    $report = Report::find($id);

    if (!$report) {
        return response()->json([
            'status' => false,
            'message' => 'البلاغ غير موجود.'
        ], 404);
    }

    // [تحديث بناءً على منطق الحالات الخاص بكِ]:
    // تحويل الحالة لـ Pending في الداتابيز (عشان تظهر New في الـ UI عند السوبرفايزر)
    // وتفعيل الـ sorted عشان ميرجعش لقايمة الفرز
    $report->update([
        'current_status' => 'Pending', 
        'sorted' => true,
        'admin_id' => auth()->id(), 
    ]);

    return response()->json([
        'status' => true,
        'message' => 'تمت الموافقة على البلاغ بنجاح، وتحويله للمشرف المختص',
        'data' => [
            'db_id' => $report->report_id,
            'db_status' => 'Pending',
            'ui_status' => 'New'      // الحالة اللي هتظهر في الاسكرينات
        ]
    ]);
    }


}