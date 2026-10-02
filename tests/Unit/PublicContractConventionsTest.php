<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Unit;

use FilesystemIterator;
use JsonSerializable;
use IteratorAggregate;
use Maatify\I18n\Exception\I18nExceptionInterface;
use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\Exception\InvalidLanguageCodeException;
use Maatify\I18n\Management\Command\CreateDomainCommand;
use Maatify\I18n\Management\Command\CreateKeyCommand;
use Maatify\I18n\Management\Command\CreateScopeCommand;
use Maatify\I18n\Management\Command\RenameKeyCommand;
use Maatify\I18n\Management\Command\UpdateDomainMetadataCommand;
use Maatify\I18n\Management\Command\UpdateScopeMetadataCommand;
use Maatify\I18n\Management\Command\UpsertTranslationCommand;
use Maatify\I18n\Management\Criteria\DomainKeySummaryCriteria;
use Maatify\I18n\Management\Criteria\DomainListCriteria;
use Maatify\I18n\Management\Criteria\DomainTranslationGridCriteria;
use Maatify\I18n\Management\Criteria\KeyListCriteria;
use Maatify\I18n\Management\Criteria\LanguageTranslationValuesCriteria;
use Maatify\I18n\Management\Criteria\ScopeListCriteria;
use Maatify\I18n\Management\Criteria\ScopeDomainListCriteria;
use Maatify\I18n\ValueObject\TranslationType;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Throwable;

/**
 * GA-F07: the public runtime contract conventions, proven by reflection over
 * the real source tree (no database).
 */
