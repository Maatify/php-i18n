<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * Raised when an upsert fails to persist the value for its exact language
 * scope and key; the exception message includes both identities.
 */
final class TranslationUpsertFailedException extends I18nSystemException
{
    public function __construct(
        ?string $languageCode,
        int $keyId,
    ) {
        parent::__construct(
            sprintf(
                'Failed to upsert translation (language_code=%s, key_id=%d).',
                $languageCode ?? 'NULL',
                $keyId,
            ),
        );
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::TRANSLATION_UPSERT_FAILED;
    }
}
