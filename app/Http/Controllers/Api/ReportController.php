<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class ReportController extends Controller
{
    // 1. إنشاء بلاغ (زرار Create Ticket)
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string',
            'description' => 'required|string',
            'image'       => 'nullable|image|max:5120',
            'latitude'    => 'nullable|numeric', // لاستقبال خط العرض من الخريطة
            'longitude'   => 'nullable|numeric', // لاستقبال خط الطول من الخريطة
            'location_address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $photoUrl = null;
            if ($request->hasFile('image')) {
                // تخزين الملف في storage/app/public/reports
                $path = $request->file('image')->store('reports', 'public');
                // تحويل المسار لرابط كامل (Asset URL)
                $photoUrl = asset('storage/' . $path);
            }

            // إنشاء البلاغ
            $report = Report::create([
                'user_id'            => auth()->id(),      // ID اليوزر من الـ Token
                'title'              => $request->title,
                'description'        => $request->description,
                'photo_url'          => $photoUrl,
                'current_status'     => 'Pending',
                'report_date'        => now(),
                'priority_level'     => 'Medium',
                'report_type'        => 'External',
                'sorted'             => false,
                'location_address'   => $request->location_address,
                'latitude'           => $request->latitude,  // الحقل الجديد
                'longitude'          => $request->longitude, // الحقل الجديد
                'admin_id'           => null,
                'supervisor_id'      => null,
            ]);

            // --- الخطوة المهمة جداً لشاشة الـ Tracking ---
            // إضافة أول حالة للبلاغ في جدول التحديثات عشان تظهر في التايم لاين عند الفلاتر
            $report->statusUpdates()->create([
                'user_id'    => auth()->id(),
                'new_status' => 'Submitted',
                'update_type'=> 'Status Change',
                'content'    => 'Your ticket has been successfully submitted.',
                'timestamp'  => now(),
            ]);

            return response()->json([
                'status' => true, 
                'message' => 'تم إنشاء البلاغ وبدء التتبع بنجاح', 
                'data' => $report->load('statusUpdates') // بنرجع البيانات ومعاها التتبع بتاعها
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء الحفظ في قاعدة البيانات',
                'error' => $e->getMessage() 
            ], 500);
        }
    }

    // 2. عرض بلاغاتي (زرار My Tickets)
    public function index() {
        // ترتيب تنازلي عشان الجديد يظهر فوق
        $reports = Report::where('user_id', auth()->id())->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => true, 'data' => $reports]);
    }

    // 3. تتبع بلاغ (زرار Track My Ticket)
    public function show($id) {
        try {
            // سحب التقرير مع تحديثات الحالة المرتبطة (Status Updates) مرتبة من الأقدم للأحدث للتايم لاين
            $report = Report::with(['statusUpdates' => function($query) {
                                $query->orderBy('timestamp', 'asc');
                            }])
                            ->where('user_id', auth()->id())
                            ->findOrFail($id);

            return response()->json(['status' => true, 'data' => $report]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'البلاغ غير موجود أو لا تملك صلاحية الوصول إليه'], 404);
        }
    }
}
//reportcontroller
