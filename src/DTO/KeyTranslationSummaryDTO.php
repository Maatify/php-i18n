<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use JsonSerializable;

/**
 * One key of a (scope, domain) with how many of the supplied exact language
 * codes it is still missing. The language universe is supplied by the Host.
 */

final readonly class KeyTranslationSummaryDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $keyPart,
        public ?string $description,
        public int $totalLanguages,
        public int $missingCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'key_part' => $this->keyPart,
            'description' => $this->description,
            'total_languages' => $this->totalLanguages,
            'missing_count' => $this->missingCount,
        ];
    }
}
