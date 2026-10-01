<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Command;

use Maatify\I18n\Exception\I18nInvalidArgumentException;

/**
 * Intent: change the descriptive metadata of a scope. `null` leaves a
 * field untouched; at least one field must change.
 */
final readonly class UpdateScopeMetadataCommand
{
    /**
     * Requires a positive ID and at least one changed field. A supplied name
     * must be non-blank and no longer than 64 characters; null leaves a field
     * unchanged.
     *
     * @throws I18nInvalidArgumentException
     */
    public function __construct(
        public int $id,
        public ?string $name = null,
        public ?string $description = null,
    ) {
        if ($id <= 0) {
            throw I18nInvalidArgumentException::notPositive('id');
        }

        if ($name === null && $description === null) {
            throw I18nInvalidArgumentException::noChange();
        }

        if ($name !== null && trim($name) === '') {
            throw I18nInvalidArgumentException::emptyField('name');
        }

        if ($name !== null && mb_strlen($name) > 64) {
            throw I18nInvalidArgumentException::tooLong('name', 64);
        }
    }
}
