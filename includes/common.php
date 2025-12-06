<?php
// 最简版 common.php

// 启动会话
if(!isset($_SESSION)) session_start();

// 基本常量
define('SYSTEM_ROOT', dirname(__FILE__) . '/');
define('ROOT', dirname(SYSTEM_ROOT) . '/');

// 包含配置 - 检查是否存在，如果不存在则提供说明
if(file_exists(ROOT . 'config.php')) {
    require_once ROOT . 'config.php';
} else {
    // 检查是否有配置示例文件
    if(file_exists(ROOT . 'config.php.example')) {
        echo "<h2>配置文件说明</h2>";
        echo "<p>请复制 config.php.example 为 config.php 并根据您的环境修改配置。</p>";
        echo "<pre>cp config.php.example config.php</pre>";
        echo "<p>然后修改 config.php 中的数据库连接信息等配置项。</p>";
        exit();
    } else {
        die("错误：找不到配置文件 config.php 或 config.php.example");
    }
}

// 一些基本变量设置
$islogin2 = 0;  // 默认未登录
$islogin = 0;   // 默认未登录

// 简单的数据库连接
$DB = null;
try {
    $DB = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PWD);
    $DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

// 简单的配置获取
$conf = [];

// 系统密钥
define('SYS_KEY', 'default_key');
