<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\HistoricalQuestions;

use Sifrious\Kilgore\ChangeStory\ChangeStory;

enum ConfidenceLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}

final class CompletenessAssessment
{
    /**
     * @param array<int, non-empty-string> $missingExpectedEvidence
     */
    public function __construct(
        public readonly bool $hasSufficientEvidence,
        public readonly array $missingExpectedEvidence = [],
    ) {
    }
}

final class FactAssertion
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

final class InferenceAssertion
{
    /**
     * @param array<int, non-empty-string> $supportingFunesRefs
     */
    public function __construct(
        public readonly string $statement,
        public readonly array $supportingFunesRefs = [],
    ) {
    }
}

final class HistoricalAnswer
{
    /**
     * @param array<int, FactAssertion> $facts
     * @param array<int, InferenceAssertion> $inferences
     */
    public function __construct(
        public readonly array $facts,
        public readonly array $inferences,
        public readonly CompletenessAssessment $completeness,
        public readonly ConfidenceLevel $confidence,
        public readonly ?ChangeStory $changeStory = null,
    ) {
    }
}

final class RefusalReason
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
    ) {
    }
}

final class HistoricalAnswerResult
{
    private function __construct(
        public readonly bool $answered,
        public readonly ?HistoricalAnswer $answer,
        public readonly ?RefusalReason $refusalReason,
        public readonly CompletenessAssessment $completeness,
    ) {
    }

    public static function answered(HistoricalAnswer $answer): self
    {
        return new self(true, $answer, null, $answer->completeness);
    }

    public static function refused(RefusalReason $reason, CompletenessAssessment $completeness): self
    {
        return new self(false, null, $reason, $completeness);
    }
}
