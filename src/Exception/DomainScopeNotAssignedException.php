<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * The domain is not assigned to the scope.
 */
final class DomainScopeNotAssignedException extends I18nNotFoundException
{
    public function __construct(public readonly string $scopeCode, public readonly string $domainCode)
    {
        parent::__construct(
            sprintf('I18n domain %s is not assigned to scope %s.', $domainCode, $scopeCode),
        );
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::DOMAIN_SCOPE_NOT_ASSIGNED;
    }
}
