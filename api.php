<?php
// API接口 - 已重构
$nosession = true;
$nosecu = true;
require_once("./includes/common.php");

// API响应函数
function send_api_response($data, $format='json'){
    $format = isset($_POST['format']) ? $_POST['format'] : 'json';

    switch ($format) {
        case 'json':
            header('Content-Type: application/json; charset=UTF-8');
            exit(json_encode($data));

        case 'jsonp':
            $callback = isset($_POST['callback']) ? $_POST['callback'] : 'callback';
            header('Content-Type: application/javascript; charset=UTF-8');
            exit($callback . '(' . json_encode($data) . ')');

        default:
            header('Content-Type: text/html; charset=UTF-8');
            if ($data['code'] == 0) {
                $redirect_url = isset($_POST['backurl']) ? $_POST['backurl'] : $_SERVER['HTTP_REFERER'];
                $response_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>文件上传完成</title>
</head>
<body>
    <form action="' . $redirect_url . '" method="post">
        <input type="hidden" name="file" value="' . $data['downurl'] . '" />
        <input type="hidden" name="type" value="' . $data['type'] . '" />
        <input type="hidden" name="name" value="' . $data['name'] . '" />
        <input type="submit" name="submit" value="下一步" />
    </form>
</body>
</html>';
                exit($response_html);
            } else {
                sysmsg($data['msg']);
            }
    }
}

// 检查API是否开启
if (!$conf['api_open']) {
    send_api_response(['code' => -4, 'msg' => '当前站点未开启API接口']);
}

// 检查来源
if (!empty($conf['api_referer'])) {
    $allowed_referers = explode('|', $conf['api_referer']);
    $referer_url = parse_url($_SERVER['HTTP_REFERER']);
    $referer_host = $referer_url['host'] ?? '';

    if (!in_array($referer_host, $allowed_referers)) {
        send_api_response(['code' => -4, 'msg' => '来源地址不被允许']);
    }
}

// 检查是否有文件上传
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    send_api_response(['code' => -1, 'msg' => '请选择要上传的文件']);
}

// 获取文件信息
$file_name = trim(htmlspecialchars($_FILES['file']['name']));
$file_size = intval($_FILES['file']['size']);
$is_public = $_POST['show'] == 1 ? 0 : 1; // 0为公开，1为私有
$has_password = intval($_POST['ispwd']);
$file_password = $has_password == 1 ? trim(htmlspecialchars($_POST['pwd'])) : null;

// 文件名清理
$file_name = preg_replace('/[\/\\\:\*\?"<>|]/', '', $file_name);
if (empty($file_name)) {
    send_api_response(['code' => -1, 'msg' => '文件名不能为空']);
}

// 密码验证
if ($has_password == 1 && !empty($file_password)) {
    if (!preg_match('/^[a-zA-Z0-9]+$/', $file_password)) {
        send_api_response(['code' => -1, 'msg' => '文件密码只能包含字母和数字']);
    }
}

// 获取文件扩展名
$file_extension = get_file_ext($file_name);

// 检查文件类型限制
if ($conf['type_block']) {
    $blocked_types = explode('|', $conf['type_block']);
    if (in_array($file_extension, $blocked_types)) {
        send_api_response(['code' => -1, 'msg' => '该文件类型不允许上传', 'error' => 'block']);
    }
}

// 检查文件名包含的关键词
if ($conf['name_block']) {
    $blocked_keywords = explode('|', $conf['name_block']);
    foreach ($blocked_keywords as $keyword) {
        if (strpos($file_name, $keyword) !== false) {
            send_api_response(['code' => -1, 'msg' => '文件名包含不允许的关键词', 'error' => 'block']);
        }
    }
}

// 计算文件哈希值
$file_hash = md5_file($_FILES['file']['tmp_name']);

// 检查是否已存在相同的文件
$existing_file = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash' => $file_hash]);

if ($existing_file) {
    // 文件已存在，返回已存在的文件信息
    unset($_SESSION['csrf_token']);
    $download_url = $siteurl . 'down.php/' . $existing_file['hash'] . '.' . $existing_file['type'];
    if (!empty($existing_file['pwd'])) {
        $download_url .= '&password=' . $existing_file['pwd'];
    }

    $result = [
        'code' => 0,
        'msg' => '系统中已存在该文件',
        'exists' => 1,
        'hash' => $file_hash,
        'name' => $file_name,
        'size' => $file_size,
        'type' => $file_extension,
        'id' => $existing_file['id'],
        'downurl' => $download_url
    ];

    if (is_view($existing_file['type'])) {
        $result['viewurl'] = $siteurl . 'view.php/' . $file_hash . '.' . $existing_file['type'];
    }

    send_api_response($result);
}

// 上传到存储服务
$upload_result = $stor->upload($file_hash, $_FILES['file']['tmp_name'], minetype($file_extension));
if (!$upload_result) {
    send_api_response(['code' => -1, 'msg' => '文件上传到存储服务失败', 'error' => 'storage']);
}

// 插入数据库记录
$insert_result = $DB->exec(
    "INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`hide`,`pwd`)
     VALUES (:name,:type,:size,:hash,NOW(),:ip,:hide,:pwd)",
    [
        ':name' => $file_name,
        ':type' => $file_extension,
        ':size' => $file_size,
        ':hash' => $file_hash,
        ':ip' => $clientip,
        ':hide' => $is_public,
        ':pwd' => $file_password
    ]
);

if (!$insert_result) {
    send_api_response(['code' => -1, 'msg' => '文件信息保存失败: ' . $DB->error(), 'error' => 'database']);
}

$new_file_id = $DB->lastInsertId();

// 内容审核检查
$image_types = explode('|', $conf['type_image']);
$video_types = explode('|', $conf['type_video']);

if ($conf['green_check'] > 0 && in_array($file_extension, $image_types)) {
    if (checkImage($file_hash, $file_extension)) {
        $DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`='{$new_file_id}' LIMIT 1");
    }
}

if ($conf['videoreview'] == 1 && in_array($file_extension, $video_types)) {
    $DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`='{$new_file_id}' LIMIT 1");
}

// 生成下载链接
$download_url = $siteurl . 'down.php/' . $file_hash . '.' . $file_extension;
if (!empty($file_password)) {
    $download_url .= '&password=' . $file_password;
}

// 返回成功响应
$result = [
    'code' => 0,
    'msg' => '文件上传成功！',
    'exists' => 0,
    'hash' => $file_hash,
    'name' => $file_name,
    'size' => $file_size,
    'type' => $file_extension,
    'id' => $new_file_id,
    'downurl' => $download_url
];

if (is_view($file_extension)) {
    $result['viewurl'] = $siteurl . 'view.php/' . $file_hash . '.' . $file_extension;
}

send_api_response($result);
