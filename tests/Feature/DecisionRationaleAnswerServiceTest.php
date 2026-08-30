<?php

declare(strict_types=1);

use DateTimeImmutable;
use Sifrious\Kilgore\ChangeStory\ChangeStory;
use Sifrious\Kilgore\ChangeStory\DecisionCitation;
use Sifrious\Kilgore\DecisionRationaleAnswers\DecisionRationaleAnswerQuery;
use Sifrious\Kilgore\DecisionRationaleAnswers\DecisionRationaleAnswerService;
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
use Sifrious\Kilgore\PastDecisionContext\PastDecisionContextService;

it('answers why we chose this using recorded rationale, alternatives, and cited uncertainty', function (): void {
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
                    funesRef: 'funes:decision:900',
                    kind: EvidenceKind::DecisionCitation,
                    summary: 'Decision record',
                    attributes: [
                        'decision_title' => 'Choose event-sourced reconstruction',
                        'decision_source' => 'architecture-review',
                        'author_account_id' => 'acct_9',
                        'citation_label' => 'ADR-900',
                        'citation_locator' => 'funes://decision/900#rationale',
                        'citation_position' => 1,
                    ],
                ),
                new EvidenceItem(
                    funesRef: 'funes:alt:901',
                    kind: EvidenceKind::Comparison,
                    summary: 'Alternative design',
                ),
            ], ['funes:tradeoffs:*']);
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
                    new FactAssertion(
                        statement: 'Recorded rationale: event-sourcing preserves provenance.',
                        funesRefs: ['funes:decision:900'],
                    ),
                ],
                inferences: [
                    new InferenceAssertion(
                        statement: 'Later inference: this likely reduced reconciliation errors.',
                        supportingFunesRefs: ['funes:alt:901'],
                    ),
                ],
                completeness: new CompletenessAssessment(true, ['funes:decision:notes:*']),
                uncertainty: new UncertaintyAssessment(['funes:decision:notes:*'], ['notes_missing']),
                confidence: ConfidenceLevel::Medium,
                changeStory: new ChangeStory(
                    decisionCitations: [
                        new DecisionCitation(
                            decision: 'Choose event-sourced reconstruction',
                            funesRefs: ['funes:decision:900'],
                        ),
                    ],
                ),
            );
        }
    };

    $service = new DecisionRationaleAnswerService(
        new PastDecisionContextService(
            new HistoricalQuestionService($retriever, $interpreter),
        ),
    );

    $result = $service->answer(new DecisionRationaleAnswerQuery(
        decisionFunesRef: 'funes:decision:900',
        contextStart: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
        contextEnd: new DateTimeImmutable('2026-02-28T23:59:59+00:00'),
        corpusScope: 'engineering',
    ));

    expect($result->answered)->toBeTrue()
        ->and($tracker->events)->toBe(['retrieve', 'interpret'])
        ->and($result->answer)->not->toBeNull()
        ->and($result->answer?->decisionTitle)->toBe('Choose event-sourced reconstruction')
        ->and($result->answer?->recordedRationale)->toHaveCount(1)
        ->and($result->answer?->inferredRationale)->toHaveCount(1)
        ->and($result->answer?->alternatives)->toHaveCount(1)
        ->and($result->answer?->alternatives[0]->funesRefs)->toBe(['funes:alt:901'])
        ->and($result->answer?->citations)->toHaveCount(1)
        ->and($result->answer?->citations[0]->label)->toBe('ADR-900')
        ->and($result->uncertainty->missingExpectedEvidence)->toBe(['funes:decision:notes:*', 'funes:tradeoffs:*'])
        ->and($result->uncertainty->signals)->toContain('recorded_vs_inferred_distinct')
        ->and($result->uncertainty->signals)->toContain('decision_rationale_answered');
});

it('keeps uncertainty explicit when rationale and alternatives are incomplete', function (): void {
    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet([
                new EvidenceItem(
                    funesRef: 'funes:decision:incomplete',
                    kind: EvidenceKind::DecisionCitation,
                    summary: 'Decision shell',
                    attributes: [
                        'decision_title' => 'Incomplete decision',
                    ],
                ),
            ]);
        }
    };

    $interpreter = new class implements HistoricalInterpreter
    {
        public function interpret(HistoricalQuestion $question, EvidenceSet $evidence): HistoricalAnswer
        {
            return new HistoricalAnswer(
                facts: [],
                inferences: [
                    new InferenceAssertion('Likely chosen under delivery pressure.', ['funes:decision:incomplete']),
                ],
                completeness: new CompletenessAssessment(true),
                uncertainty: new UncertaintyAssessment(),
                confidence: ConfidenceLevel::Low,
            );
        }
    };

    $service = new DecisionRationaleAnswerService(
        new PastDecisionContextService(
            new HistoricalQuestionService($retriever, $interpreter),
        ),
    );

    $result = $service->answer(new DecisionRationaleAnswerQuery(
        decisionFunesRef: 'funes:decision:incomplete',
        contextStart: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        contextEnd: new DateTimeImmutable('2026-12-31T23:59:59+00:00'),
    ));

    expect($result->answered)->toBeTrue()
        ->and($result->answer?->recordedRationale)->toHaveCount(0)
        ->and($result->answer?->alternatives)->toHaveCount(0)
        ->and($result->completeness->missingExpectedEvidence)->toContain(
            'funes:decision_source:*',
            'funes:author_account_id:*',
            'funes:decision_rationale:*',
            'funes:decision_alternatives:*',
        )
        ->and($result->uncertainty->signals)->toContain(
            'decision_source_missing',
            'author_account_missing',
            'recorded_rationale_missing',
            'alternatives_missing',
        );
});

it('refuses when decision context cannot be reconstructed', function (): void {
    $tracker = new class
    {
        public bool $interpreterCalled = false;
    };

    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet([], ['funes:decision:rationale:*']);
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

    $service = new DecisionRationaleAnswerService(
        new PastDecisionContextService(
            new HistoricalQuestionService($retriever, $interpreter),
        ),
    );

    $result = $service->answer(new DecisionRationaleAnswerQuery(
        decisionFunesRef: 'funes:decision:none',
        contextStart: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        contextEnd: new DateTimeImmutable('2026-01-31T23:59:59+00:00'),
    ));

    expect($result->answered)->toBeFalse()
        ->and($result->refusalReason?->code)->toBe('insufficient_evidence')
        ->and($result->completeness->missingExpectedEvidence)->toBe(['funes:decision:rationale:*'])
        ->and($result->uncertainty->signals)->toContain('insufficient_evidence')
        ->and($tracker->interpreterCalled)->toBeFalse();
});
