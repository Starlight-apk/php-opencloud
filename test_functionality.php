<?php
// 简单的功能测试脚本

echo "系统功能测试开始...\n";

// 测试1: 验证核心文件是否存在
$core_files = [
    '/storage/emulated/0/htdocs/tv/config.php',
    '/storage/emulated/0/htdocs/tv/core_functions.php',
    '/storage/emulated/0/htdocs/tv/common_extended.php',
    '/storage/emulated/0/htdocs/tv/index.php',
    '/storage/emulated/0/htdocs/tv/login.php',
    '/storage/emulated/0/htdocs/tv/register.php',
    '/storage/emulated/0/htdocs/tv/dashboard.php',
    '/storage/emulated/0/htdocs/tv/api.php',
    '/storage/emulated/0/htdocs/tv/ajax.php',
    '/storage/emulated/0/htdocs/tv/view.php'
];

$missing_files = [];
foreach ($core_files as $file) {
    if (!file_exists($file)) {
        $missing_files[] = $file;
    }
}

if (empty($missing_files)) {
    echo "✓ 所有核心文件都存在\n";
} else {
    echo "✗ 缺失文件: \n";
    foreach ($missing_files as $file) {
        echo "  - $file\n";
    }
}

// 测试2: 验证函数库是否能正确包含
echo "\n测试函数库...\n";
try {
    require_once '/storage/emulated/0/htdocs/tv/core_functions.php';
    echo "✓ core_functions.php 包含成功\n";
} catch (Exception $e) {
    echo "✗ core_functions.php 包含失败: " . $e->getMessage() . "\n";
}

try {
    require_once '/storage/emulated/0/htdocs/tv/common_extended.php';
    echo "✓ common_extended.php 包含成功\n";
} catch (Exception $e) {
    echo "✗ common_extended.php 包含失败: " . $e->getMessage() . "\n";
}

// 测试3: 验证主要功能函数是否存在
$functions_to_test = [
    'sanitize_input',
    'validate_email', 
    'generate_random_string',
    'get_file_extension',
    'format_file_size',
    'is_user_logged_in',
    'validate_file_hash',
    'validate_file_name',
    'user_can_access_file'
];

echo "\n测试函数可用性...\n";
foreach ($functions_to_test as $func) {
    if (function_exists($func)) {
        echo "✓ 函数 $func 存在\n";
    } else {
        echo "✗ 函数 $func 不存在\n";
    }
}

// 测试4: 验证文件是否包含关键字符串
$test_files = [
    'config.php' => 'DB_HOST',
    'core_functions.php' => 'core function module',
    'common_extended.php' => '扩展功能模块',
    'index.php' => 'landing-page',
    'login.php' => 'main-wrapper',
    'register.php' => 'auth-container',
    'dashboard.php' => 'dashboard-wrapper'
];

echo "\n测试关键内容...\n";
foreach ($test_files as $filename => $expected_content) {
    $filepath = "/storage/emulated/0/htdocs/tv/$filename";
    if (file_exists($filepath)) {
        $content = file_get_contents($filepath);
        if (strpos($content, $expected_content) !== false) {
            echo "✓ $filename 包含预期内容\n";
        } else {
            echo "✗ $filename 不包含预期内容 '$expected_content'\n";
        }
    } else {
        echo "✗ $filename 文件不存在\n";
    }
}

echo "\n系统功能测试完成。\n";