<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use JsonSerializable;

/**
 * One (key, exact language code) cell of a domain translation grid.
 * A null translation ID means no row exists; an existing row may have a null type.
 */

final readonly class TranslationGridRowDTO implements JsonSerializable
{
    public function __construct(
        public ?int $translationId,
        public int $keyId,
        public string $keyPart,
        public ?string $description,
        public string $languageCode,
        public ?string $value,
        public ?string $type,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'translation_id' => $this->translationId,
            'key_id' => $this->keyId,
            'key_part' => $this->keyPart,
            'description' => $this->description,
            'language_code' => $this->languageCode,
            'value' => $this->value,
            'type' => $this->type,
        ];
    }
}
