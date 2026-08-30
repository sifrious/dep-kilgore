<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\HistoricalQuestions;

use Sifrious\Kilgore\ChangeStory\ChangeStory;
use Sifrious\Kilgore\ChangeStory\ResearchClaimKind;

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

final class UncertaintyAssessment
{
    /**
     * @param array<int, non-empty-string> $missingExpectedEvidence
     * @param array<int, non-empty-string> $signals
     */
    public function __construct(
        public readonly array $missingExpectedEvidence = [],
        public readonly array $signals = [],
    ) {
    }
}

enum ClaimAssertionType: string
{
    case Fact = 'fact';
    case Hypothesis = 'hypothesis';
}

final class HistoricalClaim
{
    /**
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $statement,
        public readonly ClaimAssertionType $assertionType,
        public readonly ResearchClaimKind $kind,
        public readonly array $funesRefs = [],
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
     * @param array<int, HistoricalClaim> $claims
     */
    public function __construct(
        public readonly array $facts,
        public readonly array $inferences,
        public readonly CompletenessAssessment $completeness,
        public readonly UncertaintyAssessment $uncertainty,
        public readonly ConfidenceLevel $confidence,
        public readonly array $claims = [],
        public readonly ?ChangeStory $changeStory = null,
    ) {
    }

    public function withCompletenessAndUncertainty(
        CompletenessAssessment $completeness,
        UncertaintyAssessment $uncertainty,
    ): self {
        return new self(
            facts: $this->facts,
            inferences: $this->inferences,
            completeness: $completeness,
            uncertainty: $uncertainty,
            confidence: $this->confidence,
            claims: $this->claims,
            changeStory: $this->changeStory,
        );
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
        public readonly UncertaintyAssessment $uncertainty,
    ) {
    }

    public static function answered(HistoricalAnswer $answer): self
    {
        return new self(true, $answer, null, $answer->completeness, $answer->uncertainty);
    }

    public static function refused(RefusalReason $reason, CompletenessAssessment $completeness): self
    {
        return new self(
            false,
            null,
            $reason,
            $completeness,
            new UncertaintyAssessment(
                missingExpectedEvidence: $completeness->missingExpectedEvidence,
                signals: ['insufficient_evidence'],
            ),
        );
    }
}
