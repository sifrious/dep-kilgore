<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\CurrentStateExplanation;

use DateTimeImmutable;
use InvalidArgumentException;
use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\ConfidenceLevel;
use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;

final class CurrentProjectStateQuery
{
    public function __construct(
        public readonly string $projectFunesRef,
        public readonly string $question,
        public readonly DateTimeImmutable $baselineAt,
        public readonly DateTimeImmutable $currentAt,
        public readonly DateTimeImmutable $timelineStart,
        public readonly DateTimeImmutable $timelineEnd,
        public readonly ?string $corpusScope = null,
    ) {
        if ($this->projectFunesRef === '') {
            throw new InvalidArgumentException('Project Funes ref is required.');
        }

        if ($this->baselineAt > $this->currentAt) {
            throw new InvalidArgumentException('Baseline must be earlier than or equal to current time.');
        }

        if ($this->timelineStart > $this->timelineEnd) {
            throw new InvalidArgumentException('Timeline start must be earlier than or equal to timeline end.');
        }
    }
}

final class ExplanationObservation
{
    /**
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $statement,
        public readonly string $source,
        public readonly array $funesRefs,
    ) {
    }
}

final class ExplanationInference
{
    /**
     * @param array<int, non-empty-string> $supportingFunesRefs
     */
    public function __construct(
        public readonly string $statement,
        public readonly string $source,
        public readonly array $supportingFunesRefs,
    ) {
    }
}

final class ExplanationContradiction
{
    /**
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $statement,
        public readonly string $kind,
        public readonly array $funesRefs,
    ) {
    }
}

final class CurrentProjectStateExplanation
{
    /**
     * @param array<int, ExplanationObservation> $observations
     * @param array<int, ExplanationInference> $inferences
     * @param array<int, ExplanationContradiction> $contradictions
     */
    public function __construct(
        public readonly string $projectFunesRef,
        public readonly array $observations,
        public readonly array $inferences,
        public readonly array $contradictions,
        public readonly CompletenessAssessment $completeness,
        public readonly UncertaintyAssessment $uncertainty,
        public readonly ConfidenceLevel $confidence,
    ) {
    }
}

final class CurrentProjectStateExplanationResult
{
    private function __construct(
        public readonly bool $explained,
        public readonly ?CurrentProjectStateExplanation $explanation,
        public readonly ?RefusalReason $refusalReason,
        public readonly CompletenessAssessment $completeness,
        public readonly UncertaintyAssessment $uncertainty,
    ) {
    }

    public static function explained(CurrentProjectStateExplanation $explanation): self
    {
        return new self(
            explained: true,
            explanation: $explanation,
            refusalReason: null,
            completeness: $explanation->completeness,
            uncertainty: $explanation->uncertainty,
        );
    }

    public static function refused(
        RefusalReason $reason,
        CompletenessAssessment $completeness,
        UncertaintyAssessment $uncertainty,
    ): self {
        return new self(
            explained: false,
            explanation: null,
            refusalReason: $reason,
            completeness: $completeness,
            uncertainty: $uncertainty,
        );
    }
}
