<?php

namespace App\Http\Controllers;

use App\Models\Technician;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SupervisorController extends Controller
{
    // 1. إضافة فني جديد (العنوان إجباري)
    public function addTechnician(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255', 
            'email'    => 'required|email|unique:technicians,email',
            'password' => 'required|min:6|confirmed',
            'address'  => 'required|string|max:500', 
        ]);

        $names = explode(' ', $request->name, 2);
        $firstName = $names[0];
        $lastName = $names[1] ?? ''; 

        $technician = \App\Models\Technician::create([
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'email'         => $request->email,
            'password'      => \Hash::make($request->password),
            'address'       => $request->address,
            'supervisor_id' => \Auth::id(), 
            'status'        => 'Active',
        ]);

        return response()->json(['status' => true, 'message' => 'Technician added successfully']);
    }

    // 2. عرض قائمة الفنيين
    public function getTechniciansList()
    {
        $technicians = \App\Models\Technician::where('supervisor_id', \Auth::id())
            ->withCount(['reports as workload' => function($query) {
                $query->where('current_status', '!=', 'Completed');
            }])
            ->get()
            ->map(function ($tech) {
                
                $uiStatus = $tech->status; 

                if ($tech->status === 'Active') {
                    $uiStatus = ($tech->workload >= 5) ? 'Busy' : 'Available';
                }

                return [
                    'technician_name' => trim($tech->first_name . ' ' . $tech->last_name),
                    'address'         => $tech->address, 
                    'workload'        => $tech->workload, 
                    'status'          => $uiStatus,
                ];
            });

        return response()->json(['status' => true, 'data' => $technicians]);
    }

    // 3. جدول مهام الفنيين (حساب وقت الاستجابة مع عداد الانتظار)
    public function getTechnicianTasks()
    {
        $tasks = Report::where('supervisor_id', \Auth::id())
            ->whereNotNull('technician_id')
            ->with('technician:technician_id,first_name,last_name')
            ->get()
            ->map(function ($report) {
                
                $assignedTime = \Carbon\Carbon::parse($report->created_at); 
                $responseTime = '00h 00m';

                if ($report->current_status === 'Assigned') {
                    $now = \Carbon\Carbon::now();
                    $diff = $assignedTime->diff($now);
                    $totalHours = ($diff->days * 24) + $diff->h;
                    $responseTime = sprintf('%02dh %02dm (Waiting)', $totalHours, $diff->i);
                } 
                else if (in_array($report->current_status, ['In Progress', 'Completed'])) {
                    $startTime = \Carbon\Carbon::parse($report->updated_at); 
                    $diff = $assignedTime->diff($startTime);
                    $totalHours = ($diff->days * 24) + $diff->h;
                    $responseTime = sprintf('%02dh %02dm', $totalHours, $diff->i);
                }

                return [
                    'report_id'       => $report->report_id,
                    'technician_name' => trim($report->technician->first_name . ' ' . $report->technician->last_name),
                    'status'          => $report->current_status, 
                    'add_comment'     => $report->supervisor_comment ?? '',
                    'response_time'   => $responseTime,
                ];
            });

        return response()->json(['status' => true, 'data' => $tasks]);
    }

    // 4. إضافة كومنت للفني
    public function addComment(Request $request, $report_id)
    {
        $request->validate(['supervisor_comment' => 'required|string']);

        $report = Report::where('report_id', $report_id)
                        ->where('supervisor_id', Auth::id())
                        ->firstOrFail();

        $report->update(['supervisor_comment' => $request->supervisor_comment]);

        return response()->json(['status' => true, 'message' => 'Comment added successfully']);
    }

    // 5. إحصائيات الصفحة الرئيسية (Dashboard Stats)
    public function getHomeDashboardStats()
    {
        $supervisorId = Auth::id();
        $now = Carbon::now();

        // 1. الكروت العلوية (Counters)
        $allReportsCount = Report::where('supervisor_id', $supervisorId)->count();
        
        $newReportsCount = Report::where('supervisor_id', $supervisorId)
            ->where('current_status', 'Pending')->count();

        $pendingInUICount = Report::where('supervisor_id', $supervisorId)
            ->where('current_status', 'Assigned')
            ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, NOW()) <= target_hours')->count();

        $inProgressCount = Report::where('supervisor_id', $supervisorId)
            ->where('current_status', 'In Progress')->count();

        $fixedReportsCount = Report::where('supervisor_id', $supervisorId)
            ->where('current_status', 'Completed')->count();
        
        $lateReportsCount = Report::where('supervisor_id', $supervisorId)
            ->where('current_status', 'Assigned')
            ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, NOW()) > target_hours')->count();

        // 2. الرسم البياني اليومي (Daily Reports - آخر 7 أيام)
        $dailyReports = Report::where('supervisor_id', $supervisorId)
            ->where('created_at', '>=', $now->copy()->subDays(6))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get()
            ->map(function($item) {
                return [
                    'day' => Carbon::parse($item->date)->format('D'),
                    'count' => $item->total
                ];
            });

        // 3. نسبة الحالات (Pie Chart)
        $pieChart = [
            ['label' => 'Late', 'value' => ($allReportsCount > 0) ? round(($lateReportsCount / $allReportsCount) * 100) : 0],
            ['label' => 'Fixed', 'value' => ($allReportsCount > 0) ? round(($fixedReportsCount / $allReportsCount) * 100) : 0],
            ['label' => 'In progress', 'value' => ($allReportsCount > 0) ? round(($inProgressCount / $allReportsCount) * 100) : 0],
            ['label' => 'Pending', 'value' => ($allReportsCount > 0) ? round(($pendingInUICount / $allReportsCount) * 100) : 0],
        ];

        // 4. متوسط وقت الحل (الأعمدة) - تم التعديل لـ priority_level
        $resolutionStats = [
            [
                'priority' => 'High', 
                'target'   => 4, 
                'actual'   => round(Report::where('priority_level', 'High')->avg('target_hours') ?? 0, 1)
            ],
            [
                'priority' => 'Medium', 
                'target'   => 7, 
                'actual'   => round(Report::where('priority_level', 'Medium')->avg('target_hours') ?? 0, 1)
            ],
            [
                'priority' => 'Low', 
                'target'   => 48, 
                'actual'   => round(Report::where('priority_level', 'Low')->avg('target_hours') ?? 0, 1)
            ],
        ];

        return response()->json([
            'status' => true,
            'data' => [
                'top_cards' => [
                    'all' => $allReportsCount,
                    'new' => $newReportsCount,
                    'pending' => $pendingInUICount,
                    'in_progress' => $inProgressCount,
                    'fixed' => $fixedReportsCount,
                    'late' => $lateReportsCount,
                ],
                'daily_chart' => $dailyReports,
                'pie_chart' => $pieChart,
                'resolution_bars' => $resolutionStats
            ]
        ]);
    }
    // 6. جدول كل البلاغات (جلب النوع من قسم المشرف)
    public function getAllReports()
    {
        $supervisorId = Auth::id();
        $now = Carbon::now();

        // بنجيب الريبورت ومعاه بيانات المشرف (عشان ناخد اسم القسم)
        $reports = Report::with('supervisor') 
            ->where('supervisor_id', $supervisorId)
            ->orderBy('created_at', 'DESC') 
            ->get()
            ->map(function ($report) use ($now) {
                
                $assignedTime = Carbon::parse($report->created_at);
                $responseTime = '00h 00m';

                // حساب الـ Response Time (موحد)
                if (in_array($report->current_status, ['Pending', 'Assigned'])) {
                    $diff = $assignedTime->diff($now);
                    $totalHours = ($diff->days * 24) + $diff->h;
                    $responseTime = sprintf('%02dh %02dm', $totalHours, $diff->i);
                } 
                else {
                    $startTime = Carbon::parse($report->updated_at);
                    $diff = $assignedTime->diff($startTime);
                    $totalHours = ($diff->days * 24) + $diff->h;
                    $responseTime = sprintf('%02dh %02dm', $totalHours, $diff->i);
                }

                // تحديد الحالة للـ UI
                $uiStatus = 'New';
                if ($report->current_status === 'Pending') {
                    $uiStatus = 'New';
                } elseif ($report->current_status === 'Assigned') {
                    $uiStatus = ($assignedTime->diffInHours($now) > $report->target_hours) ? 'Late' : 'Pending';
                } elseif ($report->current_status === 'In Progress') {
                    $uiStatus = 'In Progress';
                } elseif ($report->current_status === 'Completed') {
                    $uiStatus = 'Fixed';
                }elseif ($report->current_status === 'Canceled') {
                    $uiStatus = 'Rejected'; // هنا السحر! بنحول الكلمة للـ UI
                }

                return [
                    'report_id'    => '#' . $report->report_id,
                    // بنجيب اسم القسم من جدول السوبرفايزر المربوط بالبلاغ
                    'issue_type'   => $report->supervisor->department_name ?? 'General', 
                    'submitted'    => $assignedTime->format('d M Y'), 
                    'response_time'=> $responseTime,
                    'status'       => $uiStatus,
                ];
            });

        return response()->json(['status' => true, 'data' => $reports]);
    }

    // 7. جلب بيانات صفحة الـ Assign (تفاصيل البلاغ + الفنيين المتاحين)
    public function getAssignPageData($report_id)
    {
        // 1. جلب تفاصيل البلاغ المختار
        $report = Report::with('supervisor')->where('report_id', $report_id)->firstOrFail();

        // 2. جلب قائمة الفنيين التابعين لهذا السوبرفايزر فقط
        $technicians = Technician::where('supervisor_id', Auth::id())
            ->withCount(['reports as workload' => function($query) {
                $query->where('current_status', '!=', 'Completed');
            }])
            ->get()
            ->map(function ($tech) {
                return [
                    'technician_id'   => $tech->technician_id,
                    'technician_name' => trim($tech->first_name . ' ' . $tech->last_name),
                    'address'         => $tech->address,
                    'workload'        => $tech->workload . ' Tasks',
                    'status'          => ($tech->workload >= 5) ? 'Busy' : 'Available',
                ];
            });

        return response()->json([
            'status' => true,
            'data' => [
                'report_details' => [
                    'report_id' => '#' . $report->report_id,
                    'title'     => $report->title ?? 'No Title', // عنوان البلاغ
                    'category'  => $report->supervisor->department_name ?? 'General',
                    'location'  => $report->location_address ?? 'Location not set',
                ],
                'technicians' => $technicians
            ]
        ]);
    }

    // 8. تنفيذ عملية التكليف (Confirm Assign) - مع قيود الـ SLA
    public function confirmAssign(Request $request)
    {
        $request->validate([
            'report_id'     => 'required|exists:reports,report_id',
            'technician_id' => 'required|exists:technicians,technician_id',
            'target_hours'  => 'required|integer|min:1',
            'priority_level'=> 'required|in:Low,Medium,High',
        ]);
        
        // 1. القيد الخاص بـ High (أقصى حاجة 4 ساعات)
        if ($request->priority_level === 'High' && $request->target_hours > 4) {
            return response()->json(['status' => false, 'message' => 'For High priority, SLA cannot exceed 4 hours'], 422);
        }

        // 2. القيد الخاص بـ Medium (أقصى حاجة 7 ساعات)
        if ($request->priority_level === 'Medium' && $request->target_hours > 7) {
            return response()->json(['status' => false, 'message' => 'For Medium priority, SLA cannot exceed 7 hours'], 422);
        }

        // 3. القيد الخاص بـ Low (أقصى حاجة 48 ساعة)
        if ($request->priority_level === 'Low' && $request->target_hours > 48) {
            return response()->json(['status' => false, 'message' => 'For Low priority, SLA cannot exceed 48 hours'], 422);
        }

        // لو كل الشروط تمام.. نحدث الداتابيز
        $report = Report::findOrFail($request->report_id);
        $report->update([
            'technician_id'  => $request->technician_id,
            'target_hours'   => $request->target_hours,
            'priority_level' => $request->priority_level,
            'current_status' => 'Assigned', // تظهر في الـ UI كـ Pending
            'assigned_at'    => now(),
        ]);

        return response()->json([
            'status' => true, 
            'message' => 'Report assigned to technician successfully'
        ]);
    }

    // 9. رفض البلاغ (Reject Report)
    // ملاحظة: الحالة في الداتابيز Canceled ولكنها تظهر في الـ UI باسم Rejected
    public function rejectReport(Request $request)
    {
        // التأكد إن الـ report_id مبعوث وصح
        $request->validate([
            'report_id' => 'required|exists:reports,report_id',
        ]);

        // البحث عن البلاغ
        $report = Report::findOrFail($request->report_id);
        
        // تحديث الحالة لـ Canceled (بناءً على الـ Enum اللي عندك)
        $report->update([
            'current_status' => 'Canceled',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Report has been rejected successfully' 
        ]);
    }

    // 10. جلب تفاصيل البلاغ كاملة (الوصف، الصور، وبيانات المواطن)
    public function getReportDetails($report_id)
    {
        // بنجيب الريبورت مع بيانات اليوزر (المواطن) اللي رفعه
        $report = Report::with('user')->where('report_id', $report_id)->firstOrFail();

        return response()->json([
            'status' => true,
            'data' => [
                'report_id'    => '#' . $report->report_id,
                'description'  => $report->description ?? 'No description provided', 
                'status'       => ($report->current_status === 'Canceled') ? 'Rejected' : $report->current_status,
                'location'     => $report->location_address ?? 'Location not specified',
                
                // تعديل اسم العمود لـ photo_url لضمان ظهور الصورة
                'photo'        => $report->photo_url ? url($report->photo_url) : null, 
                
                'user_details' => [
                    // دمج الاسم الأول والأخير مع معالجة لو مش موجودين
                    'full_name' => trim(($report->user->first_name ?? '') . ' ' . ($report->user->last_name ?? '')) ?: 'Unknown User',
                    'email'     => $report->user->email ?? 'Not Available',
                    // حل مشكلة الفون وإظهار Not Available لو قيمته صفر أو غير موجود
                    'phone'     => (!empty($report->user->phone) && $report->user->phone != 0) ? (string)$report->user->phone : 'Not Available',
                ],
                'submitted_at' => $report->created_at ? $report->created_at->format('d M Y - h:i A') : 'N/A',
            ]
        ]);
    }

}