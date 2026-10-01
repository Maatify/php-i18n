<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Exception\BusinessRule\BusinessRuleMaatifyException;
use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Throwable;

/**
 * Base type for package business-rule exceptions. It supplies I18n's error-code
 * policy while retaining the shared exceptions package category.
 */
abstract class I18nBusinessRuleException extends BusinessRuleMaatifyException implements I18nExceptionInterface
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        ?ErrorCodeInterface $errorCodeOverride = null,
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
