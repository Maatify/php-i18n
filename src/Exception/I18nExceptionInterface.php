<?php

declare(strict_types=1);

namespace Maatify\I18n\Exception;

use Throwable;

/**
 * Package marker: every exception defined by maatify/php-i18n implements it
 * directly or through its hierarchy.
 *
 * External throwables (for example a PDOException that the package
 * propagates unchanged) are NOT forced to implement it.
 */
interface I18nExceptionInterface extends Throwable {}
