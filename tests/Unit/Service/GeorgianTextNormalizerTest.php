<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\Translation\GeorgianNormalizationResultDto;
use App\Service\GeorgianTextNormalizer;
use PHPUnit\Framework\TestCase;

final class GeorgianTextNormalizerTest extends TestCase
{
    public function testKeepsMkhedruliAsIs(): void
    {
        $service = new GeorgianTextNormalizer();

        $result = $service->normalize('გამარჯობა');

        self::assertTrue($result->supported);
        self::assertSame(GeorgianNormalizationResultDto::ALPHABET_MKHEDRULI, $result->alphabet);
        self::assertFalse($result->converted);
        self::assertSame('გამარჯობა', $result->normalizedText);
    }

    public function testConvertsLatinIntoMkhedruli(): void
    {
        $service = new GeorgianTextNormalizer();

        $result = $service->normalize('gamarjoba');

        self::assertTrue($result->supported);
        self::assertSame(GeorgianNormalizationResultDto::ALPHABET_LATIN, $result->alphabet);
        self::assertTrue($result->converted);
        self::assertSame('გამარჯობა', $result->normalizedText);
    }

    public function testMarksCyrillicAsUnsupported(): void
    {
        $service = new GeorgianTextNormalizer();

        $result = $service->normalize('привет');

        self::assertFalse($result->supported);
        self::assertSame(GeorgianNormalizationResultDto::ALPHABET_CYRILLIC, $result->alphabet);
    }
}
