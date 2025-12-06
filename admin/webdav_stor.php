<?php
/**
 * WebDAV存储管理
**/
define('IN_ADMIN', true);
include("../includes/common.php");

// 检查并创建WebDAV存储表
$table_exists = $DB->getColumn("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'pre_webdav_stor'");

if (!$table_exists) {
    $sql = "CREATE TABLE `pre_webdav_stor` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `url` varchar(500) NOT NULL,
        `username` varchar(255) NOT NULL,
        `password` varchar(255) NOT NULL,
        `root_path` varchar(500) NOT NULL DEFAULT '/',
        `addtime` datetime NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $result = $DB->exec($sql);
    if ($result === false) {
        exit("创建WebDAV存储表失败：" . $DB->error());
    }
}

$title='WebDAV存储管理';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

// 处理添加/编辑WebDAV存储
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'add' || $_POST['action'] == 'edit') {
        $name = trim($_POST['name']);
        $url = trim($_POST['url']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $root_path = trim($_POST['root_path']) ?: '/';
        
        if (empty($name) || empty($url) || empty($username)) {
            $msg = '请填写完整信息';
        } else {
            if ($_POST['action'] == 'add') {
                $sql = "INSERT INTO pre_webdav_stor (name, url, username, password, root_path, addtime) VALUES (?, ?, ?, ?, ?, NOW())";
                $result = $DB->exec($sql, [$name, $url, $username, $password, $root_path]);
            } else { // edit
                $id = intval($_POST['id']);
                if (!empty($password)) {
                    // 更新密码
                    $sql = "UPDATE pre_webdav_stor SET name=?, url=?, username=?, password=?, root_path=? WHERE id=?";
                    $result = $DB->exec($sql, [$name, $url, $username, $password, $root_path, $id]);
                } else {
                    // 不更新密码
                    $sql = "UPDATE pre_webdav_stor SET name=?, url=?, username=?, root_path=? WHERE id=?";
                    $result = $DB->exec($sql, [$name, $url, $username, $root_path, $id]);
                }
            }
            
            if ($result) {
                $msg = $_POST['action'] == 'add' ? 'WebDAV存储添加成功！' : 'WebDAV存储更新成功！';
            } else {
                $msg = '操作失败：' . $DB->error();
            }
        }
    } elseif ($_POST['action'] == 'delete') {
        $id = intval($_POST['id']);
        $result = $DB->exec("DELETE FROM pre_webdav_stor WHERE id=?", [$id]);
        if ($result) {
            $msg = 'WebDAV存储删除成功！';
        } else {
            $msg = '删除失败：' . $DB->error();
        }
    } elseif ($_POST['action'] == 'test') {
        $url = trim($_POST['test_url']);
        $username = trim($_POST['test_username']);
        $password = $_POST['test_password'];
        
        if (empty($url) || empty($username)) {
            $test_result = '请填写完整信息';
            $test_status = 'error';
        } else {
            $test_result = testWebDAVConnection($url, $username, $password);
            $test_status = $test_result === true ? 'success' : 'error';
        }
    }
}

// 测试WebDAV连接函数
function testWebDAVConnection($url, $username, $password) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return "连接错误: " . $error;
    } elseif ($http_code == 200 || $http_code == 401) {
        // 200表示连接成功，401表示认证失败但连接成功
        return true;
    } else {
        return "HTTP错误: " . $http_code;
    }
}

// 获取所有WebDAV存储
$storages = $DB->getAll("SELECT * FROM pre_webdav_stor ORDER BY id DESC");
?>

<div class="container" style="padding-top:70px;">
<div class="col-xs-12 col-sm-10 col-lg-12 center-block" style="float: none;">

<?php if (isset($msg)) {
    if (strpos($msg, '成功') !== false) {
        echo '<div class="alert alert-success">'.$msg.'</div>';
    } else {
        echo '<div class="alert alert-danger">'.$msg.'</div>';
    }
}?>

