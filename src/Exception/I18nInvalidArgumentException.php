<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;

/**
 * A Command / Criteria / public input violated its own contract
 * (empty code, non-positive id, ...). Raised by the object that owns the
 * contract, before any storage is touched.
 */
final class I18nInvalidArgumentException extends InvalidArgumentMaatifyException implements I18nExceptionInterface
{
    public static function emptyField(string $field): self
    {
        return new self(sprintf('I18n input "%s" must not be empty.', $field));
    }

    public static function tooLong(string $field, int $max): self
    {
        return new self(sprintf('I18n input "%s" must be at most %d characters.', $field, $max));
    }

    public static function notPositive(string $field): self
    {
        return new self(sprintf('I18n input "%s" must be a positive integer.', $field));
    }

    public static function noChange(): self
    {
        return new self('I18n update must change at least one field.');
    }
}
