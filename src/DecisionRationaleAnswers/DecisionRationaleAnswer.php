<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\DecisionRationaleAnswers;

use DateTimeImmutable;
use InvalidArgumentException;
use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\ConfidenceLevel;
use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;
use Sifrious\Kilgore\PastDecisionContext\DecisionCitationEdge;

final class DecisionRationaleAnswerQuery
{
    public function __construct(
        public readonly string $decisionFunesRef,
        public readonly DateTimeImmutable $contextStart,
        public readonly DateTimeImmutable $contextEnd,
        public readonly ?string $corpusScope = null,
    ) {
        if ($this->decisionFunesRef === '') {
            throw new InvalidArgumentException('Decision Funes ref is required.');
        }
    }
}

final class RationaleObservation
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

final class RationaleInference
{
    /**
     * @param array<int, non-empty-string> $supportingFunesRefs
     */
    public function __construct(
        public readonly string $statement,
        public readonly array $supportingFunesRefs,
    ) {
    }
}

final class DecisionAlternative
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

final class DecisionRationaleAnswer
{
    /**
     * @param array<int, RationaleObservation> $recordedRationale
     * @param array<int, RationaleInference> $inferredRationale
     * @param array<int, DecisionAlternative> $alternatives
     * @param array<int, DecisionCitationEdge> $citations
     */
    public function __construct(
        public readonly string $decisionFunesRef,
        public readonly string $decisionTitle,
        public readonly string $decisionSource,
        public readonly string $authorAccountId,
        public readonly array $recordedRationale,
        public readonly array $inferredRationale,
        public readonly array $alternatives,
        public readonly array $citations,
        public readonly CompletenessAssessment $completeness,
        public readonly UncertaintyAssessment $uncertainty,
        public readonly ConfidenceLevel $confidence,
    ) {
    }
}

final class DecisionRationaleAnswerResult
{
    private function __construct(
        public readonly bool $answered,
        public readonly ?DecisionRationaleAnswer $answer,
        public readonly ?RefusalReason $refusalReason,
        public readonly CompletenessAssessment $completeness,
        public readonly UncertaintyAssessment $uncertainty,
    ) {
    }

    public static function answered(DecisionRationaleAnswer $answer): self
    {
        return new self(
            answered: true,
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
            answered: false,
            answer: null,
            refusalReason: $reason,
            completeness: $completeness,
            uncertainty: $uncertainty,
        );
    }
}
