<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * A scope with this code already exists.
 */
final class ScopeAlreadyExistsException extends I18nConflictException
{
    public function __construct(public readonly string $scopeCode)
    {
        parent::__construct(
            sprintf('I18n scope already exists: %s', $scopeCode),
        );
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::SCOPE_ALREADY_EXISTS;
    }
}
