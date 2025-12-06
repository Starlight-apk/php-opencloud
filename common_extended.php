<?php
// 扩展功能模块文件 - 已合并
// 包含项目核心功能

// 从core_functions.php引入的核心功能
require_once 'core_functions.php';

// 数据库辅助函数
function get_user_by_id($user_id) {
    global $DB;
    return $DB->getRow("SELECT * FROM pre_user WHERE uid = ?", [$user_id]);
}

function get_file_by_hash($file_hash) {
    global $DB;
    return $DB->getRow("SELECT * FROM pre_file WHERE hash = ?", [$file_hash]);
}

function log_user_action($user_id, $action, $details = '') {
    global $DB, $clientip;
    $DB->exec("INSERT INTO pre_logs (uid, action, details, ip, addtime) VALUES (?, ?, ?, ?, NOW())", 
              [$user_id, $action, $details, $clientip]);
}

// 文件操作函数
function format_file_type_icon($file_type) {
    $icon_map = [
        'jpg' => 'fa-file-image-o',
        'jpeg' => 'fa-file-image-o',
        'png' => 'fa-file-image-o',
        'gif' => 'fa-file-image-o',
        'bmp' => 'fa-file-image-o',
        'mp4' => 'fa-file-video-o',
        'avi' => 'fa-file-video-o',
        'mov' => 'fa-file-video-o',
        'wmv' => 'fa-file-video-o',
        'flv' => 'fa-file-video-o',
        'f4v' => 'fa-file-video-o',
        'webm' => 'fa-file-video-o',
        '3gp' => 'fa-file-video-o',
        '3gpp' => 'fa-file-video-o',
        'mp3' => 'fa-file-audio-o',
        'wav' => 'fa-file-audio-o',
        'flac' => 'fa-file-audio-o',
        'pdf' => 'fa-file-pdf-o',
        'doc' => 'fa-file-word-o',
        'docx' => 'fa-file-word-o',
        'xls' => 'fa-file-excel-o',
        'xlsx' => 'fa-file-excel-o',
        'ppt' => 'fa-file-powerpoint-o',
        'pptx' => 'fa-file-powerpoint-o',
        'txt' => 'fa-file-text-o',
        'zip' => 'fa-file-archive-o',
        'rar' => 'fa-file-archive-o',
        '7z' => 'fa-file-archive-o',
        'tar' => 'fa-file-archive-o',
        'gz' => 'fa-file-archive-o',
        'default' => 'fa-file-o'
    ];
    
    return isset($icon_map[strtolower($file_type)]) ? $icon_map[strtolower($file_type)] : $icon_map['default'];
}

// 安全函数
function validate_file_hash($file_hash) {
    return preg_match('/^[0-9a-f]{32}$/i', $file_hash);
}

function validate_file_name($file_name) {
    $forbidden_chars = ['/', '\\', ':', '*', '?', '"', '<', '>', '|'];
    foreach ($forbidden_chars as $char) {
        if (strpos($file_name, $char) !== false) {
            return false;
        }
    }
    return true;
}

// 权限检查函数
function user_can_access_file($user_id, $file_info) {
    // 如果文件是公开的，任何人都可以访问
    if ($file_info['hide'] == 0) {
        return true;
    }
    
    // 如果文件属于当前用户，可以访问
    if ($user_id && $file_info['uid'] == $user_id) {
        return true;
    }
    
    // 如果是管理员，可以访问
    global $userrow;
    if ($userrow && $userrow['level'] > 0) {
        return true;
    }
    
    // 检查临时会话ID（未登录用户上传的文件）
    if (!$user_id && isset($_SESSION['fileids']) && in_array($file_info['id'], $_SESSION['fileids'])) {
        return true;
    }
    
    return false;
}

// 上传限制检查
function check_upload_limits($user_id, $conf) {
    if ($conf['upload_limit'] > 0) {
        $today_start = date("Y-m-d 00:00:00");
        global $DB, $clientip;
        
        $upload_count = 0;
        if ($user_id) {
            $upload_count = $DB->getColumn("SELECT COUNT(*) FROM pre_file WHERE uid=? AND addtime >= ?", [$user_id, $today_start]);
        } else {
            $upload_count = $DB->getColumn("SELECT COUNT(*) FROM pre_file WHERE ip=? AND addtime >= ?", [$clientip, $today_start]);
        }
        
        return $upload_count < $conf['upload_limit'];
    }
    
    return true; // 如果没有限制，则允许上传
}

