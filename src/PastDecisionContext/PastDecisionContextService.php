<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\PastDecisionContext;

use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalQuestion;
use Sifrious\Kilgore\HistoricalQuestions\HistoricalQuestionService;
use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;

final class PastDecisionContextService
{
    public function __construct(
        private readonly HistoricalQuestionService $historicalQuestionService,
    ) {
    }

    public function reconstruct(PastDecisionContextQuery $query): PastDecisionContextResult
    {
        $package = $this->historicalQuestionService->askWithEvidence(new HistoricalQuestion(
            text: $query->question,
            subject: $query->decisionFunesRef,
            timeScope: sprintf('%s..%s', $query->contextStart->format('c'), $query->contextEnd->format('c')),
            corpusScope: $query->corpusScope,
        ));

        if (! $package->result->answered) {
            return PastDecisionContextResult::refused(
                reason: $package->result->refusalReason ?? new RefusalReason(
                    code: 'decision_context_unavailable',
                    message: 'Past decision context could not be reconstructed.',
                ),
                completeness: $package->result->completeness,
                uncertainty: $package->result->uncertainty,
            );
        }

        $answer = $package->result->answer;
        $evidenceItems = $package->evidence->items;

        $decisionTitle = $this->firstStringAttribute($evidenceItems, 'decision_title')
            ?? sprintf('decision:%s', $query->decisionFunesRef);
        $decisionSource = $this->firstStringAttribute($evidenceItems, 'decision_source') ?? 'unknown_source';
        $authorAccountId = $this->firstStringAttribute($evidenceItems, 'author_account_id') ?? 'unknown_account';

        $linkedIdentities = [];
        foreach ($evidenceItems as $item) {
            $stackId = $this->nullableString($item->attributes['stack_id'] ?? null);
            $linkedIdentities[$item->funesRef] = new LinkedIdentity($item->funesRef, $stackId);
        }

        $citationEdges = [];
        foreach ($evidenceItems as $item) {
            $label = $this->nullableString($item->attributes['citation_label'] ?? null);
            $locator = $this->nullableString($item->attributes['citation_locator'] ?? null);
            $position = $item->attributes['citation_position'] ?? null;

            if ($label === null || $locator === null || ! is_int($position)) {
                continue;
            }

            $citationEdges[] = new DecisionCitationEdge(
                label: $label,
                locator: $locator,
                position: $position,
                funesRef: $item->funesRef,
                stackId: $this->nullableString($item->attributes['stack_id'] ?? null),
            );
        }

        $recordedRationales = [];
        foreach ($answer->facts as $fact) {
            $recordedRationales[] = new RecordedRationale($fact->statement, $fact->funesRefs);
        }

        $inferredRationales = [];
        foreach ($answer->inferences as $inference) {
            $inferredRationales[] = new InferredRationale($inference->statement, $inference->supportingFunesRefs);
        }

        $missingEvidence = $answer->completeness->missingExpectedEvidence;
        $signals = $answer->uncertainty->signals;

        if ($decisionSource === 'unknown_source') {
            $missingEvidence[] = 'funes:decision_source:*';
            $signals[] = 'decision_source_missing';
        }
        if ($authorAccountId === 'unknown_account') {
            $missingEvidence[] = 'funes:author_account_id:*';
            $signals[] = 'author_account_missing';
        }

        $missingEvidence = array_values(array_unique($missingEvidence));
        $signals[] = 'recorded_vs_inferred_distinct';
        $signals = array_values(array_unique($signals));

        $context = new PastDecisionContext(
            decisionFunesRef: $query->decisionFunesRef,
            decisionTitle: $decisionTitle,
            decisionSource: $decisionSource,
            authorAccountId: $authorAccountId,
            linkedIdentities: array_values($linkedIdentities),
            citationEdges: $citationEdges,
            recordedRationales: $recordedRationales,
            inferredRationales: $inferredRationales,
            completeness: new CompletenessAssessment(
                hasSufficientEvidence: $answer->completeness->hasSufficientEvidence,
                missingExpectedEvidence: $missingEvidence,
            ),
            uncertainty: new UncertaintyAssessment(
                missingExpectedEvidence: $missingEvidence,
                signals: $signals,
            ),
            confidence: $answer->confidence,
            changeStory: $answer->changeStory,
        );

        return PastDecisionContextResult::reconstructed($context);
    }

    /**
     * @param array<int, \Sifrious\Kilgore\HistoricalQuestions\EvidenceItem> $items
     */
    private function firstStringAttribute(array $items, string $attribute): ?string
    {
        foreach ($items as $item) {
            $value = $this->nullableString($item->attributes[$attribute] ?? null);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
