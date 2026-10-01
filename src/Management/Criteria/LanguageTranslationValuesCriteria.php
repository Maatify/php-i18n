<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Criteria;

use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;

/**
 * Keys with their translation in one exact language scope, paginated. The
 * language code is not normalized and no Host language fallback is applied.
 */
final readonly class LanguageTranslationValuesCriteria
{
    /**
     * Requires a non-empty exact language code; whitespace is preserved as-is.
     *
     * @throws I18nInvalidArgumentException
     */
    public function __construct(
        public string $languageCode,
        public ?string $globalSearch = null,
        public ?int $id = null,
        public ?string $scopeLike = null,
        public ?string $domainLike = null,
        public ?string $keyPartLike = null,
        public ?string $valueLike = null,
        public PageRequest $page = new PageRequest(),
    ) {
        if ($languageCode === '') {
            throw I18nInvalidArgumentException::emptyField('languageCode');
        }
    }
}
