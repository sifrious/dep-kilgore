<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\EntityTimelines;

use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;
use Sifrious\Kilgore\ProjectTimelines\ProjectTimelineQuery;
use Sifrious\Kilgore\ProjectTimelines\ProjectTimelineService;

final class EntityTimelineService
{
    public function __construct(
        private readonly EntityRelationshipTraversal $relationshipTraversal,
        private readonly ProjectTimelineService $projectTimelineService,
    ) {
    }

    public function build(EntityTimelineQuery $query): EntityTimelineResult
    {
        $traversal = $this->relationshipTraversal->traverse($query);
        $entityScope = array_values(array_unique(array_merge([$query->entityFunesRef], $traversal->entityFunesRefs)));

        $timelineResult = $this->projectTimelineService->build(new ProjectTimelineQuery(
            question: $query->question,
            periodStart: $query->periodStart,
            periodEnd: $query->periodEnd,
            subject: $query->entityFunesRef,
            corpusScope: $this->buildCorpusScope($query, $entityScope),
        ));

        if (! $timelineResult->built) {
            return EntityTimelineResult::refused(
                reason: $timelineResult->refusalReason ?? new RefusalReason(
                    code: 'entity_timeline_unavailable',
                    message: 'Entity timeline could not be built.',
                ),
                completeness: $timelineResult->completeness,
                uncertainty: $timelineResult->uncertainty,
            );
        }

        $missingEvidence = array_values(array_unique(array_merge(
            $timelineResult->timeline->completeness->missingExpectedEvidence,
            $traversal->missingExpectedEvidence,
        )));

        $signals = $timelineResult->timeline->uncertainty->signals;
        $signals[] = 'entity_relationship_traversed';
        if ($traversal->relationships === []) {
            $signals[] = 'no_relationship_edges';
        }
        $signals = array_values(array_unique($signals));

        $entityTimeline = new EntityTimeline(
            entityFunesRef: $query->entityFunesRef,
            traversal: $traversal,
            timeline: new \Sifrious\Kilgore\ProjectTimelines\ProjectTimeline(
                periodStart: $timelineResult->timeline->periodStart,
                periodEnd: $timelineResult->timeline->periodEnd,
                groups: $timelineResult->timeline->groups,
                completeness: new CompletenessAssessment(
                    hasSufficientEvidence: $timelineResult->timeline->completeness->hasSufficientEvidence,
                    missingExpectedEvidence: $missingEvidence,
                ),
                uncertainty: new UncertaintyAssessment(
                    missingExpectedEvidence: $missingEvidence,
                    signals: $signals,
                ),
                confidence: $timelineResult->timeline->confidence,
            ),
        );

        return EntityTimelineResult::built($entityTimeline);
    }

    /**
     * @param array<int, non-empty-string> $entityScope
     */
    private function buildCorpusScope(EntityTimelineQuery $query, array $entityScope): string
    {
        $scope = sprintf('entity_scope:%s', implode(',', $entityScope));

        if ($query->corpusScope === null || $query->corpusScope === '') {
            return $scope;
        }

        return $query->corpusScope.'|'.$scope;
    }
}
