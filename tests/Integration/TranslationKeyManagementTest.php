<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Integration;

use Maatify\I18n\Exception\DomainNotAllowedException;
use Maatify\I18n\Exception\DomainScopeViolationException;
use Maatify\I18n\Exception\ScopeNotAllowedException;
use Maatify\I18n\Exception\TranslationKeyAlreadyExistsException;
use Maatify\I18n\Exception\TranslationKeyNotFoundException;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;

final class TranslationKeyManagementTest extends MysqlIntegrationTestCase
{
    public function testCreateKeyReturnsIdAndPersistsStructuredIdentity(): void
    {
        $id = $this->createKey('ct', 'home', 'title', 'Page title');

        $dto = $this->keys->getById($id);
        self::assertNotNull($dto);
        self::assertSame(['ct', 'home', 'title', 'Page title'], [$dto->scope, $dto->domain, $dto->key, $dto->description]);
    }

    public function testCreateDuplicateStructuredKeyFails(): void
    {
        $this->createKey('ct', 'home', 'title');

        $this->expectException(TranslationKeyAlreadyExistsException::class);
        $this->createKey('ct', 'home', 'title');
    }

    public function testRenameMissingKeyFails(): void
    {
        $this->expectException(TranslationKeyNotFoundException::class);
        $this->renameKey(999999, 'ct', 'home', 'x');
    }

    public function testRenameIntoDuplicateStructuredIdentityFailsAndChangesNothing(): void
    {
        $a = $this->createKey('ct', 'home', 'a');
        $this->createKey('ct', 'home', 'b');

        try {
            $this->renameKey($a, 'ct', 'home', 'b');
            self::fail('Expected TranslationKeyAlreadyExistsException');
        } catch (TranslationKeyAlreadyExistsException) {
            $dto = $this->keys->getById($a);
            self::assertNotNull($dto);
            self::assertSame('a', $dto->key);
        }
    }

    public function testRenameToTheSameIdentityIsAccepted(): void
    {
        $a = $this->createKey('ct', 'home', 'a');

        $this->renameKey($a, 'ct', 'home', 'a');

        $dto = $this->keys->getById($a);
        self::assertNotNull($dto);
        self::assertSame('a', $dto->key);
    }

    public function testCreateAndRenameRejectGovernanceInvalidTargets(): void
    {
        $this->pdo()->exec("INSERT INTO maa_i18n_scopes (code, name, is_active) VALUES ('off', 'Off', 0), ('free', 'Free', 1)");
        $this->pdo()->exec("INSERT INTO maa_i18n_domains (code, name, is_active) VALUES ('dead', 'Dead', 0), ('loose', 'Loose', 1)");

        $cases = [
            [ScopeNotAllowedException::class, 'off', 'home'],
            [DomainNotAllowedException::class, 'ct', 'dead'],
            [DomainScopeViolationException::class, 'free', 'loose'],
        ];

        $existing = $this->createKey('ct', 'home', 'existing');

        foreach ($cases as [$exception, $scope, $domain]) {
            try {
                $this->createKey($scope, $domain, 'k');
                self::fail('createKey should reject ' . $exception);
            } catch (\Throwable $e) {
                self::assertInstanceOf($exception, $e);
            }

            try {
                $this->renameKey($existing, $scope, $domain, 'k');
                self::fail('renameKey should reject ' . $exception);
            } catch (\Throwable $e) {
                self::assertInstanceOf($exception, $e);
            }
        }

        $dto = $this->keys->getById($existing);
        self::assertNotNull($dto);
        self::assertSame(['ct', 'home', 'existing'], [$dto->scope, $dto->domain, $dto->key]);
        self::assertSame(1, $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_keys'));
    }

    public function testUpdateKeyDescriptionUpdatesTheDescription(): void
    {
        $id = $this->createKey('ct', 'home', 'title', 'old');

        $this->writer->updateKeyDescription($id, 'new');

        $dto = $this->keys->getById($id);
        self::assertNotNull($dto);
        self::assertSame('new', $dto->description);
    }

    public function testUpdateKeyDescriptionOnMissingKeyFails(): void
    {
        $this->expectException(TranslationKeyNotFoundException::class);
        $this->writer->updateKeyDescription(999999, 'x');
    }
}
