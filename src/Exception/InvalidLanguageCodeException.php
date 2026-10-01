<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * The language code violates the technical storage contract (ADR-019):
 * non-NULL, not empty/whitespace-only, at most 16 characters.
 *
 * This is NOT a semantic check — I18n never decides whether a language
 * exists, is active or is supported.
 */
final class InvalidLanguageCodeException extends I18nBusinessRuleException
{
    public function __construct(string $languageCode)
    {
        parent::__construct(
            sprintf(
                'Invalid language code (length=%d): must be non-empty, not whitespace-only and at most 16 characters.',
                strlen($languageCode),
            ),
        );
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::LANGUAGE_CODE_INVALID;
    }
}
