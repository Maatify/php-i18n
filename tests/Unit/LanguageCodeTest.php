<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Unit;

use Maatify\I18n\Exception\InvalidLanguageCodeException;
use Maatify\I18n\ValueObject\LanguageCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LanguageCodeTest extends TestCase
{
    public function testNullIsTheExactUnlocalizedScopeWithEmptyIdentity(): void
    {
        $code = LanguageCode::fromNullable(null);

        self::assertNull($code->value());
        self::assertTrue($code->isUnlocalized());
        self::assertSame('', $code->identity());
    }

    public function testCodeIsHeldExactlyWithoutNormalization(): void
    {
        $code = LanguageCode::fromNullable('ar-EG');

        self::assertSame('ar-EG', $code->value());
        self::assertFalse($code->isUnlocalized());
        self::assertSame('ar-EG', $code->identity());
        self::assertSame('AR', LanguageCode::fromNullable('AR')->value());
        self::assertSame(str_repeat('a', 16), LanguageCode::fromNullable(str_repeat('a', 16))->value());
    }

    public function testTryFactoryPreservesValidExactCodesAndUsesNullForInvalidCodes(): void
    {
        self::assertSame('ar-EG', LanguageCode::tryFromNullable('ar-EG')?->value());
        self::assertSame('AR', LanguageCode::tryFromNullable('AR')?->value());
        self::assertSame('custom.CODE', LanguageCode::tryFromNullable('custom.CODE')?->value());
        self::assertInstanceOf(LanguageCode::class, LanguageCode::tryFromNullable(null));
        self::assertNull(LanguageCode::tryFromNullable(null)->value());

        foreach (['', " \t\n", str_repeat('a', 17)] as $invalid) {
            self::assertNull(LanguageCode::tryFromNullable($invalid));
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCodes(): array
    {
        return [
            'empty' => [''],
            'whitespace only' => ['   '],
            'too long' => [str_repeat('a', 17)],
        ];
    }

    #[DataProvider('invalidCodes')]
    public function testInvalidCodesAreRejectedOnConstruction(string $invalid): void
    {
        $this->expectException(InvalidLanguageCodeException::class);
        LanguageCode::fromNullable($invalid);
    }
}
