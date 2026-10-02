<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Unit;

use Maatify\I18n\DTO\TranslationKeyDTO;
use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\Exception\InvalidLanguageCodeException;
use Maatify\I18n\Repository\DomainLanguageSummaryRepositoryInterface;
use Maatify\I18n\Repository\KeyStatsRepositoryInterface;
use Maatify\I18n\Repository\TranslationKeyRepositoryInterface;
use Maatify\I18n\Service\MissingCounterService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MissingCounterServiceLanguageCodeTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function invalidCodes(): array
    {
        return [
            'empty' => [''],
            'whitespace only' => [" \t\n"],
            'too long' => [str_repeat('x', 17)],
        ];
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function acceptedCodes(): array
    {
        return [
            'custom exact code' => ['custom.CODE'],
            'uppercase code' => ['AR'],
            'regional code' => ['ar-EG'],
            'unlocalized scope' => [null],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidRekeyInputs(): array
    {
        $inputs = [];
        foreach (self::invalidCodes() as $label => [$invalid]) {
            $inputs[$label . ' old code'] = ['old', $invalid];
            $inputs[$label . ' new code'] = ['new', $invalid];
        }

        return $inputs;
    }

    #[DataProvider('invalidCodes')]
    public function testTranslationCreatedRejectsInvalidCodeBeforeRepositoryCalls(string $invalid): void
    {
        $summary = $this->createMock(DomainLanguageSummaryRepositoryInterface::class);
        $keys = $this->createMock(TranslationKeyRepositoryInterface::class);
        $stats = $this->createMock(KeyStatsRepositoryInterface::class);

        $keys->expects(self::never())->method('getById');
        $summary->expects(self::never())->method('refreshExactScope');
        $stats->expects(self::never())->method('incrementTranslated');

        $this->expectException(InvalidLanguageCodeException::class);
        (new MissingCounterService($summary, $keys, $stats))->onTranslationCreated($invalid, 17);
    }

    #[DataProvider('invalidCodes')]
    public function testTranslationDeletedRejectsInvalidCodeBeforeRepositoryCalls(string $invalid): void
    {
        $summary = $this->createMock(DomainLanguageSummaryRepositoryInterface::class);
        $keys = $this->createMock(TranslationKeyRepositoryInterface::class);
        $stats = $this->createMock(KeyStatsRepositoryInterface::class);

        $keys->expects(self::never())->method('getById');
        $summary->expects(self::never())->method('refreshExactScope');
        $stats->expects(self::never())->method('decrementTranslated');

        $this->expectException(InvalidLanguageCodeException::class);
        (new MissingCounterService($summary, $keys, $stats))->onTranslationDeleted($invalid, 17);
    }

    #[DataProvider('invalidRekeyInputs')]
    public function testRekeyRejectsEitherInvalidCodeBeforeAnyRepositoryCall(string $position, string $invalid): void
    {
        $summary = $this->createMock(DomainLanguageSummaryRepositoryInterface::class);
        $keys = $this->createStub(TranslationKeyRepositoryInterface::class);
        $stats = $this->createStub(KeyStatsRepositoryInterface::class);

        $summary->expects(self::never())->method('rebuildLanguageCode');

        $oldCode = $position === 'old' ? $invalid : 'custom.CODE';
        $newCode = $position === 'new' ? $invalid : 'ar-EG';

        $this->expectException(InvalidLanguageCodeException::class);
        (new MissingCounterService($summary, $keys, $stats))->onLanguageCodeRekeyed($oldCode, $newCode);
    }

    #[DataProvider('acceptedCodes')]
    public function testTranslationCreatedPassesExactCodeOrNullToSummary(?string $languageCode): void
    {
        $summary = $this->createMock(DomainLanguageSummaryRepositoryInterface::class);
        $keys = $this->createMock(TranslationKeyRepositoryInterface::class);
        $stats = $this->createMock(KeyStatsRepositoryInterface::class);

        $keys->expects(self::once())
            ->method('getById')
            ->with(17)
            ->willReturn(new TranslationKeyDTO(17, 'web', 'home', 'title', null, '2026-10-02 00:00:00'));
        $summary->expects(self::once())
            ->method('refreshExactScope')
            ->with('web', 'home', self::identicalTo($languageCode));
        $stats->expects(self::once())->method('incrementTranslated')->with(17);

        (new MissingCounterService($summary, $keys, $stats))->onTranslationCreated($languageCode, 17);
    }

    #[DataProvider('acceptedCodes')]
    public function testTranslationDeletedPassesExactCodeOrNullToSummary(?string $languageCode): void
    {
        $summary = $this->createMock(DomainLanguageSummaryRepositoryInterface::class);
        $keys = $this->createMock(TranslationKeyRepositoryInterface::class);
        $stats = $this->createMock(KeyStatsRepositoryInterface::class);

        $keys->expects(self::once())
            ->method('getById')
            ->with(17)
            ->willReturn(new TranslationKeyDTO(17, 'web', 'home', 'title', null, '2026-10-02 00:00:00'));
        $summary->expects(self::once())
            ->method('refreshExactScope')
            ->with('web', 'home', self::identicalTo($languageCode));
        $stats->expects(self::once())->method('decrementTranslated')->with(17);

        (new MissingCounterService($summary, $keys, $stats))->onTranslationDeleted($languageCode, 17);
    }

    public function testInvalidLanguageCodeDoesNotChangePositiveKeyIdValidationPrecedence(): void
    {
        $summary = $this->createMock(DomainLanguageSummaryRepositoryInterface::class);
        $keys = $this->createMock(TranslationKeyRepositoryInterface::class);
        $stats = $this->createMock(KeyStatsRepositoryInterface::class);

        $keys->expects(self::never())->method('getById');
        $summary->expects(self::never())->method('refreshExactScope');
        $stats->expects(self::never())->method('incrementTranslated');

        $this->expectException(I18nInvalidArgumentException::class);
        (new MissingCounterService($summary, $keys, $stats))->onTranslationCreated('', 0);
    }

    public function testRekeyPassesBothValidCodesThroughExactlyAndInOrder(): void
    {
        $summary = $this->createMock(DomainLanguageSummaryRepositoryInterface::class);
        $keys = $this->createStub(TranslationKeyRepositoryInterface::class);
        $stats = $this->createStub(KeyStatsRepositoryInterface::class);

        $passedCodes = [];
        $summary->expects(self::exactly(2))
            ->method('rebuildLanguageCode')
            ->willReturnCallback(static function (?string $languageCode) use (&$passedCodes): void {
                $passedCodes[] = $languageCode;
            });

        (new MissingCounterService($summary, $keys, $stats))->onLanguageCodeRekeyed('custom.CODE', 'AR');

        self::assertSame(['custom.CODE', 'AR'], $passedCodes);
    }
}
