<?php

declare(strict_types=1);

namespace Maatify\I18n\Service;

use Maatify\I18n\Repository\DomainLanguageSummaryRepositoryInterface;
use Maatify\I18n\Repository\KeyStatsRepositoryInterface;
use Maatify\I18n\Repository\TranslationKeyRepositoryInterface;
use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\Exception\InvalidLanguageCodeException;
use Maatify\I18n\Exception\TranslationKeyNotFoundException;
use Maatify\I18n\ValueObject\LanguageCode;

/**
 * Maintains the package-owned derived key and translation counters as authoritative keys and translations change.
 */
final readonly class MissingCounterService
{
    public function __construct(
        private DomainLanguageSummaryRepositoryInterface $summaryRepository,
        private TranslationKeyRepositoryInterface $keyRepository,
        private KeyStatsRepositoryInterface $keyStatsRepository,
    ) {}

    /**
     * Adds the key to existing summary rows and creates its zeroed key-stats
     * row. A missing key is a not-found error.
     *
     * @throws TranslationKeyNotFoundException
     * @throws I18nInvalidArgumentException when keyId is not positive
     */
    public function onKeyCreated(int $keyId): void
    {
        $this->assertPositiveKeyId($keyId);

        $key = $this->keyRepository->getById($keyId);

        if ($key === null) {
            throw new TranslationKeyNotFoundException($keyId);
        }

        // summary table
        $this->summaryRepository->incrementTotalKeys(
            $key->scope,
            $key->domain,
        );

        // key_stats table
        $this->keyStatsRepository->createForKey($keyId);
    }

    /**
     * Removes the key from derived summaries and stats; a missing key is a
     * fail-soft no-op. keyId must be positive.
     *
     * @throws I18nInvalidArgumentException when keyId is not positive
     */
    public function onKeyDeleted(int $keyId): void
    {
        $this->assertPositiveKeyId($keyId);

        $key = $this->keyRepository->getById($keyId);

        if ($key === null) {
            return; // fail-soft
        }

        // summary table
        $this->summaryRepository->decrementTotalKeys(
            $key->scope,
            $key->domain,
        );

        // key_stats table
        $this->keyStatsRepository->deleteForKey($keyId);
    }

    /**
     * Validate the positive key ID and exact nullable language scope before
     * repository access, then rebuild that summary and increment the key's
     * translated count. Null is unlocalized; valid non-null codes stay exact
     * without normalization. A missing key raises TranslationKeyNotFoundException.
     *
     * @throws TranslationKeyNotFoundException
     * @throws InvalidLanguageCodeException
     * @throws I18nInvalidArgumentException when keyId is not positive
     */
    public function onTranslationCreated(
        ?string $languageCode,
        int $keyId,
    ): void {
        $this->assertPositiveKeyId($keyId);
        LanguageCode::fromNullable($languageCode);

        $key = $this->keyRepository->getById($keyId);

        if ($key === null) {
            throw new TranslationKeyNotFoundException($keyId);
        }

        // summary table (exact-scope row, recomputed from authoritative data)
        $this->summaryRepository->refreshExactScope(
            $key->scope,
            $key->domain,
            $languageCode,
        );

        // key_stats table
        $this->keyStatsRepository->incrementTranslated($keyId);
    }

    /**
     * Validate the positive key ID and exact nullable language scope before
     * repository access, then rebuild that summary and decrement the key's
     * translated count. Null is unlocalized; valid non-null codes stay exact
     * without normalization. A missing key remains a fail-soft no-op.
     *
     * @throws InvalidLanguageCodeException
     * @throws I18nInvalidArgumentException when keyId is not positive
     */
    public function onTranslationDeleted(
        ?string $languageCode,
        int $keyId,
    ): void {
        $this->assertPositiveKeyId($keyId);
        LanguageCode::fromNullable($languageCode);

        $key = $this->keyRepository->getById($keyId);

        if ($key === null) {
            return; // fail-soft
        }

        // summary table (row is removed when no translation remains)
        $this->summaryRepository->refreshExactScope(
            $key->scope,
            $key->domain,
            $languageCode,
        );

        // key_stats table
        $this->keyStatsRepository->decrementTranslated($keyId);
    }

    /**
     * Validate both exact non-null codes before rebuilding derived rows for
     * the old and new scopes; the supplied values are preserved without
     * normalization.
     *
     * @throws InvalidLanguageCodeException
     */
    public function onLanguageCodeRekeyed(
        string $oldCode,
        string $newCode,
    ): void {
        LanguageCode::fromNullable($oldCode);
        LanguageCode::fromNullable($newCode);

        $this->summaryRepository->rebuildLanguageCode($oldCode);
        $this->summaryRepository->rebuildLanguageCode($newCode);
    }

    /**
     * Rebuilds summaries for both the old and new scope/domain identities.
     */
    public function onKeyMoved(
        string $oldScope,
        string $oldDomain,
        string $newScope,
        string $newDomain,
    ): void {
        $this->summaryRepository->rebuildScopeDomain($oldScope, $oldDomain);
        $this->summaryRepository->rebuildScopeDomain($newScope, $newDomain);
    }

    /**
     * @throws I18nInvalidArgumentException when keyId is not positive
     */
    private function assertPositiveKeyId(int $keyId): void
    {
        if ($keyId <= 0) {
            throw I18nInvalidArgumentException::notPositive('keyId');
        }
    }
}
