<?php

declare(strict_types=1);

namespace App\DTO\Translation;

final readonly class GeorgianNormalizationResultDto
{
    public const ALPHABET_MKHEDRULI = 'mkhedruli';
    public const ALPHABET_LATIN = 'latin';
    public const ALPHABET_CYRILLIC = 'cyrillic';
    public const ALPHABET_UNKNOWN = 'unknown';

    public function __construct(
        public bool $supported,
        public string $alphabet,
        public bool $converted,
        public string $normalizedText,
    ) {
    }
}
