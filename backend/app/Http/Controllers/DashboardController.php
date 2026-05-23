<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Job;
use App\Models\Matches;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $userId = $request->user()->id;

        $totalCandidates = Candidate::where('user_id', $userId)->count();
        $totalJobs = Job::where('user_id', $userId)->count();
        $totalMatches = Matches::whereHas('candidate', fn ($q) => $q->where('user_id', $userId))->count();
        $shortlisted = Matches::whereHas('candidate', fn ($q) => $q->where('user_id', $userId))
            ->where('status', 'shortlisted')
            ->count();

        $avgMatch = (int) Matches::whereHas('candidate', fn ($q) => $q->where('user_id', $userId))
            ->avg('match_percentage');

        $recentActivities = Candidate::where('user_id', $userId)
            ->latest()
            ->limit(8)
            ->get(['id', 'parsed_name', 'status', 'ai_match_percentage', 'created_at']);

        $hiringTrend = Matches::query()
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->whereHas('candidate', fn ($q) => $q->where('user_id', $userId))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $matchDistribution = [
            'excellent' => Matches::whereHas('candidate', fn ($q) => $q->where('user_id', $userId))->where('match_percentage', '>=', 80)->count(),
            'good' => Matches::whereHas('candidate', fn ($q) => $q->where('user_id', $userId))->whereBetween('match_percentage', [60, 79])->count(),
            'review' => Matches::whereHas('candidate', fn ($q) => $q->where('user_id', $userId))->where('match_percentage', '<', 60)->count(),
        ];

        return response()->json([
            'stats' => [
                'total_candidates' => $totalCandidates,
                'total_jobs' => $totalJobs,
                'total_matches' => $totalMatches,
                'shortlisted' => $shortlisted,
                'avg_match_percentage' => $avgMatch,
                'applications' => Application::where('user_id', $userId)->count(),
            ],
            'recent_activities' => $recentActivities,
            'hiring_trend' => $hiringTrend,
            'match_distribution' => $matchDistribution,
        ]);
    }
}
