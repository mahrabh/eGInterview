<?php

namespace App\Http\Controllers;

use App\Models\Interview;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $interviewQuery = Interview::query();
        $loanQuery = LoanApplication::query()->with(['applicant']);

        if (!$user->isAdmin()) {
            $interviewQuery->where('user_id', $user->id);
            $loanQuery->whereHas('applicant', function ($q) use ($user) {
                $q->where('created_by', $user->id);
            });
        }

        $totalCandidates = (clone $interviewQuery)->count();
        $completedInterviews = (clone $interviewQuery)->where('status', 'completed')->count();

        $recruitmentStatusCounts = (clone $interviewQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $activeLinks = (clone $interviewQuery)
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereNull('link_expires_at')->orWhere('link_expires_at', '>=', now());
            })
            ->count();

        $expiredLinks = (clone $interviewQuery)
            ->where('status', 'approved')
            ->whereNotNull('link_expires_at')
            ->where('link_expires_at', '<', now())
            ->count();

        $pendingApproval = (int) ($recruitmentStatusCounts['pending'] ?? 0);
        $draftCandidates = (int) ($recruitmentStatusCounts['draft'] ?? 0);

        $totalLoanApplicants = (clone $loanQuery)->count();

        $loanStatusCounts = (clone $loanQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $loanOutcomeCounts = (clone $loanQuery)
            ->whereNotNull('outcome')
            ->select('outcome', DB::raw('count(*) as total'))
            ->groupBy('outcome')
            ->pluck('total', 'outcome');

        $loanNeedsReview = (clone $loanQuery)
            ->where(function ($q) {
                $q->where('status', 'needs_review')
                    ->orWhere('outcome', 'Needs Review');
            })
            ->count();

        $loanProcessing = (int) ($loanStatusCounts['processing'] ?? 0);

        $needsAttention = $pendingApproval + $expiredLinks + $loanNeedsReview + $loanProcessing;

        $recentInterviews = (clone $interviewQuery)
            ->with('user')
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $recentLoans = (clone $loanQuery)
            ->with(['applicant'])
            ->latest('updated_at')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'totalCandidates' => $totalCandidates,
            'totalLoanApplicants' => $totalLoanApplicants,
            'completedInterviews' => $completedInterviews,
            'needsAttention' => $needsAttention,
            'recruitmentStatusCounts' => $recruitmentStatusCounts,
            'loanStatusCounts' => $loanStatusCounts,
            'loanOutcomeCounts' => $loanOutcomeCounts,
            'activeLinks' => $activeLinks,
            'expiredLinks' => $expiredLinks,
            'pendingApproval' => $pendingApproval,
            'draftCandidates' => $draftCandidates,
            'loanNeedsReview' => $loanNeedsReview,
            'loanProcessing' => $loanProcessing,
            'recentInterviews' => $recentInterviews,
            'recentLoans' => $recentLoans,
        ]);
    }
}
