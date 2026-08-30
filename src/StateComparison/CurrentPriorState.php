<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\StateComparison;

use DateTimeImmutable;
use InvalidArgumentException;
use Sifrious\Kilgore\ChangeStory\ChangeStory;
use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\ConfidenceLevel;
use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;

final class CurrentPriorStateQuery
{
    public function __construct(
        public readonly string $question,
        public readonly DateTimeImmutable $baselineAt,
        public readonly DateTimeImmutable $currentAt,
        public readonly ?string $subject = null,
        public readonly ?string $corpusScope = null,
    ) {
        if ($this->baselineAt > $this->currentAt) {
            throw new InvalidArgumentException('Baseline must be earlier than or equal to current time.');
        }
    }
}

enum ChangeClassification: string
{
    case Added = 'added';
    case Removed = 'removed';
    case Persisted = 'persisted';
}

final class StateChange
{
    /**
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $statement,
        public readonly ChangeClassification $classification,
        public readonly array $funesRefs,
    ) {
    }
}

final class CurrentPriorStateAnswer
{
    /**
     * @param array<int, StateChange> $observations
     * @param array<int, StateChange> $interpretations
     */
    public function __construct(
        public readonly DateTimeImmutable $baselineAt,
        public readonly DateTimeImmutable $currentAt,
        public readonly array $observations,
        public readonly array $interpretations,
        public readonly CompletenessAssessment $completeness,
        public readonly UncertaintyAssessment $uncertainty,
        public readonly ConfidenceLevel $confidence,
        public readonly ChangeStory $changeStory,
    ) {
    }
}

final class CurrentPriorStateResult
{
    private function __construct(
        public readonly bool $compared,
        public readonly ?CurrentPriorStateAnswer $answer,
        public readonly ?RefusalReason $refusalReason,
        public readonly CompletenessAssessment $completeness,
        public readonly UncertaintyAssessment $uncertainty,
    ) {
    }

    public static function compared(CurrentPriorStateAnswer $answer): self
    {
        return new self(
            compared: true,
            answer: $answer,
            refusalReason: null,
            completeness: $answer->completeness,
            uncertainty: $answer->uncertainty,
        );
    }

    public static function refused(
        RefusalReason $reason,
        CompletenessAssessment $completeness,
        UncertaintyAssessment $uncertainty,
    ): self {
        return new self(
            compared: false,
            answer: null,
            refusalReason: $reason,
            completeness: $completeness,
            uncertainty: $uncertainty,
        );
    }
}
