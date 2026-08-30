<?php

declare(strict_types=1);

use DateTimeImmutable;
use Sifrious\Kilgore\ChangeStory\ResearchClaimKind;
use Sifrious\Kilgore\HistoricalQuestions\ClaimAssertionType;
use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\ConfidenceLevel;
use Sifrious\Kilgore\HistoricalQuestions\EvidenceItem;
use Sifrious\Kilgore\HistoricalQuestions\EvidenceKind;
use Sifrious\Kilgore\HistoricalQuestions\EvidenceSet;
use Sifrious\Kilgore\HistoricalQuestions\FactAssertion;
use Sifrious\Kilgore\HistoricalQuestions\FunesEvidenceRetriever;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalAnswer;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalClaim;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalInterpreter;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalQuestion;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalQuestionService;
use Sifrious\Kilgore\HistoricalQuestions\InferenceAssertion;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;
use Sifrious\Kilgore\PeriodSummaries\PeriodSummaryService;
use Sifrious\Kilgore\ProjectTimelines\ProjectTimelineQuery;
use Sifrious\Kilgore\ProjectTimelines\ProjectTimelineService;
use Sifrious\Kilgore\ProjectTimelines\TimelineLayer;
use Sifrious\Kilgore\ProjectTimelines\TimelineTemporalSemantics;

it('builds project timelines with explicit temporal semantics and citations', function (): void {
    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet([
                new EvidenceItem(
                    'funes:timeline:1',
                    EvidenceKind::Other,
                    'Project kickoff',
                    new DateTimeImmutable('2026-08-01T09:00:00+00:00'),
                ),
                new EvidenceItem(
                    'funes:timeline:2',
                    EvidenceKind::DecisionCitation,
                    'Architecture decision',
                    new DateTimeImmutable('2026-08-02T11:00:00+00:00'),
                ),
            ]);
        }
    };

    $interpreter = new class implements HistoricalInterpreter
    {
        public function interpret(HistoricalQuestion $question, EvidenceSet $evidence): HistoricalAnswer
        {
            return new HistoricalAnswer(
                facts: [
                    new FactAssertion('Kickoff completed.', ['funes:timeline:1']),
                    new FactAssertion('Architecture decision recorded.', ['funes:timeline:2']),
                ],
                inferences: [
                    new InferenceAssertion('Decision likely reduced implementation churn.', ['funes:timeline:2']),
                ],
                completeness: new CompletenessAssessment(true, ['funes:risk-log:*']),
                uncertainty: new UncertaintyAssessment(['funes:risk-log:*'], ['evidence_gap']),
                confidence: ConfidenceLevel::Medium,
                claims: [
                    new HistoricalClaim(
                        statement: 'Delivery may accelerate after decision.',
                        assertionType: ClaimAssertionType::Hypothesis,
                        kind: ResearchClaimKind::Implication,
                    ),
                ],
            );
        }
    };

    $questionService = new HistoricalQuestionService($retriever, $interpreter);
    $periodSummaryService = new PeriodSummaryService($questionService);
    $timelineService = new ProjectTimelineService($periodSummaryService);
    $result = $timelineService->build(new ProjectTimelineQuery(
        question: 'Build a project timeline for early August',
        periodStart: new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
        periodEnd: new DateTimeImmutable('2026-08-03T00:00:00+00:00'),
        subject: 'kilgore',
    ));

    expect($result->built)->toBeTrue()
        ->and($result->timeline)->not->toBeNull()
        ->and($result->timeline?->groups)->toHaveCount(3)
        ->and($result->timeline?->groups[0]->bucketLabel)->toBe('2026-08-01')
        ->and($result->timeline?->groups[0]->temporalSemantics)->toBe(TimelineTemporalSemantics::ExactDate)
        ->and($result->timeline?->groups[0]->events[0]->layer)->toBe(TimelineLayer::Observation)
        ->and($result->timeline?->groups[1]->bucketLabel)->toBe('2026-08-02')
        ->and($result->timeline?->groups[1]->events[0]->layer)->toBe(TimelineLayer::Observation)
        ->and($result->timeline?->groups[1]->events[1]->layer)->toBe(TimelineLayer::Interpretation)
        ->and($result->timeline?->groups[2]->bucketLabel)->toBe('undated')
        ->and($result->timeline?->groups[2]->temporalSemantics)->toBe(TimelineTemporalSemantics::Undated)
        ->and($result->timeline?->groups[2]->events[0]->funesRefs)->toBe([])
        ->and($result->timeline?->uncertainty->missingExpectedEvidence)->toBe(['funes:risk-log:*'])
        ->and($result->timeline?->uncertainty->signals)->toContain('timeline_interpretation_layered')
        ->and($result->timeline?->uncertainty->signals)->toContain('missing_expected_history')
        ->and($result->timeline?->uncertainty->signals)->toContain('uncertain_event_time');
});

it('preserves refusal when timeline evidence is insufficient', function (): void {
    $tracker = new class
    {
        public bool $interpreterCalled = false;
    };

    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet([], ['funes:timeline:*']);
        }
    };

    $interpreter = new class($tracker) implements HistoricalInterpreter
    {
        public function __construct(private object $tracker)
        {
        }

        public function interpret(HistoricalQuestion $question, EvidenceSet $evidence): HistoricalAnswer
        {
            $this->tracker->interpreterCalled = true;

            return new HistoricalAnswer(
                facts: [],
                inferences: [],
                completeness: new CompletenessAssessment(false),
                uncertainty: new UncertaintyAssessment(),
                confidence: ConfidenceLevel::Low,
            );
        }
    };

    $questionService = new HistoricalQuestionService($retriever, $interpreter);
    $periodSummaryService = new PeriodSummaryService($questionService);
    $timelineService = new ProjectTimelineService($periodSummaryService);
    $result = $timelineService->build(new ProjectTimelineQuery(
        question: 'Build timeline',
        periodStart: new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
        periodEnd: new DateTimeImmutable('2026-08-31T23:59:59+00:00'),
    ));

    expect($result->built)->toBeFalse()
        ->and($result->refusalReason?->code)->toBe('insufficient_evidence')
        ->and($result->completeness->missingExpectedEvidence)->toBe(['funes:timeline:*'])
        ->and($result->uncertainty->missingExpectedEvidence)->toBe(['funes:timeline:*'])
        ->and($result->uncertainty->signals)->toContain('insufficient_evidence')
        ->and($tracker->interpreterCalled)->toBeFalse();
});
