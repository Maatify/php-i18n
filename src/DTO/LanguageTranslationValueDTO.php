<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use JsonSerializable;

/**
 * One key with its translation value and type in one exact language scope.
 * A null translation ID means the exact scope has no row.
 */

final readonly class LanguageTranslationValueDTO implements JsonSerializable
{
    public function __construct(
        public int $keyId,
        public string $scope,
        public string $domain,
        public string $keyPart,
        public ?int $translationId,
        public ?string $value,
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
            'key_id' => $this->keyId,
            'scope' => $this->scope,
            'domain' => $this->domain,
            'key_part' => $this->keyPart,
            'translation_id' => $this->translationId,
            'value' => $this->value,
            'type' => $this->type,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
