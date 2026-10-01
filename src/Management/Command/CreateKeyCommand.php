<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Command;

use Maatify\I18n\Exception\I18nInvalidArgumentException;

/**
 * Intent: create a structured translation key (scope + domain + key part).
 */
final readonly class CreateKeyCommand
{
    /**
     * Validates required key parts and their storage limits; a nullable
     * description is limited to 255 characters when supplied.
     *
     * @throws I18nInvalidArgumentException
     */
    public function __construct(
        public string $scope,
        public string $domain,
        public string $key,
        public ?string $description = null,
    ) {
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

        if ($description !== null && mb_strlen($description) > 255) {
            throw I18nInvalidArgumentException::tooLong('description', 255);
        }
    }
}
