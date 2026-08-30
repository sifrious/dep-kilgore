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
use Sifrious\Kilgore\StateComparison\ChangeClassification;
use Sifrious\Kilgore\StateComparison\CurrentPriorStateQuery;
use Sifrious\Kilgore\StateComparison\CurrentPriorStateService;

it('compares current versus prior state using explicit temporal baselines', function (): void {
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
            $this->tracker->events[] = sprintf('retrieve:%s', $question->timeScope ?? 'none');

            if (str_contains($question->timeScope ?? '', '2026-06-01')) {
                return new EvidenceSet([
                    new EvidenceItem('funes:baseline:1', EvidenceKind::Other, 'Baseline server state'),
                ]);
            }

            return new EvidenceSet([
                new EvidenceItem('funes:current:1', EvidenceKind::Other, 'Current server state'),
                new EvidenceItem('funes:current:2', EvidenceKind::DecisionCitation, 'Current decision context'),
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
            $this->tracker->events[] = sprintf('interpret:%s', $question->timeScope ?? 'none');

            if (str_contains($question->timeScope ?? '', '2026-06-01')) {
                return new HistoricalAnswer(
                    facts: [
                        new FactAssertion('API endpoint /v1 existed.', ['funes:baseline:1']),
                        new FactAssertion('Worker queue was disabled.', ['funes:baseline:1']),
                    ],
                    inferences: [
                        new InferenceAssertion('Scale limits were likely intentional.', ['funes:baseline:1']),
                    ],
                    completeness: new CompletenessAssessment(true),
                    uncertainty: new UncertaintyAssessment(),
                    confidence: ConfidenceLevel::Medium,
                );
            }

            return new HistoricalAnswer(
                facts: [
                    new FactAssertion('API endpoint /v1 existed.', ['funes:current:1']),
                    new FactAssertion('Worker queue is enabled.', ['funes:current:2']),
                ],
                inferences: [
                    new InferenceAssertion('Scale limits were likely intentional.', ['funes:current:2']),
                    new InferenceAssertion('Queue enablement likely reduced latency.', ['funes:current:2']),
                ],
                completeness: new CompletenessAssessment(true, ['funes:latency:*']),
                uncertainty: new UncertaintyAssessment(['funes:latency:*'], ['needs_latency_validation']),
                confidence: ConfidenceLevel::High,
            );
        }
    };

    $historicalQuestionService = new HistoricalQuestionService($retriever, $interpreter);
    $service = new CurrentPriorStateService($historicalQuestionService);
    $result = $service->compare(new CurrentPriorStateQuery(
        question: 'What changed in system state?',
        baselineAt: new DateTimeImmutable('2026-06-01T00:00:00+00:00'),
        currentAt: new DateTimeImmutable('2026-06-15T00:00:00+00:00'),
        subject: 'kilgore',
        corpusScope: 'engineering',
    ));

    expect($result->compared)->toBeTrue()
        ->and($result->answer)->not->toBeNull()
        ->and($tracker->events)->toBe([
            'retrieve:as_of:2026-06-01T00:00:00+00:00',
            'interpret:as_of:2026-06-01T00:00:00+00:00',
            'retrieve:as_of:2026-06-15T00:00:00+00:00',
            'interpret:as_of:2026-06-15T00:00:00+00:00',
        ])
        ->and($result->answer?->observations)->toHaveCount(3)
        ->and($result->answer?->observations[0]->classification)->toBe(ChangeClassification::Persisted)
        ->and($result->answer?->observations[1]->classification)->toBe(ChangeClassification::Added)
        ->and($result->answer?->observations[2]->classification)->toBe(ChangeClassification::Removed)
        ->and($result->answer?->interpretations)->toHaveCount(2)
        ->and($result->answer?->changeStory->comparisons[0]->comparisonLabel)->toBe('current-vs-prior-state')
        ->and($result->answer?->changeStory->comparisons[0]->funesRefs)->toContain('funes:baseline:1', 'funes:current:2')
        ->and($result->uncertainty->signals)->toContain('explicit_temporal_baseline')
        ->and($result->completeness->missingExpectedEvidence)->toBe(['funes:latency:*']);
});

it('refuses comparison when baseline evidence is insufficient', function (): void {
    $tracker = new class
    {
        public bool $interpreterCalled = false;
    };

    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet([], ['funes:baseline:*']);
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

    $historicalQuestionService = new HistoricalQuestionService($retriever, $interpreter);
    $service = new CurrentPriorStateService($historicalQuestionService);
    $result = $service->compare(new CurrentPriorStateQuery(
        question: 'Compare prior and current state',
        baselineAt: new DateTimeImmutable('2026-06-01T00:00:00+00:00'),
        currentAt: new DateTimeImmutable('2026-06-15T00:00:00+00:00'),
    ));

    expect($result->compared)->toBeFalse()
        ->and($result->refusalReason?->code)->toBe('insufficient_evidence')
        ->and($result->completeness->missingExpectedEvidence)->toBe(['funes:baseline:*'])
        ->and($result->uncertainty->missingExpectedEvidence)->toBe(['funes:baseline:*'])
        ->and($result->uncertainty->signals)->toContain('insufficient_evidence')
        ->and($tracker->interpreterCalled)->toBeFalse();
});
