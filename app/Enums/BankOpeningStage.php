<?php

namespace App\Enums;

enum BankOpeningStage: string
{
    case Invited = 'invited';
    case InformationSubmitted = 'information_submitted';
    case InterviewInProgress = 'interview_in_progress';
    case InterviewCompleted = 'interview_completed';
    case DocumentsPending = 'documents_pending';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case ResubmissionRequired = 'resubmission_required';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::InformationSubmitted => 'Information Submitted',
            self::InterviewInProgress => 'Interview In Progress',
            self::InterviewCompleted => 'Interview Completed',
            self::DocumentsPending => 'Documents Pending',
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::ResubmissionRequired => 'Resubmission Required',
            self::Completed => 'Completed',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
