<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * A non-throwing PDO failure state (prepare/execute/fetch returned a failure
 * value instead of throwing). It is NEVER a domain miss: callers must not
 * treat it as "not found", "empty" or a policy denial. Thrown PDOExceptions
 * are not wrapped; they propagate unchanged.
 */
final class I18nStorageException extends I18nSystemException
{
    public function __construct(string $operation)
    {
        parent::__construct(
            sprintf('I18n storage failure (%s).', $operation),
        );
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::STORAGE_FAILURE;
    }
}
