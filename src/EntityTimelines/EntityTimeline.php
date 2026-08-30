<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\EntityTimelines;

use DateTimeImmutable;
use InvalidArgumentException;
use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;
use Sifrious\Kilgore\ProjectTimelines\ProjectTimeline;

final class EntityTimelineQuery
{
    public function __construct(
        public readonly string $entityFunesRef,
        public readonly string $question,
        public readonly DateTimeImmutable $periodStart,
        public readonly DateTimeImmutable $periodEnd,
        public readonly int $relationshipDepth = 1,
        public readonly ?string $corpusScope = null,
    ) {
        if ($this->entityFunesRef === '') {
            throw new InvalidArgumentException('Entity Funes ref is required.');
        }

        if ($this->periodStart > $this->periodEnd) {
            throw new InvalidArgumentException('Timeline period start must be earlier than or equal to end.');
        }

        if ($this->relationshipDepth < 0) {
            throw new InvalidArgumentException('Relationship depth must be zero or greater.');
        }
    }
}

final class EntityRelationship
{
    /**
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $fromEntityFunesRef,
        public readonly string $toEntityFunesRef,
        public readonly string $relationshipType,
        public readonly array $funesRefs,
    ) {
    }
}

final class EntityTraversalResult
{
    /**
     * @param array<int, non-empty-string> $entityFunesRefs
     * @param array<int, EntityRelationship> $relationships
     * @param array<int, non-empty-string> $missingExpectedEvidence
     */
    public function __construct(
        public readonly array $entityFunesRefs,
        public readonly array $relationships = [],
        public readonly array $missingExpectedEvidence = [],
    ) {
    }
}

interface EntityRelationshipTraversal
{
    public function traverse(EntityTimelineQuery $query): EntityTraversalResult;
}

final class EntityTimeline
{
    public function __construct(
        public readonly string $entityFunesRef,
        public readonly EntityTraversalResult $traversal,
        public readonly ProjectTimeline $timeline,
    ) {
    }
}

final class EntityTimelineResult
{
    private function __construct(
        public readonly bool $built,
        public readonly ?EntityTimeline $entityTimeline,
        public readonly ?RefusalReason $refusalReason,
        public readonly CompletenessAssessment $completeness,
        public readonly UncertaintyAssessment $uncertainty,
    ) {
    }

    public static function built(EntityTimeline $entityTimeline): self
    {
        return new self(
            built: true,
            entityTimeline: $entityTimeline,
            refusalReason: null,
            completeness: $entityTimeline->timeline->completeness,
            uncertainty: $entityTimeline->timeline->uncertainty,
        );
    }

    public static function refused(
        RefusalReason $reason,
        CompletenessAssessment $completeness,
        UncertaintyAssessment $uncertainty,
    ): self {
        return new self(
            built: false,
            entityTimeline: null,
            refusalReason: $reason,
            completeness: $completeness,
            uncertainty: $uncertainty,
        );
    }
}
