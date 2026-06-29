CREATE TABLE `cron_report_error`
(
  `id`           INT           NOT NULL AUTO_INCREMENT,
  `report_id`    INT           NOT NULL COMMENT 'ID запуска cron_report',
  `message_uid`  INT           NULL DEFAULT NULL COMMENT 'UID письма в IMAP',
  `subject`      VARCHAR(500)  NULL DEFAULT NULL COMMENT 'Тема письма',
  `sender_email` VARCHAR(255)  NULL DEFAULT NULL COMMENT 'Email отправителя',
  `filename`     VARCHAR(255)  NULL DEFAULT NULL COMMENT 'Имя файла вложения',
  `message`      TEXT          NOT NULL COMMENT 'Текст ошибки',
  `cdate`        DATETIME      DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cron_report_error_report` (`report_id`),
  KEY `idx_cron_report_error_uid` (`message_uid`),
  CONSTRAINT `fk_cron_report_error_report` FOREIGN KEY (`report_id`) REFERENCES `cron_report` (`id`) ON DELETE CASCADE
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;

ALTER TABLE `cron_report` DROP COLUMN `errors`;
