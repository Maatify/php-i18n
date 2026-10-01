<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;

/**
 * Domains assigned to a scope, for selectors.
 *
 * @implements IteratorAggregate<int, DomainOptionDTO>
 */
final readonly class DomainOptionCollectionDTO implements IteratorAggregate, JsonSerializable
{
    /**
     * @param list<DomainOptionDTO> $items
     */
    public function __construct(
        public array $items,
    ) {}

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return ArrayIterator<int, DomainOptionDTO>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return list<DomainOptionDTO>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
