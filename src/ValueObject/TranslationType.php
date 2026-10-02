<?php

declare(strict_types=1);

namespace Maatify\I18n\ValueObject;

use Maatify\I18n\Exception\I18nInvalidArgumentException;

/**
 * Exact nullable consumer-defined type token for a translation.
 *
 * A non-null value is an exact, opaque consumer-defined token. It is validated
 * without trimming or normalization, and does not describe how the Package
 * renders or sanitizes the translation value.
 */
final readonly class TranslationType
{
    public const MAX_LENGTH = 32;

    private function __construct(
        private ?string $type,
    ) {}

    /**
     * @throws I18nInvalidArgumentException
     */
    public static function fromNullable(?string $type): self
    {
        if ($type !== null && preg_match('/^[\p{Z}\x{0009}-\x{000D}\x{0085}]*$/uD', $type) !== 0) {
            throw I18nInvalidArgumentException::emptyField('type');
        }

        if ($type !== null && mb_strlen($type) > self::MAX_LENGTH) {
            throw I18nInvalidArgumentException::tooLong('type', self::MAX_LENGTH);
        }

        return new self($type);
    }

    /** Returns the validated token exactly as supplied, or null. */
    public function value(): ?string
    {
        return $this->type;
    }
}
