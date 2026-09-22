<?php

namespace App\Services;

use App\Enums\BankOpeningStage;
use App\Models\BankOpeningApplication;
use App\Models\BankOpeningDocument;
use App\Models\BankOpeningInterviewTurn;
use App\Support\BankOpening\AccountProductCatalog;
use App\Support\BankOpening\AmountNormalizer;
use App\Support\BankOpening\AnswerCompleteness;
use App\Support\BankOpening\DocumentRequirementCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BankOpeningInterviewService
{
    public function greeting(?string $applicantName, string $language = 'en'): string
    {
        $name = trim((string) $applicantName);
        $bank = AccountProductCatalog::DISPLAY_NAME;

        if ($language === 'bn') {
            if ($name === '') {
                return "স্বাগতম, {$bank}-এ। আমি আপনাকে সংক্ষিপ্ত অ্যাকাউন্ট খোলার সাক্ষাৎকারে সহায়তা করব।";
            }

            return "স্বাগতম, {$name}। {$bank}-এর সংক্ষিপ্ত অ্যাকাউন্ট খোলার সাক্ষাৎকারে আমি আপনাকে সহায়তা করব।";
        }

        if ($name === '') {
            return "Welcome to {$bank}. I’ll guide you through a short account-opening interview.";
        }

        return "Welcome, {$name}, to {$bank}. I’ll guide you through a short account-opening interview.";
    }

    public function completionMessage(?string $applicantName, string $language = 'en'): string
    {
        $name = trim((string) $applicantName);

        if ($language === 'bn') {
            $who = $name !== '' ? $name : 'আপনি';

            return "ধন্যবাদ, {$who}। আপনার সাক্ষাৎকার সম্পন্ন হয়েছে। এখন ম্যানুয়াল যাচাইয়ের জন্য প্রয়োজনীয় কাগজপত্র জমা দিন।";
        }

        $who = $name !== '' ? $name : 'applicant';
        if ($name === '') {
            return 'Thank you. Your interview is complete. Please submit the requested documents for manual review.';
        }

        return "Thank you, {$name}. Your interview is complete. Please submit the requested documents for manual review.";
    }

    /**
     * @return array<string, mixed>
     */
    public function bootstrap(BankOpeningApplication $application, ?string $publicToken = null): array
    {
        $application->loadMissing(['applicant', 'documents', 'interviewTurns']);
        $product = $application->account_type
            ? AccountProductCatalog::find($application->account_type)
            : null;
        $profile = $application->question_profile
            ?: ($product['question_profile'] ?? null);
        $questions = $profile
            ? array_slice(
                AccountProductCatalog::questionsForProfile($profile),
                0,
                AccountProductCatalog::MAX_PLANNED_QUESTIONS
            )
            : [];

        $interviewState = $this->interviewState($application);
        $displayName = $application->applicant->displayName();
        $language = $application->interview_language ?: 'en';
        $greetingName = filled($application->applicant->name)
            ? trim((string) $application->applicant->name)
            : '';

        return [
            'id' => $application->id,
            'application_id' => $application->id,
            'token' => $publicToken ?? request()->route('token'),
            'public_url' => $publicToken ?? request()->route('token'),
            'candidate_name' => $displayName,
            'applicant_name' => $displayName,
            'first_name' => $this->firstName($application->applicant->name),
            'reference' => $application->applicant->application_reference,
            'status' => $application->stage->value,
            'stage' => $application->stage->value,
            'is_submitted' => $application->isInterviewFinished(),
            'application_submitted' => $application->isApplicationSubmitted(),
            'phase' => $interviewState,
            'interview_state' => $interviewState,
            'interview_language' => $application->interview_language,
            'interview_session_id' => $application->interview_session_id,
            'bank_code' => $application->bank_code ?: AccountProductCatalog::BANK_CODE,
            'bank_name' => AccountProductCatalog::BANK_NAME,
            'display_name' => AccountProductCatalog::DISPLAY_NAME,
            'catalog_version' => $application->catalog_version ?: AccountProductCatalog::CATALOG_VERSION,
            'greeting' => $this->greeting($greetingName, $language),
            'account_type' => $application->account_type,
            'account_label' => $application->accountTypeLabel(),
            'account_type_name_snapshot' => $application->account_type_name_snapshot,
            'account_confirmed' => (bool) $application->account_type_confirmed,
            'product_groups' => AccountProductCatalog::groupedForInterview(),
            'account_type_groups' => AccountProductCatalog::groupedForInterview(),
            'planned_questions' => $questions,
            'live_interview_guide' => AccountProductCatalog::liveInterviewGuide(),
            'current_question' => in_array($interviewState, ['asking_question', 'awaiting_answer', 'clarifying_answer'], true)
                ? $this->currentQuestionPayload($application)
                : null,
            'answered_keys' => array_keys($application->structured_answers ?? []),
            'structured_answers' => $application->structured_answers ?? [],
            'confirmed_summary' => $application->confirmed_summary,
            'summary' => $this->summaryPayload($application),
            'turns' => $this->turnsPayload($application),
            'follow_ups_used' => (int) $application->interview_follow_ups_used,
            'max_follow_ups' => AccountProductCatalog::MAX_FOLLOW_UPS,
            'completion_message' => $this->completionMessage($greetingName, $language),
            'transcript_text' => $application->transcript_text,
            'document_requirements' => $application->account_type_confirmed
                ? $application->documentRequirementGroups()
                : [],
            'documents_required_count' => $application->account_type_confirmed
                ? $application->requiredDocumentsTotal()
                : 0,
            'documents_uploaded_count' => $application->requiredDocumentsUploadedCount(),
            'documents_missing' => $application->account_type_confirmed
                ? $application->missingRequiredDocumentKeys()
                : [],
            'needs_account_type_selection' => ! $application->account_type_confirmed
                || ! filled($application->account_type),
            'account_type_options' => collect(AccountProductCatalog::enabledProducts())
                ->map(fn (array $p) => [
                    'slug' => $p['slug'],
                    'label' => $p['label'],
                    'group_label' => $p['group_label'],
                    'short_description' => $p['short_description'],
                ])
                ->values()
                ->all(),
            'documents_window_hours' => BankOpeningApplication::PUBLIC_LINK_HOURS_DOCS_OR_RESUBMISSION,
            'resubmission' => data_get($application->meta, 'resubmission'),
            'documents' => $application->documents
                ->whereNull('removed_at')
                ->values()
                ->map(fn (BankOpeningDocument $doc) => [
                    'id' => $doc->id,
                    'document_type' => $doc->document_type,
                    'label' => $doc->label(),
                    'original_name' => $doc->original_name,
                    'mime_type' => $doc->mime_type,
                    'size_bytes' => $doc->size_bytes,
                    'is_image' => $doc->isImage(),
                    'created_at' => $doc->created_at?->toIso8601String(),
                ])->values()->all(),
            'interview_completed_at' => $application->interview_completed_at?->toIso8601String(),
            'summary_confirmed_at' => $application->summary_confirmed_at?->toIso8601String(),
            'documents_submitted_at' => $application->documents_submitted_at?->toIso8601String(),
            'public_token_expiry' => $application->public_token_expiry?->toIso8601String(),
            'documents_window_expires_at' => $application->documentsWindowExpiresAt()?->toIso8601String(),
        ];
    }

    /**
     * Server-controlled interview state (single source of truth).
     */
    public function interviewState(BankOpeningApplication $application): string
    {
        if ($application->interview_completed_at
            || $application->summary_confirmed_at
            || in_array($application->stage, [
                BankOpeningStage::InterviewCompleted,
                BankOpeningStage::DocumentsPending,
                BankOpeningStage::Submitted,
                BankOpeningStage::UnderReview,
                BankOpeningStage::ResubmissionRequired,
                BankOpeningStage::Completed,
            ], true)) {
            return 'completed';
        }

        if (! $application->interview_language) {
            return 'awaiting_language';
        }

        if (! $application->account_type) {
            return 'awaiting_account_selection';
        }

        if (! $application->account_type_confirmed) {
            return 'awaiting_account_confirmation';
        }

        $profile = (string) $application->question_profile;
        $questions = AccountProductCatalog::questionsForProfile($profile);
        $total = min(count($questions), AccountProductCatalog::MAX_PLANNED_QUESTIONS);
        $index = (int) $application->interview_question_index;
        $answers = $application->structured_answers ?? [];

        if ($index >= $total && count($answers) >= $total) {
            return 'reviewing_summary';
        }

        if ((int) $application->interview_follow_ups_used > 0
            && isset($questions[$index])
            && ! isset($answers[$questions[$index]['key']])) {
            // Clarification in flight on current question.
            $lastAi = $application->interviewTurns()
                ->where('speaker', 'ai')
                ->where('question_key', $questions[$index]['key'])
                ->latest('sequence')
                ->first();
            if ($lastAi && str_starts_with((string) $lastAi->question_text, 'Just to clarify')) {
                return 'clarifying_answer';
            }
        }

        if (isset($questions[$index]) && ! isset($answers[$questions[$index]['key']])) {
            return 'awaiting_answer';
        }

        return 'asking_question';
    }

    /** @deprecated Use interviewState() */
    public function phase(BankOpeningApplication $application): string
    {
        return $this->interviewState($application);
    }

    public function selectLanguage(BankOpeningApplication $application, string $language): BankOpeningApplication
    {
        if (! in_array($language, ['bn', 'en'], true)) {
            throw new InvalidArgumentException('Please choose বাংলা or English.');
        }

        if ($application->interview_language && $application->interview_language !== $language) {
            // Allow explicit change only via this endpoint (applicant requested).
        }

        $application->interview_language = $language;
        if (! $application->interview_session_id) {
            $application->interview_session_id = (string) Str::uuid();
        }
        if ($application->stage === BankOpeningStage::InformationSubmitted
            || $application->stage === BankOpeningStage::Invited) {
            $application->stage = BankOpeningStage::InterviewInProgress;
        }
        $application->save();

        $application->recordEvent('interview_language_selected', [
            'language' => $language,
            'session_id' => $application->interview_session_id,
        ]);

        return $application->fresh();
    }

    /**
     * Soft preselect from a voice/text hint — never persists an unconfigured slug.
     *
     * @return list<string>
     */
    public function matchProducts(string $hint): array
    {
        return AccountProductCatalog::matchHint($hint);
    }

    public function selectAccount(BankOpeningApplication $application, string $slug): BankOpeningApplication
    {
        if (! $application->interview_language) {
            throw new InvalidArgumentException('Select a language first.');
        }

        if (! AccountProductCatalog::isValidSlug($slug)) {
            throw new InvalidArgumentException('Invalid account type.');
        }

        $product = AccountProductCatalog::find($slug);

        $application->account_type = $slug;
        $application->question_profile = $product['question_profile'];
        $application->account_type_confirmed = false;
        $application->bank_code = AccountProductCatalog::BANK_CODE;
        $application->catalog_version = AccountProductCatalog::CATALOG_VERSION;
        $application->account_type_name_snapshot = $product['label'];
        $application->interview_question_index = 0;
        $application->interview_follow_ups_used = 0;
        $application->structured_answers = [];
        $application->confirmed_summary = null;

        if ($application->stage === BankOpeningStage::InformationSubmitted
            || $application->stage === BankOpeningStage::Invited) {
            $application->stage = BankOpeningStage::InterviewInProgress;
        }

        $application->save();

        $application->recordEvent('account_type_selected', [
            'account_type' => $slug,
            'question_profile' => $product['question_profile'],
        ]);

        return $application->fresh();
    }

    /**
     * Docs-phase account type selection (manual). Used when Live did not confirm a type.
     */
    public function setAccountTypeForDocuments(BankOpeningApplication $application, string $slug): BankOpeningApplication
    {
        if (! $application->isInterviewFinished()) {
            throw new InvalidArgumentException('Complete the interview before selecting an account type.');
        }

        if ($application->isApplicationSubmitted() && $application->stage !== BankOpeningStage::ResubmissionRequired) {
            throw new InvalidArgumentException('Account type can no longer be changed.');
        }

        if (! AccountProductCatalog::isValidSlug($slug)) {
            throw new InvalidArgumentException('Invalid account type.');
        }

        $product = AccountProductCatalog::find($slug);

        if (! $application->interview_language) {
            $application->interview_language = 'en';
        }

        $application->account_type = $slug;
        $application->question_profile = $product['question_profile'];
        $application->account_type_confirmed = true;
        $application->bank_code = AccountProductCatalog::BANK_CODE;
        $application->catalog_version = AccountProductCatalog::CATALOG_VERSION;
        $application->account_type_name_snapshot = $product['label'];
        $application->documents_required = max(1, count(
            DocumentRequirementCatalog::requiredKeysFor($slug, $application->meta)
        ));

        // Keep officer summary in sync with the docs-page selection (avoid stale RFCD etc.).
        $summary = is_array($application->confirmed_summary) ? $application->confirmed_summary : [];
        $summary['account_type_slug'] = $slug;
        $summary['account_type_name'] = $product['label'];
        $summary['language'] = $application->interview_language ?: ($summary['language'] ?? 'en');
        $summary['source'] = 'documents_account_selection';
        $summary['confirmed_at'] = now()->toIso8601String();
        $application->confirmed_summary = $summary;

        if (in_array($application->stage, [
            BankOpeningStage::InterviewCompleted,
            BankOpeningStage::DocumentsPending,
            BankOpeningStage::ResubmissionRequired,
        ], true)) {
            $application->stage = BankOpeningStage::DocumentsPending;
        }

        $application->save();

        $application->recordEvent('account_type_selected_for_documents', [
            'account_type' => $slug,
            'question_profile' => $product['question_profile'],
        ]);

        return $application->fresh(['documents', 'applicant']);
    }

    public function confirmAccount(BankOpeningApplication $application, bool $confirmed): BankOpeningApplication
    {
        if (! $application->account_type || ! AccountProductCatalog::isValidSlug($application->account_type)) {
            throw new InvalidArgumentException('Select a valid account type first.');
        }

        if (! $confirmed) {
            $application->account_type = null;
            $application->question_profile = null;
            $application->account_type_confirmed = false;
            $application->account_type_name_snapshot = null;
            $application->interview_question_index = 0;
            $application->structured_answers = [];
            $application->confirmed_summary = null;
            $application->save();

            $application->recordEvent('account_type_rejected', []);

            return $application->fresh();
        }

        return DB::transaction(function () use ($application) {
            $application->loadMissing('applicant');
            $application->account_type_confirmed = true;
            $application->stage = BankOpeningStage::InterviewInProgress;
            $application->interview_question_index = 0;
            $application->interview_follow_ups_used = 0;
            $application->bank_code = AccountProductCatalog::BANK_CODE;
            $application->catalog_version = AccountProductCatalog::CATALOG_VERSION;
            $application->account_type_name_snapshot = AccountProductCatalog::labelFor($application->account_type);
            $application->structured_answers = [];
            $application->confirmed_summary = null;
            $application->save();

            $application->recordEvent('account_type_confirmed', [
                'account_type' => $application->account_type,
                'catalog_version' => $application->catalog_version,
            ]);

            $this->appendAiQuestionTurn($application);

            return $application->fresh(['interviewTurns']);
        });
    }

    /**
     * @param  array<string, mixed>|null  $normalized
     */
    public function submitAnswer(
        BankOpeningApplication $application,
        string $answer,
        bool $asFollowUp = false,
        ?string $clientTurnId = null,
        ?string $answerRaw = null,
        ?array $normalized = null,
    ): BankOpeningApplication {
        $answer = trim($answer);
        $raw = trim((string) ($answerRaw ?? $answer));

        if ($answer === '') {
            throw new InvalidArgumentException('Please provide an answer.');
        }

        $state = $this->interviewState($application);
        if (! in_array($state, ['asking_question', 'awaiting_answer', 'clarifying_answer'], true)) {
            throw new InvalidArgumentException('Interview is not accepting answers right now.');
        }

        if ($clientTurnId) {
            $existing = $application->interviewTurns()
                ->where('client_turn_id', $clientTurnId)
                ->first();
            if ($existing) {
                return $application->fresh(['interviewTurns']);
            }
        }

        $profile = (string) $application->question_profile;
        $questions = AccountProductCatalog::questionsForProfile($profile);
        $index = (int) $application->interview_question_index;

        if (! isset($questions[$index])) {
            throw new InvalidArgumentException('No active question.');
        }

        $question = $questions[$index];
        $key = $question['key'];
        $answers = $application->structured_answers ?? [];

        // Already stored — do not re-ask / re-store.
        if (isset($answers[$key])) {
            return $application->fresh(['interviewTurns']);
        }

        if (AnswerCompleteness::isIncompleteFragment($answer)) {
            throw new InvalidArgumentException('Please give a clearer answer to the question.');
        }

        $parsedAmount = null;
        if (AnswerCompleteness::isAmountQuestion($key)) {
            $parsedAmount = AmountNormalizer::parse($answer);
            if ($parsedAmount === null) {
                throw new InvalidArgumentException('Please say a clear amount (for example BDT 5,000).');
            }
            $normalized = array_merge($normalized ?? [], $parsedAmount);
        }

        $maxPlanned = min(count($questions), AccountProductCatalog::MAX_PLANNED_QUESTIONS);

        return DB::transaction(function () use (
            $application,
            $answer,
            $raw,
            $asFollowUp,
            $question,
            $questions,
            $index,
            $maxPlanned,
            $clientTurnId,
            $normalized,
            $key,
            $answers,
            $parsedAmount
        ) {
            if ($asFollowUp) {
                if ((int) $application->interview_follow_ups_used >= AccountProductCatalog::MAX_FOLLOW_UPS) {
                    throw new InvalidArgumentException('Follow-up limit reached.');
                }
                $application->interview_follow_ups_used = (int) $application->interview_follow_ups_used + 1;
            }

            $this->appendApplicantTurn(
                $application,
                $key,
                $question['text'],
                $answer,
                $raw,
                $normalized,
                'accepted',
                $clientTurnId
            );

            $answers[$key] = [
                'text' => $answer,
                'raw' => $raw,
                'normalized' => $normalized,
                'display' => $parsedAmount['display'] ?? $answer,
            ];
            $application->structured_answers = $answers;

            $nextIndex = $index + 1;
            while (
                $nextIndex < $maxPlanned
                && isset($questions[$nextIndex])
                && isset($answers[$questions[$nextIndex]['key']])
            ) {
                $nextIndex++;
            }

            $plannedComplete = $nextIndex >= $maxPlanned
                || $nextIndex >= count($questions)
                || count($answers) >= $maxPlanned;

            if ($plannedComplete) {
                $application->interview_question_index = max($nextIndex, $maxPlanned);
                $application->save();

                $application->recordEvent('questions_complete', [
                    'account_type' => $application->account_type,
                    'answers' => count($answers),
                ]);

                return $application->fresh(['interviewTurns']);
            }

            $application->interview_question_index = $nextIndex;
            $application->save();
            $this->appendAiQuestionTurn($application);

            return $application->fresh(['interviewTurns']);
        });
    }

    public function requestClarification(BankOpeningApplication $application): BankOpeningApplication
    {
        $state = $this->interviewState($application);
        if (! in_array($state, ['asking_question', 'awaiting_answer', 'clarifying_answer'], true)) {
            throw new InvalidArgumentException('No active question to clarify.');
        }

        if ((int) $application->interview_follow_ups_used >= AccountProductCatalog::MAX_FOLLOW_UPS) {
            throw new InvalidArgumentException('Follow-up limit reached.');
        }

        $profile = (string) $application->question_profile;
        $questions = AccountProductCatalog::questionsForProfile($profile);
        $index = (int) $application->interview_question_index;
        if (! isset($questions[$index])) {
            throw new InvalidArgumentException('No active question.');
        }

        return DB::transaction(function () use ($application, $questions, $index) {
            $application->interview_follow_ups_used = (int) $application->interview_follow_ups_used + 1;
            $application->save();

            $q = $questions[$index];
            $text = 'Just to clarify — '.$q['text'];
            $this->appendAiTurn($application, $q['key'], $text, null);

            return $application->fresh(['interviewTurns']);
        });
    }

    public function confirmSummary(BankOpeningApplication $application, bool $confirmed, ?string $correctKey = null): BankOpeningApplication
    {
        $state = $this->interviewState($application);

        if ($state === 'completed') {
            return $application->fresh(['interviewTurns']);
        }

        if ($state !== 'reviewing_summary') {
            throw new InvalidArgumentException('Summary is not ready yet.');
        }

        $profile = (string) $application->question_profile;
        $questions = AccountProductCatalog::questionsForProfile($profile);
        $total = min(count($questions), AccountProductCatalog::MAX_PLANNED_QUESTIONS);
        $answers = $application->structured_answers ?? [];

        if (count($answers) < $total || ! $application->account_type_confirmed) {
            throw new InvalidArgumentException('All required answers must be complete before confirmation.');
        }

        if (! $confirmed) {
            if (! $correctKey || ! isset($answers[$correctKey])) {
                throw new InvalidArgumentException('Specify which answer to correct.');
            }

            unset($answers[$correctKey]);
            $application->structured_answers = $answers;

            $rewind = 0;
            foreach ($questions as $i => $q) {
                if ($q['key'] === $correctKey) {
                    $rewind = $i;
                    break;
                }
            }
            $application->interview_question_index = $rewind;
            $application->interview_follow_ups_used = 0;
            $application->summary_confirmed_at = null;
            $application->interview_completed_at = null;
            $application->save();

            $this->appendAiQuestionTurn($application);
            $application->recordEvent('summary_correction', ['key' => $correctKey]);

            return $application->fresh(['interviewTurns']);
        }

        return DB::transaction(function () use ($application) {
            $application->loadMissing('applicant');
            $label = AccountProductCatalog::labelFor($application->account_type);
            $summary = $this->summaryPayload($application);
            $language = $application->interview_language ?: 'en';
            $applicantName = filled($application->applicant?->name)
                ? trim((string) $application->applicant->name)
                : '';

            $application->summary_confirmed_at = now();
            $application->interview_completed_at = now();
            $application->stage = BankOpeningStage::DocumentsPending;
            $application->bank_code = AccountProductCatalog::BANK_CODE;
            $application->catalog_version = AccountProductCatalog::CATALOG_VERSION;
            $application->account_type_name_snapshot = $label;
            $application->confirmed_summary = [
                'account_type_slug' => $application->account_type,
                'account_type_name' => $label,
                'language' => $language,
                'fields' => $summary['fields'],
                'confirmed_at' => now()->toIso8601String(),
            ];
            $application->save();

            $this->appendAiTurn(
                $application,
                null,
                $this->completionMessage($applicantName, $language),
                null
            );
            $application->recordEvent('interview_completed', [
                'bank_code' => $application->bank_code,
                'catalog_version' => $application->catalog_version,
                'account_type' => $application->account_type,
                'language' => $language,
            ]);

            return $application->fresh(['interviewTurns']);
        });
    }

    /**
     * Persist live transcript without completing the interview or guessing account type.
     */
    public function saveLiveTranscript(BankOpeningApplication $application, string $transcriptText): BankOpeningApplication
    {
        $application->transcript = $transcriptText;
        $application->transcript_text = $transcriptText;
        $application->save();

        $application->recordEvent('live_transcript_saved', [
            'chars' => strlen($transcriptText),
            'account_type' => $application->account_type,
        ]);

        return $application->fresh();
    }

    /**
     * End Session from the loan-style Live panel: save transcript, open the
     * document upload window. Account type is confirmed on the docs page
     * (not inferred from the transcript).
     */
    public function completeLiveSession(BankOpeningApplication $application, string $transcriptText): BankOpeningApplication
    {
        if ($application->interview_completed_at || $application->isApplicationSubmitted()) {
            $this->saveLiveTranscript($application, $transcriptText);

            return $application->fresh(['documents', 'interviewTurns']);
        }

        return DB::transaction(function () use ($application, $transcriptText) {
            $application->loadMissing('applicant');
            $application->transcript = $transcriptText;
            $application->transcript_text = $transcriptText;

            if (! $application->interview_language) {
                $application->interview_language = preg_match('/[\x{0980}-\x{09FF}]/u', $transcriptText) === 1
                    ? 'bn'
                    : 'en';
            }

            // Do not guess account type from transcript — applicant confirms on the docs page
            // unless it was already explicitly confirmed during the interview.
            if ($application->account_type && ! $application->account_type_confirmed) {
                $application->account_type_confirmed = false;
            }

            $application->bank_code = AccountProductCatalog::BANK_CODE;
            $application->catalog_version = AccountProductCatalog::CATALOG_VERSION;
            $application->summary_confirmed_at = now();
            $application->interview_completed_at = now();
            $application->stage = BankOpeningStage::DocumentsPending;
            $application->documents_required = max(1, count(
                \App\Support\BankOpening\DocumentRequirementCatalog::requiredKeysFor(
                    $application->account_type_confirmed ? $application->account_type : null,
                    $application->meta
                )
            ));
            $application->confirmed_summary = [
                'account_type_slug' => $application->account_type,
                'account_type_name' => $application->account_type_name_snapshot
                    ?: AccountProductCatalog::labelFor($application->account_type),
                'language' => $application->interview_language,
                'fields' => $this->summaryPayload($application)['fields'],
                'source' => 'live_session',
                'confirmed_at' => now()->toIso8601String(),
            ];
            $application->save();

            $application->recordEvent('interview_completed', [
                'bank_code' => $application->bank_code,
                'catalog_version' => $application->catalog_version,
                'account_type' => $application->account_type,
                'language' => $application->interview_language,
                'source' => 'live_session',
            ]);

            return $application->fresh(['documents', 'interviewTurns']);
        });
    }

    /**
     * @return array{key: string, text: string, index: int, total: int}|null
     */
    public function currentQuestionPayload(BankOpeningApplication $application): ?array
    {
        $profile = (string) $application->question_profile;
        $questions = AccountProductCatalog::questionsForProfile($profile);
        $index = (int) $application->interview_question_index;
        $total = min(count($questions), AccountProductCatalog::MAX_PLANNED_QUESTIONS);

        if (! isset($questions[$index]) || $index >= $total) {
            return null;
        }

        return [
            'key' => $questions[$index]['key'],
            'text' => $questions[$index]['text'],
            'index' => $index,
            'total' => $total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryPayload(BankOpeningApplication $application): array
    {
        $answers = $application->structured_answers ?? [];
        $fields = [];
        foreach ($answers as $key => $payload) {
            $fields[] = [
                'key' => $key,
                'label' => str_replace('_', ' ', Str::title((string) $key)),
                'value' => is_array($payload) ? ($payload['display'] ?? $payload['text'] ?? '') : (string) $payload,
            ];
        }

        return [
            'account_type' => $application->account_type,
            'account_label' => $application->accountTypeLabel(),
            'language' => $application->interview_language,
            'fields' => $fields,
            'ready' => $this->interviewState($application) === 'reviewing_summary',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function turnsPayload(BankOpeningApplication $application): array
    {
        return $application->interviewTurns()
            ->orderBy('sequence')
            ->get()
            ->map(fn (BankOpeningInterviewTurn $turn) => [
                'id' => $turn->id,
                'speaker' => $turn->speaker === 'ai' ? 'AI' : 'Applicant',
                'question_key' => $turn->question_key,
                'question_text' => $turn->question_text,
                'answer_text' => $turn->answer_text,
                'answer_raw' => $turn->answer_raw,
                'answer_normalized' => $turn->answer_normalized,
                'answer_status' => $turn->answer_status,
                'client_turn_id' => $turn->client_turn_id,
                'sequence' => $turn->sequence,
                'timestamp' => $turn->spoken_at?->toIso8601String(),
            ])
            ->all();
    }

    private function appendAiQuestionTurn(BankOpeningApplication $application): void
    {
        $payload = $this->currentQuestionPayload($application);
        if (! $payload) {
            return;
        }

        // Avoid duplicate AI question for same key while unanswered.
        $answers = $application->structured_answers ?? [];
        if (isset($answers[$payload['key']])) {
            return;
        }

        $last = $application->interviewTurns()
            ->where('speaker', 'ai')
            ->where('question_key', $payload['key'])
            ->latest('sequence')
            ->first();

        if ($last && $last->question_text === $payload['text']) {
            return;
        }

        $this->appendAiTurn($application, $payload['key'], $payload['text'], null);
    }

    private function appendAiTurn(
        BankOpeningApplication $application,
        ?string $questionKey,
        string $questionText,
        ?string $answerText
    ): void {
        $sequence = (int) $application->interviewTurns()->max('sequence') + 1;

        BankOpeningInterviewTurn::create([
            'bank_opening_application_id' => $application->id,
            'speaker' => 'ai',
            'question_key' => $questionKey,
            'question_text' => $questionText,
            'answer_text' => $answerText,
            'sequence' => $sequence,
            'spoken_at' => now(),
        ]);
    }

    private function appendApplicantTurn(
        BankOpeningApplication $application,
        string $questionKey,
        string $questionText,
        string $answer,
        string $raw,
        ?array $normalized,
        string $status,
        ?string $clientTurnId
    ): void {
        $sequence = (int) $application->interviewTurns()->max('sequence') + 1;

        BankOpeningInterviewTurn::create([
            'bank_opening_application_id' => $application->id,
            'speaker' => 'applicant',
            'question_key' => $questionKey,
            'question_text' => $questionText,
            'answer_text' => $answer,
            'answer_raw' => $raw,
            'answer_normalized' => $normalized,
            'answer_status' => $status,
            'client_turn_id' => $clientTurnId,
            'sequence' => $sequence,
            'spoken_at' => now(),
        ]);
    }

    private function firstName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }

        return explode(' ', $name)[0];
    }
}
