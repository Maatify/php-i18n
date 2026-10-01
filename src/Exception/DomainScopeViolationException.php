<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * Raised when a key or translation references a domain not assigned to its scope.
 */
final class DomainScopeViolationException extends I18nBusinessRuleException
{
    public function __construct(string $scope, string $domain)
    {
        parent::__construct(
            "Domain '{$domain}' is not allowed for scope '{$scope}'.",
        );
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::DOMAIN_SCOPE_VIOLATION;
    }
}
