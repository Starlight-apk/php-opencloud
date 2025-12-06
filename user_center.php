<?php
include("./includes/common.php");

if (!$islogin2) {
    header('Location: login.php');
    exit;
}

$title = '用户中心 - ' . $conf['title'];
include SYSTEM_ROOT.'header.php';

// 处理头像上传
if (isset($_POST['action']) && $_POST['action'] == 'update_avatar') {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = $_FILES['avatar']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            if ($_FILES['avatar']['size'] < 5 * 1024 * 1024) { // 5MB限制
                $upload_dir = ROOT . 'upload/avatars/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $filename = $uid . '_' . time() . '.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $filepath)) {
                    $avatar_url = './upload/avatars/' . $filename;
                    
                    // 更新数据库
                    $result = $DB->exec("UPDATE pre_user SET avatar = ? WHERE uid = ?", [$avatar_url, $uid]);
                    if ($result) {
                        $userrow['avatar'] = $avatar_url;
                        $msg = '头像更新成功！';
                    } else {
                        $msg = '更新头像失败：' . $DB->error();
                    }
                } else {
                    $msg = '上传失败';
                }
            } else {
                $msg = '文件大小不能超过5MB';
            }
        } else {
            $msg = '只支持JPG、PNG、GIF格式的图片';
        }
    } else {
        $msg = '请选择图片文件';
    }
}

// 获取当前用户信息
$userrow = $DB->getRow("SELECT * FROM pre_user WHERE uid = ?", [$uid]);

// 获取用户头像URL
$avatar_url = $userrow['avatar'] ?: $userrow['faceimg'] ?: './assets/img/avatar.png';
if (empty($userrow['avatar']) && !empty($userrow['faceimg'])) {
    $avatar_url = $userrow['faceimg'];
}
if (empty($avatar_url) || !file_exists(str_replace('./', ROOT, $avatar_url))) {
    $avatar_url = './assets/img/avatar.png';
}
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 col-lg-6 center-block" style="float: none;">
            <div class="well bs-component">
                <div class="text-center">
                    <h3>用户中心</h3>
                </div>
                
                <?php if (isset($msg)): ?>
                    <div class="alert alert-info"><?php echo $msg; ?></div>
                <?php endif; ?>
                
                <div class="text-center mb-4">
                    <div style="position: relative; display: inline-block;">
                        <img src="<?php echo $avatar_url; ?>" alt="头像" class="img-circle" id="avatar-img" style="width: 100px; height: 100px; object-fit: cover; cursor: pointer;" title="点击更换头像">
                        <input type="file" id="avatar-upload" name="avatar" style="display: none;" accept="image/*">
                    </div>
                    <p><strong><?php echo htmlspecialchars($userrow['nickname']); ?></strong></p>
                </div>
                
                <form method="post" enctype="multipart/form-data" class="form-horizontal" id="avatar-form">
                    <input type="hidden" name="action" value="update_avatar">
                    <div class="form-group">
                        <label class="col-sm-3 control-label">当前头像</label>
                        <div class="col-sm-9">
                            <img src="<?php echo $avatar_url; ?>" alt="当前头像" class="img-thumbnail" id="current-avatar" style="width: 80px; height: 80px; object-fit: cover; cursor: pointer;" title="点击更换头像">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">更换头像</label>
                        <div class="col-sm-9">
                            <input type="file" name="avatar" id="file-input" class="form-control" accept="image/*">
                            <p class="help-block">支持JPG、PNG、GIF格式，文件大小不超过5MB</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-offset-3 col-sm-9">
                            <button type="submit" class="btn btn-primary">更新头像</button>
                            <a href="login.php?logout=1" class="btn btn-warning" onclick="return confirm('是否确定退出登录？')">退出登录</a>
                        </div>
                    </div>
                </form>
                
                <hr>
                
                <div class="text-center">
                    <p>用户ID: <?php echo $uid; ?></p>
                    <p>注册时间: <?php echo $userrow['addtime']; ?></p>
                    <?php if($userrow['lasttime'] != $userrow['addtime']): ?>
                    <p>最后登录: <?php echo $userrow['lasttime']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include SYSTEM_ROOT.'footer.php'; ?>

<script>
document.getElementById('avatar-img').addEventListener('click', function() {
    document.getElementById('avatar-upload').click();
});

document.getElementById('current-avatar').addEventListener('click', function() {
    document.getElementById('file-input').click();
});

document.getElementById('avatar-upload').addEventListener('change', function() {
    if (this.files.length > 0) {
        uploadAvatar(this.files[0]);
    }
});

document.getElementById('file-input').addEventListener('change', function() {
    if (this.files.length > 0) {
        uploadAvatar(this.files[0]);
    }
});

function uploadAvatar(file) {
    var formData = new FormData();
    formData.append('avatar', file);
    formData.append('action', 'update_avatar');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);

    // 显示上传进度
    var loadingElement = document.createElement('div');
    loadingElement.id = 'loading';
    loadingElement.innerHTML = '<div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.7); color: white; padding: 20px; border-radius: 5px; z-index: 9999;">正在上传头像...</div>';
    document.body.appendChild(loadingElement);

    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            document.body.removeChild(loadingElement);
            if (xhr.status === 200) {
                // 重新加载页面以显示新头像（添加时间戳防止缓存）
                window.location.href = window.location.href.split('?')[0] + '?' + Date.now();
            } else {
                alert('上传失败，请重试');
            }
        }
    };

    xhr.send(formData);
}

// 防止表单默认提交，使用AJAX处理
document.getElementById('avatar-form').addEventListener('submit', function(e) {
    e.preventDefault();

    var fileInput = document.getElementById('file-input');
    if (fileInput.files.length > 0) {
        uploadAvatar(fileInput.files[0]);
    } else {
        alert('请选择图片文件');
    }
});
</script>
</body>
</html>