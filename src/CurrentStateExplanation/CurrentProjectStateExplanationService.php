<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\CurrentStateExplanation;

use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;
use Sifrious\Kilgore\ProjectTimelines\ProjectTimelineQuery;
use Sifrious\Kilgore\ProjectTimelines\ProjectTimelineService;
use Sifrious\Kilgore\ProjectTimelines\TimelineLayer;
use Sifrious\Kilgore\StateComparison\ChangeClassification;
use Sifrious\Kilgore\StateComparison\CurrentPriorStateQuery;
use Sifrious\Kilgore\StateComparison\CurrentPriorStateService;

final class CurrentProjectStateExplanationService
{
    public function __construct(
        private readonly CurrentPriorStateService $currentPriorStateService,
        private readonly ProjectTimelineService $projectTimelineService,
    ) {
    }

    public function explain(CurrentProjectStateQuery $query): CurrentProjectStateExplanationResult
    {
        $stateResult = $this->currentPriorStateService->compare(new CurrentPriorStateQuery(
            question: $query->question,
            baselineAt: $query->baselineAt,
            currentAt: $query->currentAt,
            subject: $query->projectFunesRef,
            corpusScope: $query->corpusScope,
        ));

        if (! $stateResult->compared) {
            return CurrentProjectStateExplanationResult::refused(
                reason: $stateResult->refusalReason ?? new RefusalReason(
                    code: 'state_comparison_unavailable',
                    message: 'Current-versus-prior state could not be interpreted.',
                ),
                completeness: $stateResult->completeness,
                uncertainty: $stateResult->uncertainty,
            );
        }

        $timelineResult = $this->projectTimelineService->build(new ProjectTimelineQuery(
            question: $query->question,
            periodStart: $query->timelineStart,
            periodEnd: $query->timelineEnd,
            subject: $query->projectFunesRef,
            corpusScope: $query->corpusScope,
        ));

        if (! $timelineResult->built) {
            return CurrentProjectStateExplanationResult::refused(
                reason: $timelineResult->refusalReason ?? new RefusalReason(
                    code: 'project_timeline_unavailable',
                    message: 'Project timeline could not be interpreted.',
                ),
                completeness: $timelineResult->completeness,
                uncertainty: $timelineResult->uncertainty,
            );
        }

        $observations = [];
        foreach ($stateResult->answer->observations as $stateObservation) {
            $observations[] = new ExplanationObservation(
                statement: sprintf(
                    '[%s] %s',
                    $stateObservation->classification->value,
                    $stateObservation->statement,
                ),
                source: 'current_prior_state',
                funesRefs: $stateObservation->funesRefs,
            );
        }

        foreach ($timelineResult->timeline->groups as $timelineGroup) {
            foreach ($timelineGroup->events as $event) {
                if ($event->layer !== TimelineLayer::Observation) {
                    continue;
                }

                $observations[] = new ExplanationObservation(
                    statement: sprintf('[%s] %s', $timelineGroup->bucketLabel, $event->statement),
                    source: 'project_timeline',
                    funesRefs: $event->funesRefs,
                );
            }
        }

        $inferences = [];
        foreach ($stateResult->answer->interpretations as $stateInterpretation) {
            $inferences[] = new ExplanationInference(
                statement: sprintf(
                    '[%s] %s',
                    $stateInterpretation->classification->value,
                    $stateInterpretation->statement,
                ),
                source: 'current_prior_state',
                supportingFunesRefs: $stateInterpretation->funesRefs,
            );
        }

        foreach ($timelineResult->timeline->groups as $timelineGroup) {
            foreach ($timelineGroup->events as $event) {
                if ($event->layer !== TimelineLayer::Interpretation) {
                    continue;
                }

                $inferences[] = new ExplanationInference(
                    statement: sprintf('[%s] %s', $timelineGroup->bucketLabel, $event->statement),
                    source: 'project_timeline',
                    supportingFunesRefs: $event->funesRefs,
                );
            }
        }

        $contradictions = $this->deriveContradictions($stateResult->answer->observations);

        $missingEvidence = array_values(array_unique(array_merge(
            $stateResult->completeness->missingExpectedEvidence,
            $timelineResult->completeness->missingExpectedEvidence,
        )));

        $signals = array_values(array_unique(array_merge(
            $stateResult->uncertainty->signals,
            $timelineResult->uncertainty->signals,
            $contradictions === [] ? [] : ['contradictions_present'],
            ['causal_temporal_reasoning_applied'],
        )));

        $explanation = new CurrentProjectStateExplanation(
            projectFunesRef: $query->projectFunesRef,
            observations: $observations,
            inferences: $inferences,
            contradictions: $contradictions,
            completeness: new CompletenessAssessment(
                hasSufficientEvidence: $stateResult->completeness->hasSufficientEvidence
                    && $timelineResult->completeness->hasSufficientEvidence,
                missingExpectedEvidence: $missingEvidence,
            ),
            uncertainty: new UncertaintyAssessment(
                missingExpectedEvidence: $missingEvidence,
                signals: $signals,
            ),
            confidence: $stateResult->answer->confidence,
        );

        return CurrentProjectStateExplanationResult::explained($explanation);
    }

    /**
     * @param array<int, \Sifrious\Kilgore\StateComparison\StateChange> $stateObservations
     * @return array<int, ExplanationContradiction>
     */
    private function deriveContradictions(array $stateObservations): array
    {
        $added = [];
        $removed = [];

        foreach ($stateObservations as $observation) {
            $normalized = $this->normalizeStatement($observation->statement);
            if ($observation->classification === ChangeClassification::Added) {
                $added[$normalized] = $observation;
            }
            if ($observation->classification === ChangeClassification::Removed) {
                $removed[$normalized] = $observation;
            }
        }

        $contradictions = [];
        foreach ($added as $normalized => $addedObservation) {
            if (! array_key_exists($normalized, $removed)) {
                continue;
            }

            $removedObservation = $removed[$normalized];
            $contradictions[] = new ExplanationContradiction(
                statement: sprintf(
                    'State changed from removed to added for: %s',
                    $addedObservation->statement,
                ),
                kind: 'state_transition_conflict',
                funesRefs: array_values(array_unique(array_merge(
                    $addedObservation->funesRefs,
                    $removedObservation->funesRefs,
                ))),
            );
        }

        return $contradictions;
    }

    private function normalizeStatement(string $statement): string
    {
        $normalized = strtolower($statement);
        $normalized = str_replace([' was ', ' is ', ' are '], ' ', $normalized);
        $normalized = str_replace([' enabled', ' disabled'], '', $normalized);

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }
}
