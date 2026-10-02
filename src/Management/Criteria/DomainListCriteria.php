<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Criteria;

use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;

/**
 * Search and filter input for the paginated domain list. A null filter is
 * omitted; the default PageRequest supplies pagination when none is provided.
 */
final readonly class DomainListCriteria
{
    /**
     * Builds domain filters without normalizing their search values.
     *
     * @throws I18nInvalidArgumentException when id is not positive
     */
    public function __construct(
        public ?string $globalSearch = null,
        public ?int $id = null,
        public ?string $code = null,
        public ?string $name = null,
        public ?bool $isActive = null,
        public PageRequest $page = new PageRequest(),
    ) {
        if ($id !== null && $id <= 0) {
            throw I18nInvalidArgumentException::notPositive('id');
        }
    }
}
