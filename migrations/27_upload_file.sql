CREATE TABLE `upload_file`
(
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `company_id` INT          NOT NULL COMMENT 'ID компании',
  `path`       VARCHAR(512) NULL DEFAULT NULL COMMENT 'Относительный путь в var/uploads/download',
  `exception`  TEXT         NULL DEFAULT NULL COMMENT 'Текст ошибки при обработке',
  `update`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cdate`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_upload_file_company` (`company_id`),
  CONSTRAINT `fk_upload_file_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`id`)
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;
