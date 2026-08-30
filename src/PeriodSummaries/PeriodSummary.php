<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\PeriodSummaries;

use DateTimeImmutable;
use InvalidArgumentException;
use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\ConfidenceLevel;
use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;

final class PeriodSummaryQuery
{
    public function __construct(
        public readonly string $question,
        public readonly DateTimeImmutable $periodStart,
        public readonly DateTimeImmutable $periodEnd,
        public readonly ?string $subject = null,
        public readonly ?string $corpusScope = null,
    ) {
        if ($this->periodStart > $this->periodEnd) {
            throw new InvalidArgumentException('Period start must be earlier than or equal to period end.');
        }
    }
}

final class PeriodObservation
{
    /**
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $statement,
        public readonly array $funesRefs,
    ) {
    }
}

final class PeriodInterpretation
{
    /**
     * @param array<int, non-empty-string> $supportingFunesRefs
     */
    public function __construct(
        public readonly string $statement,
        public readonly string $type,
        public readonly array $supportingFunesRefs = [],
    ) {
    }
}

final class ChronologicalGroup
{
    /**
     * @param array<int, PeriodObservation> $observations
     * @param array<int, PeriodInterpretation> $interpretations
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $bucketLabel,
        public readonly array $observations,
        public readonly array $interpretations,
        public readonly array $funesRefs,
    ) {
    }
}

final class PeriodSummary
{
    /**
     * @param array<int, ChronologicalGroup> $groups
     */
    public function __construct(
        public readonly DateTimeImmutable $periodStart,
        public readonly DateTimeImmutable $periodEnd,
        public readonly array $groups,
        public readonly CompletenessAssessment $completeness,
        public readonly UncertaintyAssessment $uncertainty,
        public readonly ConfidenceLevel $confidence,
    ) {
    }
}

final class PeriodSummaryResult
{
    private function __construct(
        public readonly bool $summarized,
        public readonly ?PeriodSummary $summary,
        public readonly ?RefusalReason $refusalReason,
        public readonly CompletenessAssessment $completeness,
        public readonly UncertaintyAssessment $uncertainty,
    ) {
    }

    public static function summarized(PeriodSummary $summary): self
    {
        return new self(
            summarized: true,
            summary: $summary,
            refusalReason: null,
            completeness: $summary->completeness,
            uncertainty: $summary->uncertainty,
        );
    }

    public static function refused(
        RefusalReason $reason,
        CompletenessAssessment $completeness,
        UncertaintyAssessment $uncertainty,
    ): self {
        return new self(
            summarized: false,
            summary: null,
            refusalReason: $reason,
            completeness: $completeness,
            uncertainty: $uncertainty,
        );
    }
}
