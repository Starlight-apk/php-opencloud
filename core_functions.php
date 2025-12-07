<?php
// 核心功能模块 - 合并版
// 包含原config.php和其它小功能模块
// core function module

// 数据库配置信息
define('DB_HOST', '117.72.109.119');
define('DB_PORT', 61475);
define('DB_USER', 'qysn');
define('DB_PWD', 'X123456qqX');
define('DB_NAME', 'mysql');

// 原config.php中的配置数组
$core_config = [
    'host' => DB_HOST,
    'port' => DB_PORT,
    'user' => DB_USER,
    'pwd' => DB_PWD,
    'dbname' => DB_NAME
];

// 通用工具函数
function sanitize_input($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generate_random_string($length = 12) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $random_string;
}

// 文件处理相关函数
function get_file_extension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function format_file_size($size) {
    $units = array('B', 'KB', 'MB', 'GB');
    $unit_index = 0;
    while ($size >= 1024 && $unit_index < count($units) - 1) {
        $size /= 1024;
        $unit_index++;
    }
    return round($size, 2) . ' ' . $units[$unit_index];
}

// 日期时间处理
function get_current_time() {
    return date('Y-m-d H:i:s');
}

function format_time_ago($timestamp) {
    $time_diff = time() - strtotime($timestamp);
    
    if ($time_diff < 60) {
        return $time_diff . '秒前';
    } elseif ($time_diff < 3600) {
        return floor($time_diff / 60) . '分钟前';
    } elseif ($time_diff < 86400) {
        return floor($time_diff / 3600) . '小时前';
    } elseif ($time_diff < 2592000) {
        return floor($time_diff / 86400) . '天前';
    } else {
        return date('Y-m-d', strtotime($timestamp));
    }
}

// 安全相关函数
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 简单的密码强度检查
function check_password_strength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = '密码长度至少8位';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = '密码至少包含一个大写字母';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = '密码至少包含一个小写字母';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = '密码至少包含一个数字';
    }
    
    return $errors;
}

// 用户会话管理
function is_user_logged_in() {
    return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
}

function set_user_session($user_data) {
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_data'] = $user_data;
}

function get_current_login_user() {
    return isset($_SESSION['user_data']) ? $_SESSION['user_data'] : null;
}

function destroy_user_session() {
    unset($_SESSION['user_logged_in']);
    unset($_SESSION['user_data']);
    setcookie('user_token', '', time() - 3600, '/');
}

// 简单的API响应格式化
function api_response($code, $message, $data = null) {
    $response = ['code' => $code, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// 验证文件扩展名
function is_valid_file_type($filename, $allowed_types = []) {
    $extension = get_file_extension($filename);
    if (empty($allowed_types)) {
        // 默认允许的文件类型
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];
    }
    return in_array(strtolower($extension), $allowed_types);
}

// 检查文件大小限制
function is_file_size_valid($file_size, $max_size = 10485760) { // 默认10MB
    return $file_size <= $max_size;
}

// 生成唯一的文件哈希
function generate_file_hash($file_path) {
    if (file_exists($file_path)) {
        return md5_file($file_path);
    }
    return null;
}