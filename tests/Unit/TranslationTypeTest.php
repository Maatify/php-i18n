<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Unit;

use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\ValueObject\TranslationType;
use PHPUnit\Framework\TestCase;

final class TranslationTypeTest extends TestCase
{
    public function testNullAndWysiwygAreValidExactTypes(): void
    {
        self::assertNull(TranslationType::fromNullable(null)->value());
        self::assertSame('wysiwyg', TranslationType::WYSIWYG);
        self::assertSame('wysiwyg', TranslationType::fromNullable(TranslationType::WYSIWYG)->value());
    }

    public function testUnicodeLengthBoundaryAndInputArePreservedWithoutNormalization(): void
    {
        $thirtyTwoCharacters = str_repeat('界', TranslationType::MAX_LENGTH);
        self::assertSame($thirtyTwoCharacters, TranslationType::fromNullable($thirtyTwoCharacters)->value());
        self::assertSame(' wysiwyg ', TranslationType::fromNullable(' wysiwyg ')->value());
        self::assertSame("\0", TranslationType::fromNullable("\0")->value());

        $this->expectException(I18nInvalidArgumentException::class);
        TranslationType::fromNullable(str_repeat('界', TranslationType::MAX_LENGTH + 1));
    }

    public function testEmptyAndWhitespaceOnlyTypesAreRejected(): void
    {
        $invalidTypes = ['', ' ', "\t\n\r\x0B\x0C\x85", "\u{00A0}", "\u{2028}"];
        $rejectedTypes = [];

        foreach ($invalidTypes as $invalid) {
            try {
                TranslationType::fromNullable($invalid);
                self::fail('Expected whitespace-only metadata to be rejected.');
            } catch (I18nInvalidArgumentException) {
                $rejectedTypes[] = $invalid;
            }
        }

        self::assertSame($invalidTypes, $rejectedTypes);
    }
}
