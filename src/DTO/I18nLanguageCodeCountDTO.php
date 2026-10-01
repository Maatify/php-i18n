<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use JsonSerializable;

/**
 * Raw count for one exact language scope (ADR-019).
 *
 * `$languageCode === null` is the exact unlocalized scope.
 * Display metadata (name, icon, active flag) is Host-owned composition.
 */

final readonly class I18nLanguageCodeCountDTO implements JsonSerializable
{
    public function __construct(
        public ?string $languageCode,
        public int $count,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'language_code' => $this->languageCode,
            'count' => $this->count,
        ];
    }
}
