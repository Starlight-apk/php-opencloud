<?php
include("./includes/common.php");

// 检查并添加头像字段
$columns = $DB->getAll("DESCRIBE pre_user");

$has_avatar = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'avatar') {
        $has_avatar = true;
        break;
    }
}

if (!$has_avatar) {
    $result = $DB->exec("ALTER TABLE `pre_user` ADD COLUMN `avatar` VARCHAR(500) NULL DEFAULT NULL AFTER `faceimg`");
    if ($result) {
        echo "成功添加头像字段\n";
    } else {
        echo "添加头像字段失败: " . $DB->error() . "\n";
    }
} else {
    echo "头像字段已存在\n";
}

// 检查并更新faceimg字段，确保其兼容性
foreach ($columns as $col) {
    if ($col['Field'] === 'faceimg') {
        // 更改faceimg字段类型以支持更长的URL
        $result = $DB->exec("ALTER TABLE `pre_user` MODIFY COLUMN `faceimg` VARCHAR(500) NULL DEFAULT NULL");
        if ($result) {
            echo "成功更新faceimg字段\n";
        } else {
            echo "更新faceimg字段失败: " . $DB->error() . "\n";
        }
        break;
    }
}

echo "头像功能更新完成！\n";
?>