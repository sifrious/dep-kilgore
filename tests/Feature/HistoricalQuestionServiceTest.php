<?php

declare(strict_types=1);

use DomainException;
use Sifrious\Kilgore\ChangeStory\ChangeStory;
use Sifrious\Kilgore\ChangeStory\Comparison;
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
use Sifrious\Kilgore\HistoricalQuestions\HistoricalInterpreter;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalClaim;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalQuestion;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalQuestionService;
use Sifrious\Kilgore\HistoricalQuestions\InferenceAssertion;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;

it('retrieves evidence before interpretation', function (): void {
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
                new EvidenceItem('funes:event:001', EvidenceKind::Other, 'Found a historical event'),
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
                        statement: 'A historical event occurred.',
                        funesRefs: ['funes:event:001'],
                    ),
                ],
                inferences: [],
                completeness: new CompletenessAssessment(true),
                uncertainty: new UncertaintyAssessment(),
                confidence: ConfidenceLevel::High,
            );
        }
    };

    $service = new HistoricalQuestionService($retriever, $interpreter);
    $result = $service->ask(new HistoricalQuestion('What happened?'));

    expect($result->answered)->toBeTrue()
        ->and($tracker->events)->toBe(['retrieve', 'interpret']);
});

it('refuses interpretation when evidence is insufficient', function (): void {
    $tracker = new class
    {
        public bool $interpreterCalled = false;
    };

    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet(
                items: [],
                missingExpectedEvidence: ['funes:decision:*', 'funes:plan:*'],
            );
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
                completeness: new CompletenessAssessment(false, ['unused']),
                uncertainty: new UncertaintyAssessment(['unused']),
                confidence: ConfidenceLevel::Low,
            );
        }
    };

    $service = new HistoricalQuestionService($retriever, $interpreter);
    $result = $service->ask(new HistoricalQuestion('Why was this decision made?'));

    expect($result->answered)->toBeFalse()
        ->and($result->refusalReason?->code)->toBe('insufficient_evidence')
        ->and($result->completeness->missingExpectedEvidence)->toBe(['funes:decision:*', 'funes:plan:*'])
        ->and($result->uncertainty->missingExpectedEvidence)->toBe(['funes:decision:*', 'funes:plan:*'])
        ->and($result->uncertainty->signals)->toContain('insufficient_evidence')
        ->and($tracker->interpreterCalled)->toBeFalse();
});

it('returns a typed traceable historical answer with distinct inference', function (): void {
    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet([
                new EvidenceItem(
                    'funes:comparison:001',
                    EvidenceKind::Comparison,
                    'Before and after implementation diff.',
                ),
                new EvidenceItem(
                    'funes:decision:009',
                    EvidenceKind::DecisionCitation,
                    'Decision note from architecture review.',
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
                    new FactAssertion(
                        statement: 'The team approved retrieval-first interpretation.',
                        funesRefs: ['funes:decision:009'],
                    ),
                    new FactAssertion(
                        statement: 'A before/after comparison exists for this slice.',
                        funesRefs: ['funes:comparison:001'],
                    ),
                ],
                inferences: [
                    new InferenceAssertion(
                        statement: 'This likely reduces uncited answers in follow-up tasks.',
                        supportingFunesRefs: ['funes:decision:009'],
                    ),
                ],
                completeness: new CompletenessAssessment(
                    hasSufficientEvidence: true,
                    missingExpectedEvidence: [],
                ),
                uncertainty: new UncertaintyAssessment(
                    missingExpectedEvidence: [],
                    signals: ['inference_present'],
                ),
                confidence: ConfidenceLevel::Medium,
                claims: [
                    new HistoricalClaim(
                        statement: 'Retrieval-first interpretation is adopted.',
                        assertionType: ClaimAssertionType::Fact,
                        kind: ResearchClaimKind::Fact,
                        funesRefs: ['funes:decision:009'],
                    ),
                    new HistoricalClaim(
                        statement: 'This approach may lower recency bias.',
                        assertionType: ClaimAssertionType::Hypothesis,
                        kind: ResearchClaimKind::Implication,
                    ),
                ],
                changeStory: new ChangeStory(
                    comparisons: [
                        new Comparison(
                            comparisonLabel: 'approved-approach',
                            labelId: 'label_approved',
                            funesRefs: ['funes:comparison:001'],
                        ),
                    ],
                ),
            );
        }
    };

    $service = new HistoricalQuestionService($retriever, $interpreter);
    $result = $service->ask(new HistoricalQuestion(
        text: 'How did we decide this?',
        subject: 'kilgore',
        timeScope: 'last-quarter',
        corpusScope: 'engineering',
    ));

    expect($result->answered)->toBeTrue()
        ->and($result->answer)->not->toBeNull()
        ->and($result->answer?->facts)->toHaveCount(2)
        ->and($result->answer?->inferences)->toHaveCount(1)
        ->and($result->answer?->claims)->toHaveCount(2)
        ->and($result->answer?->claims[0]->assertionType)->toBe(ClaimAssertionType::Fact)
        ->and($result->answer?->claims[0]->kind)->toBe(ResearchClaimKind::Fact)
        ->and($result->answer?->claims[1]->assertionType)->toBe(ClaimAssertionType::Hypothesis)
        ->and($result->answer?->claims[1]->kind)->toBe(ResearchClaimKind::Implication)
        ->and($result->answer?->facts[0]->funesRefs)->toBe(['funes:decision:009'])
        ->and($result->answer?->changeStory?->comparisons[0]->labelId)->toBe('label_approved')
        ->and($result->completeness->missingExpectedEvidence)->toBe(['funes:plan:*'])
        ->and($result->uncertainty->missingExpectedEvidence)->toBe(['funes:plan:*'])
        ->and($result->uncertainty->signals)->toContain('missing_expected_history');
});

it('rejects uncited fact claims but allows uncited hypotheses', function (): void {
    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet([
                new EvidenceItem('funes:claim:100', EvidenceKind::ResearchClaimSource, 'A cited claim source'),
            ]);
        }
    };

    $interpreter = new class implements HistoricalInterpreter
    {
        public function interpret(HistoricalQuestion $question, EvidenceSet $evidence): HistoricalAnswer
        {
            return new HistoricalAnswer(
                facts: [],
                inferences: [],
                completeness: new CompletenessAssessment(true),
                uncertainty: new UncertaintyAssessment(),
                confidence: ConfidenceLevel::Low,
                claims: [
                    new HistoricalClaim(
                        statement: 'Uncited fact should fail.',
                        assertionType: ClaimAssertionType::Fact,
                        kind: ResearchClaimKind::Fact,
                    ),
                    new HistoricalClaim(
                        statement: 'Uncited hypothesis is allowed.',
                        assertionType: ClaimAssertionType::Hypothesis,
                        kind: ResearchClaimKind::Synthesis,
                    ),
                ],
            );
        }
    };

    $service = new HistoricalQuestionService($retriever, $interpreter);

    expect(fn () => $service->ask(new HistoricalQuestion('Is this grounded?')))
        ->toThrow(DomainException::class, 'Fact claims must cite at least one Funes ref.');
});
