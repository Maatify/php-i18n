<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use JsonSerializable;

/**
 * Package-owned coverage facts of one scope (ADR-019).
 *
 * `totalKeys` counts the keys of the domains assigned to the scope.
 * `translatedByLanguage` lists only exact codes that own translations; the
 * Host composes its language list and treats an absent code as translated = 0.
 */
final readonly class ScopeKeyCoverageDTO implements JsonSerializable
{
    /**
     * @param list<I18nLanguageCodeCountDTO> $translatedByLanguage
     */
    public function __construct(
        public int $totalKeys,
        public array $translatedByLanguage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'total_keys' => $this->totalKeys,
            'translated_by_language' => $this->translatedByLanguage,
        ];
    }
}
