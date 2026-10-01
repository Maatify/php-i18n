<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;

/**
 * Ordered list of scopes.
 *
 * @implements IteratorAggregate<int, ScopeDTO>
 */
final readonly class ScopeCollectionDTO implements IteratorAggregate, JsonSerializable
{
    /**
     * @param list<ScopeDTO> $items
     */
    public function __construct(
        public array $items,
    ) {}

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return ArrayIterator<int, ScopeDTO>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return list<ScopeDTO>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
