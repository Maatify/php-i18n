<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Service;

use Maatify\I18n\Enum\LockModeEnum;
use Maatify\I18n\Exception\LanguageCodeAlreadyInUseException;
use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\Exception\TranslationKeyAlreadyExistsException;
use Maatify\I18n\Exception\TranslationKeyCreateFailedException;
use Maatify\I18n\Exception\TranslationKeyNotFoundException;
use Maatify\I18n\Exception\TranslationUpsertFailedException;
use Maatify\I18n\Management\Command\CreateKeyCommand;
use Maatify\I18n\Management\Command\RenameKeyCommand;
use Maatify\I18n\Management\Command\UpsertTranslationCommand;
use Maatify\I18n\Repository\TranslationKeyRepositoryInterface;
use Maatify\I18n\Repository\TranslationRepositoryInterface;
use Maatify\I18n\Service\I18nGovernancePolicyService;
use Maatify\I18n\Service\MissingCounterService;
use Maatify\I18n\ValueObject\LanguageCode;
use Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface;

/**
 * Key and translation mutations.
 *
 * Transaction owner: this service, through the shared persistence
 * TransactionRunnerInterface (owns BEGIN/COMMIT/ROLLBACK when no transaction is
 * active; participates in a caller-owned one and never commits / rolls it back;
 * the original Throwable is preserved).
 *
 * Concurrency: a mutation that CREATES usage of a scope / domain (create key,
 * move key) first takes SHARE locks on those governance rows (scope, then
 * domain, then mapping). A scope / domain code change takes the UPDATE lock on
 * the same row before checking usage, so "unused -> usage created -> code
 * changed" cannot orphan keys. The DB UNIQUE constraint remains the final
 * authority for duplicate keys and surfaces as
 * {@see TranslationKeyAlreadyExistsException}.
 */
