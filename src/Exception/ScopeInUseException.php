<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * The scope code is used by domain mappings or translation keys and cannot change.
 */
final class ScopeInUseException extends I18nConflictException
{
    public function __construct(public readonly string $scopeCode)
    {
        parent::__construct(
            sprintf('I18n scope is in use and its code cannot change: %s', $scopeCode),
        );
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::SCOPE_IN_USE;
    }
}
