CREATE TABLE `variable`
(
  `id`     INT          NOT NULL AUTO_INCREMENT,
  `type`   VARCHAR(64)  NOT NULL COMMENT 'Тип переменной',
  `name`   VARCHAR(255) NOT NULL COMMENT 'Имя переменной',
  `value`  TEXT         NOT NULL COMMENT 'Значение (ключи, секреты и т.п.)',
  `update` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cdate`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_variable_type_name` (`type`, `name`)
)
ENGINE = InnoDB;
