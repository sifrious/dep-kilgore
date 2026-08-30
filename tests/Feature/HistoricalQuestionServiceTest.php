<?php

declare(strict_types=1);

use Sifrious\Kilgore\ChangeStory\ChangeStory;
use Sifrious\Kilgore\ChangeStory\Comparison;
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

it('retrieves evidence before interpretation', function (): void {
    $events = [];

    $retriever = new class($events) implements FunesEvidenceRetriever
    {
        /**
         * @param array<int, string> $events
         */
        public function __construct(private array &$events)
        {
        }

        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            $this->events[] = 'retrieve';

            return new EvidenceSet([
                new EvidenceItem('funes:event:001', EvidenceKind::Other, 'Found a historical event'),
            ]);
        }
    };

    $interpreter = new class($events) implements HistoricalInterpreter
    {
        /**
         * @param array<int, string> $events
         */
        public function __construct(private array &$events)
        {
        }

        public function interpret(HistoricalQuestion $question, EvidenceSet $evidence): HistoricalAnswer
        {
            $this->events[] = 'interpret';

            return new HistoricalAnswer(
                facts: [
                    new FactAssertion(
                        statement: 'A historical event occurred.',
                        funesRefs: ['funes:event:001'],
                    ),
                ],
                inferences: [],
                completeness: new CompletenessAssessment(true),
                confidence: ConfidenceLevel::High,
            );
        }
    };

    $service = new HistoricalQuestionService($retriever, $interpreter);
    $result = $service->ask(new HistoricalQuestion('What happened?'));

    expect($result->answered)->toBeTrue()
        ->and($events)->toBe(['retrieve', 'interpret']);
});

it('refuses interpretation when evidence is insufficient', function (): void {
    $interpreterCalled = false;

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

    $interpreter = new class($interpreterCalled) implements HistoricalInterpreter
    {
        public function __construct(private bool &$interpreterCalled)
        {
        }

        public function interpret(HistoricalQuestion $question, EvidenceSet $evidence): HistoricalAnswer
        {
            $this->interpreterCalled = true;

            return new HistoricalAnswer(
                facts: [],
                inferences: [],
                completeness: new CompletenessAssessment(false, ['unused']),
                confidence: ConfidenceLevel::Low,
            );
        }
    };

    $service = new HistoricalQuestionService($retriever, $interpreter);
    $result = $service->ask(new HistoricalQuestion('Why was this decision made?'));

    expect($result->answered)->toBeFalse()
        ->and($result->refusalReason?->code)->toBe('insufficient_evidence')
        ->and($result->completeness->missingExpectedEvidence)->toBe(['funes:decision:*', 'funes:plan:*'])
        ->and($interpreterCalled)->toBeFalse();
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
                    missingExpectedEvidence: ['funes:plan:*'],
                ),
                confidence: ConfidenceLevel::Medium,
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
        ->and($result->answer?->facts[0]->funesRefs)->toBe(['funes:decision:009'])
        ->and($result->answer?->changeStory?->comparisons[0]->labelId)->toBe('label_approved')
        ->and($result->completeness->missingExpectedEvidence)->toBe(['funes:plan:*']);
});
