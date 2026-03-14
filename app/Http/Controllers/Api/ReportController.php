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
            $imageFile = $request->file('image');

            // 1. حفظ الصورة في Laravel
            if ($request->hasFile('image')) {
                $path = $imageFile->store('reports', 'public');
                $photoUrl = asset('storage/' . $path);
            }

            // 2. محاولة الاتصال بنظام الـ AI   هنا لينك احمد 
            $aiUrl = "https://freckly-sleeveless-louvenia.ngrok-free.dev/api/classify";
            
            $suggestedDeptName = 'general_emergency'; 
            $confidence = 0;

            try {
                // التعديل الجوهري: إضافة asMultipart() لضمان إرسال البيانات كـ Form Data وليس JSON
                $aiResponse = Http::timeout(30)
                    ->asMultipart() 
                    ->attach(
                        'image', 
                        fopen($imageFile->getRealPath(), 'r'), 
                        $imageFile->getClientOriginalName()
                    )
                    ->post($aiUrl, [
                        'notes' => $request->description, // إرسال الوصف تحت مسمى notes كما يتوقع Flask
                    ]);

                // تسجيل الرد في اللوج لمعرفة سبب الـ 415 إذا استمرت
                Log::info("AI Response Body: " . $aiResponse->body());

                if ($aiResponse->successful()) {
                    $aiData = $aiResponse->json();
                    $tempConfidence = $aiData['confidence'] ?? 0;
                    
                    if ($tempConfidence > 0.30) {
                        $suggestedDeptName = $aiData['suggested_department'] ?? 'general_emergency';
                        $confidence = $tempConfidence;
                    }
                }
            } catch (Exception $aiEx) {
                Log::error("AI Connection Failed: " . $aiEx->getMessage());
            }

            // 3. البحث عن المشرف
            $supervisor = Supervisor::whereRaw('LOWER(department_name) LIKE ?', ['%' . strtolower($suggestedDeptName) . '%'])->first();

            // 4. إنشاء البلاغ
            $report = Report::create([
                'user_id'            => auth()->id(),
                'title'              => $request->title,
                'description'        => $request->description,
                'photo_url'          => $photoUrl,
                'current_status'     => 'Pending',
                'report_date'        => now(),
                'priority_level'     => 'Medium',
                'report_type'        => 'External',
                'sorted'             => ($confidence > 0.30), 
                'location_address'   => $request->location_address,
                'latitude'           => $request->latitude,
                'longitude'          => $request->longitude,
                'ai_confidence_score'=> $confidence,
                'supervisor_id'      => $supervisor ? $supervisor->supervisor_id : null,
            ]);

            // 5. تحديث التايم لاين
            $deptNameAr = $supervisor ? $supervisor->department_name : "الطوارئ العامة (قيد الفرز)";
            $report->statusUpdates()->create([
                'user_id'    => auth()->id(),
                'new_status' => 'Submitted',
                'update_type'=> 'Status Change',
                'content'    => "تم استلام البلاغ وتوجيهه لقسم: " . $deptNameAr,
                'timestamp'  => now(),
            ]);

            return response()->json([
                'status' => true, 
                'message' => 'تم إنشاء البلاغ بنجاح', 
                'ai_analysis' => [
                    'confidence' => $confidence,
                    'department' => $suggestedDeptName
                ],
                'data' => $report->load('statusUpdates')
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'خطأ في الخادم الداخلي',
                'error' => $e->getMessage() 
            ], 500);
        }
    }

    public function index() {
        $reports = Report::where('user_id', auth()->id())->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => true, 'data' => $reports]);
    }

    public function show($id) {
        try {
            $report = Report::with(['statusUpdates' => function($query) {
                                $query->orderBy('timestamp', 'asc');
                            }])
                            ->where('user_id', auth()->id())
                            ->findOrFail($id);
            return response()->json(['status' => true, 'data' => $report]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'البلاغ غير موجود'], 404);
        }
    }
}