<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Emotion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmotionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'emotion' => 'required|string',
            'satisfaction' => 'required|string'
        ]);

        $emotion = Emotion::create($data);

        return response()->json([
            'message' => 'Saved',
            'data' => $emotion
        ]);
    }

    public function dashboard()
    {
        // total hari ini
        $todayCount = DB::table('emotion_logs')
            ->whereDate('created_at', Carbon::today())
            ->count();

        // count per emosi
        $emotions = DB::table('emotion_logs')
            ->select('emotion', DB::raw('count(*) as total'))
            ->groupBy('emotion')
            ->get();

        // format ke array
        $emotionData = [
            'angry' => 0,
            'happy' => 0,
            'neutral' => 0,
            'sad' => 0
        ];

        foreach ($emotions as $e) {
            $emotionData[$e->emotion] = $e->total;
        }

        // total semua
        $total = array_sum($emotionData);

        // hitung persen
        foreach ($emotionData as $key => $val) {
            $emotionData[$key] = $total > 0 ? round(($val / $total) * 100) : 0;
        }
        

        return view('dashboard', compact('todayCount', 'emotionData'));
    }

    public function index()
    {
        $todayCount = DB::table('emotions')
            ->whereDate('created_at', Carbon::today())
            ->count();

        $emotions = DB::table('emotions')
            ->select('emotion', DB::raw('count(*) as total'))
            ->groupBy('emotion')
            ->get();

        $emotionData = [
            'angry' => 0,
            'happy' => 0,
            'neutral' => 0,
            'sad' => 0
        ];

        foreach ($emotions as $e) {
            $emotionData[$e->emotion] = $e->total;
        }

        $total = array_sum($emotionData);

        foreach ($emotionData as $key => $val) {
            $emotionData[$key] = $total > 0 ? round(($val / $total) * 100) : 0;
        }

        $puas = ($emotionData['happy'] ?? 0) + ($emotionData['neutral'] ?? 0);
        $tidakPuas = ($emotionData['angry'] ?? 0) + ($emotionData['sad'] ?? 0);

        return view('dashboard', compact('todayCount', 'emotionData', 'puas', 'tidakPuas'));
    }

    public function analytics(Request $request)
    {
        $type = $request->get('type', 'daily');

        $chartQuery = DB::table('emotions');

        if ($type === 'daily') {
            $chartQuery->whereDate('created_at', today());
        } elseif ($type === 'weekly') {
            $chartQuery->whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ]);
        } elseif ($type === 'monthly') {
            $chartQuery->whereMonth('created_at', now()->month);
        }

        // ===== CHART DATA =====
        $emotions = $chartQuery
            ->select('emotion', DB::raw('count(*) as total'))
            ->groupBy('emotion')
            ->pluck('total', 'emotion');

        $labels = ['happy', 'neutral', 'sad', 'angry'];

        $data = [
            $emotions['happy'] ?? 0,
            $emotions['neutral'] ?? 0,
            $emotions['sad'] ?? 0,
            $emotions['angry'] ?? 0,
        ];

        $total = array_sum($data);

        // ===== SEARCH LOGIC =====
        $query = DB::table('emotions');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('emotion', 'like', '%' . $request->search . '%')
                ->orWhere('satisfaction', 'like', '%' . $request->search . '%')
                ->orWhere('id', 'like', '%' . $request->search . '%');
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(5);

        // ===== RETURN SEKALI AJA =====
        return view('analytics', compact('labels', 'data', 'total', 'logs'));
    }    
}