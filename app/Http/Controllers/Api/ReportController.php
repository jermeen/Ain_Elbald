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
use App\Notifications\GeneralNotification; // [إضافة]: استدعاء كلاس الإشعارات

class ReportController extends Controller
{
    /**
     * 1. إنشاء بلاغ جديد (الصورة اختيارية)
     */
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string',
            'description' => 'required|string',
            'image'       => 'nullable|image|max:5120', 
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
                $path = $imageFile->store('reports', 'public');
                $photoUrl = asset('storage/' . $path);
                $imageBase64 = base64_encode(file_get_contents($imageFile->getRealPath()));
            }

            $aiUrl = "https://Besoxjr-3in-el-balad.hf.space/api/classify";
            $suggestedDeptName = 'general_emergency'; 
            $confidence = 0;

            try {
                $aiResponse = Http::timeout(30)->post($aiUrl, [
                    'description' => $request->description,
                    'image_base64' => $imageBase64 
                ]);

                if ($aiResponse->successful()) {
                    $aiData = $aiResponse->json();
                    $tempConfidence = $aiData['confidence'] ?? 0;
                    
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

            $supervisor = Supervisor::whereRaw('LOWER(department_name) LIKE ?', ['%' . strtolower($suggestedDeptName) . '%'])->first();

            $report = Report::create([
                'user_id'             => auth()->id(),
                'title'               => $request->title,
                'description'         => $request->description,
                'photo_url'           => $photoUrl,
                'current_status'      => 'Pending',
                'report_date'         => now(),
                'priority_level'      => 'Medium',
                'report_type'         => 'External',
                'sorted'              => ($confidence >= 0.50),
                'location_address'    => $request->location_address,
                'latitude'            => $request->latitude,
                'longitude'           => $request->longitude,
                'ai_confidence_score'=> $confidence,
                'supervisor_id'       => $supervisor ? $supervisor->supervisor_id : null,
            ]);

            $deptNameAr = $supervisor ? $supervisor->department_name : "الطوارئ العامة (قيد الفرز)";
            
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

            // ========================================================================
            // START: [إضافة الإشعار للسوبرفايزر الجديد]
            // ========================================================================
            if ($supervisor) {
                $supervisor->notify(new GeneralNotification([
                    'title'     => 'New Report Submitted',
                    'message'   => 'A new report #' . $report->report_id . ' has been assigned to your department (' . $deptNameAr . ').',
                    'report_id' => $report->report_id,
                    'status'    => 'New',
                    'photo'     => $report->photo_url,
                ]));
            }
            // ========================================================================
            // END: [إضافة الإشعار]
            // ========================================================================

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
     * 2. عرض كل بلاغاتي
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
     * 3. تتبع بلاغ محدد
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