<?php

declare(strict_types=1);

use DateTimeImmutable;
use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\ConfidenceLevel;
use Sifrious\Kilgore\HistoricalQuestions\EvidenceItem;
use Sifrious\Kilgore\HistoricalQuestions\EvidenceKind;
use Sifrious\Kilgore\HistoricalQuestions\EvidenceSet;
use Sifrious\Kilgore\HistoricalQuestions\FactAssertion;
use Sifrious\Kilgore\HistoricalQuestions\FunesEvidenceRetriever;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalAnswer;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalInterpreter;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalQuestion;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalQuestionService;
use Sifrious\Kilgore\HistoricalQuestions\InferenceAssertion;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;
use Sifrious\Kilgore\PeriodSummaries\PeriodSummaryService;
use Sifrious\Kilgore\ProjectTimelines\ProjectTimelineService;
use Sifrious\Kilgore\StateComparison\CurrentPriorStateService;
use Sifrious\Kilgore\CurrentStateExplanation\CurrentProjectStateExplanationService;
use Sifrious\Kilgore\CurrentStateExplanation\CurrentProjectStateQuery;

it('explains current project state with cited observations, inferences, and contradictions', function (): void {
    $tracker = new class
    {
        /**
         * @var array<int, string>
         */
        public array $events = [];
    };

    $retriever = new class($tracker) implements FunesEvidenceRetriever
    {
        public function __construct(private object $tracker)
        {
        }

        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            $scope = $question->timeScope ?? 'none';
            $this->tracker->events[] = 'retrieve:'.$scope;

            if (str_starts_with($scope, 'as_of:2026-07-01')) {
                return new EvidenceSet([
                    new EvidenceItem('funes:baseline:1', EvidenceKind::Other, 'Baseline feature flag state'),
                ]);
            }

            if (str_starts_with($scope, 'as_of:2026-08-01')) {
                return new EvidenceSet([
                    new EvidenceItem('funes:current:1', EvidenceKind::Other, 'Current feature flag state'),
                ]);
            }

            return new EvidenceSet([
                new EvidenceItem(
                    'funes:timeline:1',
                    EvidenceKind::Other,
                    'Deployment completed',
                    new DateTimeImmutable('2026-07-15T10:00:00+00:00'),
                ),
                new EvidenceItem(
                    'funes:timeline:2',
                    EvidenceKind::DecisionCitation,
                    'Rollback decision',
                    new DateTimeImmutable('2026-07-20T11:00:00+00:00'),
                ),
            ]);
        }
    };

    $interpreter = new class($tracker) implements HistoricalInterpreter
    {
        public function __construct(private object $tracker)
        {
        }

        public function interpret(HistoricalQuestion $question, EvidenceSet $evidence): HistoricalAnswer
        {
            $scope = $question->timeScope ?? 'none';
            $this->tracker->events[] = 'interpret:'.$scope;

            if (str_starts_with($scope, 'as_of:2026-07-01')) {
                return new HistoricalAnswer(
                    facts: [
                        new FactAssertion('Feature flag checkout is enabled.', ['funes:baseline:1']),
                    ],
                    inferences: [
                        new InferenceAssertion('Enablement likely supported rollout.', ['funes:baseline:1']),
                    ],
                    completeness: new CompletenessAssessment(true),
                    uncertainty: new UncertaintyAssessment(),
                    confidence: ConfidenceLevel::Medium,
                );
            }

            if (str_starts_with($scope, 'as_of:2026-08-01')) {
                return new HistoricalAnswer(
                    facts: [
                        new FactAssertion('Feature flag checkout is disabled.', ['funes:current:1']),
                    ],
                    inferences: [
                        new InferenceAssertion('Disablement likely mitigated incidents.', ['funes:current:1']),
                    ],
                    completeness: new CompletenessAssessment(true, ['funes:incident:*']),
                    uncertainty: new UncertaintyAssessment(['funes:incident:*'], ['incident_gap']),
                    confidence: ConfidenceLevel::Low,
                );
            }

            return new HistoricalAnswer(
                facts: [
                    new FactAssertion('Deployment was completed.', ['funes:timeline:1']),
                ],
                inferences: [
                    new InferenceAssertion('Rollback decision likely influenced current state.', ['funes:timeline:2']),
                ],
                completeness: new CompletenessAssessment(true, ['funes:postmortem:*']),
                uncertainty: new UncertaintyAssessment(['funes:postmortem:*'], ['postmortem_missing']),
                confidence: ConfidenceLevel::Medium,
            );
        }
    };

    $questionService = new HistoricalQuestionService($retriever, $interpreter);
    $stateService = new CurrentPriorStateService($questionService);
    $timelineService = new ProjectTimelineService(new PeriodSummaryService($questionService));
    $service = new CurrentProjectStateExplanationService($stateService, $timelineService);

    $result = $service->explain(new CurrentProjectStateQuery(
        projectFunesRef: 'funes:project:kilgore',
        question: 'Why is checkout in its current state?',
        baselineAt: new DateTimeImmutable('2026-07-01T00:00:00+00:00'),
        currentAt: new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
        timelineStart: new DateTimeImmutable('2026-07-01T00:00:00+00:00'),
        timelineEnd: new DateTimeImmutable('2026-07-31T23:59:59+00:00'),
        corpusScope: 'engineering',
    ));

    expect($result->explained)->toBeTrue()
        ->and($result->explanation)->not->toBeNull()
        ->and($tracker->events)->toBe([
            'retrieve:as_of:2026-07-01T00:00:00+00:00',
            'interpret:as_of:2026-07-01T00:00:00+00:00',
            'retrieve:as_of:2026-08-01T00:00:00+00:00',
            'interpret:as_of:2026-08-01T00:00:00+00:00',
            'retrieve:2026-07-01T00:00:00+00:00..2026-07-31T23:59:59+00:00',
            'interpret:2026-07-01T00:00:00+00:00..2026-07-31T23:59:59+00:00',
        ])
        ->and($result->explanation?->observations)->toHaveCount(4)
        ->and($result->explanation?->inferences)->toHaveCount(3)
        ->and($result->explanation?->contradictions)->toHaveCount(1)
        ->and($result->explanation?->contradictions[0]->kind)->toBe('state_transition_conflict')
        ->and($result->explanation?->contradictions[0]->funesRefs)->toContain('funes:baseline:1', 'funes:current:1')
        ->and($result->uncertainty->missingExpectedEvidence)->toBe(['funes:incident:*', 'funes:postmortem:*'])
        ->and($result->uncertainty->signals)->toContain('causal_temporal_reasoning_applied')
        ->and($result->uncertainty->signals)->toContain('contradictions_present')
        ->and($result->uncertainty->signals)->toContain('timeline_interpretation_layered')
        ->and($result->uncertainty->signals)->toContain('explicit_temporal_baseline');
});

it('refuses explanation when state comparison cannot be built', function (): void {
    $tracker = new class
    {
        public bool $interpreterCalled = false;
    };

    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet([], ['funes:state:*']);
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
    $stateService = new CurrentPriorStateService($questionService);
    $timelineService = new ProjectTimelineService(new PeriodSummaryService($questionService));
    $service = new CurrentProjectStateExplanationService($stateService, $timelineService);

    $result = $service->explain(new CurrentProjectStateQuery(
        projectFunesRef: 'funes:project:blocked',
        question: 'Why is the state blocked?',
        baselineAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        currentAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
        timelineStart: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        timelineEnd: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
    ));

    expect($result->explained)->toBeFalse()
        ->and($result->refusalReason?->code)->toBe('insufficient_evidence')
        ->and($result->completeness->missingExpectedEvidence)->toBe(['funes:state:*'])
        ->and($result->uncertainty->missingExpectedEvidence)->toBe(['funes:state:*'])
        ->and($result->uncertainty->signals)->toContain('insufficient_evidence')
        ->and($tracker->interpreterCalled)->toBeFalse();
});