// 内容类型检查
function validate_file_type($file_ext, $conf) {
    if (!empty($conf['type_block'])) {
        $blocked_types = explode('|', $conf['type_block']);
        if (in_array(strtolower($file_ext), $blocked_types)) {
            return false;
        }
    }
    
    return true;
}

// 文件名关键词检查
function validate_file_name_keywords($file_name, $conf) {
    if (!empty($conf['name_block'])) {
        $blocked_keywords = explode('|', $conf['name_block']);
        foreach ($blocked_keywords as $keyword) {
            if (strpos(strtolower($file_name), strtolower($keyword)) !== false) {
                return false;
            }
        }
    }
    
    return true;
}

// 获取用户文件统计
function get_user_file_stats($user_id) {
    global $DB;
    
    $total_files = $DB->getColumn("SELECT COUNT(*) FROM pre_file WHERE uid = ?", [$user_id]);
    $total_size = $DB->getColumn("SELECT COALESCE(SUM(size), 0) FROM pre_file WHERE uid = ?", [$user_id]);
    $public_files = $DB->getColumn("SELECT COUNT(*) FROM pre_file WHERE uid = ? AND hide = 0", [$user_id]);
    $private_files = $DB->getColumn("SELECT COUNT(*) FROM pre_file WHERE uid = ? AND hide = 1", [$user_id]);
    
    return [
        'total_files' => $total_files,
        'total_size' => $total_size,
        'public_files' => $public_files,
        'private_files' => $private_files
    ];
}

// 检查用户权限级别
function has_admin_privileges($userrow) {
    return isset($userrow['level']) && $userrow['level'] > 0;
}

// 生成安全的文件访问令牌
function generate_file_access_token($file_hash, $user_id, $expires_in = 3600) {
    $token_data = [
        'file_hash' => $file_hash,
        'user_id' => $user_id,
        'expires' => time() + $expires_in
    ];
    
    return authcode(json_encode($token_data), 'ENCODE', SYS_KEY);
}

// 验证文件访问令牌
function validate_file_access_token($token, $file_hash) {
    $token_data = json_decode(authcode($token, 'DECODE', SYS_KEY), true);
    
    if (!$token_data || !isset($token_data['file_hash']) || !isset($token_data['expires'])) {
        return false;
    }
    
    if ($token_data['file_hash'] !== $file_hash || $token_data['expires'] < time()) {
        return false;
    }
    
    return true;
}

// 获取文件类型分组
function get_file_type_group($file_ext) {
    $image_types = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    $video_types = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'f4v', 'webm', '3gp', '3gpp'];
    $audio_types = ['mp3', 'wav', 'flac', 'aac', 'ogg'];
    $document_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
    $archive_types = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'];
    
    $ext = strtolower($file_ext);
    
    if (in_array($ext, $image_types)) return 'image';
    if (in_array($ext, $video_types)) return 'video';
    if (in_array($ext, $audio_types)) return 'audio';
    if (in_array($ext, $document_types)) return 'document';
    if (in_array($ext, $archive_types)) return 'archive';
    
    return 'other';
}

// 转换文件大小为人类可读格式
function format_file_size_readable($size_in_bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $unit_index = 0;
    $size = $size_in_bytes;
    
    while ($size >= 1024 && $unit_index < count($units) - 1) {
        $size /= 1024;
        $unit_index++;
    }
    
    return round($size, 2) . ' ' . $units[$unit_index];
}

// 增强版文件上传验证
function validate_file_upload($file_info, $conf, $user_id) {
    $errors = [];
    
    // 检查文件大小
    if ($conf['upload_size'] > 0 && $file_info['size'] > $conf['upload_size'] * 1024 * 1024) {
        $errors[] = '文件大小超过限制 (' . $conf['upload_size'] . 'MB)';
    }
    
    // 检查文件类型
    $file_ext = get_file_ext($file_info['name']);
    if (!validate_file_type($file_ext, $conf)) {
        $errors[] = '文件类型不被允许';
    }
    
    // 检查文件名关键词
    if (!validate_file_name_keywords($file_info['name'], $conf)) {
        $errors[] = '文件名包含不允许的关键词';
    }
    
    // 检查文件名格式
    if (!validate_file_name($file_info['name'])) {
        $errors[] = '文件名包含非法字符';
    }
    
    // 检查上传限制
    if (!check_upload_limits($user_id, $conf)) {
        $errors[] = '今日上传数量已达到限制';
    }
    
    return $errors;
}