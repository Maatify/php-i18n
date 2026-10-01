<?php

declare(strict_types=1);

namespace Maatify\I18n\Enum;

/**
 * Row-lock mode of a locking read. Anything other than NONE requires an
 * active transaction (the lock lives until commit / rollback).
 *
 * The Package serializes "governance code change vs. new usage" with these
 * locks; the order is always: scope row, then domain row, then dependent rows.
 */
enum LockModeEnum: string
{
    case NONE = 'none';
    case SHARE = 'share';
    case UPDATE = 'update';

    /**
     * SQL suffix of a SELECT using this mode.
     */
    public function sqlSuffix(): string
    {
        return match ($this) {
            self::NONE => '',
            self::SHARE => ' LOCK IN SHARE MODE',
            self::UPDATE => ' FOR UPDATE',
        };
    }
}
