<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use JsonSerializable;

/**
 * Immutable translation row with exact nullable type metadata.
 */
final readonly class TranslationDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $keyId,
        public ?string $languageCode,
        public string $value,
        public ?string $type,
        public string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'key_id' => $this->keyId,
            'language_code' => $this->languageCode,
            'value' => $this->value,
            'type' => $this->type,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
