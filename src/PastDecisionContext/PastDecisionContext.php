<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\PastDecisionContext;

use DateTimeImmutable;
use InvalidArgumentException;
use Sifrious\Kilgore\ChangeStory\ChangeStory;
use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\ConfidenceLevel;
use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;

final class PastDecisionContextQuery
{
    public function __construct(
        public readonly string $decisionFunesRef,
        public readonly string $question,
        public readonly DateTimeImmutable $contextStart,
        public readonly DateTimeImmutable $contextEnd,
        public readonly ?string $corpusScope = null,
    ) {
        if ($this->decisionFunesRef === '') {
            throw new InvalidArgumentException('Decision Funes ref is required.');
        }

        if ($this->contextStart > $this->contextEnd) {
            throw new InvalidArgumentException('Context start must be earlier than or equal to context end.');
        }
    }
}

final class DecisionCitationEdge
{
    public function __construct(
        public readonly string $label,
        public readonly string $locator,
        public readonly int $position,
        public readonly string $funesRef,
        public readonly ?string $stackId = null,
    ) {
    }
}

final class LinkedIdentity
{
    public function __construct(
        public readonly string $funesRef,
        public readonly ?string $stackId = null,
    ) {
    }
}

final class RecordedRationale
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

final class InferredRationale
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

final class PastDecisionContext
{
    /**
     * @param array<int, LinkedIdentity> $linkedIdentities
     * @param array<int, DecisionCitationEdge> $citationEdges
     * @param array<int, RecordedRationale> $recordedRationales
     * @param array<int, InferredRationale> $inferredRationales
     */
    public function __construct(
        public readonly string $decisionFunesRef,
        public readonly string $decisionTitle,
        public readonly string $decisionSource,
        public readonly string $authorAccountId,
        public readonly array $linkedIdentities,
        public readonly array $citationEdges,
        public readonly array $recordedRationales,
        public readonly array $inferredRationales,
        public readonly CompletenessAssessment $completeness,
        public readonly UncertaintyAssessment $uncertainty,
        public readonly ConfidenceLevel $confidence,
        public readonly ?ChangeStory $changeStory = null,
    ) {
    }
}

final class PastDecisionContextResult
{
    private function __construct(
        public readonly bool $reconstructed,
        public readonly ?PastDecisionContext $context,
        public readonly ?RefusalReason $refusalReason,
        public readonly CompletenessAssessment $completeness,
        public readonly UncertaintyAssessment $uncertainty,
    ) {
    }

    public static function reconstructed(PastDecisionContext $context): self
    {
        return new self(
            reconstructed: true,
            context: $context,
            refusalReason: null,
            completeness: $context->completeness,
            uncertainty: $context->uncertainty,
        );
    }

    public static function refused(
        RefusalReason $reason,
        CompletenessAssessment $completeness,
        UncertaintyAssessment $uncertainty,
    ): self {
        return new self(
            reconstructed: false,
            context: null,
            refusalReason: $reason,
            completeness: $completeness,
            uncertainty: $uncertainty,
        );
    }
}
