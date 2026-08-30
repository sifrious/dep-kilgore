<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\ProjectTimelines;

use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;
use Sifrious\Kilgore\PeriodSummaries\PeriodSummaryQuery;
use Sifrious\Kilgore\PeriodSummaries\PeriodSummaryService;

final class ProjectTimelineService
{
    public function __construct(
        private readonly PeriodSummaryService $periodSummaryService,
    ) {
    }

    public function build(ProjectTimelineQuery $query): ProjectTimelineResult
    {
        $summaryResult = $this->periodSummaryService->summarize(new PeriodSummaryQuery(
            question: $query->question,
            periodStart: $query->periodStart,
            periodEnd: $query->periodEnd,
            subject: $query->subject,
            corpusScope: $query->corpusScope,
        ));

        if (! $summaryResult->summarized) {
            return ProjectTimelineResult::refused(
                reason: $summaryResult->refusalReason ?? new RefusalReason(
                    code: 'timeline_unavailable',
                    message: 'Project timeline could not be built.',
                ),
                completeness: $summaryResult->completeness,
                uncertainty: $summaryResult->uncertainty,
            );
        }

        $groups = [];
        foreach ($summaryResult->summary->groups as $group) {
            $temporalSemantics = $group->bucketLabel === 'undated'
                ? TimelineTemporalSemantics::Undated
                : TimelineTemporalSemantics::ExactDate;

            $events = [];
            foreach ($group->observations as $observation) {
                $events[] = new TimelineEvent(
                    statement: $observation->statement,
                    layer: TimelineLayer::Observation,
                    timeLabel: $group->bucketLabel,
                    temporalSemantics: $temporalSemantics,
                    funesRefs: $observation->funesRefs,
                );
            }
            foreach ($group->interpretations as $interpretation) {
                $events[] = new TimelineEvent(
                    statement: $interpretation->statement,
                    layer: TimelineLayer::Interpretation,
                    timeLabel: $group->bucketLabel,
                    temporalSemantics: $temporalSemantics,
                    funesRefs: $interpretation->supportingFunesRefs,
                );
            }

            $groups[] = new TimelineGroup(
                bucketLabel: $group->bucketLabel,
                temporalSemantics: $temporalSemantics,
                events: $events,
                funesRefs: $group->funesRefs,
            );
        }

        $signals = $summaryResult->summary->uncertainty->signals;
        $signals[] = 'timeline_interpretation_layered';
        $signals = array_values(array_unique($signals));

        $timeline = new ProjectTimeline(
            periodStart: $summaryResult->summary->periodStart,
            periodEnd: $summaryResult->summary->periodEnd,
            groups: $groups,
            completeness: $summaryResult->summary->completeness,
            uncertainty: new UncertaintyAssessment(
                missingExpectedEvidence: $summaryResult->summary->uncertainty->missingExpectedEvidence,
                signals: $signals,
            ),
            confidence: $summaryResult->summary->confidence,
        );

        return ProjectTimelineResult::built($timeline);
    }
}
