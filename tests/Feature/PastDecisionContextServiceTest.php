<?php

declare(strict_types=1);

use DateTimeImmutable;
use Sifrious\Kilgore\ChangeStory\ChangeStory;
use Sifrious\Kilgore\ChangeStory\DecisionCitation;
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
use Sifrious\Kilgore\PastDecisionContext\PastDecisionContextQuery;
use Sifrious\Kilgore\PastDecisionContext\PastDecisionContextService;

it('reconstructs past decision context with recorded rationale separated from inference', function (): void {
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
                    funesRef: 'funes:decision:777',
                    kind: EvidenceKind::DecisionCitation,
                    summary: 'Decision record',
                    occurredAt: new DateTimeImmutable('2026-03-12T10:00:00+00:00'),
                    attributes: [
                        'decision_title' => 'Adopt retrieval-first interpretation',
                        'decision_source' => 'architecture_review',
                        'author_account_id' => 'acct_42',
                        'stack_id' => 'stack:kilgore-core',
                        'citation_label' => 'ADR-42',
                        'citation_locator' => 'funes://decision/777#rationale',
                        'citation_position' => 1,
                    ],
                ),
                new EvidenceItem(
                    funesRef: 'funes:stack:991',
                    kind: EvidenceKind::Comparison,
                    summary: 'Linked implementation stack',
                    attributes: [
                        'stack_id' => 'stack:api',
                    ],
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
            $this->tracker->events[] = 'interpret';

            return new HistoricalAnswer(
                facts: [
                    new FactAssertion(
                        statement: 'Recorded rationale: retrieval reduces unsupported conclusions.',
                        funesRefs: ['funes:decision:777'],
                    ),
                ],
                inferences: [
                    new InferenceAssertion(
                        statement: 'Later inference: adoption likely improved context handoffs.',
                        supportingFunesRefs: ['funes:stack:991'],
                    ),
                ],
                completeness: new CompletenessAssessment(true, ['funes:decision:minutes:*']),
                uncertainty: new UncertaintyAssessment(['funes:decision:minutes:*'], ['minutes_missing']),
                confidence: ConfidenceLevel::Medium,
                changeStory: new ChangeStory(
                    decisionCitations: [
                        new DecisionCitation(
                            decision: 'Adopt retrieval-first interpretation',
                            funesRefs: ['funes:decision:777'],
                        ),
                    ],
                ),
            );
        }
    };

    $service = new PastDecisionContextService(new HistoricalQuestionService($retriever, $interpreter));
    $result = $service->reconstruct(new PastDecisionContextQuery(
        decisionFunesRef: 'funes:decision:777',
        question: 'What was the context for this decision?',
        contextStart: new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
        contextEnd: new DateTimeImmutable('2026-03-31T23:59:59+00:00'),
        corpusScope: 'engineering',
    ));

    expect($result->reconstructed)->toBeTrue()
        ->and($tracker->events)->toBe(['retrieve', 'interpret'])
        ->and($result->context)->not->toBeNull()
        ->and($result->context?->decisionTitle)->toBe('Adopt retrieval-first interpretation')
        ->and($result->context?->decisionSource)->toBe('architecture_review')
        ->and($result->context?->authorAccountId)->toBe('acct_42')
        ->and($result->context?->linkedIdentities)->toHaveCount(2)
        ->and($result->context?->linkedIdentities[0]->funesRef)->toBe('funes:decision:777')
        ->and($result->context?->citationEdges)->toHaveCount(1)
        ->and($result->context?->citationEdges[0]->label)->toBe('ADR-42')
        ->and($result->context?->citationEdges[0]->locator)->toBe('funes://decision/777#rationale')
        ->and($result->context?->citationEdges[0]->position)->toBe(1)
        ->and($result->context?->citationEdges[0]->funesRef)->toBe('funes:decision:777')
        ->and($result->context?->recordedRationales)->toHaveCount(1)
        ->and($result->context?->inferredRationales)->toHaveCount(1)
        ->and($result->context?->recordedRationales[0]->funesRefs)->toBe(['funes:decision:777'])
        ->and($result->context?->inferredRationales[0]->supportingFunesRefs)->toBe(['funes:stack:991'])
        ->and($result->uncertainty->missingExpectedEvidence)->toBe(['funes:decision:minutes:*'])
        ->and($result->uncertainty->signals)->toContain('recorded_vs_inferred_distinct')
        ->and($result->context?->changeStory?->decisionCitations)->toHaveCount(1);
});

it('surfaces missing decision metadata as uncertainty without losing citations', function (): void {
    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet([
                new EvidenceItem(
                    funesRef: 'funes:decision:meta-missing',
                    kind: EvidenceKind::DecisionCitation,
                    summary: 'Decision evidence without source/author',
                    attributes: [
                        'decision_title' => 'Fallback decision title',
                        'citation_label' => 'ADR-unknown',
                        'citation_locator' => 'funes://decision/meta-missing',
                        'citation_position' => 2,
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
                facts: [
                    new FactAssertion('Recorded rationale exists.', ['funes:decision:meta-missing']),
                ],
                inferences: [],
                completeness: new CompletenessAssessment(true),
                uncertainty: new UncertaintyAssessment(),
                confidence: ConfidenceLevel::Low,
            );
        }
    };

    $service = new PastDecisionContextService(new HistoricalQuestionService($retriever, $interpreter));
    $result = $service->reconstruct(new PastDecisionContextQuery(
        decisionFunesRef: 'funes:decision:meta-missing',
        question: 'Reconstruct context',
        contextStart: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        contextEnd: new DateTimeImmutable('2026-12-31T23:59:59+00:00'),
    ));

    expect($result->reconstructed)->toBeTrue()
        ->and($result->context?->decisionSource)->toBe('unknown_source')
        ->and($result->context?->authorAccountId)->toBe('unknown_account')
        ->and($result->context?->citationEdges)->toHaveCount(1)
        ->and($result->completeness->missingExpectedEvidence)->toContain('funes:decision_source:*', 'funes:author_account_id:*')
        ->and($result->uncertainty->signals)->toContain('decision_source_missing', 'author_account_missing');
});

it('refuses reconstruction when evidence is insufficient', function (): void {
    $tracker = new class
    {
        public bool $interpreterCalled = false;
    };

    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet([], ['funes:decision:*']);
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

    $service = new PastDecisionContextService(new HistoricalQuestionService($retriever, $interpreter));
    $result = $service->reconstruct(new PastDecisionContextQuery(
        decisionFunesRef: 'funes:decision:missing',
        question: 'Reconstruct context',
        contextStart: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        contextEnd: new DateTimeImmutable('2026-01-31T23:59:59+00:00'),
    ));

    expect($result->reconstructed)->toBeFalse()
        ->and($result->refusalReason?->code)->toBe('insufficient_evidence')
        ->and($result->completeness->missingExpectedEvidence)->toBe(['funes:decision:*'])
        ->and($result->uncertainty->missingExpectedEvidence)->toBe(['funes:decision:*'])
        ->and($result->uncertainty->signals)->toContain('insufficient_evidence')
        ->and($tracker->interpreterCalled)->toBeFalse();
});
