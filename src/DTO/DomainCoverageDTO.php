<?php

declare(strict_types=1);

namespace Maatify\I18n\DTO;

use JsonSerializable;

/**
 * Key coverage of one domain of a scope for one exact language code.
 */

final readonly class DomainCoverageDTO implements JsonSerializable
{
    public function __construct(
        public int $domainId,
        public string $domainCode,
        public string $domainName,
        public int $totalKeys,
        public int $translatedCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'domain_id' => $this->domainId,
            'domain_code' => $this->domainCode,
            'domain_name' => $this->domainName,
            'total_keys' => $this->totalKeys,
            'translated_count' => $this->translatedCount,
        ];
    }
}
