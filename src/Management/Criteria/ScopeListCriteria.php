<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Criteria;

use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;

/**
 * Search and filter input for the paginated scope list. Null filters are
 * omitted; pagination and sorting mechanics belong to maatify/persistence.
 */
final readonly class ScopeListCriteria
{
    /**
     * Builds scope filters without normalizing their search values.
     */
    public function __construct(
        public ?string $globalSearch = null,
        public ?int $id = null,
        public ?string $code = null,
        public ?string $name = null,
        public ?bool $isActive = null,
        public PageRequest $page = new PageRequest(),
    ) {}
}
