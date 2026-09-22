<?php

namespace App\Http\Controllers;

use App\Enums\BankOpeningStage;
use App\Models\BankOpeningApplication;
use App\Models\BankOpeningDocument;
use App\Services\BankOpeningInterviewService;
use App\Support\BankOpening\DocumentRequirementCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class BankOpeningInterviewController extends Controller
{
    public function __construct(private BankOpeningInterviewService $interview)
    {
    }

    public function bootstrap(string $token): JsonResponse
    {
        return response()->json($this->interview->bootstrap($this->resolveReady($token), $token));
    }

    public function matchProducts(Request $request, string $token): JsonResponse
    {
        $this->resolveReady($token);
        $hint = $request->string('hint')->toString();

        return response()->json([
            'matches' => $this->interview->matchProducts($hint),
        ]);
    }

    public function selectAccount(Request $request, string $token): JsonResponse
    {
        $application = $this->resolveReady($token);
        $validated = $request->validate([
            'account_type' => ['required', 'string', 'max:64'],
        ]);

        try {
            $application = $this->interview->selectAccount($application, $validated['account_type']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->interview->bootstrap($application, $token));
    }

    public function selectLanguage(Request $request, string $token): JsonResponse
    {
        $application = $this->resolveReady($token);
        $validated = $request->validate([
            'language' => ['required', Rule::in(['bn', 'en'])],
        ]);

        try {
            $application = $this->interview->selectLanguage($application, $validated['language']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->interview->bootstrap($application, $token));
    }

    public function confirmAccount(Request $request, string $token): JsonResponse
    {
        $application = $this->resolveReady($token);
        $validated = $request->validate([
            'confirmed' => ['required', 'boolean'],
        ]);

        try {
            $application = $this->interview->confirmAccount($application, (bool) $validated['confirmed']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->interview->bootstrap($application, $token));
    }

    public function submitAnswer(Request $request, string $token): JsonResponse
    {
        $application = $this->resolveReady($token);
        $validated = $request->validate([
            'answer' => ['required', 'string', 'max:5000'],
            'answer_raw' => ['nullable', 'string', 'max:5000'],
            'client_turn_id' => ['nullable', 'string', 'max:64'],
            'as_follow_up' => ['nullable', 'boolean'],
        ]);

        try {
            $application = $this->interview->submitAnswer(
                $application,
                $validated['answer'],
                (bool) ($validated['as_follow_up'] ?? false),
                $validated['client_turn_id'] ?? null,
                $validated['answer_raw'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->interview->bootstrap($application, $token));
    }

    public function clarify(Request $request, string $token): JsonResponse
    {
        $application = $this->resolveReady($token);

        try {
            $application = $this->interview->requestClarification($application);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->interview->bootstrap($application, $token));
    }

    public function confirmSummary(Request $request, string $token): JsonResponse
    {
        $application = $this->resolveReady($token);
        $validated = $request->validate([
            'confirmed' => ['required', 'boolean'],
            'correct_key' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $application = $this->interview->confirmSummary(
                $application,
                (bool) $validated['confirmed'],
                $validated['correct_key'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Only mint a token when none is active. Hours follow stage rule (24h post-interview).
        // Do not rotate an existing token here — the applicant's open URL would break.
        if ($application->interview_completed_at && ! $application->hasActivePublicLink()) {
            $application->issuePublicToken(BankOpeningApplication::PUBLIC_LINK_HOURS_DOCS_OR_RESUBMISSION);
        }

        return response()->json($this->interview->bootstrap($application, $token));
    }

    public function saveTranscript(Request $request, string $token): JsonResponse
    {
        $application = BankOpeningApplication::findByPublicToken($token)
            ?? BankOpeningApplication::query()
                ->where('public_token_hash', hash('sha256', $token))
                ->first();

        if (! $application) {
            abort(404);
        }

        $transcriptText = trim((string) $request->input('transcript_text', ''));
        if ($transcriptText === '') {
            return response()->json(['success' => false, 'error' => 'Empty transcript'], 400);
        }

        try {
            $this->interview->saveLiveTranscript($application, $transcriptText);
        } catch (\Throwable $e) {
            Log::error('Bank opening transcript save failed.', [
                'application_id' => $application->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => 'Server Error'], 500);
        }

        return response()->json([
            'success' => true,
            'interview_state' => $this->interview->interviewState($application->fresh()),
        ]);
    }

    public function completeLive(Request $request, string $token): JsonResponse
    {
        $application = BankOpeningApplication::findByPublicToken($token)
            ?? BankOpeningApplication::query()
                ->where('public_token_hash', hash('sha256', $token))
                ->first();

        if (! $application) {
            abort(404);
        }

        $transcriptText = trim((string) $request->input('transcript_text', ''));
        if ($transcriptText === '') {
            return response()->json(['success' => false, 'error' => 'Empty transcript'], 400);
        }

        try {
            $application = $this->interview->completeLiveSession($application, $transcriptText);
        } catch (\Throwable $e) {
            Log::error('Bank opening live completion failed.', [
                'application_id' => $application->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => 'Server Error'], 500);
        }

        // Only mint a token when none is active. Hours follow stage rule (24h post-interview).
        // Do not rotate an existing token here — the applicant's open URL would break.
        if (! $application->hasActivePublicLink()) {
            $application->issuePublicToken(BankOpeningApplication::PUBLIC_LINK_HOURS_DOCS_OR_RESUBMISSION);
        }

        return response()->json($this->interview->bootstrap($application->fresh(), $token));
    }

    public function setDocumentsAccountType(Request $request, string $token): JsonResponse
    {
        $application = $this->resolveForDocuments($token);

        if (! $application->canEditDocuments()) {
            return response()->json(['message' => 'Account type can no longer be changed.'], 422);
        }

        $validated = $request->validate([
            'account_type' => ['required', 'string', 'max:64'],
        ]);

        try {
            $application = $this->interview->setAccountTypeForDocuments(
                $application,
                $validated['account_type']
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->interview->bootstrap($application, $token));
    }

    public function listDocuments(string $token): JsonResponse
    {
        $application = $this->resolveForDocuments($token);

        return response()->json([
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
            'account_type' => $application->account_type,
            'account_label' => $application->accountTypeLabel(),
            'account_confirmed' => (bool) $application->account_type_confirmed,
            'needs_account_type_selection' => ! $application->account_type_confirmed
                || ! filled($application->account_type),
            'account_type_options' => collect(\App\Support\BankOpening\AccountProductCatalog::enabledProducts())
                ->map(fn (array $p) => [
                    'slug' => $p['slug'],
                    'label' => $p['label'],
                    'group_label' => $p['group_label'],
                    'short_description' => $p['short_description'],
                ])
                ->values()
                ->all(),
            'resubmission' => data_get($application->meta, 'resubmission'),
            'documents' => $application->documents()->active()->latest()->get()->map(fn (BankOpeningDocument $doc) => [
                'id' => $doc->id,
                'document_type' => $doc->document_type,
                'label' => $doc->label(),
                'original_name' => $doc->original_name,
                'mime_type' => $doc->mime_type,
                'size_bytes' => $doc->size_bytes,
                'is_image' => $doc->isImage(),
                'created_at' => $doc->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function uploadDocument(Request $request, string $token): JsonResponse
    {
        $application = $this->resolveForDocuments($token);

        if (! $application->isInterviewFinished()) {
            return response()->json(['message' => 'Complete the interview before uploading documents.'], 422);
        }

        if (! $application->canEditDocuments()) {
            return response()->json(['message' => 'Application already submitted. Documents can no longer be changed.'], 422);
        }

        if (! $application->account_type_confirmed || ! filled($application->account_type)) {
            return response()->json(['message' => 'Please select an account type before uploading documents.'], 422);
        }

        $allowedKeys = array_column($application->documentRequirementGroups(), 'key');
        if ($allowedKeys === []) {
            $allowedKeys = DocumentRequirementCatalog::allKnownKeys();
        }

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:64', Rule::in($allowedKeys)],
            'file' => [
                'required',
                'file',
                'max:'.DocumentRequirementCatalog::MAX_FILE_KB,
                'mimes:'.implode(',', DocumentRequirementCatalog::ALLOWED_MIMES),
            ],
        ]);

        $groupKey = $validated['document_type'];
        $maxFiles = DocumentRequirementCatalog::maxFilesFor(
            $groupKey,
            $application->account_type,
            $application->meta
        );
        $activeCount = $application->documents()
            ->active()
            ->where('document_type', $groupKey)
            ->count();

        if ($activeCount >= $maxFiles) {
            return response()->json([
                'message' => "This document group already has the maximum of {$maxFiles} file(s). Remove one before uploading another.",
            ], 422);
        }

        $file = $request->file('file');
        // Safe generated path; original name kept only as metadata.
        $path = $file->store('bank-opening/'.$application->id, 'local');

        $document = $application->documents()->create([
            'document_type' => $groupKey,
            'original_name' => $file->getClientOriginalName(),
            'disk_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
        ]);

        $application->documents_uploaded = $application->documents()->active()->count();
        $application->documents_required = $application->requiredDocumentsTotal();
        // Keep ResubmissionRequired until the applicant submits again.
        if (in_array($application->stage, [
            BankOpeningStage::InterviewCompleted,
            BankOpeningStage::DocumentsPending,
        ], true)) {
            $application->stage = BankOpeningStage::DocumentsPending;
        }
        $application->save();

        $application->recordEvent('document_uploaded', [
            'document_type' => $document->document_type,
            'document_id' => $document->id,
            'during_resubmission' => $application->stage === BankOpeningStage::ResubmissionRequired,
        ]);

        return response()->json([
            'success' => true,
            'document' => [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'label' => $document->label(),
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'size_bytes' => $document->size_bytes,
                'is_image' => $document->isImage(),
                'created_at' => $document->created_at?->toIso8601String(),
            ],
            'documents_required_count' => $application->requiredDocumentsTotal(),
            'documents_uploaded_count' => $application->fresh()->requiredDocumentsUploadedCount(),
            'documents_missing' => $application->fresh()->missingRequiredDocumentKeys(),
        ]);
    }

    public function removeDocument(string $token, string $document): JsonResponse
    {
        $application = $this->resolveForDocuments($token);

        if (! $application->canEditDocuments()) {
            return response()->json(['message' => 'Documents can no longer be changed.'], 422);
        }

        $doc = $application->documents()->active()->whereKey($document)->firstOrFail();
        $doc->markRemoved();

        $application->documents_uploaded = $application->documents()->active()->count();
        $application->save();

        $application->recordEvent('document_removed', [
            'document_type' => $doc->document_type,
            'document_id' => $doc->id,
        ]);

        return response()->json([
            'success' => true,
            'documents_required_count' => $application->requiredDocumentsTotal(),
            'documents_uploaded_count' => $application->fresh()->requiredDocumentsUploadedCount(),
            'documents_missing' => $application->fresh()->missingRequiredDocumentKeys(),
        ]);
    }

    public function submitApplication(string $token): JsonResponse
    {
        $application = $this->resolveForDocuments($token);

        if (! $application->isInterviewFinished()) {
            return response()->json(['message' => 'Please complete the interview first.'], 422);
        }

        if ($application->isApplicationSubmitted() && $application->stage !== BankOpeningStage::ResubmissionRequired) {
            return response()->json([
                'success' => true,
                'application_submitted' => true,
                'message' => 'Application already submitted.',
            ]);
        }

        if (! $application->account_type_confirmed || ! filled($application->account_type)) {
            return response()->json([
                'message' => 'Please select an account type before submitting.',
            ], 422);
        }

        $missing = $application->missingRequiredDocumentKeys();
        if ($missing !== []) {
            return response()->json([
                'message' => 'Please upload all required documents before submitting.',
                'missing' => $missing,
            ], 422);
        }

        $wasResubmission = $application->stage === BankOpeningStage::ResubmissionRequired;
        $meta = $application->meta ?? [];
        $activeResubmission = is_array($meta['resubmission'] ?? null) ? $meta['resubmission'] : null;

        if ($wasResubmission && $activeResubmission) {
            $fulfilled = array_merge($activeResubmission, [
                'completed_at' => now()->toIso8601String(),
                'status' => 'fulfilled',
            ]);
            $meta['last_resubmission'] = $fulfilled;
            $history = is_array($meta['resubmission_history'] ?? null) ? $meta['resubmission_history'] : [];
            $history[] = $fulfilled;
            $meta['resubmission_history'] = array_slice($history, -10);
        }

        unset($meta['resubmission']);
        $application->meta = $meta;
        $application->stage = BankOpeningStage::Submitted;
        $application->submitted_at = now();
        $application->documents_submitted_at = now();
        $application->documents_uploaded = $application->documents()->active()->count();
        $application->documents_required = $application->requiredDocumentsTotal();
        $application->save();

        $application->recordEvent($wasResubmission ? 'resubmission_fulfilled' : 'application_submitted', [
            'documents' => $application->documents_uploaded,
            'account_type' => $application->account_type,
            'groups' => $activeResubmission['groups'] ?? null,
            'was_resubmission' => $wasResubmission,
        ]);

        return response()->json([
            'success' => true,
            'application_submitted' => true,
            'stage' => $application->stage->value,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'documents_submitted_at' => $application->documents_submitted_at?->toIso8601String(),
            'resubmission_fulfilled' => $wasResubmission,
            'message' => $wasResubmission
                ? 'Thank you. Your resubmitted documents have been received for manual review.'
                : 'Thank you. Your information and documents have been submitted for manual review.',
        ]);
    }

    private function resolveReady(string $token): BankOpeningApplication
    {
        $application = BankOpeningApplication::findByPublicToken($token);

        if (! $application) {
            abort(404, 'This application link is invalid or has expired.');
        }

        $application->load('applicant');

        if (! filled($application->applicant->name)
            || (! filled($application->applicant->phone_masked) && ! filled($application->applicant->phone))) {
            abort(409, 'Please submit your name and phone first.');
        }

        return $application;
    }

    private function resolveForDocuments(string $token): BankOpeningApplication
    {
        $application = BankOpeningApplication::query()
            ->where('public_token_hash', hash('sha256', $token))
            ->first();

        if (! $application) {
            abort(404, 'This application link is invalid or has expired.');
        }

        $linkActive = $application->public_token_expiry
            && $application->public_token_expiry->isFuture();

        // Interview can finish near expiry; allow document upload/submit for a short grace window.
        $withinDocsGrace = $application->isInterviewFinished()
            && $application->canEditDocuments()
            && $application->interview_completed_at
            && $application->interview_completed_at->greaterThan(now()->subHours(24));

        // Resubmission keeps the public link usable even after prior submit.
        $resubmissionOpen = $application->stage === BankOpeningStage::ResubmissionRequired
            && $application->hasActivePublicLink();

        if (! $linkActive && ! $withinDocsGrace && ! $resubmissionOpen) {
            abort(404, 'This application link is invalid or has expired.');
        }

        return $application->load('documents');
    }
}
