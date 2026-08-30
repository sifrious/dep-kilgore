<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\HistoricalQuestions;

final class HistoricalQuestion
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $subject = null,
        public readonly ?string $timeScope = null,
        public readonly ?string $corpusScope = null,
    ) {
    }
}
