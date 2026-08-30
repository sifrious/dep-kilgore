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
use Sifrious\Kilgore\LeftOffContext\LeftOffContextQuery;
use Sifrious\Kilgore\LeftOffContext\LeftOffContextService;
use Sifrious\Kilgore\PeriodSummaries\PeriodSummaryService;
use Sifrious\Kilgore\ProjectTimelines\ProjectTimelineService;

it('reconstructs what was happening at the last confirmed work boundary', function (): void {
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
            $this->tracker->events[] = 'retrieve';

            return new EvidenceSet([
                new EvidenceItem(
                    'funes:leftoff:1',
                    EvidenceKind::Other,
                    'Started migration',
                    new DateTimeImmutable('2026-10-01T09:00:00+00:00'),
                ),
                new EvidenceItem(
                    'funes:leftoff:2',
                    EvidenceKind::Other,
                    'Completed migration script',
                    new DateTimeImmutable('2026-10-02T10:00:00+00:00'),
                ),
                new EvidenceItem(
                    'funes:leftoff:3',
                    EvidenceKind::DecisionCitation,
                    'Open question after migration',
                    new DateTimeImmutable('2026-10-02T11:00:00+00:00'),
                ),
            ], ['funes:qa:validation:*']);
        }
    };

    $interpreter = new class($tracker) implements HistoricalInterpreter
    {
        public function __construct(private object $tracker)
        {
        }

        public function interpret(HistoricalQuestion $question, EvidenceSet $evidence): HistoricalAnswer
        {
            $this->tracker->events[] = 'interpret';

            return new HistoricalAnswer(
                facts: [
                    new FactAssertion('Migration started.', ['funes:leftoff:1']),
                    new FactAssertion('Migration script completed.', ['funes:leftoff:2']),
                ],
                inferences: [
                    new InferenceAssertion('Validation likely remained unfinished.', ['funes:leftoff:3']),
                ],
                completeness: new CompletenessAssessment(true),
                uncertainty: new UncertaintyAssessment([], ['evidence_ranked']),
                confidence: ConfidenceLevel::Medium,
            );
        }
    };

    $service = new LeftOffContextService(new ProjectTimelineService(
        new PeriodSummaryService(
            new HistoricalQuestionService($retriever, $interpreter),
        ),
    ));

    $result = $service->answer(new LeftOffContextQuery(
        projectFunesRef: 'funes:project:kilgore',
        question: 'What was I doing when I left off?',
        historyStart: new DateTimeImmutable('2026-10-01T00:00:00+00:00'),
        historyEnd: new DateTimeImmutable('2026-10-03T00:00:00+00:00'),
        corpusScope: 'engineering',
    ));

    expect($result->answered)->toBeTrue()
        ->and($tracker->events)->toBe(['retrieve', 'interpret'])
        ->and($result->context)->not->toBeNull()
        ->and($result->context?->lastConfirmedEpisode->boundaryLabel)->toBe('2026-10-02')
        ->and($result->context?->lastConfirmedEpisode->observations)->toHaveCount(1)
        ->and($result->context?->lastConfirmedEpisode->inferences)->toHaveCount(1)
        ->and($result->context?->lastConfirmedEpisode->observations[0]->funesRefs)->toBe(['funes:leftoff:2'])
        ->and($result->context?->lastConfirmedEpisode->inferences[0]->supportingFunesRefs)->toBe(['funes:leftoff:3'])
        ->and($result->context?->rankedEvidence)->toHaveCount(3)
        ->and($result->context?->rankedEvidence[0]->statement)->toBe('Migration script completed.')
        ->and($result->context?->unresolvedContext)->toHaveCount(2)
        ->and($result->context?->unresolvedContext[0]->kind)->toBe('inference')
        ->and($result->context?->unresolvedContext[1]->kind)->toBe('missing_evidence')
        ->and($result->uncertainty->signals)->toContain('last_work_boundary_detected')
        ->and($result->uncertainty->signals)->toContain('unresolved_context_present')
        ->and($result->uncertainty->signals)->toContain('timeline_interpretation_layered')
        ->and($result->uncertainty->signals)->toContain('missing_expected_history')
        ->and($result->completeness->missingExpectedEvidence)->toBe(['funes:qa:validation:*']);
});

it('refuses when bounded history has insufficient evidence', function (): void {
    $tracker = new class
    {
        public bool $interpreterCalled = false;
    };

    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet([], ['funes:leftoff:*']);
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

    $service = new LeftOffContextService(new ProjectTimelineService(
        new PeriodSummaryService(
            new HistoricalQuestionService($retriever, $interpreter),
        ),
    ));

    $result = $service->answer(new LeftOffContextQuery(
        projectFunesRef: 'funes:project:empty',
        question: 'What was I doing when I left off?',
        historyStart: new DateTimeImmutable('2026-10-01T00:00:00+00:00'),
        historyEnd: new DateTimeImmutable('2026-10-03T00:00:00+00:00'),
    ));

    expect($result->answered)->toBeFalse()
        ->and($result->refusalReason?->code)->toBe('insufficient_evidence')
        ->and($result->completeness->missingExpectedEvidence)->toBe(['funes:leftoff:*'])
        ->and($result->uncertainty->signals)->toContain('insufficient_evidence')
        ->and($tracker->interpreterCalled)->toBeFalse();
});