final readonly class TranslationWriteService
{
    public function __construct(
        private TransactionRunnerInterface $tx,
        private TranslationKeyRepositoryInterface $keyRepository,
        private TranslationRepositoryInterface $translationRepository,
        private I18nGovernancePolicyService $governancePolicy,
        private MissingCounterService $missingCounter,
    ) {}

    /**
     * @return int id of the new key
     *
     * @throws TranslationKeyAlreadyExistsException
     */
    public function createKey(CreateKeyCommand $command): int
    {
        return $this->tx->run(function () use ($command): int {

            $this->governancePolicy
                ->assertScopeAndDomainAllowedForUsage($command->scope, $command->domain);

            if ($this->keyRepository
                    ->getByStructuredKey($command->scope, $command->domain, $command->key) !== null) {
                throw new TranslationKeyAlreadyExistsException(
                    $command->scope,
                    $command->domain,
                    $command->key,
                );
            }

            // A concurrent duplicate that slips past the check above is
            // classified by the repository from the UNIQUE violation.
            $id = $this->keyRepository->create($command);

            if ($id <= 0) {
                throw new TranslationKeyCreateFailedException(
                    $command->scope,
                    $command->domain,
                    $command->key,
                );
            }

            // Must stay inside same TX
            $this->missingCounter->onKeyCreated($id);

            return $id;
        });
    }

    /**
     * @throws TranslationKeyNotFoundException
     * @throws I18nInvalidArgumentException when keyId is not positive
     * @throws TranslationKeyAlreadyExistsException
     */
    public function renameKey(RenameKeyCommand $command): void
    {
        $this->tx->run(function () use ($command): void {

            // Governance first (lock order), then the key row.
            $this->governancePolicy
                ->assertScopeAndDomainAllowedForUsage($command->scope, $command->domain);

            $existingKey = $this->keyRepository->getById($command->keyId, LockModeEnum::UPDATE);

            if ($existingKey === null) {
                throw new TranslationKeyNotFoundException($command->keyId);
            }

            $duplicate = $this->keyRepository
                ->getByStructuredKey($command->scope, $command->domain, $command->key);

            if ($duplicate !== null && $duplicate->id !== $command->keyId) {
                throw new TranslationKeyAlreadyExistsException(
                    $command->scope,
                    $command->domain,
                    $command->key,
                );
            }

            $oldScope  = $existingKey->scope;
            $oldDomain = $existingKey->domain;

            $this->keyRepository->rename($command);

            /*
             * If scope/domain changed -> adjust the derived summary layer for the
             * old and the new (scope, domain). Must stay inside the same TX.
             */
            if ($oldScope !== $command->scope || $oldDomain !== $command->domain) {
                $this->missingCounter->onKeyMoved(
                    $oldScope,
                    $oldDomain,
                    $command->scope,
                    $command->domain,
                );
            }
        });
    }

    /**
     * @throws TranslationKeyNotFoundException
     * @throws I18nInvalidArgumentException when keyId is not positive
     */
    public function updateKeyDescription(
        int $keyId,
        string $description,
    ): void {
        if ($keyId <= 0) {
            throw I18nInvalidArgumentException::notPositive('keyId');
        }

        $this->tx->run(function () use ($keyId, $description): void {
            $key = $this->keyRepository->getById($keyId, LockModeEnum::UPDATE);
            if ($key === null) {
                throw new TranslationKeyNotFoundException($keyId);
            }

            if ($key->description === $description) {
                return;
            }

            if (!$this->keyRepository->updateDescription($keyId, $description)) {
                throw new TranslationKeyNotFoundException($keyId);
            }
        });
    }

    /**
     * Upsert the translation of an exact language scope.
     *
     * `languageCode === null` is the exact unlocalized scope. The code is
     * checked against the technical storage contract only (ADR-019); whether
     * the language exists / is active / is supported is Host policy.
     *
     * @throws TranslationKeyNotFoundException
     */
    public function upsertTranslation(UpsertTranslationCommand $command): int
    {
        return $this->tx->run(function () use ($command): int {

            if ($this->keyRepository->getById($command->keyId) === null) {
                throw new TranslationKeyNotFoundException($command->keyId);
            }

            $result = $this->translationRepository->upsert(
                $command->languageCode,
                $command->keyId,
                $command->value,
                $command->type,
            );

            if ($result->id <= 0) {
                throw new TranslationUpsertFailedException(
                    $command->languageCode,
                    $command->keyId,
                );
            }

            if ($result->created) {
                // Must be inside same TX
                $this->missingCounter->onTranslationCreated(
                    $command->languageCode,
                    $command->keyId,
                );
            }

            return $result->id;
        });
    }

    /**
     * @throws TranslationKeyNotFoundException
     */
    public function deleteTranslation(
        ?string $languageCode,
        int $keyId,
    ): void {
        if ($keyId <= 0) {
            throw I18nInvalidArgumentException::notPositive('keyId');
        }

        $exactCode = LanguageCode::fromNullable($languageCode)->value();

        $this->tx->run(function () use ($exactCode, $keyId): void {

            if ($this->keyRepository->getById($keyId) === null) {
                throw new TranslationKeyNotFoundException($keyId);
            }

            $deleted = $this->translationRepository
                ->deleteByLanguageAndKey($exactCode, $keyId);

            if ($deleted) {
                // Must be inside same TX
                $this->missingCounter->onTranslationDeleted(
                    $exactCode,
                    $keyId,
                );
            }
        });
    }

    /**
     * Explicit language-code identity migration (ADR-019 §6).
     *
     * Re-keys every authoritative translation of `$oldCode` to `$newCode` and
     * recomputes the affected derived summary rows, inside one I18n
     * transaction (or the caller's, when one is already open).
     *
     * The Host orchestrates this together with its own language registry
     * rename. I18n does not validate either code semantically; it fails hard
     * when `$newCode` already owns translations so no rows are merged or lost.
     *
     * @return int number of re-keyed translations
     *
     * @throws \Maatify\I18n\Exception\InvalidLanguageCodeException
     * @throws LanguageCodeAlreadyInUseException
     */
    public function rekeyLanguageCode(string $oldCode, string $newCode): int
    {
        $oldCode = (string) LanguageCode::fromNullable($oldCode)->value();
        $newCode = (string) LanguageCode::fromNullable($newCode)->value();

        if ($oldCode === $newCode) {
            return 0;
        }

        return $this->tx->run(function () use ($oldCode, $newCode): int {

            if ($this->translationRepository->hasAnyForLanguage($newCode)) {
                throw new LanguageCodeAlreadyInUseException($newCode);
            }

            $rekeyed = $this->translationRepository->rekeyLanguageCode($oldCode, $newCode);

            // Must be inside same TX
            $this->missingCounter->onLanguageCodeRekeyed($oldCode, $newCode);

            return $rekeyed;
        });
    }
}
