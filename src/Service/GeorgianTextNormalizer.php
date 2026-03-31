<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Translation\GeorgianNormalizationResultDto;

final class GeorgianTextNormalizer
{
    /**
     * @var array<string, string>
     */
    private const LATIN_TO_MKHEDRULI = [
        "ch'" => 'ჭ',
        "ts'" => 'წ',
        "t'" => 'თ',
        "k'" => 'ქ',
        "p'" => 'ფ',
        "zh" => 'ჟ',
        "gh" => 'ღ',
        "sh" => 'შ',
        "ch" => 'ჩ',
        "ts" => 'ც',
        "dz" => 'ძ',
        "kh" => 'ხ',
        'a' => 'ა',
        'b' => 'ბ',
        'g' => 'გ',
        'd' => 'დ',
        'e' => 'ე',
        'v' => 'ვ',
        'z' => 'ზ',
        't' => 'ტ',
        'i' => 'ი',
        'k' => 'კ',
        'l' => 'ლ',
        'm' => 'მ',
        'n' => 'ნ',
        'o' => 'ო',
        'p' => 'პ',
        'j' => 'ჯ',
        'r' => 'რ',
        's' => 'ს',
        'u' => 'უ',
        'f' => 'ფ',
        'q' => 'ქ',
        'y' => 'ყ',
        'c' => 'ც',
        'x' => 'ხ',
        'h' => 'ჰ',
        'w' => 'ვ',
    ];

    public function normalize(string $text): GeorgianNormalizationResultDto
    {
        $trimmed = trim($text);
        if ('' === $trimmed) {
            return new GeorgianNormalizationResultDto(
                false,
                GeorgianNormalizationResultDto::ALPHABET_UNKNOWN,
                false,
                ''
            );
        }

        if (1 === preg_match('/[\x{10D0}-\x{10FF}]/u', $trimmed)) {
            return new GeorgianNormalizationResultDto(
                true,
                GeorgianNormalizationResultDto::ALPHABET_MKHEDRULI,
                false,
                $trimmed
            );
        }

        if (1 === preg_match('/\p{Cyrillic}/u', $trimmed)) {
            return new GeorgianNormalizationResultDto(
                false,
                GeorgianNormalizationResultDto::ALPHABET_CYRILLIC,
                false,
                $trimmed
            );
        }

        if (1 === preg_match('/[A-Za-z]/', $trimmed)) {
            $converted = $this->convertLatinToMkhedruli($trimmed);
            if (1 === preg_match('/[\x{10D0}-\x{10FF}]/u', $converted)) {
                return new GeorgianNormalizationResultDto(
                    true,
                    GeorgianNormalizationResultDto::ALPHABET_LATIN,
                    true,
                    $converted
                );
            }
        }

        return new GeorgianNormalizationResultDto(
            false,
            GeorgianNormalizationResultDto::ALPHABET_UNKNOWN,
            false,
            $trimmed
        );
    }

    private function convertLatinToMkhedruli(string $input): string
    {
        $normalized = str_replace(['’', '‘', 'ʼ', '`'], "'", mb_strtolower($input));
        $length = strlen($normalized);
        $result = '';

        for ($index = 0; $index < $length; ++$index) {
            $threeChars = $index + 3 <= $length ? substr($normalized, $index, 3) : '';
            if ('' !== $threeChars && isset(self::LATIN_TO_MKHEDRULI[$threeChars])) {
                $result .= self::LATIN_TO_MKHEDRULI[$threeChars];
                $index += 2;
                continue;
            }

            $twoChars = $index + 2 <= $length ? substr($normalized, $index, 2) : '';
            if ('' !== $twoChars && isset(self::LATIN_TO_MKHEDRULI[$twoChars])) {
                $result .= self::LATIN_TO_MKHEDRULI[$twoChars];
                ++$index;
                continue;
            }

            $char = $normalized[$index];
            $result .= self::LATIN_TO_MKHEDRULI[$char] ?? $char;
        }

        return $result;
    }
}
