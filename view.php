<?php
// 文件预览页面 - 已重构
$nosession = true;
$nosecu = true;
require_once("./includes/common.php");

// 解析URL路径获取文件信息
$url_segments = explode('/', $_SERVER['PATH_INFO']);
$file_identifier = '';
if (($segment_count = count($url_segments)) > 1) {
    $file_identifier = $url_segments[$segment_count - 1];
}

// 解析密码参数
$extension_parts = explode('&', $file_identifier);
$file_password = '';
if (count($extension_parts) > 1) {
    $file_password = $extension_parts[count($extension_parts) - 1];
    $file_identifier = $extension_parts[0];
}

// 提取文件哈希值
$file_hash = '';
if (strpos($file_identifier, ".")) {
    $file_hash = substr($file_identifier, 0, strpos($file_identifier, "."));
} else {
    $file_hash = $file_identifier;
}

// 查询文件信息
$file_info = $DB->getRow("SELECT * FROM `pre_file` WHERE `hash`=:hash LIMIT 1", [':hash' => $file_hash]);
if (!$file_info) {
    exit('文件不存在');
}

// 检查文件是否被禁用
if ($file_info['block'] >= 1) {
    header("Content-type: " . minetype('gif'));
    readfile(ROOT . 'assets/img/block.gif');
    exit;
}

// 检查文件是否存在于存储服务中
if ($stor->exists($file_info['hash'])) {
    if (is_view($file_info['type'])) {
        // 更新文件访问统计
        $DB->exec("UPDATE `pre_file` SET `lasttime`=NOW(),`count`=`count`+1 WHERE `id`='{$file_info['id']}'");

        // 输出文件内容
        file_output($file_hash, $file_info['type'], $file_info['size'], $file_info['name'], true, isset($_GET['greencheck']));
    }
}
