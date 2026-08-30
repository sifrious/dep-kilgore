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
use Sifrious\Kilgore\PeriodSummaries\PeriodSummaryQuery;
use Sifrious\Kilgore\PeriodSummaries\PeriodSummaryService;

it('summarizes period activity into chronological cited groups', function (): void {
    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet([
                new EvidenceItem(
                    'funes:evt:1',
                    EvidenceKind::Other,
                    'Kickoff happened',
                    new DateTimeImmutable('2026-06-01T10:00:00+00:00'),
                ),
                new EvidenceItem(
                    'funes:evt:2',
                    EvidenceKind::DecisionCitation,
                    'Decision finalized',
                    new DateTimeImmutable('2026-06-02T16:00:00+00:00'),
                ),
                new EvidenceItem(
                    'funes:evt:3',
                    EvidenceKind::PlanSummary,
                    'Outside-period event',
                    new DateTimeImmutable('2026-05-15T16:00:00+00:00'),
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
                    new FactAssertion('Kickoff was completed.', ['funes:evt:1']),
                    new FactAssertion('Decision was approved.', ['funes:evt:2']),
                    new FactAssertion('Old event outside period.', ['funes:evt:3']),
                ],
                inferences: [
                    new InferenceAssertion('Approval likely unblocked implementation.', ['funes:evt:2']),
                ],
                completeness: new CompletenessAssessment(true),
                uncertainty: new UncertaintyAssessment(),
                confidence: ConfidenceLevel::Medium,
                claims: [
                    new HistoricalClaim(
                        statement: 'Work may accelerate next sprint.',
                        assertionType: ClaimAssertionType::Hypothesis,
                        kind: ResearchClaimKind::Implication,
                    ),
                ],
            );
        }
    };

    $questionService = new HistoricalQuestionService($retriever, $interpreter);
    $service = new PeriodSummaryService($questionService);
    $result = $service->summarize(new PeriodSummaryQuery(
        question: 'What happened during kickoff week?',
        periodStart: new DateTimeImmutable('2026-06-01T00:00:00+00:00'),
        periodEnd: new DateTimeImmutable('2026-06-03T00:00:00+00:00'),
        subject: 'kilgore',
    ));

    expect($result->summarized)->toBeTrue()
        ->and($result->summary)->not->toBeNull()
        ->and($result->summary?->groups)->toHaveCount(3)
        ->and($result->summary?->groups[0]->bucketLabel)->toBe('2026-06-01')
        ->and($result->summary?->groups[1]->bucketLabel)->toBe('2026-06-02')
        ->and($result->summary?->groups[2]->bucketLabel)->toBe('undated')
        ->and($result->summary?->groups[0]->observations)->toHaveCount(1)
        ->and($result->summary?->groups[1]->interpretations)->toHaveCount(1)
        ->and($result->summary?->groups[2]->interpretations)->toHaveCount(1)
        ->and($result->summary?->groups[0]->funesRefs)->toBe(['funes:evt:1'])
        ->and($result->summary?->groups[1]->funesRefs)->toBe(['funes:evt:2'])
        ->and($result->summary?->uncertainty->signals)->toContain('uncertain_event_time');
});

it('preserves refusal and visible uncertainty when evidence is insufficient', function (): void {
    $tracker = new class
    {
        public bool $interpreterCalled = false;
    };

    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet([], ['funes:decision:*', 'funes:timeline:*']);
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
    $service = new PeriodSummaryService($questionService);
    $result = $service->summarize(new PeriodSummaryQuery(
        question: 'Summarize this period',
        periodStart: new DateTimeImmutable('2026-07-01T00:00:00+00:00'),
        periodEnd: new DateTimeImmutable('2026-07-31T23:59:59+00:00'),
    ));

    expect($result->summarized)->toBeFalse()
        ->and($result->refusalReason?->code)->toBe('insufficient_evidence')
        ->and($result->completeness->missingExpectedEvidence)->toBe(['funes:decision:*', 'funes:timeline:*'])
        ->and($result->uncertainty->missingExpectedEvidence)->toBe(['funes:decision:*', 'funes:timeline:*'])
        ->and($result->uncertainty->signals)->toContain('insufficient_evidence')
        ->and($tracker->interpreterCalled)->toBeFalse();
});

it('keeps undated evidence visible with uncertainty signals', function (): void {
    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet([
                new EvidenceItem('funes:undated:1', EvidenceKind::Other, 'Undated event'),
            ]);
        }
    };

    $interpreter = new class implements HistoricalInterpreter
    {
        public function interpret(HistoricalQuestion $question, EvidenceSet $evidence): HistoricalAnswer
        {
            return new HistoricalAnswer(
                facts: [
                    new FactAssertion('Something happened, but timestamp is unknown.', ['funes:undated:1']),
                ],
                inferences: [],
                completeness: new CompletenessAssessment(true, ['funes:timestamp:*']),
                uncertainty: new UncertaintyAssessment(['funes:timestamp:*'], ['incomplete_timing']),
                confidence: ConfidenceLevel::Low,
            );
        }
    };

    $questionService = new HistoricalQuestionService($retriever, $interpreter);
    $service = new PeriodSummaryService($questionService);
    $result = $service->summarize(new PeriodSummaryQuery(
        question: 'What happened this month?',
        periodStart: new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
        periodEnd: new DateTimeImmutable('2026-08-31T23:59:59+00:00'),
    ));

    expect($result->summarized)->toBeTrue()
        ->and($result->summary?->groups)->toHaveCount(1)
        ->and($result->summary?->groups[0]->bucketLabel)->toBe('undated')
        ->and($result->uncertainty->missingExpectedEvidence)->toBe(['funes:timestamp:*'])
        ->and($result->uncertainty->signals)->toContain('incomplete_timing')
        ->and($result->uncertainty->signals)->toContain('missing_expected_history')
        ->and($result->uncertainty->signals)->toContain('uncertain_event_time');
});
