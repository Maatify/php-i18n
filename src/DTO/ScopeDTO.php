<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use JsonSerializable;

/**
 * Immutable scope result value. Its fields describe `id`, `code`, `name`, `description`, `isActive`, `sortOrder`, `createdAt`.
 */
final readonly class ScopeDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $description,
        public bool $isActive,
        public int $sortOrder,
        public string $createdAt,
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
            'created_at' => $this->createdAt,
        ];
    }
}
