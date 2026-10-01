<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Criteria;

use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;

/**
 * Per-key translation summary of one (scope, domain) over a Host-supplied set
 * of exact language codes. I18n does not know the language universe: the Host
 * passes the codes it wants measured and I18n counts exact matches only.

 */
final readonly class DomainKeySummaryCriteria
{
    /**
     * @param list<string> $languageCodes
     * @throws I18nInvalidArgumentException when scope/domain is blank or a
     *     language code is the empty string; language codes are not normalized
     */
    public function __construct(
        public string $scopeCode,
        public string $domainCode,
        public array $languageCodes,
        public ?string $globalSearch = null,
        public ?int $keyId = null,
        public ?string $keyPart = null,
        public bool $onlyMissing = false,
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
