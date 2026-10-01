<?php

declare(strict_types=1);

namespace Maatify\I18n\Service;

use Maatify\I18n\Repository\DomainLanguageSummaryRepositoryInterface;
use Maatify\I18n\Repository\KeyStatsRepositoryInterface;
use Maatify\I18n\Repository\TranslationKeyRepositoryInterface;
use Maatify\I18n\Exception\TranslationKeyNotFoundException;

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
     */
    public function onKeyCreated(int $keyId): void
    {
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
     * fail-soft no-op.
     */
    public function onKeyDeleted(int $keyId): void
    {
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
     * Recomputes the exact-scope summary and increments the key's translated
     * count. A missing key is a not-found error.
     *
     * @throws TranslationKeyNotFoundException
     */
    public function onTranslationCreated(
        ?string $languageCode,
        int $keyId,
    ): void {
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
     * Recomputes the exact-scope summary and decrements the key's translated
     * count; a missing key is a fail-soft no-op.
     */
    public function onTranslationDeleted(
        ?string $languageCode,
        int $keyId,
    ): void {
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
     * A language code was re-keyed in authoritative translations:
     * recompute the derived rows of the old and the new exact scope.
     */
    public function onLanguageCodeRekeyed(
        string $oldCode,
        string $newCode,
    ): void {
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
}
