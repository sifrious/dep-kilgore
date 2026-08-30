<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\LeftOffContext;

use DateTimeImmutable;
use InvalidArgumentException;
use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\ConfidenceLevel;
use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;

final class LeftOffContextQuery
{
    public function __construct(
        public readonly string $projectFunesRef,
        public readonly string $question,
        public readonly DateTimeImmutable $historyStart,
        public readonly DateTimeImmutable $historyEnd,
        public readonly ?string $corpusScope = null,
    ) {
        if ($this->projectFunesRef === '') {
            throw new InvalidArgumentException('Project Funes ref is required.');
        }

        if ($this->historyStart > $this->historyEnd) {
            throw new InvalidArgumentException('History start must be earlier than or equal to history end.');
        }
    }
}

final class WorkObservation
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

final class WorkInference
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

final class LastConfirmedWorkEpisode
{
    /**
     * @param array<int, WorkObservation> $observations
     * @param array<int, WorkInference> $inferences
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $boundaryLabel,
        public readonly array $observations,
        public readonly array $inferences,
        public readonly array $funesRefs,
    ) {
    }
}

final class RankedEvidence
{
    /**
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $statement,
        public readonly string $layer,
        public readonly float $rankScore,
        public readonly array $funesRefs,
    ) {
    }
}

final class UnresolvedContext
{
    /**
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $statement,
        public readonly string $kind,
        public readonly array $funesRefs = [],
    ) {
    }
}

final class LeftOffContext
{
    /**
     * @param array<int, RankedEvidence> $rankedEvidence
     * @param array<int, UnresolvedContext> $unresolvedContext
     */
    public function __construct(
        public readonly string $projectFunesRef,
        public readonly LastConfirmedWorkEpisode $lastConfirmedEpisode,
        public readonly array $rankedEvidence,
        public readonly array $unresolvedContext,
        public readonly CompletenessAssessment $completeness,
        public readonly UncertaintyAssessment $uncertainty,
        public readonly ConfidenceLevel $confidence,
    ) {
    }
}

final class LeftOffContextResult
{
    private function __construct(
        public readonly bool $answered,
        public readonly ?LeftOffContext $context,
        public readonly ?RefusalReason $refusalReason,
        public readonly CompletenessAssessment $completeness,
        public readonly UncertaintyAssessment $uncertainty,
    ) {
    }

    public static function answered(LeftOffContext $context): self
    {
        return new self(
            answered: true,
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
            answered: false,
            context: null,
            refusalReason: $reason,
            completeness: $completeness,
            uncertainty: $uncertainty,
        );
    }
}
