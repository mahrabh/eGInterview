<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Interview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelReader;

class InterviewController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Interview::class);

        $query = Interview::with('user')
            ->orderByRaw("CASE status WHEN 'draft' THEN 1 WHEN 'pending' THEN 2 WHEN 'approved' THEN 3 WHEN 'completed' THEN 4 ELSE 5 END ASC")
            ->latest('updated_at');
        
        $users = [];
        if (!auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        } else {
            $users = User::whereIn('role', ['admin', 'recruiter'])->orderBy('name')->get();
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
        }

        if ($request->filled('status')) {
            if ($request->status === 'expired') {
                $query->where('status', 'approved')->whereNotNull('link_expires_at')->where('link_expires_at', '<', now());
            } elseif ($request->status === 'approved') {
                $query->where('status', 'approved')->where(function($q) {
                    $q->whereNull('link_expires_at')->orWhere('link_expires_at', '>=', now());
                });
            } else {
                $query->where('status', $request->status);
            }
        }

        $interviews = $query->paginate(15)->appends($request->query());
        return view('recruitment', compact('interviews', 'users'));
    }

    public function import(Request $request)
    {
        Gate::authorize('create', Interview::class);

        $request->validate([
            'file' => 'required|mimes:csv,txt,xlsx,xls',
        ]);

        try {
            $file = $request->file('file');
            $path = $file->getRealPath();
            $extension = $file->getClientOriginalExtension();

            if (strtolower($extension) === 'xlsx' && !extension_loaded('zip')) {
                return back()->withErrors([
                    'file' => 'XLSX import requires PHP zip extension. Please enable extension=zip or upload CSV instead.',
                ]);
            }

            $rows = SimpleExcelReader::create($path, $extension)->getRows();
            $firstRow = $rows->first();

            // Check for required columns (case-insensitive)
            $requiredColumns = ['candidate_name', 'applied_role', 'job_description', 'candidate_email'];
            $headers = array_keys(array_change_key_case($firstRow, CASE_LOWER));

            $missingColumns = [];
            foreach ($requiredColumns as $required) {
                if (!in_array($required, $headers)) {
                    $missingColumns[] = $required;
                }
            }

            if (!empty($missingColumns)) {
                return back()->withErrors([
                    'file' => 'Invalid template. Required columns: ' . implode(', ', $requiredColumns) . '. Missing: ' . implode(', ', $missingColumns),
                ]);
            }

            $user = auth()->user();
            if (!$user->isAdmin()) {
                $plan = $user->plan;
                if (!$plan) {
                    return back()->withErrors(['file' => 'You need an active plan to import candidates.']);
                }
                $used = $user->interviews()->count();
                if ($used >= $plan->interview_limit) {
                    return back()->withErrors(['file' => 'You have reached your plan limit for candidates. Please upgrade your plan.']);
                }
            }

            $count = 0;
            $skipped = 0;

            foreach ($rows as $index => $row) {
                if (!$user->isAdmin() && ($used + $count) >= $plan->interview_limit) {
                    $skipped += (count($rows) - $index);
                    break;
                }

                $data = array_change_key_case($row, CASE_LOWER);

                if (empty(array_filter($data))) {
                    $skipped++;
                    continue;
                }

                Interview::create([
                    'candidate_name'  => $data['candidate_name'] ?? 'Unknown',
                    'candidate_email' => $data['candidate_email'] ?? null,
                    'applied_role'    => $data['applied_role'] ?? 'General',
                    'job_description' => $data['job_description'] ?? null,
                    'status'          => 'draft',
                    'user_id'         => auth()->id() ?? 1,
                ]);

                $count++;
            }

            $message = "Imported {$count} candidates successfully!";
            if ($skipped > 0) {
                $message .= " ({$skipped} empty rows skipped)";
            }

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            Log::error('Import error: ' . $e->getMessage());

            $message = $e->getMessage();
            if (!extension_loaded('zip') && (str_contains($message, 'ZipArchive') || str_contains($message, 'zip'))) {
                $message = 'XLSX import requires PHP zip extension. Please enable extension=zip or upload CSV instead.';
            }

            return back()->withErrors([
                'file' => 'Import Error: ' . $message,
            ]);
        }
    }

    public function generateQuestions(Interview $interview)
    {
        Gate::authorize('update', $interview);

        try {
            $prompt = "You are an expert technical recruiter.
Generate exactly 10 interview questions for the role '{$interview->applied_role}'.

Rules:
- Base them on this job description: '{$interview->job_description}'
- Focus on fundamental and practical knowledge
- Keep each question clear and concise
- Return ONLY a valid JSON array of strings
- Do not wrap the JSON in markdown";

            // Try primary model first only
            $result = $this->callGeminiSingleModelWithRetry(
                $prompt,
                (string) config('services.gemini.text_model'),
                true
            );

            $questions = $this->parseJsonArrayFromGeminiText($result['text']);

            // Only fallback if primary returned invalid format
            if (empty($questions)) {
                Log::warning('Primary model returned unusable question JSON, trying fallback model.');

                $fallback = $this->callGeminiSingleModelWithRetry(
                    $prompt,
                    'gemini-2.0-flash-lite',
                    true
                );

                $questions = $this->parseJsonArrayFromGeminiText($fallback['text']);
            }

            if (empty($questions)) {
                Log::error('Both primary and fallback models returned invalid question JSON.');

                return back()->withErrors([
                    'file' => 'AI returned invalid question format. Please try again.',
                ]);
            }

            $interview->update([
                'approved_questions' => $questions,
                'status' => 'pending',
            ]);

            return redirect()->route('interviews.review', array_filter([
                'interview' => $interview->id,
                'page' => request('page')
            ]));
        } catch (\Throwable $e) {
            Log::error('generateQuestions error: ' . $e->getMessage());

            return back()->withErrors([
                'file' => $this->friendlyGeminiError($e->getMessage()),
            ]);
        }
    }

    public function review(Interview $interview)
    {
        if (auth()->check()) {
            Gate::authorize('view', $interview);
        }

        return view('interview.review', compact('interview'));
    }

    public function approve(Request $request, Interview $interview)
    {
        Gate::authorize('update', $interview);

        $request->validate([
            'questions' => 'required|array|min:1',
            'questions.*' => 'required|string',
        ]);

        $questions = array_values(array_filter(array_map(function ($q) {
            return trim($q);
        }, $request->questions)));

        $interview->update([
            'approved_questions' => $questions,
            'public_url' => $interview->public_url ?: Str::random(32),
            'status' => 'approved',
            'link_expires_at' => now()->addHours(48),
        ]);

        return redirect()->route('recruitment.index', array_filter(['page' => request('page')]))
            ->with('success', 'Link activated for ' . $interview->candidate_name);
    }

    public function regenerateLink(Interview $interview)
    {
        Gate::authorize('update', $interview);

        $interview->update([
            'public_url' => Str::random(32),
            'link_expires_at' => now()->addHours(48),
        ]);

        return back()->with('success', 'New interview link generated successfully!');
    }

    public function publicSession($identifier)
    {
        $interview = $this->resolveInterviewFromIdentifier($identifier);

        if ($interview->status !== 'approved' && $interview->status !== 'completed') {
            abort(403, 'This interview session is not active.');
        }

        if ($interview->status === 'completed') {
            return view('interview.completed', compact('interview'));
        }

        if ($interview->status === 'approved' && $interview->link_expires_at && now()->greaterThan($interview->link_expires_at)) {
            return view('interview.expired');
        } elseif ($interview->status === 'approved') {
            $interview->setAttribute('window_expires_at', $interview->link_expires_at ? $interview->link_expires_at->timestamp * 1000 : 0);
        }

        return view('interview.live', compact('interview'));
    }

    public function saveTranscript(Request $request, $identifier)
    {
        $request->validate([
            'transcript_text' => 'required|string',
        ]);

        $interview = $this->resolveInterviewFromIdentifier($identifier);

        if ($interview->status === 'completed') {
            return response()->json(['error' => 'Interview already completed'], 400);
        }
        $transcript = trim($request->transcript_text);

        try {
            $approvedQuestions = $interview->approved_questions ?? [];
            $questionList = implode(
                "\n",
                array_map(fn($q, $i) => ($i + 1) . '. ' . $q, $approvedQuestions, array_keys($approvedQuestions))
            );

            $prompt = "You are an expert technical recruiter and evaluator.

Analyze the following interview transcript for the role '{$interview->applied_role}'.

The candidate was asked the following approved questions:
{$questionList}

Evaluate the candidate strictly on how well they answered those approved questions.

Return ONLY a valid JSON object with these keys:
- score (number from 1 to 10)
- summary (string, max 2 sentences)
- strengths (array of exactly 3 strings)
- weaknesses (array of exactly 3 strings)

Transcript:
{$transcript}";

            $result = $this->callGeminiSingleModelWithRetry(
                $prompt,
                (string) config('services.gemini.text_model'),
                true
            );

            $evaluation = json_decode($result['text'], true);

            if (!is_array($evaluation)) {
                $fallback = $this->callGeminiSingleModelWithRetry(
                    $prompt,
                    'gemini-2.0-flash-lite',
                    true
                );

                $evaluation = json_decode($fallback['text'], true);
            }

            if (!is_array($evaluation)) {
                throw new \Exception('Invalid evaluation JSON received from Gemini');
            }

            $interview->update([
                'transcript_text' => $transcript,
                'evaluation_json' => $evaluation,
                'status' => 'completed',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Interview saved and evaluated successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Transcript save/evaluation error: ' . $e->getMessage());

            $interview->update([
                'transcript_text' => $transcript,
                'status' => 'completed',
            ]);

            return response()->json([
                'status' => 'partial_success',
                'message' => $this->friendlyGeminiError($e->getMessage()),
                'error' => $e->getMessage(),
            ], 200);
        }
    }

    public function savePhoto(Request $request, $identifier)
    {
        $request->validate([
            'photo' => 'required|string',
        ]);

        $interview = $this->resolveInterviewFromIdentifier($identifier);
        $photo = trim($request->input('photo'));

        if (!Str::startsWith($photo, 'data:image/')) {
            return response()->json(['error' => 'Invalid photo format.'], 400);
        }

        $interview->update([
            'candidate_photo' => $photo,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Candidate photo saved successfully.',
        ], 200);
    }

    public function downloadTemplate(Request $request)
    {
        $filename = 'interview_template.xlsx';
        $rows = [
            ['candidate_name', 'applied_role', 'job_description', 'candidate_email'],
            ['John Doe', 'Software Engineer', 'Develop and maintain web applications using Laravel, React, and MySQL', 'john.doe@example.com'],
            ['Jane Smith', 'Frontend Developer', 'Build responsive user interfaces with React, TypeScript, and modern CSS frameworks', 'jane.smith@example.com'],
        ];

        $content = $this->createXlsx($rows);

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function createXlsx(array $rows): string
    {
        $files = [
            '[Content_Types].xml' => $this->xlsxContentTypes(),
            '_rels/.rels' => $this->xlsxRels(),
            'xl/workbook.xml' => $this->xlsxWorkbook(),
            'xl/_rels/workbook.xml.rels' => $this->xlsxWorkbookRels(),
            'xl/worksheets/sheet1.xml' => $this->xlsxWorksheet($rows),
            'xl/styles.xml' => $this->xlsxStyles(),
        ];

        return $this->zipFiles($files);
    }

    private function xlsxContentTypes(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
XML;
    }

    private function xlsxRels(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private function xlsxWorkbook(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Template" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
    }

    private function xlsxWorkbookRels(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private function xlsxWorksheet(array $rows): string
    {
        $sheetData = '';
        $rowIndex = 1;

        foreach ($rows as $row) {
            $sheetData .= '<row r="' . $rowIndex . '">';
            $col = 'A';
            foreach ($row as $cell) {
                $escaped = htmlspecialchars((string) $cell, ENT_XML1 | ENT_QUOTES);
                $sheetData .= '<c r="' . $col . $rowIndex . '" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
                $col++; // Letters A-Z only for these four columns
            }
            $sheetData .= '</row>';
            $rowIndex++;
        }

        $maxColumn = 'D';
        $lastRow = $rowIndex - 1;

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:' . $maxColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<sheetData>' . $sheetData . '</sheetData>'
            . '</worksheet>';
    }

    private function xlsxStyles(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="1"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font></fonts>
  <fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
  <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>
XML;
    }

    private function zipFiles(array $files): string
    {
        $data = '';
        $centralDirectory = '';
        $offset = 0;

        foreach ($files as $name => $content) {
            $name = str_replace('\\', '/', $name);
            $crc = crc32($content);
            $size = strlen($content);
            $time = $this->zipDosTime();
            $date = $this->zipDosDate();

            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $time, $date, $crc, $size, $size, strlen($name), 0);
            $localHeader .= $name . $content;

            $data .= $localHeader;

            $centralDirectory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $time, $date, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset);
            $centralDirectory .= $name;

            $offset += strlen($localHeader);
        }

        $data .= $centralDirectory;
        $data .= pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($centralDirectory), $offset, 0);

        return $data;
    }

    private function zipDosTime(): int
    {
        $now = localtime(time(), true);
        return ($now['tm_hour'] << 11) | ($now['tm_min'] << 5) | ((int) floor($now['tm_sec'] / 2));
    }

    private function zipDosDate(): int
    {
        $now = localtime(time(), true);
        return (($now['tm_year'] + 1900 - 1980) << 9) | (($now['tm_mon'] + 1) << 5) | $now['tm_mday'];
    }

    public function destroy($identifier)
    {
        if (!Str::isUuid($identifier)) {
            return back()->withErrors([
                'file' => "Invalid interview identifier: {$identifier}",
            ]);
        }

        $interview = Interview::where('id', $identifier)->firstOrFail();
        Gate::authorize('delete', $interview);
        $interview->delete();

        return back()->with('success', 'Candidate deleted successfully.');
    }

    protected function resolveInterviewFromIdentifier(string $identifier): Interview
    {
        if (Str::isUuid($identifier)) {
            return Interview::where('id', $identifier)
                ->orWhere('public_url', $identifier)
                ->firstOrFail();
        }

        return Interview::where('public_url', $identifier)->firstOrFail();
    }

    protected function callGeminiSingleModelWithRetry(string $prompt, string $model, bool $expectJson = false): array
    {
        $apiKey = config('services.gemini.key');

        if (!$apiKey) {
            throw new \Exception('Missing GEMINI_API_KEY in .env');
        }

        $delaySeconds = 1;
        $lastError = 'Unknown Gemini error';

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $payload = [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                    ],
                ];

                if ($expectJson) {
                    $payload['generationConfig']['responseMimeType'] = 'application/json';
                }

                $response = Http::timeout(90)
                    ->connectTimeout(20)
                    ->withoutVerifying()
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'x-goog-api-key' => $apiKey,
                    ])
                    ->post(
                        "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                        $payload
                    );

                if ($response->successful()) {
                    $text = $response->json('candidates.0.content.parts.0.text', '');

                    Log::info("Gemini success: model={$model}, attempt={$attempt}");

                    return [
                        'model' => $model,
                        'text' => $text,
                        'raw' => $response->json(),
                    ];
                }

                $status = $response->status();
                $errorMsg = $response->json()['error']['message'] ?? 'Unknown API error';
                $lastError = "[{$model}] {$errorMsg}";

                Log::warning("Gemini call failed: model={$model}, attempt={$attempt}, status={$status}, error={$errorMsg}");

                if (in_array($status, [429, 500, 502, 503, 504], true)) {
                    sleep($delaySeconds);
                    $delaySeconds *= 2;
                    continue;
                }

                break;
            } catch (\Throwable $e) {
                $lastError = "[{$model}] " . $e->getMessage();

                Log::warning("Gemini transport error: model={$model}, attempt={$attempt}, error=" . $e->getMessage());

                if ($attempt < 4) {
                    sleep($delaySeconds);
                    $delaySeconds *= 2;
                    continue;
                }
            }
        }

        throw new \Exception($lastError);
    }

    protected function parseJsonArrayFromGeminiText(string $text): array
    {
        $decoded = json_decode($text, true);

        if (is_array($decoded)) {
            return array_values(array_filter(array_map(function ($q) {
                return is_string($q) ? trim($q) : null;
            }, $decoded)));
        }

        if (preg_match('/\[[\s\S]*\]/', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map(function ($q) {
                    return is_string($q) ? trim($q) : null;
                }, $decoded)));
            }
        }

        return [];
    }

    protected function friendlyGeminiError(string $error): string
    {
        $normalized = Str::lower($error);

        if (
            Str::contains($normalized, 'currently experiencing high demand') ||
            Str::contains($normalized, 'service unavailable') ||
            Str::contains($normalized, '503') ||
            Str::contains($normalized, 'unavailable')
        ) {
            return 'Google AI is temporarily under heavy load. Please try again in a few moments.';
        }

        if (Str::contains($normalized, 'quota') || Str::contains($normalized, '429')) {
            return 'Google AI rate limit reached. Please wait a bit and try again.';
        }

        if (Str::contains($normalized, 'api key') || Str::contains($normalized, 'permission')) {
            return 'Google AI configuration error. Please check the Gemini API key and permissions.';
        }

        // In debug mode, show the actual error for better debugging
        if (config('app.debug')) {
            return 'Google AI request failed: ' . $error;
        }

        return 'Google AI request failed. Please try again.';
    }
}