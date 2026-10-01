<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;

/**
 * List of translation rows.
 *
 * @implements IteratorAggregate<int, TranslationDTO>
 */
final readonly class TranslationCollectionDTO implements IteratorAggregate, JsonSerializable
{
    /**
     * @param list<TranslationDTO> $items
     */
    public function __construct(
        public array $items,
    ) {}

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return ArrayIterator<int, TranslationDTO>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return list<TranslationDTO>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
