<?php

namespace App\Services;

class LocalAiMatcher
{
    private const KNOWN_SKILLS = [
        'react', 'javascript', 'typescript', 'laravel', 'php', 'python',
        'machine learning', 'nlp', 'django', 'flask', 'sql', 'mysql',
        'docker', 'kubernetes', 'aws', 'tailwind', 'node', 'java',
    ];

    public function match(string $resumeText, string $jobTitle, string $jobDescription, array $requiredSkills = []): array
    {
        $skills = $this->extractSkills($resumeText);
        $contact = $this->extractContact($resumeText);

        $required = array_map('strtolower', $requiredSkills);
        $extracted = array_map('strtolower', $skills);

        $matched = [];
        $missing = [];
        foreach ($required as $skill) {
            if (in_array($skill, $extracted, true) || str_contains(strtolower($jobDescription), $skill)) {
                $matched[] = ucfirst($skill);
            } else {
                $missing[] = ucfirst($skill);
            }
        }

        if (empty($required)) {
            $skillPct = count($skills) > 0 ? 55 : 35;
        } else {
            $skillPct = (int) round((count($matched) / max(1, count($required))) * 100);
        }

        $semanticPct = $this->textOverlapScore($resumeText, $jobTitle . "\n" . $jobDescription);
        $matchPercentage = (int) round($semanticPct * 0.65 + $skillPct * 0.35);

        return [
            'name' => $contact['name'],
            'email' => $contact['email'],
            'phone' => $contact['phone'],
            'skills' => $skills,
            'education' => [],
            'experience' => [],
            'match_percentage' => min(100, max(0, $matchPercentage)),
            'rank' => 1,
            'skill_comparison' => [
                'matched' => $matched,
                'missing' => $missing,
                'extracted' => $skills,
            ],
            'interview_questions' => $this->interviewQuestions($skills, $jobTitle),
            'fallback' => true,
        ];
    }

    private function extractSkills(string $text): array
    {
        $lower = strtolower($text);
        $found = [];
        foreach (self::KNOWN_SKILLS as $skill) {
            if (str_contains($lower, $skill)) {
                $found[] = ucfirst($skill);
            }
        }

        return array_values(array_unique($found));
    }

    private function extractContact(string $text): array
    {
        $name = null;
        $email = null;
        $phone = null;

        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $m)) {
            $email = $m[0];
        }
        if (preg_match('/(\+?\d[\d\s().-]{8,}\d)/', $text, $m)) {
            $phone = trim($m[0]);
        }

        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line === '' || str_contains($line, '@')) {
                continue;
            }
            $words = preg_split('/\s+/', $line);
            if ($words && count($words) >= 2 && count($words) <= 5 && strlen($line) < 60) {
                $name = $line;
                break;
            }
        }

        return compact('name', 'email', 'phone');
    }

    private function textOverlapScore(string $resumeText, string $jobText): int
    {
        $resumeWords = array_filter(str_word_count(strtolower($resumeText), 1));
        $jobWords = array_filter(str_word_count(strtolower($jobText), 1));
        if (empty($resumeWords) || empty($jobWords)) {
            return 0;
        }

        $overlap = count(array_intersect($resumeWords, $jobWords));
        $pct = (int) round(($overlap / max(1, count($jobWords))) * 100);

        return min(100, $pct);
    }

    private function interviewQuestions(array $skills, string $jobTitle): array
    {
        $questions = [
            "Describe your experience relevant to the {$jobTitle} role.",
            'Tell us about a challenging project and how you delivered it.',
        ];
        foreach (array_slice($skills, 0, 3) as $skill) {
            $questions[] = "How have you used {$skill} in production?";
        }

        return $questions;
    }
}
