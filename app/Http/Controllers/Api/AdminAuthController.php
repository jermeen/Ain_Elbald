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

    // متغيرات العدادات
    $stats = [
        'New'         => 0,
        'Pending'     => 0,
        'In Progress' => 0,
        'Fixed'       => 0,
        'Late'        => 0,
        'Rejected'    => 0,
    ];

    foreach ($allReports as $report) {
        $uiStatus = 'New'; // الحالة الافتراضية

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

    // --- إحصائيات الفنيين ---
    $allTechs = Technician::withCount(['reports as workload' => function($query) {
        $query->whereIn('current_status', ['Assigned', 'In Progress']);
    }])->get();

    $availableTechs = 0;
    $busyTechs = 0;

    foreach ($allTechs as $tech) {
        if ($tech->status === 'Active') {
            ($tech->workload >= 5) ? $busyTechs++ : $availableTechs++;
        }
    }

    // --- توزيع المشاكل حسب القسم (تم إضافة % للنسبة) ---
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

    // --- حساب متوسط دقة الـ AI ووقت الاستجابة ---
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
            'technicians_summary' => [
                'busy'      => $busyTechs,
                'available' => $availableTechs,
            ],
            'report_status_chart' => [
                ['status' => 'Late', 'percentage' => ($totalReports > 0 ? round(($stats['Late'] / $totalReports) * 100, 1) : 0) . '%'],
                ['status' => 'In Progress', 'percentage' => ($totalReports > 0 ? round(($stats['In Progress'] / $totalReports) * 100, 1) : 0) . '%'],
                ['status' => 'Fixed', 'percentage' => ($totalReports > 0 ? round(($stats['Fixed'] / $totalReports) * 100, 1) : 0) . '%'],
                ['status' => 'Pending', 'percentage' => ($totalReports > 0 ? round(($stats['Pending'] / $totalReports) * 100, 1) : 0) . '%'],
            ],
            'issue_distribution' => $issueDistribution
        ]
    ]);
    }



}