<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * The domain code is used by scope mappings or translation keys and cannot change.
 */
final class DomainInUseException extends I18nConflictException
{
    public function __construct(public readonly string $domainCode)
    {
        parent::__construct(
            sprintf('I18n domain is in use and its code cannot change: %s', $domainCode),
        );
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::DOMAIN_IN_USE;
    }
}
