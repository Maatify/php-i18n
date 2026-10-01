<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use JsonSerializable;

/**
 * Immutable i18n stat count result value. Its fields describe `label`, `count`.
 */
final readonly class I18nStatCountDTO implements JsonSerializable
{
    public function __construct(
        public string $label,
        public int $count,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'count' => $this->count,
        ];
    }
}
