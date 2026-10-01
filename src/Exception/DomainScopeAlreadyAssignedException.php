<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * The domain is already assigned to the scope.
 */
final class DomainScopeAlreadyAssignedException extends I18nConflictException
{
    public function __construct(public readonly string $scopeCode, public readonly string $domainCode)
    {
        parent::__construct(
            sprintf('I18n domain %s is already assigned to scope %s.', $domainCode, $scopeCode),
        );
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::DOMAIN_SCOPE_ALREADY_ASSIGNED;
    }
}
