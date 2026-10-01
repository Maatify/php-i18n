<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;

/**
 * List of translation keys.
 *
 * @implements IteratorAggregate<int, TranslationKeyDTO>
 */
final readonly class TranslationKeyCollectionDTO implements IteratorAggregate, JsonSerializable
{
    /**
     * @param list<TranslationKeyDTO> $items
     */
    public function __construct(
        public array $items,
    ) {}

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return ArrayIterator<int, TranslationKeyDTO>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return list<TranslationKeyDTO>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
