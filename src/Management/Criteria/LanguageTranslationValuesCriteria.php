<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Criteria;

use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\ValueObject\LanguageCode;
use Maatify\Persistence\Pdo\Pagination\PageRequest;

/**
 * Keys with their translation in one exact language scope, paginated. The
 * language code is not normalized and no Host language fallback is applied.
 */
final readonly class LanguageTranslationValuesCriteria
{
    /**
     * Requires a technically valid exact language code without normalization.
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
        if (LanguageCode::tryFromNullable($languageCode) === null) {
            if (mb_strlen($languageCode) > LanguageCode::MAX_LENGTH) {
                throw I18nInvalidArgumentException::tooLong('languageCode', LanguageCode::MAX_LENGTH);
            }

            throw I18nInvalidArgumentException::emptyField('languageCode');
        }

        if ($id !== null && $id <= 0) {
            throw I18nInvalidArgumentException::notPositive('id');
        }
    }
}
