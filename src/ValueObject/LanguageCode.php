<?php

declare(strict_types=1);

namespace Maatify\I18n\ValueObject;

use Maatify\I18n\Exception\InvalidLanguageCodeException;

/**
 * Immutable exact language scope of an I18n translation (ADR-019).
 *
 * Holds either NULL (the exact unlocalized scope) or a technically valid exact
 * code. Construction validates the storage contract:
 *
 * - a non-NULL code must not be empty or whitespace-only and must be at most
 *   MAX_LENGTH characters;
 * - the code is never trimmed, lowercased or otherwise normalized;
 * - whether the code is a known / active / supported language is Host policy
 *   and is NOT checked here.
 */
final readonly class LanguageCode
{
    public const MAX_LENGTH = 16;

    private function __construct(
        private ?string $code,
    ) {}

    /**
     * @throws InvalidLanguageCodeException
     */
    public static function fromNullable(?string $code): self
    {
        if ($code !== null && (trim($code) === '' || mb_strlen($code) > self::MAX_LENGTH)) {
            throw new InvalidLanguageCodeException($code);
        }

        return new self($code);
    }

    /**
     * The exact nullable code (`null` = unlocalized scope).
     */
    public function value(): ?string
    {
        return $this->code;
    }

    public function isUnlocalized(): bool
    {
        return $this->code === null;
    }

    /**
     * NULL-safe identity value used by the generated `language_code_identity`
     * column: NULL maps to '' (a non-NULL code is never empty).
     */
    public function identity(): string
    {
        return $this->code ?? '';
    }
}
