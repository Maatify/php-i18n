<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Exception\NotFound\ResourceNotFoundMaatifyException;

/**
 * Base type for I18n not-found exceptions. It supplies I18n's error-code
 * policy while retaining the shared exceptions package category.
 */
abstract class I18nNotFoundException extends ResourceNotFoundMaatifyException implements I18nExceptionInterface
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
