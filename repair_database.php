<?php
/**
 * 数据库手动修复脚本
 * 该脚本可以手动运行以修复数据库结构和数据
 */

include("./includes/common.php");

// 检查访问权限
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && !isset($_GET['force']) && $_GET['token'] !== 'repair') {
    exit("此脚本只能在本地执行或通过添加?force或?token=repair参数执行");
}

echo "<h2>数据库自动修复工具</h2>\n";
echo "<p>正在检查并修复数据库结构...</p>\n";

try {
    include_once("./includes/database_checker.php");
    
    $dbChecker = new DatabaseChecker($DB);
    
    echo "<h3>1. 检查并修复数据库表...</h3>\n";
    $dbChecker->checkAndRepairTables();
    
    echo "<h3>2. 检查并修复配置数据...</h3>\n";
    $dbChecker->checkAndRepairConfigData();
    
    // 检查用户表中的数据完整性
    echo "<h3>3. 检查用户表数据完整性...</h3>\n";
    
    // 确保默认管理员用户存在
    $admin_user = $DB->getColumn("SELECT COUNT(*) FROM pre_user WHERE uid=1000"); 
    if ($admin_user == 0) {
        // 尝试创建默认管理员用户
        $admin_pwd_hash = password_hash('123456', PASSWORD_DEFAULT);
        $result = $DB->exec(
            "INSERT INTO pre_user (uid, type, openid, username, email, password_hash, nickname, regip, addtime, lasttime, enable, level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [1000, 'sql', '', 'admin', '', $admin_pwd_hash, '管理员', $_SERVER['REMOTE_ADDR'], date("Y-m-d H:i:s"), date("Y-m-d H:i:s"), 1, 1]
        );
        if ($result) {
            echo "已创建默认管理员用户 (用户名: admin, 密码: 123456)<br>\n";
        } else {
            echo "创建默认管理员用户失败: " . $DB->error() . "<br>\n";
        }
    } else {
        echo "管理员用户已存在<br>\n";
    }
    
    echo "<h3>4. 数据库修复完成！</h3>\n";
    echo "<p>所有必要的表和数据都已检查并修复（如需要）</p>\n";
    echo "<p><a href='./'>返回网站首页</a> | <a href='./admin/'>访问管理后台</a></p>\n";
    
} catch (Exception $e) {
    echo "<h3>修复过程中发生错误：</h3>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
    echo "<p>错误文件：" . $e->getFile() . "，行号：" . $e->getLine() . "</p>\n";
}
?>