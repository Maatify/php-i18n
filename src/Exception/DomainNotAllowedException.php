<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * Raised when a domain cannot be used by a write, including when it is unknown
 * or inactive.
 */
final class DomainNotAllowedException extends I18nBusinessRuleException
{
    public function __construct(string $domain)
    {
        parent::__construct("Invalid or inactive domain: {$domain}");
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::DOMAIN_NOT_ALLOWED;
    }
}
