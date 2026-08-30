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
        return $this->askWithEvidence($question)->result;
    }

    public function askWithEvidence(HistoricalQuestion $question): HistoricalInterpretationPackage
    {
        $evidence = $this->retriever->retrieve($question);

        if (! $evidence->isSufficient()) {
            return new HistoricalInterpretationPackage(
                evidence: $evidence,
                result: HistoricalAnswerResult::refused(
                    new RefusalReason(
                        code: 'insufficient_evidence',
                        message: 'Cannot interpret historical state without sufficient evidence.',
                    ),
                    new CompletenessAssessment(
                        hasSufficientEvidence: false,
                        missingExpectedEvidence: $evidence->missingExpectedEvidence,
                    ),
                ),
            );
        }

        $answer = $this->interpreter->interpret($question, $evidence);
        $this->guardTraceability($answer, $evidence);
        $answer = $this->normalizeCompletenessAndUncertainty($answer, $evidence);

        return new HistoricalInterpretationPackage(
            evidence: $evidence,
            result: HistoricalAnswerResult::answered($answer),
        );
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

        foreach ($answer->claims as $claim) {
            if ($claim->assertionType === ClaimAssertionType::Fact && $claim->funesRefs === []) {
                throw new DomainException('Fact claims must cite at least one Funes ref.');
            }

            foreach ($claim->funesRefs as $funesRef) {
                if (! array_key_exists($funesRef, $availableRefs)) {
                    throw new DomainException(
                        sprintf('Claim cites unknown Funes ref: %s', $funesRef),
                    );
                }
            }
        }
    }

    private function normalizeCompletenessAndUncertainty(HistoricalAnswer $answer, EvidenceSet $evidence): HistoricalAnswer
    {
        $missingExpectedEvidence = array_values(array_unique(array_merge(
            $evidence->missingExpectedEvidence,
            $answer->completeness->missingExpectedEvidence,
            $answer->uncertainty->missingExpectedEvidence,
        )));

        $hasSufficientEvidence = $answer->completeness->hasSufficientEvidence && $evidence->isSufficient();
        $signals = $answer->uncertainty->signals;

        if ($missingExpectedEvidence !== []) {
            $signals[] = 'missing_expected_history';
            $signals = array_values(array_unique($signals));
        }

        return $answer->withCompletenessAndUncertainty(
            completeness: new CompletenessAssessment(
                hasSufficientEvidence: $hasSufficientEvidence,
                missingExpectedEvidence: $missingExpectedEvidence,
            ),
            uncertainty: new UncertaintyAssessment(
                missingExpectedEvidence: $missingExpectedEvidence,
                signals: $signals,
            ),
        );
    }
}
