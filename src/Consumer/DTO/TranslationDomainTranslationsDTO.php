<?php

declare(strict_types=1);

namespace Maatify\I18n\Consumer\DTO;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;

/**
 * Typed translations keyed by their exact domain key part.
 *
 * An absent key part is a missing translation. A present value may be the
 * empty authoritative string and may have a null type.
 *
 * @implements IteratorAggregate<string, TranslationValueDTO>
 */
final readonly class TranslationDomainTranslationsDTO implements IteratorAggregate, JsonSerializable
{
    /**
     * @param array<string, TranslationValueDTO> $translations
     */
    public function __construct(
        public array $translations,
    ) {}

    public function get(string $keyPart): ?TranslationValueDTO
    {
        return $this->translations[$keyPart] ?? null;
    }

    /**
     * @return array<string, TranslationValueDTO>
     */
    public function all(): array
    {
        return $this->translations;
    }

    /**
     * @return ArrayIterator<string, TranslationValueDTO>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->translations);
    }

    /**
     * @return array<string, TranslationValueDTO>
     */
    public function jsonSerialize(): array
    {
        return $this->translations;
    }
}
