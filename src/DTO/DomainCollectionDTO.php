<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;

/**
 * Ordered list of domains.
 *
 * @implements IteratorAggregate<int, DomainDTO>
 */
final readonly class DomainCollectionDTO implements IteratorAggregate, JsonSerializable
{
    /**
     * @param list<DomainDTO> $items
     */
    public function __construct(
        public array $items,
    ) {}

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return ArrayIterator<int, DomainDTO>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return list<DomainDTO>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
