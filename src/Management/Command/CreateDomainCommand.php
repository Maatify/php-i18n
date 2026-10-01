<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Command;

use Maatify\I18n\Exception\I18nInvalidArgumentException;

/**
 * Intent: create a domain. The display position is never an input: the
 * Package appends it through maatify/persistence ordering.
 */
final readonly class CreateDomainCommand
{
    /**
     * Validates non-blank code/name and their storage limits (64/128 characters).
     *
     * @throws I18nInvalidArgumentException
     */
    public function __construct(
        public string $code,
        public string $name,
        public ?string $description = null,
        public bool $isActive = true,
    ) {
        if (trim($code) === '') {
            throw I18nInvalidArgumentException::emptyField('code');
        }

        if (mb_strlen($code) > 64) {
            throw I18nInvalidArgumentException::tooLong('code', 64);
        }

        if (trim($name) === '') {
            throw I18nInvalidArgumentException::emptyField('name');
        }

        if (mb_strlen($name) > 128) {
            throw I18nInvalidArgumentException::tooLong('name', 128);
        }
    }
}