final class PublicContractConventionsTest extends TestCase
{
    /**
     * @return list<class-string>
     */
    private static function classesIn(string $relativeDir): array
    {
        $root = dirname(__DIR__, 2) . '/src';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $relativeDir, FilesystemIterator::SKIP_DOTS));

        $classes = [];
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1, -4);
            /** @var class-string $class */
            $class = 'Maatify\\I18n\\' . str_replace('/', '\\', $relative);
            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }

    public function testEveryPackageDefinedExceptionBelongsToTheMarker(): void
    {
        $checked = 0;
        foreach (self::classesIn('Exception') as $class) {
            $reflection = new ReflectionClass($class);
            if ($reflection->isInterface() || !$reflection->isSubclassOf(Throwable::class)) {
                continue; // the marker itself / the error policy
            }

            self::assertTrue($reflection->implementsInterface(I18nExceptionInterface::class), $class);
            $checked++;
        }

        self::assertGreaterThan(20, $checked);
        self::assertTrue((new ReflectionClass(I18nExceptionInterface::class))->implementsInterface(Throwable::class));
    }

    public function testEveryPublicDtoIsFinalReadonlyAndJsonSerializable(): void
    {
        $checked = 0;
        foreach ([...self::classesIn('DTO'), ...self::classesIn('Consumer/DTO')] as $class) {
            $reflection = new ReflectionClass($class);

            self::assertTrue($reflection->isFinal(), $class . ' must be final');
            self::assertTrue($reflection->isReadOnly(), $class . ' must be readonly');
            self::assertTrue($reflection->implementsInterface(JsonSerializable::class), $class . ' must be JsonSerializable');
            self::assertStringEndsWith('DTO', $reflection->getShortName());

            if (str_ends_with($reflection->getShortName(), 'CollectionDTO')) {
                self::assertTrue($reflection->implementsInterface(IteratorAggregate::class), $class . ' collection must be iterable');
            }

            $checked++;
        }

        self::assertGreaterThanOrEqual(20, $checked);
    }

    public function testCommandsAndCriteriaAreFinalReadonlyValueObjects(): void
    {
        foreach ([...self::classesIn('Management/Command'), ...self::classesIn('Management/Criteria')] as $class) {
            $reflection = new ReflectionClass($class);
            self::assertTrue($reflection->isFinal() && $reflection->isReadOnly(), $class);
            self::assertFalse($reflection->implementsInterface(JsonSerializable::class), $class . ' is intent / query input, not a result DTO');
        }
    }

    public function testCommandsValidateTheirOwnContractWithoutAnyStorage(): void
    {
        $invalid = [
            static fn() => new CreateScopeCommand('', 'n'),
            static fn() => new CreateScopeCommand(str_repeat('x', 33), 'n'),
            static fn() => new CreateDomainCommand('c', str_repeat('n', 129)),
            static fn() => new UpdateScopeMetadataCommand(1),
            static fn() => new UpdateDomainMetadataCommand(0, 'n'),
            static fn() => new CreateKeyCommand('s', 'd', ''),
            static fn() => new RenameKeyCommand(-1, 's', 'd', 'k'),
            static fn() => new UpsertTranslationCommand(
                languageCode: 'ar',
                keyId: 0,
                value: 'v',
                type: null,
            ),
            static fn() => new UpsertTranslationCommand(
                languageCode: 'ar',
                keyId: 1,
                value: 'v',
                type: '',
            ),
            static fn() => new UpsertTranslationCommand(
                languageCode: 'ar',
                keyId: 1,
                value: 'v',
                type: " \t\n",
            ),
            static fn() => new UpsertTranslationCommand(
                languageCode: 'ar',
                keyId: 1,
                value: 'v',
                type: str_repeat('x', TranslationType::MAX_LENGTH + 1),
            ),
            static fn() => new KeyListCriteria(' '),
            static fn() => new ScopeListCriteria(id: 0),
            static fn() => new DomainListCriteria(id: -1),
            static fn() => new ScopeDomainListCriteria(''),
            static fn() => new ScopeDomainListCriteria('s', id: 0),
            static fn() => new KeyListCriteria('s', id: -1),
            static fn() => new DomainKeySummaryCriteria('s', 'd', ['']),
            static fn() => new DomainKeySummaryCriteria('s', 'd', [" \t\n"]),
            static fn() => new DomainKeySummaryCriteria('s', 'd', [str_repeat('a', 17)]),
            static fn() => new DomainKeySummaryCriteria('s', 'd', ['ar'], keyId: 0),
            static fn() => new DomainTranslationGridCriteria('s', '', []),
            static fn() => new DomainTranslationGridCriteria('s', 'd', [" \t\n"]),
            static fn() => new DomainTranslationGridCriteria('s', 'd', [str_repeat('a', 17)]),
            static fn() => new DomainTranslationGridCriteria('s', 'd', [], globalSearchLanguageCodes: ['']),
            static fn() => new DomainTranslationGridCriteria('s', 'd', [], globalSearchLanguageCodes: [" \t\n"]),
            static fn() => new DomainTranslationGridCriteria('s', 'd', [], globalSearchLanguageCodes: [str_repeat('a', 17)]),
            static fn() => new DomainTranslationGridCriteria('s', 'd', [], keyId: -1),
            static fn() => new LanguageTranslationValuesCriteria(''),
            static fn() => new LanguageTranslationValuesCriteria(" \t\n"),
            static fn() => new LanguageTranslationValuesCriteria(str_repeat('a', 17)),
            static fn() => new LanguageTranslationValuesCriteria('ar', id: 0),
        ];

        foreach ($invalid as $build) {
            try {
                $build();
                self::fail('Expected the contract owner to reject the input');
            } catch (I18nInvalidArgumentException $e) {
                self::assertInstanceOf(I18nExceptionInterface::class, $e);
            }
        }

        // exact language-code contract is enforced by the translation command
        $this->expectException(InvalidLanguageCodeException::class);
        new UpsertTranslationCommand(
            languageCode: str_repeat('z', 17),
            keyId: 1,
            value: 'v',
            type: null,
        );
    }

    public function testManagementLanguageCriteriaPreserveValidExactCodes(): void
    {
        $summaryCode = 'custom.CODE';
        $searchCode = 'AR';

        $summary = new DomainKeySummaryCriteria('scope', 'domain', [$summaryCode]);
        $grid = new DomainTranslationGridCriteria(
            'scope',
            'domain',
            ['ar-EG'],
            globalSearchLanguageCodes: [$searchCode],
        );
        $languageValues = new LanguageTranslationValuesCriteria('custom.CODE');

        self::assertSame([$summaryCode], $summary->languageCodes);
        self::assertSame(['ar-EG'], $grid->languageCodes);
        self::assertSame([$searchCode], $grid->globalSearchLanguageCodes);
        self::assertSame('custom.CODE', $languageValues->languageCode);
    }

    public function testAnEmptyTranslationValueAndTheNullScopeAreValid(): void
    {
        $empty = new UpsertTranslationCommand(
            languageCode: 'ar',
            keyId: 1,
            value: '',
            type: null,
        );
        self::assertSame('', $empty->value);
        self::assertNull($empty->type);

        $neutral = new UpsertTranslationCommand(
            languageCode: null,
            keyId: 1,
            value: 'n',
            type: null,
        );
        self::assertNull($neutral->languageCode);
        self::assertNull($neutral->type);
    }

    public function testTranslationTypeIsARequiredCommandArgument(): void
    {
        $constructor = new \ReflectionMethod(UpsertTranslationCommand::class, '__construct');
        self::assertSame(4, $constructor->getNumberOfRequiredParameters());
    }
}
