<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\StateComparison;

use Sifrious\Kilgore\ChangeStory\ChangeStory;
use Sifrious\Kilgore\ChangeStory\Comparison;
use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalQuestion;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalQuestionService;
use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;

final class CurrentPriorStateService
{
    public function __construct(
        private readonly HistoricalQuestionService $historicalQuestionService,
    ) {
    }

    public function compare(CurrentPriorStateQuery $query): CurrentPriorStateResult
    {
        $baselinePackage = $this->historicalQuestionService->askWithEvidence(
            new HistoricalQuestion(
                text: $query->question,
                subject: $query->subject,
                timeScope: sprintf('as_of:%s', $query->baselineAt->format('c')),
                corpusScope: $query->corpusScope,
            ),
        );

        if (! $baselinePackage->result->answered) {
            return CurrentPriorStateResult::refused(
                reason: $baselinePackage->result->refusalReason ?? new RefusalReason(
                    code: 'baseline_unavailable',
                    message: 'Baseline state could not be interpreted.',
                ),
                completeness: $baselinePackage->result->completeness,
                uncertainty: $baselinePackage->result->uncertainty,
            );
        }

        $currentPackage = $this->historicalQuestionService->askWithEvidence(
            new HistoricalQuestion(
                text: $query->question,
                subject: $query->subject,
                timeScope: sprintf('as_of:%s', $query->currentAt->format('c')),
                corpusScope: $query->corpusScope,
            ),
        );

        if (! $currentPackage->result->answered) {
            return CurrentPriorStateResult::refused(
                reason: $currentPackage->result->refusalReason ?? new RefusalReason(
                    code: 'current_unavailable',
                    message: 'Current state could not be interpreted.',
                ),
                completeness: $currentPackage->result->completeness,
                uncertainty: $currentPackage->result->uncertainty,
            );
        }

        $baseline = $baselinePackage->result->answer;
        $current = $currentPackage->result->answer;

        $baselineObservationMap = $this->indexStateChanges($baseline->facts);
        $currentObservationMap = $this->indexStateChanges($current->facts);
        $baselineInterpretationMap = $this->indexStateChanges($baseline->inferences);
        $currentInterpretationMap = $this->indexStateChanges($current->inferences);

        $observationChanges = $this->classifyChanges($baselineObservationMap, $currentObservationMap);
        $interpretationChanges = $this->classifyChanges($baselineInterpretationMap, $currentInterpretationMap);

        $allMissing = array_values(array_unique(array_merge(
            $baselinePackage->result->completeness->missingExpectedEvidence,
            $currentPackage->result->completeness->missingExpectedEvidence,
        )));

        $allSignals = array_values(array_unique(array_merge(
            $baselinePackage->result->uncertainty->signals,
            $currentPackage->result->uncertainty->signals,
            ['explicit_temporal_baseline'],
        )));

        $allComparisonRefs = [];
        foreach (array_merge($observationChanges, $interpretationChanges) as $change) {
            $allComparisonRefs = array_values(array_unique(array_merge($allComparisonRefs, $change->funesRefs)));
        }
        if ($allComparisonRefs === []) {
            $allComparisonRefs = array_values(array_unique(array_merge(
                $baselinePackage->evidence->refs(),
                $currentPackage->evidence->refs(),
            )));
        }

        $changeStory = new ChangeStory(
            comparisons: [
                new Comparison(
                    comparisonLabel: 'current-vs-prior-state',
                    labelId: 'comparison/current-vs-prior-state',
                    funesRefs: $allComparisonRefs,
                ),
            ],
        );

        $answer = new CurrentPriorStateAnswer(
            baselineAt: $query->baselineAt,
            currentAt: $query->currentAt,
            observations: $observationChanges,
            interpretations: $interpretationChanges,
            completeness: new CompletenessAssessment(
                hasSufficientEvidence: $baselinePackage->result->completeness->hasSufficientEvidence
                    && $currentPackage->result->completeness->hasSufficientEvidence,
                missingExpectedEvidence: $allMissing,
            ),
            uncertainty: new UncertaintyAssessment(
                missingExpectedEvidence: $allMissing,
                signals: $allSignals,
            ),
            confidence: $current->confidence,
            changeStory: $changeStory,
        );

        return CurrentPriorStateResult::compared($answer);
    }

    /**
     * @param array<int, object> $assertions
     * @return array<string, array{statement: string, refs: array<int, non-empty-string>}>
     */
    private function indexStateChanges(array $assertions): array
    {
        $indexed = [];

        foreach ($assertions as $assertion) {
            if (! property_exists($assertion, 'statement')) {
                continue;
            }

            $statement = (string) $assertion->statement;
            $refs = [];

            if (property_exists($assertion, 'funesRefs') && is_array($assertion->funesRefs)) {
                $refs = $assertion->funesRefs;
            }

            if (property_exists($assertion, 'supportingFunesRefs') && is_array($assertion->supportingFunesRefs)) {
                $refs = $assertion->supportingFunesRefs;
            }

            $indexed[$statement] = [
                'statement' => $statement,
                'refs' => $refs,
            ];
        }

        return $indexed;
    }

    /**
     * @param array<string, array{statement: string, refs: array<int, non-empty-string>}> $baseline
     * @param array<string, array{statement: string, refs: array<int, non-empty-string>}> $current
     * @return array<int, StateChange>
     */
    private function classifyChanges(array $baseline, array $current): array
    {
        $changes = [];
        $allStatements = array_values(array_unique(array_merge(array_keys($baseline), array_keys($current))));
        sort($allStatements);

        foreach ($allStatements as $statement) {
            $baselineItem = $baseline[$statement] ?? null;
            $currentItem = $current[$statement] ?? null;

            if ($baselineItem === null && $currentItem !== null) {
                $changes[] = new StateChange(
                    statement: $statement,
                    classification: ChangeClassification::Added,
                    funesRefs: $currentItem['refs'],
                );

                continue;
            }

            if ($baselineItem !== null && $currentItem === null) {
                $changes[] = new StateChange(
                    statement: $statement,
                    classification: ChangeClassification::Removed,
                    funesRefs: $baselineItem['refs'],
                );

                continue;
            }

            if ($baselineItem !== null && $currentItem !== null) {
                $changes[] = new StateChange(
                    statement: $statement,
                    classification: ChangeClassification::Persisted,
                    funesRefs: array_values(array_unique(array_merge($baselineItem['refs'], $currentItem['refs']))),
                );
            }
        }

        return $changes;
    }
}
