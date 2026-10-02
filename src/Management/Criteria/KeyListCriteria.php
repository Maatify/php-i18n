<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Criteria;

use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;

/**
 * Paginated key filters for one required scope. Null filters are omitted and
 * the default PageRequest supplies pagination when none is provided.
 */
final readonly class KeyListCriteria
{
    /**
     * Rejects an empty or whitespace-only scope code and non-positive IDs
     * before a query is run.
     *
     * @throws I18nInvalidArgumentException
     */
    public function __construct(
        public string $scopeCode,
        public ?string $globalSearch = null,
        public ?int $id = null,
        public ?string $domainLike = null,
        public ?string $keyPartLike = null,
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
