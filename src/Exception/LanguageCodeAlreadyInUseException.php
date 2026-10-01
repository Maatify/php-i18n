<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\I18n\Enum\I18nErrorCodeEnum;

/**
 * A language-code re-key targets a code that already owns translations.
 */
final class LanguageCodeAlreadyInUseException extends I18nConflictException
{
    public function __construct(string $languageCode)
    {
        parent::__construct(
            sprintf('Language code already owns translations: %s', $languageCode),
        );
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return I18nErrorCodeEnum::LANGUAGE_CODE_ALREADY_IN_USE;
    }
}
