<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\DecisionRationaleAnswers;

use Sifrious\Kilgore\HistoricalQuestions\CompletenessAssessment;
use Sifrious\Kilgore\HistoricalQuestions\RefusalReason;
use Sifrious\Kilgore\HistoricalQuestions\UncertaintyAssessment;
use Sifrious\Kilgore\PastDecisionContext\PastDecisionContextQuery;
use Sifrious\Kilgore\PastDecisionContext\PastDecisionContextService;

final class DecisionRationaleAnswerService
{
    public function __construct(
        private readonly PastDecisionContextService $pastDecisionContextService,
    ) {
    }

    public function answer(DecisionRationaleAnswerQuery $query): DecisionRationaleAnswerResult
    {
        $contextResult = $this->pastDecisionContextService->reconstruct(new PastDecisionContextQuery(
            decisionFunesRef: $query->decisionFunesRef,
            question: 'Why did we choose this?',
            contextStart: $query->contextStart,
            contextEnd: $query->contextEnd,
            corpusScope: $query->corpusScope,
        ));

        if (! $contextResult->reconstructed) {
            return DecisionRationaleAnswerResult::refused(
                reason: $contextResult->refusalReason ?? new RefusalReason(
                    code: 'decision_rationale_unavailable',
                    message: 'Could not reconstruct rationale answer.',
                ),
                completeness: $contextResult->completeness,
                uncertainty: $contextResult->uncertainty,
            );
        }

        $context = $contextResult->context;
        $recorded = [];
        foreach ($context->recordedRationales as $rationale) {
            $recorded[] = new RationaleObservation($rationale->statement, $rationale->funesRefs);
        }

        $inferred = [];
        foreach ($context->inferredRationales as $rationale) {
            $inferred[] = new RationaleInference($rationale->statement, $rationale->supportingFunesRefs);
        }

        $alternatives = [];
        foreach ($context->linkedIdentities as $identity) {
            if ($identity->funesRef === $context->decisionFunesRef) {
                continue;
            }

            $alternatives[] = new DecisionAlternative(
                statement: sprintf('Alternative considered in related identity: %s', $identity->funesRef),
                funesRefs: [$identity->funesRef],
            );
        }

        $missing = $context->completeness->missingExpectedEvidence;
        $signals = $context->uncertainty->signals;
        if ($recorded === []) {
            $missing[] = 'funes:decision_rationale:*';
            $signals[] = 'recorded_rationale_missing';
        }
        if ($alternatives === []) {
            $missing[] = 'funes:decision_alternatives:*';
            $signals[] = 'alternatives_missing';
        }
        $missing = array_values(array_unique($missing));
        $signals = array_values(array_unique(array_merge(
            $signals,
            ['decision_rationale_answered'],
        )));

        $answer = new DecisionRationaleAnswer(
            decisionFunesRef: $context->decisionFunesRef,
            decisionTitle: $context->decisionTitle,
            decisionSource: $context->decisionSource,
            authorAccountId: $context->authorAccountId,
            recordedRationale: $recorded,
            inferredRationale: $inferred,
            alternatives: $alternatives,
            citations: $context->citationEdges,
            completeness: new CompletenessAssessment(
                hasSufficientEvidence: $context->completeness->hasSufficientEvidence,
                missingExpectedEvidence: $missing,
            ),
            uncertainty: new UncertaintyAssessment(
                missingExpectedEvidence: $missing,
                signals: $signals,
            ),
            confidence: $context->confidence,
        );

        return DecisionRationaleAnswerResult::answered($answer);
    }
}
