<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Supervisor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Exception;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * 1. إنشاء بلاغ جديد مع ربط الـ AI وتحويل الصورة لـ Base64
     */
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string',
            'description' => 'required|string',
            'image'       => 'required|image|max:5120', 
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'location_address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $photoUrl = null;
            $imageBase64 = null;

            if ($request->hasFile('image')) {
                $imageFile = $request->file('image');
                
                // حفظ الصورة في التخزين المحلي (للعرض في التطبيق)
                $path = $imageFile->store('reports', 'public');
                $photoUrl = asset('storage/' . $path);

                // تحويل الصورة لـ Base64 لضمان وصولها للـ AI بدون مشاكل Ngrok
                $imageBase64 = base64_encode(file_get_contents($imageFile->getRealPath()));
            }

            // إعدادات الـ AI
            $aiUrl = "https://Besoxjr-3in-el-balad.hf.space/api/classify";
            $suggestedDeptName = 'general_emergency'; // القسم الافتراضي
            $confidence = 0;

            try {
                $aiResponse = Http::timeout(30)->post($aiUrl, [
                    'description' => $request->description,
                    'image_base64' => $imageBase64 
                ]);

                if ($aiResponse->successful()) {
                    $aiData = $aiResponse->json();
                    $tempConfidence = $aiData['confidence'] ?? 0;
                    
                    /* * [تعديل]: تم رفع حد الثقة (Threshold) إلى 0.50
                     * إذا كانت نسبة تأكد الـ AI أقل من 50%، سيتم تجاهل التصنيف
                     * وتحويل البلاغ لـ "الطوارئ العامة" لضمان الدقة البشرية.
                     */
                    if ($tempConfidence >= 0.50) { 
                        $suggestedDeptName = $aiData['category'] ?? 'general_emergency';
                        $confidence = $tempConfidence;
                    } else {
                        Log::warning("AI Confidence low ({$tempConfidence}). Defaulting to General Emergency.");
                    }
                }
            } catch (Exception $aiEx) {
                Log::error("AI System Failure: " . $aiEx->getMessage());
            }

            // البحث عن المشرف بناءً على القسم المختار
            $supervisor = Supervisor::whereRaw('LOWER(department_name) LIKE ?', ['%' . strtolower($suggestedDeptName) . '%'])->first();

            // إنشاء البلاغ
            $report = Report::create([
                'user_id'            => auth()->id(),
                'title'              => $request->title,
                'description'        => $request->description,
                'photo_url'          => $photoUrl,
                'current_status'     => 'Pending',
                'report_date'        => now(),
                'priority_level'     => 'Medium',
                'report_type'        => 'External',
                'sorted'             => ($confidence >= 0.50), // يعتبر مصنفاً فقط إذا تخطى الـ 50%
                'location_address'   => $request->location_address,
                'latitude'           => $request->latitude,
                'longitude'          => $request->longitude,
                'ai_confidence_score'=> $confidence,
                'supervisor_id'      => $supervisor ? $supervisor->supervisor_id : null,
            ]);

            // إضافة التحديث الأول في التايم لاين
            $deptNameAr = $supervisor ? $supervisor->department_name : "الطوارئ العامة (قيد الفرز)";
            
            /* * [تعديل]: إضافة تنبيه في الرد والتايم لاين لو كان التصنيف غير مؤكد
             */
            $isReliable = ($confidence >= 0.50);
            $timelineNote = (!$isReliable && $confidence > 0) 
                            ? " (جاري التحقق من التصنيف من قبل المختصين)" 
                            : "";

            $report->statusUpdates()->create([
                'user_id'    => auth()->id(),
                'new_status' => 'Submitted',
                'update_type'=> 'Status Change',
                'content'    => "تم استلام البلاغ وتوجيهه لقسم: " . $deptNameAr . $timelineNote,
                'timestamp'  => now(),
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'تم إنشاء البلاغ وتصنيفه بنجاح',
                'ai_analysis' => [
                    'confidence'  => number_format($confidence, 2),
                    'category'    => $suggestedDeptName,
                    'is_reliable' => $isReliable
                ],
                'data' => $report->load('statusUpdates')
            ], 201);

        } catch (Exception $e) {
            Log::error("Critical Store Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'خطأ في حفظ البيانات'], 500);
        }
    }

    /**
     * 2. عرض كل بلاغاتي (My Tickets)
     */
    public function index() {
        $reports = Report::with('supervisor')
                         ->where('user_id', auth()->id())
                         ->orderBy('created_at', 'desc')
                         ->get()
                         ->map(function($report) {
                            return [
                                'report_id'  => $report->report_id,
                                'title'      => $report->title,
                                'status'     => $report->current_status,
                                'photo'      => $report->photo_url,
                                'date'       => $report->created_at->format('Y-m-d'),
                                'department' => $report->supervisor->department_name ?? 'الطوارئ العامة'
                            ];
                         });
                         
        return response()->json([
            'status' => true, 
            'count'  => $reports->count(),
            'data'   => $reports
        ]);
    }

    /**
     * 3. تتبع بلاغ محدد بالتفصيل مع التايم لاين
     */
    public function show($id) {
        try {
            $report = Report::with(['statusUpdates' => function($query) { 
                                $query->orderBy('timestamp', 'asc'); 
                            }])
                            ->where('user_id', auth()->id())
                            ->findOrFail($id);

            return response()->json([
                'status' => true, 
                'data'   => [
                    'details'  => $report->makeHidden(['statusUpdates']),
                    'timeline' => $report->statusUpdates->map(function($update) {
                        return [
                            'status'  => $update->new_status,
                            'info'    => $update->content,
                            'time'    => $update->timestamp->format('H:i A'),
                            'date'    => $update->timestamp->format('Y-m-d')
                        ];
                    })
                ]
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'البلاغ غير موجود'], 404);
        }
    }
}