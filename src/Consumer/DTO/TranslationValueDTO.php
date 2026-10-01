<?php

declare(strict_types=1);

namespace Maatify\I18n\Consumer\DTO;

use JsonSerializable;

/**
 * Exact translation content and its optional, opaque presentation type.
 *
 * The consumer owns presentation handling; this DTO does not trust, render or
 * sanitize the value based on its type.
 */
final readonly class TranslationValueDTO implements JsonSerializable
{
    public function __construct(
        public string $value,
        public ?string $type,
    ) {}

    /**
     * @return array{value: string, type: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'value' => $this->value,
            'type' => $this->type,
        ];
    }
}
