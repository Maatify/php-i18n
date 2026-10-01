<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use JsonSerializable;

/**
 * Result of an exact-scope translation upsert. `created` is true only when a
 * new row was inserted; `id` identifies the resulting row.
 */
final readonly class TranslationUpsertResultDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public bool $created,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'created' => $this->created,
        ];
    }
}
