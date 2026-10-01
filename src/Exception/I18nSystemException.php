<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Exception\System\SystemMaatifyException;

/**
 * Base type for package system exceptions. It supplies I18n's error-code
 * policy while retaining the shared exceptions package category.
 */
abstract class I18nSystemException extends SystemMaatifyException implements I18nExceptionInterface
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?\Maatify\Exceptions\Contracts\ErrorCodeInterface $errorCodeOverride = null,
    ) {
        parent::__construct(
            message: $message,
            code: $code,
            previous: $previous,
            errorCodeOverride: $errorCodeOverride,
            policy: I18nErrorPolicy::instance(),
        );
    }
}
