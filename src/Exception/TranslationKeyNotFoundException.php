<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * Raised when the requested translation key does not exist.
 */
final class TranslationKeyNotFoundException extends I18nNotFoundException
{
    public function __construct(public readonly int $keyId)
    {
        parent::__construct(
            sprintf(
                'Translation key not found (id: %d).',
                $keyId,
            ),
        );
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::TRANSLATION_KEY_NOT_FOUND;
    }
}
