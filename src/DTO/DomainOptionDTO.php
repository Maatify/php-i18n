<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use JsonSerializable;

/**
 * Minimal (code, name) domain option for selectors.
 */

final readonly class DomainOptionDTO implements JsonSerializable
{
    public function __construct(
        public string $code,
        public string $name,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
