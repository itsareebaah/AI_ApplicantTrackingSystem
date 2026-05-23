<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Job;
use App\Models\Matches;
use App\Models\Skill;
use App\Services\LocalAiMatcher;
use App\Services\ResumeParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ResumeController extends Controller
{
    public function __construct(
        private ResumeParserService $resumeParser,
        private LocalAiMatcher $localAiMatcher,
    ) {
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'job_id' => ['nullable', 'exists:jobs,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        if ($request->job_id) {
            $job = Job::find($request->job_id);
            if (!$job || $job->user_id !== $request->user()->id) {
                return response()->json(['message' => 'Invalid job'], 422);
            }
        }

        $file = $request->file('resume');
        $storedPath = $file->store('resumes', 'local');

        $candidate = Candidate::create([
            'user_id' => $request->user()->id,
            'resume_path' => $storedPath,
            'status' => 'parsing',
        ]);

        return response()->json([
            'message' => 'Resume uploaded',
            'candidate' => $candidate,
        ], 201);
    }

    public function status(Candidate $candidate, Request $request)
    {
        if ($candidate->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($candidate->status !== 'parsing') {
            return response()->json(['candidate' => $candidate->load('matches.job')], 200);
        }

        $jobs = Job::where('user_id', $request->user()->id)->where('status', 'open')->get();

        if ($jobs->isEmpty()) {
            $candidate->update(['status' => 'failed', 'error' => 'No open jobs found. Create a job first.']);

            return response()->json(['candidate' => $candidate], 200);
        }

        $absolutePath = Storage::disk('local')->path($candidate->resume_path);
        $extension = pathinfo($candidate->resume_path, PATHINFO_EXTENSION);
        $resumeText = $this->resumeParser->extractText($absolutePath, $extension);
        $resumeBytes = Storage::disk('local')->get($candidate->resume_path);
        $pythonUrl = rtrim(config('services.ai.url', 'http://127.0.0.1:5001'), '/');
        $filename = basename($candidate->resume_path);

        try {
            $allMatches = [];
            $firstPayload = null;

            foreach ($jobs as $job) {
                $payload = $this->callAiService(
                    $pythonUrl,
                    $resumeBytes,
                    $filename,
                    $resumeText,
                    $job,
                    (string) $candidate->id
                );

                if (!$payload) {
                    continue;
                }

                if (!$firstPayload) {
                    $firstPayload = $payload;
                }

                $matchPct = (int) ($payload['match_percentage'] ?? 0);
                $this->persistMatch($candidate, $job, $payload, $matchPct, $request->user()->id);
                $allMatches[] = ['job_id' => $job->id, 'match_percentage' => $matchPct];
            }

            if (empty($allMatches)) {
                foreach ($jobs as $job) {
                    $payload = $this->localAiMatcher->match(
                        $resumeText,
                        $job->title ?? '',
                        $job->description ?? '',
                        $job->required_skills_json ?? []
                    );

                    if (!$firstPayload) {
                        $firstPayload = $payload;
                    }

                    $matchPct = (int) ($payload['match_percentage'] ?? 0);
                    $this->persistMatch($candidate, $job, $payload, $matchPct, $request->user()->id);
                    $allMatches[] = ['job_id' => $job->id, 'match_percentage' => $matchPct];
                }
            }

            if (empty($allMatches)) {
                $candidate->update([
                    'status' => 'failed',
                    'error' => 'Could not parse resume. Ensure the Python AI service is running (port 5001).',
                ]);

                return response()->json(['candidate' => $candidate], 200);
            }

            usort($allMatches, fn ($a, $b) => $b['match_percentage'] <=> $a['match_percentage']);
            $rank = 1;
            foreach ($allMatches as $m) {
                Matches::where('candidate_id', $candidate->id)
                    ->where('job_id', $m['job_id'])
                    ->update(['rank' => $rank]);
                $m['rank'] = $rank++;
            }

            $bestMatch = $allMatches[0]['match_percentage'] ?? 0;

            $candidate->update([
                'status' => 'done',
                'extracted_skills_json' => $firstPayload['skills'] ?? [],
                'parsed_education_json' => $firstPayload['education'] ?? [],
                'parsed_experience_json' => $firstPayload['experience'] ?? [],
                'ai_match_percentage' => $bestMatch,
                'ai_rank' => 1,
                'parsed_name' => $firstPayload['name'] ?? null,
                'parsed_email' => $firstPayload['email'] ?? null,
                'parsed_phone' => $firstPayload['phone'] ?? null,
            ]);

            return response()->json(['candidate' => $candidate->fresh()->load('matches.job')], 200);
        } catch (\Throwable $e) {
            Log::error('Resume parsing failed', ['error' => $e->getMessage(), 'candidate_id' => $candidate->id]);
            $candidate->update(['status' => 'failed', 'error' => $e->getMessage()]);

            return response()->json(['candidate' => $candidate], 200);
        }
    }

    public function matches(Request $request)
    {
        $jobId = $request->query('job_id');

        $jobQuery = Job::where('user_id', $request->user()->id);

        $job = $jobId
            ? $jobQuery->where('id', $jobId)->first()
            : $jobQuery->latest()->first();

        if (!$job) {
            return response()->json(['job' => null, 'matches' => [], 'jobs' => []]);
        }

        $matches = Matches::with(['candidate', 'job'])
            ->where('job_id', $job->id)
            ->orderByDesc('match_percentage')
            ->get();

        $jobs = Job::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get(['id', 'title']);

        return response()->json([
            'job' => $job,
            'jobs' => $jobs,
            'matches' => $matches,
        ]);
    }

    public function matchesForCandidate(Candidate $candidate, Request $request)
    {
        if ($candidate->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $matches = Matches::with('job')
            ->where('candidate_id', $candidate->id)
            ->orderByDesc('match_percentage')
            ->get();

        return response()->json(['candidate' => $candidate, 'matches' => $matches]);
    }

    private function callAiService(
        string $pythonUrl,
        string $resumeBytes,
        string $filename,
        string $resumeText,
        Job $job,
        string $candidateId,
    ): ?array {
        try {
            $health = Http::timeout(5)->get($pythonUrl . '/health');
            if (!$health->successful()) {
                Log::warning('AI health check failed', ['url' => $pythonUrl, 'status' => $health->status()]);

                return null;
            }

            $httpResp = Http::timeout(180)->attach(
                'resume',
                $resumeBytes,
                $filename
            )->post($pythonUrl . '/parse-and-match', [
                'job_description' => $job->description ?? '',
                'job_title' => $job->title ?? '',
                'required_skills' => json_encode($job->required_skills_json ?? []),
                'candidate_id' => $candidateId,
                'resume_text' => $resumeText,
            ]);

            if ($httpResp->successful()) {
                return $httpResp->json();
            }

            Log::warning('AI parse-and-match failed', [
                'status' => $httpResp->status(),
                'body' => $httpResp->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI service connection error', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function persistMatch(Candidate $candidate, Job $job, array $payload, int $matchPct, int $userId): void
    {
        $extractedSkills = $payload['skills'] ?? [];
        $this->syncSkills($extractedSkills);

        Matches::updateOrCreate(
            [
                'candidate_id' => $candidate->id,
                'job_id' => $job->id,
            ],
            [
                'rank' => 0,
                'match_percentage' => $matchPct,
                'status' => $matchPct >= 60 ? 'shortlisted' : 'needs_review',
                'skill_comparison_json' => $payload['skill_comparison'] ?? null,
                'interview_questions_json' => $payload['interview_questions'] ?? null,
                'raw_json' => $payload,
            ]
        );

        Application::updateOrCreate(
            [
                'candidate_id' => $candidate->id,
                'job_id' => $job->id,
            ],
            [
                'user_id' => $userId,
                'status' => $matchPct >= 60 ? 'shortlisted' : 'applied',
            ]
        );
    }

    private function syncSkills(array $skills): void
    {
        foreach ($skills as $skill) {
            $name = is_string($skill) ? trim($skill) : null;
            if (!$name) {
                continue;
            }
            Skill::firstOrCreate(['name' => $name]);
        }
    }
}
