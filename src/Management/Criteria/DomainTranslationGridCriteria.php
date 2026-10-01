<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Criteria;

use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;

/**
 * Keys x exact-language-codes grid of one (scope, domain). The Host supplies the
 * exact language codes (and, for free-text search, the codes whose Host
 * metadata matched); I18n never reads a Host language table.
 */
final readonly class DomainTranslationGridCriteria
{
    /**
     * @param list<string> $languageCodes
     * @param list<string> $globalSearchLanguageCodes
     * @throws I18nInvalidArgumentException when scope/domain is blank or a
     *     language code is the empty string; language codes are not normalized
     */
    public function __construct(
        public string $scopeCode,
        public string $domainCode,
        public array $languageCodes,
        public ?string $globalSearch = null,
        public array $globalSearchLanguageCodes = [],
        public ?int $keyId = null,
        public ?string $keyPartLike = null,
        public ?string $valueLike = null,
        public PageRequest $page = new PageRequest(),
    ) {
        if (trim($scopeCode) === '') {
            throw I18nInvalidArgumentException::emptyField('scopeCode');
        }

        if (trim($domainCode) === '') {
            throw I18nInvalidArgumentException::emptyField('domainCode');
        }

        foreach ($languageCodes as $code) {
            if ($code === '') {
                throw I18nInvalidArgumentException::emptyField('languageCodes');
            }
        }
    }
}
