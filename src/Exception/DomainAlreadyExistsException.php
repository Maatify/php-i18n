<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * A domain with this code already exists.
 */
final class DomainAlreadyExistsException extends I18nConflictException
{
    public function __construct(public readonly string $domainCode)
    {
        parent::__construct(
            sprintf('I18n domain already exists: %s', $domainCode),
        );
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::DOMAIN_ALREADY_EXISTS;
    }
}
