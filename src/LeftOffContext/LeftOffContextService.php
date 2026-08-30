<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\LeftOffContext;

use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;
use Sifrious\Kilgore\ProjectTimelines\ProjectTimelineQuery;
use Sifrious\Kilgore\ProjectTimelines\ProjectTimelineService;
use Sifrious\Kilgore\ProjectTimelines\TimelineLayer;
use Sifrious\Kilgore\ProjectTimelines\TimelineTemporalSemantics;

final class LeftOffContextService
{
    public function __construct(
        private readonly ProjectTimelineService $projectTimelineService,
    ) {
    }

    public function answer(LeftOffContextQuery $query): LeftOffContextResult
    {
        $timelineResult = $this->projectTimelineService->build(new ProjectTimelineQuery(
            question: $query->question,
            periodStart: $query->historyStart,
            periodEnd: $query->historyEnd,
            subject: $query->projectFunesRef,
            corpusScope: $query->corpusScope,
        ));

        if (! $timelineResult->built) {
            return LeftOffContextResult::refused(
                reason: $timelineResult->refusalReason ?? new RefusalReason(
                    code: 'left_off_context_unavailable',
                    message: 'Could not reconstruct left-off context.',
                ),
                completeness: $timelineResult->completeness,
                uncertainty: $timelineResult->uncertainty,
            );
        }

        $groups = $timelineResult->timeline->groups;
        $lastConfirmed = $this->detectLastConfirmedWorkEpisode($groups);
        $rankedEvidence = $this->rankEvidence($groups);
        $unresolved = $this->extractUnresolvedContext($groups, $timelineResult->timeline->completeness->missingExpectedEvidence);

        $signals = $timelineResult->timeline->uncertainty->signals;
        $signals[] = 'last_work_boundary_detected';
        if ($unresolved !== []) {
            $signals[] = 'unresolved_context_present';
        }
        if ($lastConfirmed->boundaryLabel === 'undated') {
            $signals[] = 'last_episode_time_uncertain';
        }
        $signals = array_values(array_unique($signals));

        $context = new LeftOffContext(
            projectFunesRef: $query->projectFunesRef,
            lastConfirmedEpisode: $lastConfirmed,
            rankedEvidence: $rankedEvidence,
            unresolvedContext: $unresolved,
            completeness: $timelineResult->timeline->completeness,
            uncertainty: new UncertaintyAssessment(
                missingExpectedEvidence: $timelineResult->timeline->uncertainty->missingExpectedEvidence,
                signals: $signals,
            ),
            confidence: $timelineResult->timeline->confidence,
        );

        return LeftOffContextResult::answered($context);
    }

    /**
     * @param array<int, \Sifrious\Kilgore\ProjectTimelines\TimelineGroup> $groups
     */
    private function detectLastConfirmedWorkEpisode(array $groups): LastConfirmedWorkEpisode
    {
        if ($groups === []) {
            return new LastConfirmedWorkEpisode(
                boundaryLabel: 'undated',
                observations: [],
                inferences: [],
                funesRefs: [],
            );
        }

        $fallbackGroup = end($groups);
        $lastConfirmedGroup = $fallbackGroup;

        for ($index = count($groups) - 1; $index >= 0; $index--) {
            $group = $groups[$index];
            if ($group->temporalSemantics !== TimelineTemporalSemantics::ExactDate) {
                continue;
            }

            foreach ($group->events as $event) {
                if ($event->layer === TimelineLayer::Observation) {
                    $lastConfirmedGroup = $group;
                    break 2;
                }
            }
        }

        $observations = [];
        $inferences = [];
        foreach ($lastConfirmedGroup->events as $event) {
            if ($event->layer === TimelineLayer::Observation) {
                $observations[] = new WorkObservation($event->statement, $event->funesRefs);
                continue;
            }

            $inferences[] = new WorkInference($event->statement, $event->funesRefs);
        }

        return new LastConfirmedWorkEpisode(
            boundaryLabel: $lastConfirmedGroup->bucketLabel,
            observations: $observations,
            inferences: $inferences,
            funesRefs: $lastConfirmedGroup->funesRefs,
        );
    }

    /**
     * @param array<int, \Sifrious\Kilgore\ProjectTimelines\TimelineGroup> $groups
     * @return array<int, RankedEvidence>
     */
    private function rankEvidence(array $groups): array
    {
        $ranked = [];
        $groupWeight = count($groups);

        foreach ($groups as $groupIndex => $group) {
            foreach ($group->events as $event) {
                $layerWeight = $event->layer === TimelineLayer::Observation ? 1.0 : 0.5;
                $recencyWeight = (float) ($groupWeight - $groupIndex);
                $citationWeight = (float) count($event->funesRefs) / 10;

                $ranked[] = new RankedEvidence(
                    statement: $event->statement,
                    layer: $event->layer->value,
                    rankScore: $recencyWeight + $layerWeight + $citationWeight,
                    funesRefs: $event->funesRefs,
                );
            }
        }

        usort(
            $ranked,
            static fn (RankedEvidence $a, RankedEvidence $b): int => $b->rankScore <=> $a->rankScore,
        );

        return $ranked;
    }

    /**
     * @param array<int, \Sifrious\Kilgore\ProjectTimelines\TimelineGroup> $groups
     * @param array<int, non-empty-string> $missingExpectedEvidence
     * @return array<int, UnresolvedContext>
     */
    private function extractUnresolvedContext(array $groups, array $missingExpectedEvidence): array
    {
        $unresolved = [];

        foreach ($groups as $group) {
            foreach ($group->events as $event) {
                if ($event->layer !== TimelineLayer::Interpretation) {
                    continue;
                }

                if (! $this->isUnresolvedInference($event->statement)) {
                    continue;
                }

                $unresolved[] = new UnresolvedContext(
                    statement: sprintf('[%s] %s', $group->bucketLabel, $event->statement),
                    kind: 'inference',
                    funesRefs: $event->funesRefs,
                );
            }
        }

        foreach ($missingExpectedEvidence as $missingRefPattern) {
            $unresolved[] = new UnresolvedContext(
                statement: sprintf('Expected evidence missing: %s', $missingRefPattern),
                kind: 'missing_evidence',
            );
        }

        return $unresolved;
    }

    private function isUnresolvedInference(string $statement): bool
    {
        $normalized = strtolower($statement);

        return str_contains($normalized, 'likely')
            || str_contains($normalized, 'may ')
            || str_contains($normalized, 'unknown')
            || str_contains($normalized, 'uncertain');
    }
}
