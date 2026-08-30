<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\ChangeStory;

use InvalidArgumentException;

/**
 * A typed, replaceable interpretation snapshot derived from canonical history.
 */
final class ChangeStory
{
    /**
     * @param array<int, Comparison> $comparisons
     * @param array<int, DecisionCitation> $decisionCitations
     * @param array<int, PlanSummary> $planSummaries
     * @param array<int, ResearchClaimSource> $researchClaimSources
     */
    public function __construct(
        public readonly array $comparisons = [],
        public readonly array $decisionCitations = [],
        public readonly array $planSummaries = [],
        public readonly array $researchClaimSources = [],
    ) {
    }
}

final class Comparison
{
    /**
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $comparisonLabel,
        public readonly string $labelId,
        public readonly array $funesRefs,
        public readonly ?string $stackId = null,
    ) {
        self::guardRefs($this->funesRefs);
    }

    /**
     * @param array<int, string> $funesRefs
     */
    public static function guardRefs(array $funesRefs): void
    {
        if ($funesRefs === []) {
            throw new InvalidArgumentException('Comparison requires at least one Funes ref.');
        }
    }
}

final class DecisionCitation
{
    /**
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $decision,
        public readonly array $funesRefs,
        public readonly ?string $subjectId = null,
    ) {
        Comparison::guardRefs($this->funesRefs);
    }
}

final class PlanSummary
{
    /**
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $summary,
        public readonly array $funesRefs,
        public readonly ?string $subjectId = null,
    ) {
        Comparison::guardRefs($this->funesRefs);
    }
}

enum ResearchClaimKind: string
{
    case Fact = 'fact';
    case Opinion = 'opinion';
    case Synthesis = 'synthesis';
    case Dissent = 'dissent';
    case Implication = 'implication';
}

final class ResearchClaimSource
{
    /**
     * @param array<int, non-empty-string> $funesRefs
     */
    public function __construct(
        public readonly string $claim,
        public readonly array $funesRefs,
        public readonly ResearchClaimKind $kind = ResearchClaimKind::Synthesis,
        public readonly ?string $subjectId = null,
    ) {
        Comparison::guardRefs($this->funesRefs);
    }
}
