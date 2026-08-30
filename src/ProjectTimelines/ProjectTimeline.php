<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\ProjectTimelines;

use DateTimeImmutable;
use InvalidArgumentException;
use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\ConfidenceLevel;
use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;

final class ProjectTimelineQuery
{
    public function __construct(
        public readonly string $question,
        public readonly DateTimeImmutable $periodStart,
        public readonly DateTimeImmutable $periodEnd,
        public readonly ?string $subject = null,
        public readonly ?string $corpusScope = null,
    ) {
        if ($this->periodStart > $this->periodEnd) {
            throw new InvalidArgumentException('Timeline period start must be earlier than or equal to end.');
        }
    }
}

enum TimelineLayer: string
{
    case Observation = 'observation';
    case Interpretation = 'interpretation';
}

enum TimelineTemporalSemantics: string
{
    case ExactDate = 'exact_date';
    case Undated = 'undated';
}

final class TimelineEvent
{
    /**
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $statement,
        public readonly TimelineLayer $layer,
        public readonly string $timeLabel,
        public readonly TimelineTemporalSemantics $temporalSemantics,
        public readonly array $funesRefs,
    ) {
    }
}

final class TimelineGroup
{
    /**
     * @param array<int, TimelineEvent> $events
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $bucketLabel,
        public readonly TimelineTemporalSemantics $temporalSemantics,
        public readonly array $events,
        public readonly array $funesRefs,
    ) {
    }
}

final class ProjectTimeline
{
    /**
     * @param array<int, TimelineGroup> $groups
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

final class ProjectTimelineResult
{
    private function __construct(
        public readonly bool $built,
        public readonly ?ProjectTimeline $timeline,
        public readonly ?RefusalReason $refusalReason,
        public readonly CompletenessAssessment $completeness,
        public readonly UncertaintyAssessment $uncertainty,
    ) {
    }

    public static function built(ProjectTimeline $timeline): self
    {
        return new self(
            built: true,
            timeline: $timeline,
            refusalReason: null,
            completeness: $timeline->completeness,
            uncertainty: $timeline->uncertainty,
        );
    }

    public static function refused(
        RefusalReason $reason,
        CompletenessAssessment $completeness,
        UncertaintyAssessment $uncertainty,
    ): self {
        return new self(
            built: false,
            timeline: null,
            refusalReason: $reason,
            completeness: $completeness,
            uncertainty: $uncertainty,
        );
    }
}
