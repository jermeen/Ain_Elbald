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
            'image'       => 'nullable|image|max:5120', // الملف المرفوع من Postman
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

            // إنشاء السجل مع التأكد من مطابقة الأسماء للـ Fillable عندك
            $report = Report::create([
                'user_id'            => auth()->id(),      // ID اليوزر من الـ Token
                'title'              => $request->title,
                'description'        => $request->description,
                'photo_url'          => $photoUrl,         // ربط الملف المرفوع بـ photo_url
                'current_status'     => 'Pending',
                'report_date'        => now(),
                'priority_level'     => 'Medium',
                'report_type'        => 'External',
                'sorted'             => false,             // قيمة boolean كما في الـ Migration
                'location_address'   => $request->location_address ?? null,
                'latitude_longitude' => $request->latitude_longitude ?? null,
                'ai_confidence_score'=> null,              // حقول إضافية موجودة في الـ Fillable
                'admin_id'           => null,
                'supervisor_id'      => null,
            ]);

            return response()->json([
                'status' => true, 
                'message' => 'تم إنشاء البلاغ بنجاح', 
                'data' => $report
            ], 201);

        } catch (Exception $e) {
            // هنا الـ Postman هيقولك بالظبط إيه اللي ناقص في الداتابيز
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
            // سحب التقرير مع تحديثات الحالة المرتبطة (Status Updates)
            $report = Report::with('statusUpdates')
                            ->where('user_id', auth()->id())
                            ->findOrFail($id);

            return response()->json(['status' => true, 'data' => $report]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'البلاغ غير موجود أو لا تملك صلاحية الوصول إليه'], 404);
        }
    }
}