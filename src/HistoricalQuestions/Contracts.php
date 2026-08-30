<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\HistoricalQuestions;

interface FunesEvidenceRetriever
{
    public function retrieve(HistoricalQuestion $question): EvidenceSet;
}

interface HistoricalInterpreter
{
    public function interpret(HistoricalQuestion $question, EvidenceSet $evidence): HistoricalAnswer;
}