<div class="panel panel-info">
    <div class="panel-heading"><h3 class="panel-title">WebDAV存储管理</h3></div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-5">
                <form method="post" class="form-horizontal" role="form">
                    <input type="hidden" name="action" value="add">
                    <h4>添加新的WebDAV存储</h4>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">存储名称</label>
                        <div class="col-sm-9"><input type="text" name="name" value="" class="form-control" required placeholder="如：我的云盘"/></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">WebDAV地址</label>
                        <div class="col-sm-9"><input type="url" name="url" value="" class="form-control" required placeholder="如：https://dav.jianguoyun.com/dav/"/></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">用户名</label>
                        <div class="col-sm-9"><input type="text" name="username" value="" class="form-control" required placeholder="WebDAV用户名"/></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">密码</label>
                        <div class="col-sm-9"><input type="password" name="password" value="" class="form-control" required placeholder="WebDAV密码"/></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">根目录路径</label>
                        <div class="col-sm-9"><input type="text" name="root_path" value="/" class="form-control" placeholder="如：/ 或 /MyFolder/" required/></div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-offset-3 col-sm-9"><input type="submit" value="添加" class="btn btn-success btn-block"/></div>
                    </div>
                </form>
                
                <hr>
                
                <form method="post" class="form-horizontal" role="form" id="testForm">
                    <h4>测试WebDAV连接</h4>
                    <input type="hidden" name="action" value="test">
                    <div class="form-group">
                        <label class="col-sm-3 control-label">WebDAV地址</label>
                        <div class="col-sm-9"><input type="url" name="test_url" id="test_url" class="form-control" placeholder="如：https://dav.jianguoyun.com/dav/" required/></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">用户名</label>
                        <div class="col-sm-9"><input type="text" name="test_username" id="test_username" class="form-control" placeholder="WebDAV用户名" required/></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">密码</label>
                        <div class="col-sm-9"><input type="password" name="test_password" id="test_password" class="form-control" placeholder="WebDAV密码" required/></div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-offset-3 col-sm-9"><input type="button" value="测试连接" class="btn btn-info btn-block" onclick="testConnection()"/></div>
                    </div>
                </form>
                
                <div id="testResult" style="display: none;" class="alert"></div>
            </div>
            
            <div class="col-md-7">
                <h4>已配置的WebDAV存储</h4>
                <?php if ($storages): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>名称</th>
                                <th>地址</th>
                                <th>根路径</th>
                                <th>添加时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($storages as $storage): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($storage['name']) ?></td>
                                <td><?php echo htmlspecialchars(substr($storage['url'], 0, 30)) ?>...</td>
                                <td><?php echo htmlspecialchars($storage['root_path']) ?></td>
                                <td><?php echo $storage['addtime'] ?></td>
                                <td>
                                    <a href="javascript:editWebDAV(<?php echo $storage['id'] ?>, '<?php echo addslashes($storage['name']) ?>', '<?php echo addslashes($storage['url']) ?>', '<?php echo addslashes($storage['username']) ?>', '<?php echo addslashes($storage['root_path']) ?>')" class="btn btn-xs btn-info">编辑</a>
                                    <a href="javascript:deleteWebDAV(<?php echo $storage['id'] ?>)" class="btn btn-xs btn-danger">删除</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p>暂无配置的WebDAV存储</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>
</div>

<!-- 编辑存储的模态框 -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">编辑WebDAV存储</h4>
            </div>
            <form method="post" class="form-horizontal" role="form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id" value="">
                    <div class="form-group">
                        <label class="col-sm-3 control-label">存储名称</label>
                        <div class="col-sm-9"><input type="text" name="name" id="edit_name" class="form-control" required/></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">WebDAV地址</label>
                        <div class="col-sm-9"><input type="url" name="url" id="edit_url" class="form-control" required/></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">用户名</label>
                        <div class="col-sm-9"><input type="text" name="username" id="edit_username" class="form-control" required/></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">密码</label>
                        <div class="col-sm-9"><input type="password" name="password" id="edit_password" class="form-control" placeholder="留空则不修改密码"/></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">根目录路径</label>
                        <div class="col-sm-9"><input type="text" name="root_path" id="edit_root_path" class="form-control" required/></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                    <input type="submit" value="保存" class="btn btn-primary"/>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
function editWebDAV(id, name, url, username, root_path) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_url').value = url;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_root_path').value = root_path;
    document.getElementById('edit_password').value = '';
    $('#editModal').modal('show');
}

function deleteWebDAV(id) {
    layer.confirm('确定要删除这个WebDAV存储吗？', {
        btn: ['确定','取消']
    }, function(){
        $.post('', {action: 'delete', id: id}, function(data) {
            if (data.indexOf('成功') !== -1) {
                layer.msg('删除成功', {icon: 1});
                setTimeout(function() { window.location.reload(); }, 1000);
            } else {
                layer.msg('删除失败', {icon: 2});
            }
        });
    });
}

function testConnection() {
    var url = document.getElementById('test_url').value;
    var username = document.getElementById('test_username').value;
    var password = document.getElementById('test_password').value;
    
    if (!url || !username || !password) {
        layer.msg('请填写完整信息', {icon: 2});
        return;
    }
    
    var ii = layer.load(2, {shade:[0.1,'#fff']});
    
    $.ajax({
        type: 'POST',
        url: '',
        data: {action: 'test', test_url: url, test_username: username, test_password: password},
        dataType: 'json',
        success: function(data) {
            layer.close(ii);
            var $result = $('#testResult');
            if (data.status === 'success') {
                $result.removeClass('alert-danger').addClass('alert-success').show().html('连接测试成功！');
            } else {
                $result.removeClass('alert-success').addClass('alert-danger').show().html('连接测试失败：' + data.msg);
            }
        },
        error: function() {
            layer.close(ii);
            $('#testResult').removeClass('alert-success').addClass('alert-danger').show().html('请求失败');
        }
    });
}
</script>
</body>
</html>