-- =============================================================================
-- Revskin — SQL defensivo (MySQL 8.0+ / MariaDB 10.5+)
-- Cobre as migrations a partir de 2026-04-10 (legado) e 22–26/04 (cópia, retries).
-- Pode reexecutar: ignora tabela/coluna/constraint se já existir.
-- Sempre faça backup antes. Depois disto, em produção, rode: php artisan migrate
--   (o Laravel marcará as migrations como executadas) OU insira em migrations
--   manualmente os names correspondentes, conforme o fluxo de vocês.
-- =============================================================================
SET @schema := DATABASE();
SET @fk_receita_origem := 'receitas_receita_origem_id_foreign';

-- -----------------------------------------------------------------------------
-- 2026_04_10_120000 — produtos.legado_somente_leitura
-- -----------------------------------------------------------------------------
SET @col_legado := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'produtos' AND COLUMN_NAME = 'legado_somente_leitura'
);
SET @sql_legado := IF(
  @col_legado = 0,
  'ALTER TABLE `produtos` ADD COLUMN `legado_somente_leitura` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ativo`',
  'SELECT 1 AS col_legado_ja_existe'
);
PREPARE stmt FROM @sql_legado; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 2026_04_25_000001 — receitas.receita_origem_id (self-FK)
-- -----------------------------------------------------------------------------
SET @col_origem := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'receitas' AND COLUMN_NAME = 'receita_origem_id'
);
SET @sql_origem := IF(
  @col_origem = 0,
  'ALTER TABLE `receitas` ADD COLUMN `receita_origem_id` BIGINT UNSIGNED NULL AFTER `medico_id`',
  'SELECT 1 AS col_receita_origem_ja_existe'
);
PREPARE stmt2 FROM @sql_origem; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @schema
    AND TABLE_NAME = 'receitas'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    AND CONSTRAINT_NAME = @fk_receita_origem
);
SET @sql_fk := IF(
  @fk_exists = 0
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'receitas' AND COLUMN_NAME = 'receita_origem_id') > 0,
  CONCAT(
    'ALTER TABLE `receitas` ADD CONSTRAINT `', @fk_receita_origem,
    '` FOREIGN KEY (`receita_origem_id`) REFERENCES `receitas`(`id`) ON DELETE SET NULL'
  ),
  'SELECT 1 AS fk_receita_origem_ja_existe_ou_col_ausente'
);
PREPARE stmt3 FROM @sql_fk; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- Índice implícito em FK; se o servidor não criar, não duplicar:
-- (MySQL cria index para FK; nada a fazer em geral)

-- -----------------------------------------------------------------------------
-- 2026_04_26_000001 — tabela integration_job_failure_states
-- -----------------------------------------------------------------------------
SET @t_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'integration_job_failure_states'
);
SET @sql_table := IF(
  @t_exists = 0,
  'CREATE TABLE `integration_job_failure_states` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `fingerprint` VARCHAR(128) NOT NULL,
    `last_failed_job_uuid` VARCHAR(64) NOT NULL,
    `next_retry_at` DATETIME NULL,
    `fast_retries_left` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `delayed_retry_left` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `exhausted` TINYINT(1) NOT NULL DEFAULT 0,
    `in_flight` TINYINT(1) NOT NULL DEFAULT 0,
    `last_dispatched_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    UNIQUE KEY `integration_job_failure_states_fingerprint_unique` (`fingerprint`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
  'SELECT 1 AS t_integration_job_failure_states_ja_existe'
);
PREPARE stmt4 FROM @sql_table; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;

-- Se a tabela existir sem o UNIQUE (caso raro de migração parcial), tentativa idempotente:
SET @u_exists := (
  SELECT COUNT(1) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema
    AND TABLE_NAME = 'integration_job_failure_states'
    AND INDEX_NAME = 'integration_job_failure_states_fingerprint_unique'
);
SET @sql_u := IF(
  @u_exists = 0
  AND (SELECT COUNT(*) FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'integration_job_failure_states') > 0,
  'CREATE UNIQUE INDEX `integration_job_failure_states_fingerprint_unique` ON `integration_job_failure_states` (`fingerprint`)',
  'SELECT 1 AS index_fingerprint_ja_existe_ou_tabela_ausente'
);
PREPARE stmt5 FROM @sql_u; EXECUTE stmt5; DEALLOCATE PREPARE stmt5;

SELECT 'Concluído: mysql-migrations-defensive-2026-04-recent.sql' AS done;
