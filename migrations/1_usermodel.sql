CREATE TABLE `users`
(
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `surname` VARCHAR(100) NULL DEFAULT NULL,
  `email` VARCHAR(300) NULL DEFAULT NULL,
  `phone` VARCHAR(20) NULL DEFAULT NULL,
  `img` VARCHAR(500) NULL DEFAULT NULL,
  `auth` VARCHAR(500) NULL DEFAULT NULL,
  `password` VARCHAR(500) NULL DEFAULT NULL,
  `password_length` INT NULL DEFAULT NULL COMMENT 'Длина пароля',
  `code` VARCHAR(6) NULL DEFAULT NULL,
  `password_update` DATETIME DEFAULT NULL,
  `type` TINYINT(1) DEFAULT '1' COMMENT '1 - системный администратор, 3 - администратор',
  `update` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cdate` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
)
ENGINE = InnoDB;

INSERT INTO `users` (`id`, `name`, `surname`, `email`, `phone`, `img`, `auth`, `password`, `password_length`, `code`, `password_update`, `type`, `update`, `cdate`)
VALUES (NULL, '', 'admin', 'admin@mail.ru', '79999999999', NULL, NULL, '$2y$10$5IHgBhfEyzAEu23RhBDkWuo2EwLJtDOl9.q6bXz.QZIZhxIZ0cNDq', '5', NULL, NULL, '1', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
