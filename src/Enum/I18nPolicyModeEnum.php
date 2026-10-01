<?php

declare(strict_types=1);

namespace Maatify\I18n\Enum;

/**
 * Defines the i18n policy mode values used by the I18n package.
 */
enum I18nPolicyModeEnum: string
{
    case STRICT = 'strict';
    case PERMISSIVE = 'permissive';
}
