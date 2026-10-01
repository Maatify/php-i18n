<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * Raised when a scope cannot be used by a write, including when it is unknown
 * or inactive.
 */
final class ScopeNotAllowedException extends I18nBusinessRuleException
{
    public function __construct(string $scope)
    {
        parent::__construct("Invalid or inactive scope: {$scope}");
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::SCOPE_NOT_ALLOWED;
    }
}
