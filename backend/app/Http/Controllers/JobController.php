<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $jobs = Job::where('user_id', $request->user()->id)
            ->withCount('matches')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['jobs' => $jobs]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'required_skills' => ['nullable', 'array'],
            'experience_requirements' => ['nullable', 'string'],
            'status' => ['nullable', 'in:open,closed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $job = Job::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'required_skills_json' => $request->required_skills ?? [],
            'experience_requirements' => $request->experience_requirements,
            'status' => $request->status ?? 'open',
        ]);

        return response()->json(['job' => $job], 201);
    }

    public function update(Request $request, Job $job)
    {
        if ($job->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'required_skills' => ['nullable', 'array'],
            'experience_requirements' => ['nullable', 'string'],
            'status' => ['nullable', 'in:open,closed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $job->update([
            'title' => $request->title ?? $job->title,
            'description' => $request->description ?? $job->description,
            'required_skills_json' => $request->has('required_skills')
                ? ($request->required_skills ?? [])
                : $job->required_skills_json,
            'experience_requirements' => $request->experience_requirements ?? $job->experience_requirements,
            'status' => $request->status ?? $job->status,
        ]);

        return response()->json(['job' => $job]);
    }

    public function destroy(Request $request, Job $job)
    {
        if ($job->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $job->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
