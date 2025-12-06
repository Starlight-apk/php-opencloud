<?php
// AJAX接口 - 已重构
$nosecu = true;
require_once("./includes/common.php");
require_once("./includes/dynamic_db_helper.php");

$action = isset($_GET['act']) ? daddslashes($_GET['act']) : null;

if (!checkRefererHost()) {
    exit('{"code":403,"msg":"非法请求来源"}');
}

header('Content-Type: application/json; charset=UTF-8');

// 管理员权限提升
if ($islogin2 && $userrow['level'] > 0) {
    $conf['upload_limit'] = 0;
    $conf['videoreview'] = 0;
    $conf['type_block'] = null;
    $conf['name_block'] = null;
}

// 创建动态数据库连接管理器
$dbManager = new DynamicDBHelper($DB);

// 尝试连接备用数据库（仅在上传操作时）
if ($action === 'pre_upload' || $action === 'upload_part' || $action === 'complete_upload') {
    $dbManager->connectBackupDB();
    $DB = $dbManager->getDB();
}

switch ($action) {
    case 'pre_upload':
        // CSRF验证
        if (!$_POST['csrf_token'] || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            exit('{"code":-1,"msg":"CSRF验证失败"}');
        }

        // 登录验证
        if ($conf['forcelogin'] == 1 && !$islogin2) {
            exit('{"code":-1,"msg":"请先登录"}');
        }

        $file_name = trim(htmlspecialchars($_POST['name']));
        $file_hash = trim($_POST['hash']);
        $file_size = intval($_POST['size']);
        $is_public = $_POST['show'] == 1 ? 0 : 1; // 0为公开，1为私有
        $has_password = intval($_POST['ispwd']);
        $file_password = $has_password == 1 ? trim(htmlspecialchars($_POST['pwd'])) : null;

        // 文件名清理
        $file_name = preg_replace('/[\/\\\:\*\?"<>|]/', '', $file_name);
        if (empty($file_name)) {
            exit('{"code":-1,"msg":"文件名不能为空"}');
        }

        // 文件哈希验证
        if (!preg_match('/^[0-9a-z]{32}$/i', $file_hash)) {
            exit('{"code":-1,"msg":"文件哈希值格式错误"}');
        }

        // 密码验证
        if ($has_password == 1 && !empty($file_password)) {
            if (!preg_match('/^[a-zA-Z0-9]+$/', $file_password)) {
                exit('{"code":-1,"msg":"文件密码只能为字母和数字"}');
            }
        }

        $file_ext = get_file_ext($file_name);

        // 文件类型限制检查
        if ($conf['type_block']) {
            $blocked_types = explode('|', $conf['type_block']);
            if (in_array($file_ext, $blocked_types)) {
                exit('{"code":-1,"msg":"文件上传失败，不支持该格式","error":"block"}');
            }
        }

        // 文件名关键词限制检查
        if ($conf['name_block']) {
            $blocked_keywords = explode('|', $conf['name_block']);
            foreach ($blocked_keywords as $keyword) {
                if (strpos($file_name, $keyword) !== false) {
                    exit('{"code":-1,"msg":"文件上传失败","error":"block"}');
                }
            }
        }

        // 检查是否为视频上传并应用特定限制
        $upload_limit_size = intval($conf['upload_size']);
        if (isset($_POST['title'])) {
            $upload_limit_size = intval($conf['video_upload_size'] ?? $conf['upload_size']);
            $allowed_video_exts = explode('|', $conf['video_extensions'] ?? 'mp4|mov|avi|wmv|flv|f4v|webm|3gp|3gpp');
            if (!in_array(strtolower($file_ext), $allowed_video_exts)) {
                exit('{"code":-1,"msg":"不支持的视频格式","error":"block"}');
            }
        }

        // 文件大小限制检查
        if ($upload_limit_size > 0 && $file_size > $upload_limit_size * 1024 * 1024) {
            exit('{"code":-1,"msg":"文件大小超过限制(' . $upload_limit_size . 'MB)"}');
        }

        // 上传数量限制检查
        if ($conf['upload_limit'] > 0) {
            $today_start = date("Y-m-d 00:00:00");
            $upload_count = 0;
            if ($islogin2) {
                $upload_count = $DB->getColumn("SELECT COUNT(*) FROM pre_file WHERE uid='$uid' AND addtime>='$today_start'");
            } else {
                $upload_count = $DB->getColumn("SELECT COUNT(*) FROM pre_file WHERE ip='$clientip' AND addtime>='$today_start'");
            }
            if ($upload_count > $conf['upload_limit']) {
                exit('{"code":-1,"msg":"今日上传文件数量已超限制"}');
            }
        }

        // 检查文件是否已存在
        $existing_file = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash' => $file_hash]);
        if ($existing_file) {
            unset($_SESSION['csrf_token']);
            $response = [
                'code' => 1,
                'msg' => '系统中已存在该文件',
                'exists' => 1,
                'hash' => $file_hash,
                'name' => $file_name,
                'size' => $file_size,
                'type' => $file_ext,
                'id' => $existing_file['id']
            ];
            exit(json_encode($response));
        }

        // 处理云存储上传
        if (\lib\StorHelper::is_cloud() && $conf['uploadfile_type'] == 1) {
            $upload_params = $stor->getUploadParam($file_hash, $file_name, $upload_limit_size * 1024 * 1024);
            if (!$upload_params) {
                exit('{"code":-1,"msg":"获取上传参数失败","errmsg":"' . $stor->errmsg() . '"}');
            }

            $_SESSION['upload'] = [
                'chunks' => 1,
                'name' => $file_name,
                'hash' => $file_hash,
                'size' => $file_size,
                'ext' => $file_ext,
                'hide' => $is_public,
                'pwd' => $file_password
            ];

            // 添加视频特定字段
            if (isset($_POST['title'])) {
                $_SESSION['upload']['title'] = trim(htmlspecialchars($_POST['title']));
                $_SESSION['upload']['description'] = trim(htmlspecialchars($_POST['description']));
            }

            $response = [
                'code' => 0,
                'third' => true,
                'hash' => $file_hash,
                'url' => $upload_params['url'],
                'post' => $upload_params['post']
            ];
            exit(json_encode($response));
        } else {
            // 本地分块上传
            $chunk_size = 8 * 1024 * 1024; // 8MB每块
            $total_chunks = ceil($file_size / $chunk_size);

            $_SESSION['upload'] = [
                'chunks' => $total_chunks,
                'name' => $file_name,
                'hash' => $file_hash,
                'size' => $file_size,
                'ext' => $file_ext,
                'hide' => $is_public,
                'pwd' => $file_password
            ];

            // 添加视频特定字段
            if (isset($_POST['title'])) {
                $_SESSION['upload']['title'] = trim(htmlspecialchars($_POST['title']));
                $_SESSION['upload']['description'] = trim(htmlspecialchars($_POST['description']));
            }

            $response = [
                'code' => 0,
                'third' => false,
                'hash' => $file_hash,
                'chunksize' => $chunk_size,
                'chunks' => $total_chunks
            ];
            exit(json_encode($response));
        }
        break;

    case 'upload_part':
        // 检查文件是否已上传
        if (!isset($_FILES['file'])) {
            exit('{"code":-1,"msg":"请选择上传文件"}');
        }

        // CSRF验证
        if (!$_POST['csrf_token'] || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            exit('{"code":-1,"msg":"CSRF验证失败"}');
        }

        // 登录验证
        if ($conf['forcelogin'] == 1 && !$islogin2) {
            exit('{"code":-1,"msg":"请先登录"}');
        }

        $chunk_number = intval($_POST['chunk']);
        $file_hash = trim($_POST['hash']);

        // 会话验证
        if (!$_SESSION['upload'] || !$_SESSION['upload']['hash'] || $_SESSION['upload']['hash'] !== $file_hash) {
            exit('{"code":-1,"msg":"参数校验失败，请刷新页面重试"}');
        }

        // 文件哈希验证
        if (!preg_match('/^[0-9a-z]{32}$/i', $file_hash)) {
            exit('{"code":-1,"msg":"文件哈希值格式错误"}');
        }

        $total_chunks = intval($_SESSION['upload']['chunks']);
        $file_ext = $_SESSION['upload']['ext'];

        if ($total_chunks > 1) {
            // 分块上传处理
            $temp_file_path = sys_get_temp_dir() . '/' . $file_hash . '.part' . $chunk_number;

            if (!move_uploaded_file($_FILES['file']['tmp_name'], $temp_file_path)) {
                exit('{"code":-1,"msg":"文件分块' . $chunk_number . '上传失败"}');
            }

            if ($total_chunks == $chunk_number) {
                // 最后一块，合并文件
                $merged_file_path = file_part_merge($file_hash, $total_chunks);
                $actual_hash = md5_file($merged_file_path);
                $actual_size = filesize($merged_file_path);

                $upload_result = $stor->savefile($file_hash, $merged_file_path, minetype($file_ext));
                if (!$upload_result) {
                    exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"' . $stor->errmsg() . '"}');
                }
            } else {
                // 中间块，返回成功信息
                $response = ['code' => 0, 'chunk' => $chunk_number];
                exit(json_encode($response));
            }
        } else {
            // 单文件上传
            $actual_hash = md5_file($_FILES['file']['tmp_name']);
            $actual_size = filesize($_FILES['file']['tmp_name']);

            $upload_result = $stor->upload($file_hash, $_FILES['file']['tmp_name'], minetype($file_ext));
            if (!$upload_result) {
                exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"' . $stor->errmsg() . '"}');
            }
        }

        // 验证文件大小和哈希
        $expected_size = $_SESSION['upload']['size'];
        if ($actual_size != $expected_size) {
            exit('{"code":-1,"msg":"文件大小校验失败"}');
        }
        if ($actual_hash != $file_hash) {
            exit('{"code":-1,"msg":"文件哈希校验失败"}');
        }

        $file_name = $_SESSION['upload']['name'];
        $is_public = $_SESSION['upload']['hide'];
        $file_password = $_SESSION['upload']['pwd'];

        // 检查文件是否已存在
        $existing_file = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash' => $file_hash]);
        if ($existing_file) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['upload']);
            $response = [
                'code' => 1,
                'msg' => '系统中已存在该文件',
                'exists' => 1,
                'hash' => $file_hash,
                'name' => $file_name,
                'size' => $expected_size,
                'type' => $file_ext,
                'id' => $existing_file['id']
            ];
            exit(json_encode($response));
        }

        // 判断是否为视频上传
        $is_video_upload = isset($_SESSION['upload']['title']);
        if ($is_video_upload) {
            $title = $_SESSION['upload']['title'];
            $description = $_SESSION['upload']['description'];

            // 插入视频记录
            $insert_result = $DB->exec(
                "INSERT INTO `pre_videos` (`user_id`, `title`, `description`, `file_hash`, `file_name`, `file_size`, `type`, `addtime`, `ip`, `hide`, `status`)
                 VALUES (:uid, :title, :description, :hash, :name, :size, :type, NOW(), :ip, :hide, 1)",
                [
                    ':uid' => $uid,
                    ':title' => $title,
                    ':description' => $description,
                    ':hash' => $file_hash,
                    ':name' => $file_name,
                    ':size' => $expected_size,
                    ':type' => $file_ext,
                    ':ip' => $clientip,
                    ':hide' => $is_public
                ]
            );

            if (!$insert_result) {
                exit('{"code":-1,"msg":"上传失败' . $DB->error() . '","error":"database"}');
            }
            $file_id = $DB->lastInsertId();

            // 更新用户视频计数
            $DB->exec("UPDATE pre_user SET videos_count = videos_count + 1 WHERE uid = :uid", [':uid' => $uid]);

            // 视频审核
            $video_types = explode('|', $conf['type_video']);
            if ($conf['videoreview'] == 1 && in_array($file_ext, $video_types)) {
                $DB->exec("UPDATE `pre_videos` SET `status`=2 WHERE `id`='{$file_id}' LIMIT 1");
            }
        } else {
            // 普通文件上传
            $insert_result = $DB->exec(
                "INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`hide`,`pwd`,`uid`)
                 VALUES (:name,:type,:size,:hash,NOW(),:ip,:hide,:pwd,:uid)",
                [
                    ':name' => $file_name,
                    ':type' => $file_ext,
                    ':size' => $expected_size,
                    ':hash' => $file_hash,
                    ':ip' => $clientip,
                    ':hide' => $is_public,
                    ':pwd' => $file_password,
                    ':uid' => ($uid ? $uid : 0)
                ]
            );

            if (!$insert_result) {
                exit('{"code":-1,"msg":"上传失败' . $DB->error() . '","error":"database"}');
            }
            $file_id = $DB->lastInsertId();

            // 内容审核
            $image_types = explode('|', $conf['type_image']);
            $video_types = explode('|', $conf['type_video']);
            if ($conf['green_check'] > 0 && in_array($file_ext, $image_types)) {
                if (checkImage($file_hash, $file_ext)) {
                    $DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`='{$file_id}' LIMIT 1");
                }
            }
            if ($conf['videoreview'] == 1 && in_array($file_ext, $video_types)) {
                $DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`='{$file_id}' LIMIT 1");
            }
        }

        $_SESSION['fileids'][] = $file_id;
        unset($_SESSION['csrf_token']);
        unset($_SESSION['upload']);

        $response = [
            'code' => 1,
            'msg' => '上传成功！',
            'exists' => 0,
            'hash' => $file_hash,
            'name' => $file_name,
            'size' => $expected_size,
            'type' => $file_ext,
            'id' => $file_id
        ];
        exit(json_encode($response));
        break;

    case 'complete_upload':
        // CSRF验证
        if (!$_POST['csrf_token'] || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            exit('{"code":-1,"msg":"CSRF验证失败"}');
        }

        // 登录验证
        if ($conf['forcelogin'] == 1 && !$islogin2) {
            exit('{"code":-1,"msg":"请先登录"}');
        }

        $file_hash = trim($_POST['hash']);

        // 会话验证
        if (!$_SESSION['upload'] || !$_SESSION['upload']['hash'] || $_SESSION['upload']['hash'] !== $file_hash) {
            exit('{"code":-1,"msg":"参数校验失败，请刷新页面重试"}');
        }

        // 文件哈希验证
        if (!preg_match('/^[0-9a-z]{32}$/i', $file_hash)) {
            exit('{"code":-1,"msg":"文件哈希值格式错误"}');
        }

        // 检查文件是否已存储
        if (!$stor->exists($file_hash)) {
            exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"' . $stor->errmsg() . '"}');
        }

        // 获取上传信息
        $file_name = $_SESSION['upload']['name'];
        $file_size = $_SESSION['upload']['size'];
        $file_ext = $_SESSION['upload']['ext'];
        $is_public = $_SESSION['upload']['hide'];
        $file_password = $_SESSION['upload']['pwd'];

        // 检查文件是否已存在
        $existing_file = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash' => $file_hash]);
        if ($existing_file) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['upload']);
            $response = [
                'code' => 1,
                'msg' => '系统中已存在该文件',
                'exists' => 1,
                'hash' => $file_hash,
                'name' => $file_name,
                'size' => $file_size,
                'type' => $file_ext,
                'id' => $existing_file['id']
            ];
            exit(json_encode($response));
        }

        // 判断是否为视频上传
        $is_video_upload = isset($_SESSION['upload']['title']);
        if ($is_video_upload) {
            $title = $_SESSION['upload']['title'];
            $description = $_SESSION['upload']['description'];

            // 插入视频记录
            $insert_result = $DB->exec(
                "INSERT INTO `pre_videos` (`user_id`, `title`, `description`, `file_hash`, `file_name`, `file_size`, `type`, `addtime`, `ip`, `hide`, `status`)
                 VALUES (:uid, :title, :description, :hash, :name, :size, :type, NOW(), :ip, :hide, 1)",
                [
                    ':uid' => $uid,
                    ':title' => $title,
                    ':description' => $description,
                    ':hash' => $file_hash,
                    ':name' => $file_name,
                    ':size' => $file_size,
                    ':type' => $file_ext,
                    ':ip' => $clientip,
                    ':hide' => $is_public
                ]
            );

            if (!$insert_result) {
                exit('{"code":-1,"msg":"上传失败' . $DB->error() . '","error":"database"}');
            }
            $file_id = $DB->lastInsertId();

            // 更新用户视频计数
            $DB->exec("UPDATE pre_user SET videos_count = videos_count + 1 WHERE uid = :uid", [':uid' => $uid]);

            // 视频审核
            $video_types = explode('|', $conf['type_video']);
            if ($conf['videoreview'] == 1 && in_array($file_ext, $video_types)) {
                $DB->exec("UPDATE `pre_videos` SET `status`=2 WHERE `id`='{$file_id}' LIMIT 1");
            }
        } else {
            // 普通文件上传
            $insert_result = $DB->exec(
                "INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`hide`,`pwd`,`uid`)
                 VALUES (:name,:type,:size,:hash,NOW(),:ip,:hide,:pwd,:uid)",
                [
                    ':name' => $file_name,
                    ':type' => $file_ext,
                    ':size' => $file_size,
                    ':hash' => $file_hash,
                    ':ip' => $clientip,
                    ':hide' => $is_public,
                    ':pwd' => $file_password,
                    ':uid' => ($uid ? $uid : 0)
                ]
            );

            if (!$insert_result) {
                exit('{"code":-1,"msg":"上传失败' . $DB->error() . '","error":"database"}');
            }
            $file_id = $DB->lastInsertId();

            // 内容审核
            $image_types = explode('|', $conf['type_image']);
            $video_types = explode('|', $conf['type_video']);
            if ($conf['green_check'] > 0 && in_array($file_ext, $image_types)) {
                if (checkImage($file_hash, $file_ext)) {
                    $DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`='{$file_id}' LIMIT 1");
                }
            }
            if ($conf['videoreview'] == 1 && in_array($file_ext, $video_types)) {
                $DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`='{$file_id}' LIMIT 1");
            }
        }

        $_SESSION['fileids'][] = $file_id;
        unset($_SESSION['csrf_token']);
        unset($_SESSION['upload']);

        $response = [
            'code' => 1,
            'msg' => '上传成功！',
            'exists' => 0,
            'hash' => $file_hash,
            'name' => $file_name,
            'size' => $file_size,
            'type' => $file_ext,
            'id' => $file_id
        ];
        exit(json_encode($response));
        break;

    case 'deleteFile':
        $file_hash = isset($_POST['hash']) ? trim($_POST['hash']) : exit('{"code":-1,"msg":"缺少文件哈希值"}');

        // CSRF验证
        if (!$_POST['csrf_token'] || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            exit('{"code":-1,"msg":"CSRF验证失败"}');
        }

        // 文件哈希格式验证
        if (!preg_match('/^[0-9a-z]{32}$/i', $file_hash)) {
            exit('{"code":-1,"msg":"文件哈希值格式错误"}');
        }

        $file_info = $DB->getRow("SELECT * FROM `pre_file` WHERE `hash`=:hash", [':hash' => $file_hash]);
        if (!$file_info) {
            exit('{"code":-1,"msg":"文件不存在"}');
        }

        // 权限验证
        if ($islogin2 && $file_info['uid'] != $uid || !$islogin2 && (!isset($_SESSION['fileids']) || !in_array($file_info['id'], $_SESSION['fileids']))) {
            exit('{"code":-1,"msg":"权限不足"}');
        }

        // 检查文件是否被冻结
        if ($file_info['block'] == 1) {
            exit('{"code":-1,"msg":"文件已被冻结，无法删除"}');
        }

        // 检查匿名用户是否能删除超过7天的文件
        if (!$islogin2 && strtotime($file_info['addtime']) < strtotime("-7 days")) {
            exit('{"code":-1,"msg":"无法删除7天前的文件"}');
        }

        $delete_result = $stor->delete($file_info['hash']);
        $sql = "DELETE FROM pre_file WHERE id=:id";
        if ($DB->exec($sql, [':id' => $file_info['id']])) {
            exit('{"code":0,"msg":"删除文件成功！"}');
        } else {
            exit('{"code":-1,"msg":"删除文件失败[' . $DB->error() . ']"}');
        }
        break;

    case 'like_video':
        if (!$islogin2) {
            exit('{"code":-1,"msg":"请先登录"}');
        }

        $video_id = intval($_POST['video_id']);
        if (!$video_id) {
            exit('{"code":-1,"msg":"视频ID不能为空"}');
        }

        // 检查是否已点赞
        $existing_like = $DB->getColumn("SELECT id FROM pre_likes WHERE user_id = ? AND video_id = ?", [$uid, $video_id]);
        if ($existing_like) {
            // 取消点赞
            $DB->exec("DELETE FROM pre_likes WHERE user_id = ? AND video_id = ?", [$uid, $video_id]);
            $DB->exec("UPDATE pre_videos SET likes = likes - 1 WHERE id = ?", [$video_id]);
            exit('{"code":0,"liked":false}');
        } else {
            // 添加点赞
            $DB->exec("INSERT INTO pre_likes (user_id, video_id, addtime) VALUES (?, ?, NOW())", [$uid, $video_id]);
            $DB->exec("UPDATE pre_videos SET likes = likes + 1 WHERE id = ?", [$video_id]);
            exit('{"code":0,"liked":true}');
        }
        break;

    case 'comment_video':
        if (!$islogin2) {
            exit('{"code":-1,"msg":"请先登录"}');
        }

        if (!$_POST['csrf_token'] || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            exit('{"code":-1,"msg":"CSRF验证失败"}');
        }

        $video_id = intval($_POST['video_id']);
        $comment_content = trim(htmlspecialchars($_POST['content']));

        if (!$video_id) {
            exit('{"code":-1,"msg":"视频ID不能为空"}');
        }
        if (empty($comment_content)) {
            exit('{"code":-1,"msg":"评论内容不能为空"}');
        }
        if (strlen($comment_content) > 500) {
            exit('{"code":-1,"msg":"评论内容过长"}');
        }

        $DB->exec("INSERT INTO pre_comments (user_id, video_id, content, addtime) VALUES (?, ?, ?, NOW())", [$uid, $video_id, $comment_content]);
        $DB->exec("UPDATE pre_videos SET comments = comments + 1 WHERE id = ?", [$video_id]);
        exit('{"code":0,"msg":"评论成功"}');
        break;

    case 'toggle_follow':
        if (!$islogin2) {
            exit('{"code":-1,"msg":"请先登录"}');
        }

        if (!$_POST['csrf_token'] || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            exit('{"code":-1,"msg":"CSRF验证失败"}');
        }

        $followed_user_id = intval($_POST['user_id']);
        if (!$followed_user_id) {
            exit('{"code":-1,"msg":"用户ID不能为空"}');
        }
        if ($followed_user_id == $uid) {
            exit('{"code":-1,"msg":"不能关注自己"}');
        }

        // 检查是否已关注
        $existing_follow = $DB->getColumn("SELECT id FROM pre_follows WHERE follower_id = ? AND followed_id = ?", [$uid, $followed_user_id]);
        if ($existing_follow) {
            // 取消关注
            $DB->exec("DELETE FROM pre_follows WHERE follower_id = ? AND followed_id = ?", [$uid, $followed_user_id]);
            $DB->exec("UPDATE pre_user SET followers_count = followers_count - 1 WHERE uid = ?", [$followed_user_id]);
            $DB->exec("UPDATE pre_user SET following_count = following_count - 1 WHERE uid = ?", [$uid]);
            exit('{"code":0,"following":false}');
        } else {
            // 添加关注
            $DB->exec("INSERT INTO pre_follows (follower_id, followed_id, addtime) VALUES (?, ?, NOW())", [$uid, $followed_user_id]);
            $DB->exec("UPDATE pre_user SET followers_count = followers_count + 1 WHERE uid = ?", [$followed_user_id]);
            $DB->exec("UPDATE pre_user SET following_count = following_count + 1 WHERE uid = ?", [$uid]);
            exit('{"code":0,"following":true}');
        }
        break;

    default:
        exit('{"code":-4,"msg":"无效的操作参数"}');
        break;
}