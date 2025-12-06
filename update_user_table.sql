-- 更新用户表以支持用户名/密码注册
ALTER TABLE `pre_user` 
ADD COLUMN `username` VARCHAR(50) NULL DEFAULT NULL AFTER `uid`,
ADD COLUMN `email` VARCHAR(100) NULL DEFAULT NULL AFTER `username`,
ADD COLUMN `password_hash` VARCHAR(255) NULL DEFAULT NULL AFTER `email`;

-- 为新字段创建索引以提高查询性能
ALTER TABLE `pre_user` ADD UNIQUE INDEX `username_index` (`username`);
ALTER TABLE `pre_user` ADD UNIQUE INDEX `email_index` (`email`);

-- 添加一个标志列，用于区分是OAuth用户还是传统登录用户
ALTER TABLE `pre_user` ADD COLUMN `is_oauth` TINYINT(1) DEFAULT 0 AFTER `password_hash`;

-- 更新现有记录的is_oauth字段
UPDATE `pre_user` SET `is_oauth` = 1 WHERE `type` != '';