<?php

declare(strict_types=1);

use Sifrious\Kilgore\ChangeStory\ChangeStory;
use Sifrious\Kilgore\ChangeStory\Comparison;
use Sifrious\Kilgore\ChangeStory\DecisionCitation;
use Sifrious\Kilgore\ChangeStory\PlanSummary;
use Sifrious\Kilgore\ChangeStory\ResearchClaimSource;

it('preserves the change story interpretation contract with funes identity', function (): void {
    $story = new ChangeStory(
        comparisons: [
            new Comparison(
                comparisonLabel: 'before-vs-after',
                labelId: 'label_42',
                funesRefs: ['funes:cmp:001'],
                stackId: 'stack:api',
            ),
        ],
        decisionCitations: [
            new DecisionCitation(
                decision: 'Use retrieval before interpretation.',
                funesRefs: ['funes:decision:001'],
                subjectId: 'subject:kilgore',
            ),
        ],
        planSummaries: [
            new PlanSummary(
                summary: 'Ship smallest coherent K01-K03 slice.',
                funesRefs: ['funes:plan:001'],
            ),
        ],
        researchClaimSources: [
            new ResearchClaimSource(
                claim: 'Interpretations should be replaceable snapshots.',
                funesRefs: ['funes:research:001'],
            ),
        ],
    );

    expect($story->comparisons)->toHaveCount(1)
        ->and($story->comparisons[0]->labelId)->toBe('label_42')
        ->and($story->comparisons[0]->funesRefs)->toBe(['funes:cmp:001'])
        ->and($story->decisionCitations)->toHaveCount(1)
        ->and($story->planSummaries)->toHaveCount(1)
        ->and($story->researchClaimSources)->toHaveCount(1);
});
