<?php

declare(strict_types=1);

use DateTimeImmutable;
use Sifrious\Kilgore\EntityTimelines\EntityRelationship;
use Sifrious\Kilgore\EntityTimelines\EntityRelationshipTraversal;
use Sifrious\Kilgore\EntityTimelines\EntityTimelineQuery;
use Sifrious\Kilgore\EntityTimelines\EntityTimelineService;
use Sifrious\Kilgore\EntityTimelines\EntityTraversalResult;
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

it('builds an entity timeline across traversed relationships with provenance', function (): void {
    $tracker = new class
    {
        /**
         * @var array<int, string>
         */
        public array $events = [];
    };

    $traversal = new class($tracker) implements EntityRelationshipTraversal
    {
        public function __construct(private object $tracker)
        {
        }

        public function traverse(EntityTimelineQuery $query): EntityTraversalResult
        {
            $this->tracker->events[] = sprintf('traverse:%s', $query->entityFunesRef);

            return new EntityTraversalResult(
                entityFunesRefs: ['funes:entity:service', 'funes:entity:queue'],
                relationships: [
                    new EntityRelationship(
                        fromEntityFunesRef: 'funes:entity:project',
                        toEntityFunesRef: 'funes:entity:service',
                        relationshipType: 'depends_on',
                        funesRefs: ['funes:rel:001'],
                    ),
                    new EntityRelationship(
                        fromEntityFunesRef: 'funes:entity:service',
                        toEntityFunesRef: 'funes:entity:queue',
                        relationshipType: 'emits_to',
                        funesRefs: ['funes:rel:002'],
                    ),
                ],
                missingExpectedEvidence: ['funes:rel:latency:*'],
            );
        }
    };

    $retriever = new class($tracker) implements FunesEvidenceRetriever
    {
        public function __construct(private object $tracker)
        {
        }

        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            $this->tracker->events[] = sprintf('retrieve:%s|%s', $question->subject ?? 'none', $question->corpusScope ?? 'none');

            return new EvidenceSet([
                new EvidenceItem(
                    'funes:entity:event:1',
                    EvidenceKind::Other,
                    'Entity deployment completed',
                    new DateTimeImmutable('2026-08-10T10:00:00+00:00'),
                ),
                new EvidenceItem(
                    'funes:entity:event:2',
                    EvidenceKind::DecisionCitation,
                    'Queue integration completed',
                    new DateTimeImmutable('2026-08-11T14:00:00+00:00'),
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
                    new FactAssertion('Service deployment completed.', ['funes:entity:event:1']),
                    new FactAssertion('Queue integration completed.', ['funes:entity:event:2']),
                ],
                inferences: [
                    new InferenceAssertion('Integration likely reduced retry rates.', ['funes:entity:event:2']),
                ],
                completeness: new CompletenessAssessment(true, ['funes:ops:retry:*']),
                uncertainty: new UncertaintyAssessment(['funes:ops:retry:*'], ['ops_data_gap']),
                confidence: ConfidenceLevel::Medium,
            );
        }
    };

    $questionService = new HistoricalQuestionService($retriever, $interpreter);
    $periodSummaryService = new PeriodSummaryService($questionService);
    $projectTimelineService = new ProjectTimelineService($periodSummaryService);
    $service = new EntityTimelineService($traversal, $projectTimelineService);
    $result = $service->build(new EntityTimelineQuery(
        entityFunesRef: 'funes:entity:project',
        question: 'How did this entity evolve?',
        periodStart: new DateTimeImmutable('2026-08-10T00:00:00+00:00'),
        periodEnd: new DateTimeImmutable('2026-08-12T00:00:00+00:00'),
        relationshipDepth: 1,
        corpusScope: 'engineering',
    ));

    expect($result->built)->toBeTrue()
        ->and($result->entityTimeline)->not->toBeNull()
        ->and($tracker->events[0])->toBe('traverse:funes:entity:project')
        ->and($tracker->events[1])->toContain('retrieve:funes:entity:project|engineering|entity_scope:funes:entity:project,funes:entity:service,funes:entity:queue')
        ->and($tracker->events[2])->toBe('interpret')
        ->and($result->entityTimeline?->traversal->relationships)->toHaveCount(2)
        ->and($result->entityTimeline?->traversal->relationships[0]->funesRefs)->toBe(['funes:rel:001'])
        ->and($result->entityTimeline?->timeline->groups)->toHaveCount(2)
        ->and($result->entityTimeline?->timeline->groups[0]->bucketLabel)->toBe('2026-08-10')
        ->and($result->entityTimeline?->timeline->groups[1]->bucketLabel)->toBe('2026-08-11')
        ->and($result->uncertainty->missingExpectedEvidence)->toBe(['funes:ops:retry:*', 'funes:rel:latency:*'])
        ->and($result->uncertainty->signals)->toContain('entity_relationship_traversed')
        ->and($result->uncertainty->signals)->toContain('timeline_interpretation_layered')
        ->and($result->uncertainty->signals)->toContain('missing_expected_history');
});

it('keeps entity relationship uncertainty visible when no edges are found', function (): void {
    $traversal = new class implements EntityRelationshipTraversal
    {
        public function traverse(EntityTimelineQuery $query): EntityTraversalResult
        {
            return new EntityTraversalResult(
                entityFunesRefs: [],
                relationships: [],
                missingExpectedEvidence: ['funes:relationships:*'],
            );
        }
    };

    $retriever = new class implements FunesEvidenceRetriever
    {
        public function retrieve(HistoricalQuestion $question): EvidenceSet
        {
            return new EvidenceSet([
                new EvidenceItem(
                    'funes:entity:event:3',
                    EvidenceKind::Other,
                    'Entity exists but has sparse graph',
                    new DateTimeImmutable('2026-09-01T09:00:00+00:00'),
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
                    new FactAssertion('Entity graph is sparse.', ['funes:entity:event:3']),
                ],
                inferences: [],
                completeness: new CompletenessAssessment(true),
                uncertainty: new UncertaintyAssessment(),
                confidence: ConfidenceLevel::Low,
            );
        }
    };

    $questionService = new HistoricalQuestionService($retriever, $interpreter);
    $periodSummaryService = new PeriodSummaryService($questionService);
    $projectTimelineService = new ProjectTimelineService($periodSummaryService);
    $service = new EntityTimelineService($traversal, $projectTimelineService);
    $result = $service->build(new EntityTimelineQuery(
        entityFunesRef: 'funes:entity:solo',
        question: 'What happened to this entity?',
        periodStart: new DateTimeImmutable('2026-09-01T00:00:00+00:00'),
        periodEnd: new DateTimeImmutable('2026-09-02T00:00:00+00:00'),
    ));

    expect($result->built)->toBeTrue()
        ->and($result->entityTimeline?->traversal->relationships)->toHaveCount(0)
        ->and($result->uncertainty->missingExpectedEvidence)->toBe(['funes:relationships:*'])
        ->and($result->uncertainty->signals)->toContain('entity_relationship_traversed')
        ->and($result->uncertainty->signals)->toContain('no_relationship_edges');
});
