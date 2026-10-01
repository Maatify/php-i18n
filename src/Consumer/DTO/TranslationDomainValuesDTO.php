<?php

declare(strict_types=1);

namespace Maatify\I18n\Consumer\DTO;

use JsonSerializable;

/**
 * Represents resolved translations for a single (exact language code + scope + domain).
 *
 * Structure:
 * [
 *   'page.title' => '...',
 *   'button.save' => '...'
 * ]
 */
final readonly class TranslationDomainValuesDTO implements JsonSerializable
{
    /**
     * @param array<string, string> $values
     */
    public function __construct(
        public array $values,
    ) {}

    public function get(string $keyPart): ?string
    {
        return $this->values[$keyPart] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
