<?php
include("./includes/common.php");

// 检查是否有权限执行此脚本
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && !isset($_GET['force']) && !isset($_POST['force'])) {
    exit("此脚本只能在本地执行或通过添加?force参数执行");
}

echo "开始更新数据库表结构...\n";

// 检查并添加新字段
try {
    $columns = $DB->getAll("DESCRIBE pre_user");

    $has_username = false;
    $has_email = false;
    $has_password_hash = false;

    foreach ($columns as $col) {
        if ($col['Field'] === 'username') $has_username = true;
        if ($col['Field'] === 'email') $has_email = true;
        if ($col['Field'] === 'password_hash') $has_password_hash = true;
    }

    if (!$has_username) {
        $result = $DB->exec("ALTER TABLE `pre_user` ADD COLUMN `username` VARCHAR(50) NULL DEFAULT NULL AFTER `uid`");
        if ($result) {
            echo "成功添加username字段\n";
        } else {
            echo "添加username字段失败: " . $DB->error() . "\n";
        }
    } else {
        echo "username字段已存在\n";
    }

    if (!$has_email) {
        $result = $DB->exec("ALTER TABLE `pre_user` ADD COLUMN `email` VARCHAR(100) NULL DEFAULT NULL AFTER `username`");
        if ($result) {
            echo "成功添加email字段\n";
        } else {
            echo "添加email字段失败: " . $DB->error() . "\n";
        }
    } else {
        echo "email字段已存在\n";
    }

    if (!$has_password_hash) {
        $result = $DB->exec("ALTER TABLE `pre_user` ADD COLUMN `password_hash` VARCHAR(255) NULL DEFAULT NULL AFTER `email`");
        if ($result) {
            echo "成功添加password_hash字段\n";
        } else {
            echo "添加password_hash字段失败: " . $DB->error() . "\n";
        }
    } else {
        echo "password_hash字段已存在\n";
    }

    // 检查是否已有唯一索引
    $indexes = $DB->getAll("SHOW INDEX FROM pre_user");

    $username_index_exists = false;
    $email_index_exists = false;

    foreach ($indexes as $index) {
        if ($index['Key_name'] === 'username') $username_index_exists = true;
        if ($index['Key_name'] === 'email') $email_index_exists = true;
    }

    if (!$username_index_exists) {
        $result = $DB->exec("ALTER TABLE `pre_user` ADD UNIQUE INDEX `username` (`username`)");
        if ($result) {
            echo "成功添加username唯一索引\n";
        } else {
            // 可能已存在同名索引，忽略错误
            echo "username索引可能已存在\n";
        }
    }

    if (!$email_index_exists) {
        $result = $DB->exec("ALTER TABLE `pre_user` ADD UNIQUE INDEX `email` (`email`)");
        if ($result) {
            echo "成功添加email唯一索引\n";
        } else {
            // 可能已存在同名索引，忽略错误
            echo "email索引可能已存在\n";
        }
    }

    echo "数据库更新完成！\n";

    // 检查表结构
    echo "\n当前 pre_user 表结构:\n";
    $columns = $DB->getAll("DESCRIBE pre_user");
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
} catch (Exception $e) {
    echo "执行更新时发生错误: " . $e->getMessage() . "\n";
}
?>