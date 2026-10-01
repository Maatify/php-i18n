<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use JsonSerializable;

/**
 * Represents a canonical translation key record.
 *
 * Structured, library-grade.
 * No parsing.
 * No derived strings.
 */

final readonly class TranslationKeyDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $scope,
        public string $domain,
        public string $key,
        public ?string $description,
        public string $createdAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'scope' => $this->scope,
            'domain' => $this->domain,
            'key' => $this->key,
            'description' => $this->description,
            'created_at' => $this->createdAt,
        ];
    }
}
