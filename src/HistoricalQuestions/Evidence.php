<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\HistoricalQuestions;

enum EvidenceKind: string
{
    case Comparison = 'comparison';
    case DecisionCitation = 'decision_citation';
    case PlanSummary = 'plan_summary';
    case ResearchClaimSource = 'research_claim_source';
    case Other = 'other';
}

final class EvidenceItem
{
    public function __construct(
        public readonly string $funesRef,
        public readonly EvidenceKind $kind,
        public readonly string $summary,
    ) {
    }
}

final class EvidenceSet
{
    /**
     * @param array<int, EvidenceItem> $items
     * @param array<int, non-empty-string> $missingExpectedEvidence
     */
    public function __construct(
        public readonly array $items,
        public readonly array $missingExpectedEvidence = [],
    ) {
    }

    public function isSufficient(): bool
    {
        return $this->items !== [];
    }

    /**
     * @return array<int, non-empty-string>
     */
    public function refs(): array
    {
        return array_values(
            array_map(
                static fn (EvidenceItem $item): string => $item->funesRef,
                $this->items,
            ),
        );
    }
}
