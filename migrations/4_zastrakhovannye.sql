CREATE TABLE `zastrakhovannye`
(
  `id`            INT          NOT NULL AUTO_INCREMENT,
  `surname`       VARCHAR(100) NOT NULL COMMENT 'Фамилия',
  `name`          VARCHAR(100) NOT NULL COMMENT 'Имя',
  `patronymic`    VARCHAR(100) NULL DEFAULT NULL COMMENT 'Отчество',
  `birth_date`    DATE         NOT NULL COMMENT 'Дата рождения',
  `gender`        CHAR(1)      NOT NULL COMMENT 'Пол: М или Ж',
  `address`       TEXT         NULL DEFAULT NULL COMMENT 'Адрес проживания',
  `phone_home`    VARCHAR(20)  NULL DEFAULT NULL COMMENT 'Телефон домашний',
  `phone_work`    VARCHAR(20)  NULL DEFAULT NULL COMMENT 'Телефон служебный',
  `phone_mobile`  VARCHAR(20)  NULL DEFAULT NULL COMMENT 'Телефон мобильный',
  `policy_number` VARCHAR(64)  NULL DEFAULT NULL COMMENT '№ полиса',
  `service_start` DATE         NULL DEFAULT NULL COMMENT 'Начало обслуживания',
  `service_end`   DATE         NULL DEFAULT NULL COMMENT 'Окончание обслуживания',
  `program`       TEXT         NULL DEFAULT NULL COMMENT 'Программа мед. обслуживания',
  `workplace`     VARCHAR(255) NULL DEFAULT NULL COMMENT 'Место работы (страхователь)',
  `position`      VARCHAR(255) NULL DEFAULT NULL COMMENT 'Должность',
  `update`        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cdate`         DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_zastrakhovannye_policy` (`policy_number`),
  KEY `idx_zastrakhovannye_fio` (`surname`, `name`, `patronymic`)
)
ENGINE = InnoDB;
