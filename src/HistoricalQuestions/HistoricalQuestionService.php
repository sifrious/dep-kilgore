<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\HistoricalQuestions;

use DomainException;

final class HistoricalQuestionService
{
    public function __construct(
        private readonly FunesEvidenceRetriever $retriever,
        private readonly HistoricalInterpreter $interpreter,
    ) {
    }

    public function ask(HistoricalQuestion $question): HistoricalAnswerResult
    {
        $evidence = $this->retriever->retrieve($question);

        if (! $evidence->isSufficient()) {
            return HistoricalAnswerResult::refused(
                new RefusalReason(
                    code: 'insufficient_evidence',
                    message: 'Cannot interpret historical state without sufficient evidence.',
                ),
                new CompletenessAssessment(
                    hasSufficientEvidence: false,
                    missingExpectedEvidence: $evidence->missingExpectedEvidence,
                ),
            );
        }

        $answer = $this->interpreter->interpret($question, $evidence);
        $this->guardTraceability($answer, $evidence);

        return HistoricalAnswerResult::answered($answer);
    }

    private function guardTraceability(HistoricalAnswer $answer, EvidenceSet $evidence): void
    {
        $availableRefs = array_flip($evidence->refs());

        foreach ($answer->facts as $fact) {
            if ($fact->funesRefs === []) {
                throw new DomainException('Factual assertions must cite at least one Funes ref.');
            }

            foreach ($fact->funesRefs as $funesRef) {
                if (! array_key_exists($funesRef, $availableRefs)) {
                    throw new DomainException(
                        sprintf('Fact cites unknown Funes ref: %s', $funesRef),
                    );
                }
            }
        }
    }
}
