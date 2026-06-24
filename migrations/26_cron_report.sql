CREATE TABLE `cron_report`
(
  `id`          INT           NOT NULL AUTO_INCREMENT,
  `started_at`  DATETIME      NOT NULL COMMENT 'Время запуска',
  `emails_found` INT          NOT NULL DEFAULT 0 COMMENT 'Сколько писем найдено',
  `errors`      TEXT          NULL DEFAULT NULL COMMENT 'Ошибки (текст)',
  `status`      ENUM('running', 'success', 'error', 'completed') NOT NULL DEFAULT 'running' COMMENT 'Статус выполнения',
  `update`      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cdate`       DATETIME      DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `status_idx` (`status`),
  INDEX `started_at_idx` (`started_at`)
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;