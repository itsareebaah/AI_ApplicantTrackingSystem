<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CandidateController extends Controller
{
    public function index(Request $request)
    {
        $query = Candidate::where('user_id', $request->user()->id)
            ->orderByDesc('ai_match_percentage');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('parsed_name', 'like', "%{$search}%")
                    ->orWhere('parsed_email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($minScore = $request->query('min_score')) {
            $query->where('ai_match_percentage', '>=', (int) $minScore);
        }

        $candidates = $query->paginate((int) $request->query('per_page', 12));

        return response()->json($candidates);
    }

    public function show(Request $request, Candidate $candidate)
    {
        if ($candidate->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $candidate->load(['matches.job']);

        return response()->json(['candidate' => $candidate]);
    }

    public function updateNotes(Request $request, Candidate $candidate)
    {
        if ($candidate->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'hr_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $candidate->update(['hr_notes' => $request->hr_notes]);

        return response()->json(['candidate' => $candidate]);
    }
}
