<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\PeriodSummaries;

use DateTimeImmutable;
use Sifrious\Kilgore\HistoricalQuestions\ClaimAssertionType;
use Sifrious\Kilgore\HistoricalQuestions\EvidenceItem;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalQuestion;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalQuestionService;
use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;

final class PeriodSummaryService
{
    public function __construct(
        private readonly HistoricalQuestionService $historicalQuestionService,
    ) {
    }

    public function summarize(PeriodSummaryQuery $query): PeriodSummaryResult
    {
        $package = $this->historicalQuestionService->askWithEvidence(
            new HistoricalQuestion(
                text: $query->question,
                subject: $query->subject,
                timeScope: sprintf(
                    '%s..%s',
                    $query->periodStart->format(DateTimeImmutable::ATOM),
                    $query->periodEnd->format(DateTimeImmutable::ATOM),
                ),
                corpusScope: $query->corpusScope,
            ),
        );

        if (! $package->result->answered) {
            return PeriodSummaryResult::refused(
                reason: $package->result->refusalReason ?? new RefusalReason(
                    code: 'unavailable_summary',
                    message: 'Period summary could not be produced.',
                ),
                completeness: $package->result->completeness,
                uncertainty: $package->result->uncertainty,
            );
        }

        $answer = $package->result->answer;
        $evidenceByRef = $package->evidence->byRef();
        $groups = [];
        $missingTimestamp = false;

        foreach ($answer->facts as $fact) {
            $bucketLabel = $this->bucketLabel($fact->funesRefs, $evidenceByRef, $query, $missingTimestamp);
            if ($bucketLabel === null) {
                continue;
            }
            $groups[$bucketLabel] ??= [
                'observations' => [],
                'interpretations' => [],
                'funesRefs' => [],
            ];
            $groups[$bucketLabel]['observations'][] = new PeriodObservation($fact->statement, $fact->funesRefs);
            $groups[$bucketLabel]['funesRefs'] = array_values(array_unique(array_merge(
                $groups[$bucketLabel]['funesRefs'],
                $fact->funesRefs,
            )));
        }

        foreach ($answer->inferences as $inference) {
            $bucketLabel = $this->bucketLabel($inference->supportingFunesRefs, $evidenceByRef, $query, $missingTimestamp);
            if ($bucketLabel === null) {
                continue;
            }
            $groups[$bucketLabel] ??= [
                'observations' => [],
                'interpretations' => [],
                'funesRefs' => [],
            ];
            $groups[$bucketLabel]['interpretations'][] = new PeriodInterpretation(
                statement: $inference->statement,
                type: 'inference',
                supportingFunesRefs: $inference->supportingFunesRefs,
            );
            $groups[$bucketLabel]['funesRefs'] = array_values(array_unique(array_merge(
                $groups[$bucketLabel]['funesRefs'],
                $inference->supportingFunesRefs,
            )));
        }

        foreach ($answer->claims as $claim) {
            if ($claim->assertionType === ClaimAssertionType::Fact) {
                continue;
            }

            $bucketLabel = $this->bucketLabel($claim->funesRefs, $evidenceByRef, $query, $missingTimestamp);
            if ($bucketLabel === null) {
                continue;
            }
            $groups[$bucketLabel] ??= [
                'observations' => [],
                'interpretations' => [],
                'funesRefs' => [],
            ];
            $groups[$bucketLabel]['interpretations'][] = new PeriodInterpretation(
                statement: $claim->statement,
                type: 'hypothesis',
                supportingFunesRefs: $claim->funesRefs,
            );
            $groups[$bucketLabel]['funesRefs'] = array_values(array_unique(array_merge(
                $groups[$bucketLabel]['funesRefs'],
                $claim->funesRefs,
            )));
        }

        ksort($groups);
        $chronologicalGroups = [];
        foreach ($groups as $bucketLabel => $groupData) {
            $chronologicalGroups[] = new ChronologicalGroup(
                bucketLabel: $bucketLabel,
                observations: $groupData['observations'],
                interpretations: $groupData['interpretations'],
                funesRefs: $groupData['funesRefs'],
            );
        }

        $signals = $answer->uncertainty->signals;
        if ($missingTimestamp) {
            $signals[] = 'uncertain_event_time';
            $signals = array_values(array_unique($signals));
        }

        $summary = new PeriodSummary(
            periodStart: $query->periodStart,
            periodEnd: $query->periodEnd,
            groups: $chronologicalGroups,
            completeness: $answer->completeness,
            uncertainty: new UncertaintyAssessment(
                missingExpectedEvidence: $answer->uncertainty->missingExpectedEvidence,
                signals: $signals,
            ),
            confidence: $answer->confidence,
        );

        return PeriodSummaryResult::summarized($summary);
    }

    /**
     * @param array<int, non-empty-string> $funesRefs
     * @param array<non-empty-string, EvidenceItem> $evidenceByRef
     */
    private function bucketLabel(
        array $funesRefs,
        array $evidenceByRef,
        PeriodSummaryQuery $query,
        bool &$missingTimestamp,
    ): ?string {
        if ($funesRefs === []) {
            $missingTimestamp = true;

            return 'undated';
        }

        foreach ($funesRefs as $funesRef) {
            $evidence = $evidenceByRef[$funesRef] ?? null;
            if ($evidence === null) {
                continue;
            }

            if ($evidence->occurredAt === null) {
                $missingTimestamp = true;

                return 'undated';
            }

            if ($evidence->occurredAt < $query->periodStart || $evidence->occurredAt > $query->periodEnd) {
                continue;
            }

            return $evidence->occurredAt->format('Y-m-d');
        }

        return null;
    }
}
