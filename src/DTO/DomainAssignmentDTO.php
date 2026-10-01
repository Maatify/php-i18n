<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use JsonSerializable;

/**
 * A domain together with whether it is assigned to the queried scope.
 */

final readonly class DomainAssignmentDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $description,
        public bool $isActive,
        public int $sortOrder,
        public bool $assigned,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
            'assigned' => $this->assigned,
        ];
    }
}
