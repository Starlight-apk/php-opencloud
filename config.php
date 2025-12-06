<?php
// 数据库配置文件 - 已重构
require_once 'core_functions.php';

// 从核心函数文件中获取配置
$dbconfig = [
    'host' => DB_HOST,
    'port' => DB_PORT,
    'user' => DB_USER,
    'pwd' => DB_PWD,
    'dbname' => DB_NAME
];

// 检查数据库连接
function checkDatabaseConnection() {
    try {
        $pdo = new PDO("mysql:host={$dbconfig['host']};port={$dbconfig['port']};dbname={$dbconfig['dbname']}",
                      $dbconfig['user'],
                      $dbconfig['pwd']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

// 安全配置
define('SECURITY_LEVEL', 'high');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15分钟
    