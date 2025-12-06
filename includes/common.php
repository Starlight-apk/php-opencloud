<?php
// 最简版 common.php

// 启动会话
if(!isset($_SESSION)) session_start();

// 基本常量
define('SYSTEM_ROOT', dirname(__FILE__).'/');
define('ROOT', dirname(SYSTEM_ROOT).'/');

// 包含配置
require_once ROOT.'config.php';

// 一些基本变量设置
$islogin2 = 0;  // 默认未登录
$islogin = 0;   // 默认未登录

// 简单的数据库连接
$DB = null;
try {
    $DB = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PWD);
    $DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

// 简单的配置获取
$conf = [];

// 系统密钥
define('SYS_KEY', 'default_key');