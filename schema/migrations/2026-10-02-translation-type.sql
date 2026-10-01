/*
 * Add nullable exact translation presentation metadata (ADR-020).
 *
 * This additive migration targets the exact pre-S1 schema at
 * 2408e6266b61c3fe8d79bba0bff1296ef5881b2d. Existing rows receive NULL.
 * It does not change row identity, values, language scope, timestamps,
 * uniqueness, indexes, or foreign keys.
 */
ALTER TABLE maa_i18n_translations
    ADD COLUMN type VARCHAR(32)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL
        COMMENT 'Exact optional presentation type metadata; no rendering or sanitization (ADR-020)'
        AFTER value,
    ADD CONSTRAINT chk_maa_i18n_translations_type
        CHECK (
            type IS NULL OR (
                CHAR_LENGTH(type) BETWEEN 1 AND 32
                AND type NOT REGEXP CONCAT(
                    '^[[:space:]', CONVERT(CHAR(11) USING utf8mb4), CONVERT(CHAR(12) USING utf8mb4),
                    CONVERT(0xC285 USING utf8mb4), CONVERT(CHAR(92) USING utf8mb4), 'p{Z}]*$'
                )
            )
        );
