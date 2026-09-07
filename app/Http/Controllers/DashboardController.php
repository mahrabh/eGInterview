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

        $showRecruitment = $user->canAccessRecruitment();
        $showLoans = $user->canAccessLoans();

        $totalCandidates = 0;
        $completedInterviews = 0;
        $recruitmentStatusCounts = collect();
        $activeLinks = 0;
        $expiredLinks = 0;
        $pendingApproval = 0;
        $draftCandidates = 0;
        $recentInterviews = collect();

        $totalLoanApplicants = 0;
        $loanStatusCounts = collect();
        $loanOutcomeCounts = collect();
        $loanNeedsReview = 0;
        $loanProcessing = 0;
        $recentLoans = collect();
        $needsReviewLoans = collect();

        if ($showRecruitment) {
            $interviewQuery = Interview::query();
            if (!$user->isAdmin()) {
                $interviewQuery->where('user_id', $user->id);
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

            $recentInterviews = (clone $interviewQuery)
                ->with('user')
                ->latest('updated_at')
                ->limit(5)
                ->get();
        }

        if ($showLoans) {
            $loanQuery = LoanApplication::query()->with(['applicant']);
            if (!$user->isAdmin()) {
                $loanQuery->whereHas('applicant', function ($q) use ($user) {
                    $q->where('created_by', $user->id);
                });
            }

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

            $recentLoans = (clone $loanQuery)
                ->with(['applicant'])
                ->latest('updated_at')
                ->limit(5)
                ->get();

            $needsReviewLoans = (clone $loanQuery)
                ->with(['applicant'])
                ->where(function ($q) {
                    $q->where('status', 'needs_review')
                        ->orWhere('outcome', 'Needs Review');
                })
                ->latest('updated_at')
                ->limit(5)
                ->get();
        }

        $needsAttention = $pendingApproval + $expiredLinks + $loanNeedsReview + $loanProcessing;

        return view('dashboard', [
            'dashboardMode' => match (true) {
                $user->isAdmin() => 'admin',
                $user->isBoth() => 'both',
                $user->isAnalyst() => 'analyst',
                default => 'recruiter',
            },
            'showRecruitment' => $showRecruitment,
            'showLoans' => $showLoans,
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
            'needsReviewLoans' => $needsReviewLoans,
        ]);
    }
}
