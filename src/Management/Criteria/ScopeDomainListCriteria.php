<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Criteria;

use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;

/**
 * Domains of the platform with their assignment flag for one scope (paginated).
 */
final readonly class ScopeDomainListCriteria
{
    /**
     * Rejects an empty or whitespace-only scope code and non-positive IDs;
     * null filters are omitted.
     *
     * @throws I18nInvalidArgumentException
     */
    public function __construct(
        public string $scopeCode,
        public ?string $globalSearch = null,
        public ?int $id = null,
        public ?string $code = null,
        public ?string $name = null,
        public ?bool $isActive = null,
        public ?bool $assigned = null,
        public PageRequest $page = new PageRequest(),
    ) {
        if (trim($scopeCode) === '') {
            throw I18nInvalidArgumentException::emptyField('scopeCode');
        }

        if ($id !== null && $id <= 0) {
            throw I18nInvalidArgumentException::notPositive('id');
        }
    }
}
