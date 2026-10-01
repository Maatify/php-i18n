<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Service;

use Maatify\I18n\Repository\DomainLanguageSummaryRepositoryInterface;
use Maatify\I18n\Repository\KeyStatsRepositoryInterface;
use Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface;

/**
 * Rebuilds the derived I18n statistics from authoritative keys and translations.
 */
final readonly class I18nStatsRebuilder
{
    public function __construct(
        private TransactionRunnerInterface $tx,
        private DomainLanguageSummaryRepositoryInterface $summaryRepository,
        private KeyStatsRepositoryInterface $keyStatsRepository,
    ) {}

    /**
     * Full rebuild for BOTH derived layers.
     *
     * This MUST be DB-driven (INSERT..SELECT / GROUP BY) inside repositories.
     *
     * Required repository ops:
     * - DomainLanguageSummaryRepositoryInterface::truncate()
     * - DomainLanguageSummaryRepositoryInterface::rebuildAll()
     * - KeyStatsRepositoryInterface::truncate()
     * - KeyStatsRepositoryInterface::rebuildAll()
     */
    public function fullRebuild(): void
    {
        $this->tx->run(function (): void {

            // 1) Clear derived tables
            $this->summaryRepository->truncate();
            $this->keyStatsRepository->truncate();

            // 2) Rebuild derived tables
            $this->summaryRepository->rebuildAll();
            $this->keyStatsRepository->rebuildAll();
        });
    }
}
