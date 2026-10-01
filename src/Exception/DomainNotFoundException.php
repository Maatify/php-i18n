<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * The domain does not exist.
 */
final class DomainNotFoundException extends I18nNotFoundException
{
    public function __construct(public readonly string $identifier)
    {
        parent::__construct(
            sprintf('I18n domain not found (%s).', $identifier),
        );
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::DOMAIN_NOT_FOUND;
    }
}
