<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\HistoricalQuestions;

final class HistoricalInterpretationPackage
{
    public function __construct(
        public readonly EvidenceSet $evidence,
        public readonly HistoricalAnswerResult $result,
    ) {
    }
}
