ALTER TABLE `zastrakhovannye`
  ADD COLUMN `operation_type` VARCHAR(32) NULL DEFAULT NULL COMMENT 'Тип операции: прикрепление / открепление' AFTER `id`,
  ADD KEY `idx_zastrakhovannye_operation_type` (`operation_type`);
