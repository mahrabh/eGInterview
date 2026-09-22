<?php

namespace App\Http\Controllers;

use App\Enums\BankOpeningStage;
use App\Models\BankOpeningApplicant;
use App\Models\BankOpeningApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BankOpeningApplicationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', BankOpeningApplication::class);

        $query = BankOpeningApplication::query()
            ->select([
                'bank_opening_applications.id',
                'bank_opening_applications.bank_opening_applicant_id',
                'bank_opening_applications.account_type',
                'bank_opening_applications.account_type_confirmed',
                'bank_opening_applications.account_type_name_snapshot',
                'bank_opening_applications.meta',
                'bank_opening_applications.stage',
                'bank_opening_applications.documents_required',
                'bank_opening_applications.documents_uploaded',
                'bank_opening_applications.public_token_hash',
                'bank_opening_applications.public_token_expiry',
                'bank_opening_applications.interview_completed_at',
                'bank_opening_applications.submitted_at',
                'bank_opening_applications.documents_submitted_at',
                'bank_opening_applications.created_at',
                'bank_opening_applications.updated_at',
            ])
            ->with([
                'applicant:id,name,application_reference,phone_masked,created_by',
                // Lean active docs only — enough for progress labels, no binary metadata.
                'documents' => fn ($q) => $q->select([
                    'id',
                    'bank_opening_application_id',
                    'document_type',
                    'removed_at',
                ])->whereNull('removed_at'),
            ]);

        $users = [];
        $user = $request->user();
        $applicantAlias = null;

        // Join ownership filter avoids correlated whereHas EXISTS subqueries.
        if (! $user->isAdmin()) {
            $query->join(
                'bank_opening_applicants as ownership',
                'ownership.id',
                '=',
                'bank_opening_applications.bank_opening_applicant_id'
            )->where('ownership.created_by', $user->id);
            $applicantAlias = 'ownership';
        } else {
            $users = User::query()
                ->whereIn('role', ['admin', 'analyst', 'both'])
                ->orderBy('name')
                ->get(['id', 'name']);

            if ($request->filled('user_id')) {
                $query->join(
                    'bank_opening_applicants as ownership',
                    'ownership.id',
                    '=',
                    'bank_opening_applications.bank_opening_applicant_id'
                )->where('ownership.created_by', $request->integer('user_id'));
                $applicantAlias = 'ownership';
            }
        }

        if ($request->filled('search')) {
            $searchTerm = $request->string('search')->toString();
            $digits = preg_replace('/\D/', '', $searchTerm) ?: $searchTerm;
            $phoneHash = hash('sha256', $digits);

            if ($applicantAlias === null) {
                $query->join(
                    'bank_opening_applicants as search_applicant',
                    'search_applicant.id',
                    '=',
                    'bank_opening_applications.bank_opening_applicant_id'
                );
                $applicantAlias = 'search_applicant';
            }

            $query->where(function ($inner) use ($applicantAlias, $searchTerm, $phoneHash) {
                $inner->where("{$applicantAlias}.name", 'like', "%{$searchTerm}%")
                    ->orWhere("{$applicantAlias}.application_reference", 'like', "%{$searchTerm}%")
                    ->orWhere("{$applicantAlias}.phone_hash", $phoneHash);
            });
        }

        if ($request->filled('stage')) {
            $query->where('bank_opening_applications.stage', $request->stage);
        }

        // simplePaginate skips COUNT(*) — one fewer DB round-trip on every panel load.
        $applications = $query
            ->orderByDesc('bank_opening_applications.created_at')
            ->simplePaginate(10)
            ->appends($request->query());

        $stages = BankOpeningStage::cases();

        return view('bank-openings.index', compact('applications', 'users', 'stages'));
    }

    public function statusSnapshot(Request $request)
    {
        Gate::authorize('viewAny', BankOpeningApplication::class);

        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $ids
        ))));

        // Hard cap protects against oversized poll payloads.
        $ids = array_slice($ids, 0, 50);

        if ($ids === []) {
            return response()->json(['applications' => []]);
        }

        $query = BankOpeningApplication::query()
            ->whereIn('bank_opening_applications.id', $ids)
            ->select([
                'bank_opening_applications.id',
                'bank_opening_applications.account_type',
                'bank_opening_applications.account_type_confirmed',
                'bank_opening_applications.meta',
                'bank_opening_applications.stage',
                'bank_opening_applications.documents_required',
                'bank_opening_applications.interview_completed_at',
                'bank_opening_applications.submitted_at',
                'bank_opening_applications.documents_submitted_at',
                'bank_opening_applications.updated_at',
            ])
            ->with([
                'documents' => fn ($q) => $q->select([
                    'id',
                    'bank_opening_application_id',
                    'document_type',
                    'removed_at',
                ])->whereNull('removed_at'),
            ]);

        if (! $request->user()->isAdmin()) {
            $query->join(
                'bank_opening_applicants as ownership',
                'ownership.id',
                '=',
                'bank_opening_applications.bank_opening_applicant_id'
            )->where('ownership.created_by', $request->user()->id);
        }

        $applications = $query->get();

        $snapshot = [];
        foreach ($applications as $application) {
            $snapshot[$application->id] = $this->applicationStatusPayload($application);
        }

        return response()->json(['applications' => $snapshot]);
    }

    /**
     * @return array{stage: string, account_type: ?string, documents_label: string, interview_completed_at: ?string, submitted_at: ?string, documents_submitted_at: ?string, updated_at: ?string}
     */
    private function applicationStatusPayload(BankOpeningApplication $application): array
    {
        return [
            'stage' => $application->stage instanceof BankOpeningStage
                ? $application->stage->value
                : (string) $application->stage,
            'account_type' => $application->account_type,
            'documents_label' => $application->documentProgressLabel(),
            'interview_completed_at' => $application->interview_completed_at?->toIso8601String(),
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'documents_submitted_at' => $application->documents_submitted_at?->toIso8601String(),
            'updated_at' => $application->updated_at?->toIso8601String(),
        ];
    }

    public function store(Request $request)
    {
        Gate::authorize('create', BankOpeningApplication::class);

        $token = null;
        $applicationId = null;

        DB::transaction(function () use ($request, &$token, &$applicationId) {
            $applicant = BankOpeningApplicant::create([
                'application_reference' => $this->nextReference(),
                'name' => null,
                'phone' => null,
                'created_by' => $request->user()->id,
            ]);

            // Pre-assign UUID so token hash can be written in the same INSERT (no second UPDATE).
            $applicationId = (string) Str::uuid();
            $expiry = now()->addHours(BankOpeningApplication::PUBLIC_LINK_HOURS_BEFORE_INTERVIEW);
            $token = hash_hmac('sha256', $applicationId.$expiry->timestamp, config('app.key'));

            $application = new BankOpeningApplication([
                'bank_opening_applicant_id' => $applicant->id,
                'stage' => BankOpeningStage::Invited,
                'documents_required' => 3,
                'documents_uploaded' => 0,
                'public_token_hash' => hash('sha256', $token),
                'public_token_expiry' => $expiry,
            ]);
            $application->id = $applicationId;
            $application->save();

            $application->recordEvent('invite_created', [
                'expiry' => $expiry->toIso8601String(),
                'hours' => BankOpeningApplication::PUBLIC_LINK_HOURS_BEFORE_INTERVIEW,
            ], $request->user()->id);
        });

        return redirect()
            ->route('bank-openings.index')
            ->with('success', 'Blank application link created. Share it with the applicant — it expires in '.BankOpeningApplication::PUBLIC_LINK_HOURS_BEFORE_INTERVIEW.' hours.')
            ->with('generated_link_url', url('/bank-opening/'.$token))
            ->with('generated_link_id', $applicationId);
    }

    public function show(string $application)
    {
        $record = BankOpeningApplication::query()
            ->with([
                'applicant:id,name,application_reference,phone_masked,created_by',
                'applicant.creator:id,name',
                'events' => fn ($q) => $q->latest()->limit(20)->select([
                    'id',
                    'bank_opening_application_id',
                    'event_type',
                    'created_at',
                ]),
                // Active docs only — show page never lists removed files.
                'documents' => fn ($q) => $q->select([
                    'id',
                    'bank_opening_application_id',
                    'document_type',
                    'original_name',
                    'disk_path',
                    'mime_type',
                    'size_bytes',
                    'removed_at',
                    'created_at',
                ])->whereNull('removed_at')->latest(),
            ])
            ->findOrFail($application);

        Gate::authorize('view', $record);

        return view('bank-openings.show', [
            'application' => $record,
            'stages' => BankOpeningStage::cases(),
        ]);
    }

    public function downloadDocument(string $application, string $document)
    {
        $record = BankOpeningApplication::with('applicant')->findOrFail($application);
        Gate::authorize('view', $record);

        $doc = $record->documents()->whereKey($document)->firstOrFail();

        return \Illuminate\Support\Facades\Storage::disk('local')->download(
            $doc->disk_path,
            $doc->original_name
        );
    }

    public function updateNotes(Request $request, string $application)
    {
        $record = BankOpeningApplication::with('applicant')->findOrFail($application);
        Gate::authorize('update', $record);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $record->notes = $validated['notes'] ?? null;
        $record->save();

        $record->recordEvent('officer_notes_updated', [
            'has_notes' => filled($record->notes),
        ], $request->user()->id);

        return back()->with('success', 'Officer notes saved.');
    }

    public function requestResubmission(Request $request, string $application)
    {
        $record = BankOpeningApplication::with('applicant')->findOrFail($application);
        Gate::authorize('update', $record);

        $metaForGroups = is_array($record->meta) ? $record->meta : [];
        unset($metaForGroups['resubmission']);
        $groupKeys = array_column(
            \App\Support\BankOpening\DocumentRequirementCatalog::groupsForAccountType(
                $record->account_type,
                $metaForGroups
            ),
            'key'
        );

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => ['string', Rule::in($groupKeys !== [] ? $groupKeys : ['applicant_photo_id'])],
        ]);

        $meta = $record->meta ?? [];
        $meta['resubmission'] = [
            'reason' => $validated['reason'],
            'groups' => array_values($validated['groups']),
            'requested_at' => now()->toIso8601String(),
            'requested_by' => $request->user()->id,
        ];

        $record->meta = $meta;
        $record->stage = BankOpeningStage::ResubmissionRequired;
        $record->submitted_at = null;
        $record->documents_submitted_at = null;
        $record->save();

        // Fresh reconstructable token for the docs/resubmission window (24h).
        $hours = BankOpeningApplication::PUBLIC_LINK_HOURS_DOCS_OR_RESUBMISSION;
        $plainToken = $record->issuePublicToken($hours);
        $publicUrl = url('/bank-opening/'.$plainToken);

        $record->recordEvent('resubmission_requested', [
            'reason' => $validated['reason'],
            'groups' => $validated['groups'],
            'public_link_expiry' => $record->public_token_expiry?->toIso8601String(),
            'public_link_hours' => $hours,
        ], $request->user()->id);

        return back()
            ->with('success', "Resubmission requested. Share the public link with the applicant (valid for {$hours} hours).")
            ->with('generated_link_url', $publicUrl)
            ->with('generated_link_id', $record->id);
    }

    public function generateLink(string $application)
    {
        $record = BankOpeningApplication::query()
            ->select([
                'id',
                'bank_opening_applicant_id',
                'stage',
                'interview_completed_at',
                'submitted_at',
                'public_token_hash',
                'public_token_expiry',
            ])
            ->with(['applicant:id,created_by'])
            ->findOrFail($application);

        Gate::authorize('update', $record);

        $previousExpiry = $record->public_token_expiry?->toIso8601String();
        $hours = $record->publicLinkValidityHours();
        $token = $record->issuePublicToken($hours);

        $record->recordEvent('link_regenerated', [
            'previous_expiry' => $previousExpiry,
            'expiry' => $record->public_token_expiry?->toIso8601String(),
            'hours' => $hours,
            'stage' => $record->stage instanceof BankOpeningStage
                ? $record->stage->value
                : (string) $record->stage,
        ], auth()->id());

        return back()
            ->with('success', "Application link regenerated (valid for {$hours} hours). Existing application data was kept.")
            ->with('generated_link_url', url('/bank-opening/'.$token))
            ->with('generated_link_id', $record->id);
    }

    public function destroy(string $application)
    {
        $record = BankOpeningApplication::query()
            ->select(['id', 'bank_opening_applicant_id'])
            ->with(['applicant:id,created_by'])
            ->findOrFail($application);

        Gate::authorize('delete', $record);

        $record->applicant->delete();

        return redirect()
            ->route('bank-openings.index')
            ->with('success', 'Bank account opening application deleted.');
    }

    /**
     * High-entropy reference — skip an EXISTS round-trip on every invite.
     */
    private function nextReference(): string
    {
        return 'BA-'.Str::upper(bin2hex(random_bytes(5)));
    }
}
