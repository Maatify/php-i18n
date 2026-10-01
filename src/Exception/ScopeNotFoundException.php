<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * The scope does not exist.
 */
final class ScopeNotFoundException extends I18nNotFoundException
{
    public function __construct(public readonly string $identifier)
    {
        parent::__construct(
            sprintf('I18n scope not found (%s).', $identifier),
        );
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::SCOPE_NOT_FOUND;
    }
}
