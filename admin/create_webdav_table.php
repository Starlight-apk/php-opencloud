<?php
/**
 * 创建WebDAV存储表
 */
include("../includes/common.php");

// 检查表是否存在
$table_exists = $DB->getColumn("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'pre_webdav_stor'");

if (!$table_exists) {
    $sql = "CREATE TABLE `pre_webdav_stor` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `url` varchar(500) NOT NULL,
        `username` varchar(255) NOT NULL,
        `password` varchar(255) NOT NULL,
        `root_path` varchar(500) NOT NULL DEFAULT '/',
        `addtime` datetime NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $result = $DB->exec($sql);
    if ($result !== false) {
        echo "WebDAV存储表创建成功！";
    } else {
        echo "创建表失败：" . $DB->error();
    }
} else {
    echo "WebDAV存储表已存在";
}
?>