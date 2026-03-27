<?php

namespace App\Http\Controllers;

use App\Models\Technician;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class SupervisorController extends Controller
{
    // 1. إضافة فني جديد (الـ Modal اللي فيه Name, Email, Pass)
    public function addTechnician(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255', 
            'email' => 'required|email|unique:technicians,email',
            'password' => 'required|min:6|confirmed',
        ]);

        // تقسيم الاسم لـ First و Last عشان الداتابيز عندك
        $names = explode(' ', $request->name, 2);
        $firstName = $names[0];
        $lastName = $names[1] ?? ''; 

        $technician = Technician::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'supervisor_id' => Auth::id(), // ربط الفني بالمشرف اللي ضافه (مياه مع مياه)
            'status' => 'Active',
        ]);

        return response()->json(['status' => true, 'message' => 'Technician added successfully']);
    }

    
    // 2. عرض قائمة الفنيين (الجدول العلوي) - النسخة النهائية المفلترة
    public function getTechniciansList()
    {
        // عرض فنيين المشرف الحالي فقط
        $technicians = Technician::where('supervisor_id', Auth::id())
            ->withCount(['reports as workload' => function($query) {
                // بنحسب فقط البلاغات اللي لسه مخلصتش (Active Tasks)
                $query->where('current_status', '!=', 'Completed');
            }])
            ->get()
            ->map(function ($tech) {
                
                // تحديد الـ Status اللي هتظهر في الفرونت إند (UI Status)
                $uiStatus = $tech->status; 

                if ($tech->status === 'Active') {
                    // 🚨 المنطق المطلوب: لو عنده 5 بلاغات أو أكتر يبقى Busy، أقل يبقى Available
                    $uiStatus = ($tech->workload >= 5) ? 'Busy' : 'Available';
                }

                return [
                    'technician_name' => trim($tech->first_name . ' ' . $tech->last_name),
                    'workload'        => $tech->workload, // الرقم اللي هيظهر في عمود الـ Workload
                    'status'          => $uiStatus,      // الكلمة اللي هتظهر (Available / Busy)
                ];
            });

        return response()->json(['status' => true, 'data' => $technicians]);
    }


    // 3. جدول مهام الفنيين (الجدول اللي تحت خالص في السكرينة)
    public function getTechnicianTasks()
    {
        // عرض المهام اللي وزعها المشرف ده بس ومعاها بيانات الفني
        $tasks = Report::where('supervisor_id', Auth::id())
            ->whereNotNull('technician_id')
            ->with('technician:technician_id,first_name,last_name')
            ->get()
            ->map(function ($report) {
                return [
                    'report_id' => $report->report_id,
                    'technician_name' => trim($report->technician->first_name . ' ' . $report->technician->last_name),
                    'status' => $report->current_status, // حالة البلاغ (Assigned, In Progress...)
                    'add_comment' => $report->supervisor_comment ?? '', // الكومنت
                    'response_time' => $report->target_hours . ' Hours', // الوقت المستهدف
                    'priority' => $report->priority_level, // إضافة اختيارية للأهمية
                ];
            });

        return response()->json(['status' => true, 'data' => $tasks]);
    }

    // 4. إضافة كومنت للفني (خانة Add Comment في الجدول)
    public function addComment(Request $request, $report_id)
    {
        $request->validate(['supervisor_comment' => 'required|string']);

        $report = Report::where('report_id', $report_id)
                        ->where('supervisor_id', Auth::id()) // تأكيد إن البلاغ يخص المشرف
                        ->firstOrFail();

        $report->update(['supervisor_comment' => $request->supervisor_comment]);

        return response()->json(['status' => true, 'message' => 'Comment added successfully']);
    }
}