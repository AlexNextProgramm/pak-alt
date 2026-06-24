CREATE TABLE `upload_photo`
(
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `entity`     VARCHAR(32)  NOT NULL COMMENT 'Сущность: catalog, blog, …',
  `entity_id`  INT          NULL DEFAULT NULL COMMENT 'ID записи (NULL — черновик)',
  `path`       VARCHAR(512) NOT NULL COMMENT 'Относительный путь в Storage',
  `position`   INT          NOT NULL DEFAULT 0 COMMENT 'Порядок сортировки',
  `update`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cdate`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_upload_photo_entity` (`entity`, `entity_id`)
)
ENGINE = InnoDB;
