<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Command;

use Maatify\I18n\Exception\I18nInvalidArgumentException;

/**
 * Intent: rename and/or move an existing key to a (scope, domain, key part).
 */
final readonly class RenameKeyCommand
{
    /**
     * Requires a positive key ID and non-blank scope/domain/key values within
     * the same limits used when creating a key.
     *
     * @throws I18nInvalidArgumentException
     */
    public function __construct(
        public int $keyId,
        public string $scope,
        public string $domain,
        public string $key,
    ) {
        if ($keyId <= 0) {
            throw I18nInvalidArgumentException::notPositive('keyId');
        }

        if (trim($scope) === '') {
            throw I18nInvalidArgumentException::emptyField('scope');
        }

        if (trim($domain) === '') {
            throw I18nInvalidArgumentException::emptyField('domain');
        }

        if (trim($key) === '') {
            throw I18nInvalidArgumentException::emptyField('key');
        }

        if (mb_strlen($scope) > 32) {
            throw I18nInvalidArgumentException::tooLong('scope', 32);
        }

        if (mb_strlen($domain) > 64) {
            throw I18nInvalidArgumentException::tooLong('domain', 64);
        }

        if (mb_strlen($key) > 128) {
            throw I18nInvalidArgumentException::tooLong('key', 128);
        }
    }
}
