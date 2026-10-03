<?php

/**
 * @copyright   ©2026 Maatify.dev
 * @Library     maatify/php-i18n
 * @Project     maatify:php-i18n
 * @author      Mohamed Abdulalim (megyptm) <mohamed@maatify.dev>
 * @since       2026-10-01 00:00
 * @see         https://www.maatify.dev Maatify.dev
 * @link        https://github.com/Maatify/php-i18n view Project on GitHub
 * @note        Distributed in the hope that it will be useful - WITHOUT WARRANTY.
 */

declare(strict_types=1);

namespace Maatify\I18n\Tests\Integration;

use Maatify\I18n\Exception\TranslationKeyAlreadyExistsException;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;

/**
 * GA-F03: I18n uses the shared persistence transaction runner and keeps its
 * semantics - no outer TX: the runner owns it; active outer TX: participate
 * only (never commit / roll back the caller's), original Throwable preserved.
 */
final class OuterTransactionParticipationTest extends MysqlIntegrationTestCase
{
    public function testWithoutAnOuterTransactionTheMutationCommitsByItself(): void
    {
        $id = $this->createKey('ct', 'home', 'k');
        $this->upsert('ar', $id, 'v', null);

        self::assertFalse($this->pdo()->inTransaction());
        self::assertSame(1, $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_keys'));
        self::assertSame(1, $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_translations'));
    }

    public function testInsideACallerOwnedTransactionTheMutationsStayPendingUntilTheCallerDecides(): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        $id = $this->createKey('ct', 'home', 'k');
        $this->upsert('ar', $id, 'v', null);
        $this->scopeManagement->setActive($this->scalarInt("SELECT id FROM maa_i18n_scopes WHERE code = 'ct'"), false);

        self::assertTrue($pdo->inTransaction(), 'I18n must not commit the caller-owned transaction');

        // another connection sees nothing (nothing committed)
        $other = self::newConnection();
        $seen = $other->query('SELECT COUNT(*) FROM maa_i18n_keys');
        self::assertNotFalse($seen);
        self::assertSame(0, (int) $seen->fetchColumn());

        $pdo->rollBack();

        self::assertSame(0, $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_keys'));
        self::assertSame(0, $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_translations'));
        self::assertSame(0, $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_domain_language_summary'));
        self::assertSame(1, $this->scalarInt("SELECT is_active FROM maa_i18n_scopes WHERE code = 'ct'"));
    }

    public function testAFailureInsideAnOuterTransactionKeepsItOpenAndPropagatesTheOriginalThrowable(): void
    {
        $pdo = $this->pdo();
        $this->createKey('ct', 'home', 'k');
        $pdo->beginTransaction();
        $this->createKey('ct', 'home', 'second');

        try {
            $this->createKey('ct', 'home', 'k');
            self::fail('Expected TranslationKeyAlreadyExistsException');
        } catch (TranslationKeyAlreadyExistsException) {
            self::assertTrue($pdo->inTransaction(), 'I18n must not roll back the caller-owned transaction');
        }

        // the caller's earlier work is still pending and still the caller's decision
        $other = self::newConnection();
        $count = static function () use ($other): int {
            $stmt = $other->query('SELECT COUNT(*) FROM maa_i18n_keys');
            self::assertNotFalse($stmt);

            return (int) $stmt->fetchColumn();
        };
        self::assertSame(1, $count(), 'only the first key is committed, the second is still pending');

        $pdo->commit();
        self::assertSame(2, $count());
    }

    public function testAFailureOfAnOwnedTransactionRollsEverythingBack(): void
    {
        $this->createKey('ct', 'home', 'k');
        $before = $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_domain_language_summary');

        try {
            $this->createKey('ct', 'home', 'k');
            self::fail('Expected TranslationKeyAlreadyExistsException');
        } catch (TranslationKeyAlreadyExistsException) {
            self::assertFalse($this->pdo()->inTransaction());
        }

        self::assertSame(1, $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_keys'));
        self::assertSame($before, $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_domain_language_summary'));
    }
}
