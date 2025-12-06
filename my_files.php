<?php
include("./includes/common.php");

if (!$islogin2) {
    header("Location: login.php");
    exit;
}

// 确保登录用户的所有临时文件都被正确归属
if ($islogin2 && isset($_SESSION['fileids']) && !empty($_SESSION['fileids'])) {
    $file_ids = array_reverse($_SESSION['fileids']);
    if (count($file_ids) > 60) {
        $file_ids = array_splice($file_ids, 0, 60);
    }
    if (!empty($file_ids)) {
        // 确保只更新 uid=0 的文件，防止重复更新其他用户的文件
        $file_ids_str = implode(',', array_map('intval', $file_ids));
        $DB->exec("UPDATE pre_file SET uid=? WHERE id IN ($file_ids_str) AND uid=0", [$uid]);
    }
}

$title = '我的文件 - ' . $conf['title'];
include SYSTEM_ROOT.'header.php';

// 处理删除文件请求
if (isset($_POST['action']) && $_POST['action'] == 'delete' && isset($_POST['hash']) && isset($_POST['csrf_token'])) {
    if ($_POST['csrf_token'] != $_SESSION['csrf_token']) {
        $result = ['code' => 1, 'msg' => 'CSRF令牌验证失败'];
    } else {
        $hash = $_POST['hash'];
        $file = $DB->getRow("SELECT * FROM pre_file WHERE hash = ? AND uid = ?", [$hash, $uid]);
        if ($file) {
            // 删除文件记录
            $DB->exec("DELETE FROM pre_file WHERE hash = ?", [$hash]);
            
            // 删除实际文件（如果使用本地存储）
            $stor = \lib\StorHelper::getModel($conf['storage']);
            $stor->delFile($hash);
            
            $result = ['code' => 0, 'msg' => '文件删除成功'];
        } else {
            $result = ['code' => 1, 'msg' => '文件不存在或无权删除'];
        }
    }
    
    if (!empty($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
}

$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;

// 分页处理
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page = $page < 1 ? 1 : $page;
$pagesize = 15; // 每页显示15个文件
$offset = ($page - 1) * $pagesize;

// 再次确保所有临时文件都已归属到当前用户
if (isset($_SESSION['fileids']) && !empty($_SESSION['fileids'])) {
    $file_ids = array_reverse($_SESSION['fileids']);
    if (count($file_ids) > 60) {
        $file_ids = array_splice($file_ids, 0, 60);
    }
    if (!empty($file_ids)) {
        $file_ids_str = implode(',', array_map('intval', $file_ids));
        $DB->exec("UPDATE pre_file SET uid=? WHERE id IN ($file_ids_str) AND uid=0", [$uid]);
    }
}

// 获取用户的所有文件
$user_files = $DB->getAll("SELECT * FROM pre_file WHERE uid = ? ORDER BY addtime DESC LIMIT ?, ?", [$uid, $offset, $pagesize]);
$total_files = $DB->getColumn("SELECT COUNT(*) FROM pre_file WHERE uid = ?", [$uid]);
$total_pages = ceil($total_files / $pagesize);

// 获取用户文件总数
$user_files_count = $DB->getColumn("SELECT COUNT(*) FROM pre_file WHERE uid = ?", [$uid]);

// 检查当前页面是否有文件
$has_files = !empty($user_files);
?>
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">我的文件 (<?php echo $user_files_count; ?>个文件)</h3>
                </div>
                <div class="panel-body">
                    <?php if ($user_files_count > 0): ?>
                        <?php if ($user_files && count($user_files) > 0): // 检查当前分页是否有文件 ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>文件名</th>
                                        <th>类型</th>
                                        <th>大小</th>
                                        <th>上传时间</th>
                                        <th>下载次数</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_files as $file): ?>
                                    <tr>
                                        <td style="word-break: break-all;">
                                            <i class="fa <?php echo type_to_icon($file['type']); ?> fa-fw"></i>
                                            <?php echo htmlspecialchars($file['name']); ?>
                                        </td>
                                        <td><?php echo strtoupper($file['type']); ?></td>
                                        <td><?php echo size_format($file['size']); ?></td>
                                        <td><?php echo $file['addtime']; ?></td>
                                        <td><?php echo $file['count']; ?></td>
                                        <td>
                                            <a href="./file.php?hash=<?php echo $file['hash']; ?>" class="btn btn-xs btn-info" title="查看文件">
                                                <i class="fa fa-eye"></i> 查看
                                            </a>
                                            <a href="./down.php/<?php echo $file['hash']; ?>.<?php echo $file['type'] ?: 'file'; ?>" class="btn btn-xs btn-primary" title="下载文件">
                                                <i class="fa fa-download"></i> 下载
                                            </a>
                                            <button class="btn btn-xs btn-danger" onclick="deleteFile('<?php echo $file['hash']; ?>', this)" title="删除文件">
                                                <i class="fa fa-trash"></i> 删除
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- 分页 -->
                        <?php if ($total_pages > 1): ?>
                        <nav>
                            <ul class="pagination">
                                <?php
                                $start = max(1, $page - 5);
                                $end = min($total_pages, $page + 5);

                                if ($start > 1): ?>
                                    <li><a href="?page=1">首页</a></li>
                                    <li class="<?php echo $page == 1 ? 'disabled' : ''; ?>"><a href="<?php echo $page == 1 ? '#' : '?page=' . ($page - 1); ?>">&laquo;</a></li>
                                <?php endif; ?>

                                <?php for ($i = $start; $i <= $end; $i++): ?>
                                    <li class="<?php echo $i == $page ? 'active' : ''; ?>"><a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                                <?php endfor; ?>

                                <?php if ($end < $total_pages): ?>
                                    <li class="<?php echo $page == $total_pages ? 'disabled' : ''; ?>"><a href="<?php echo $page == $total_pages ? '#' : '?page=' . ($page + 1); ?>">&raquo;</a></li>
                                    <li><a href="?page=<?php echo $total_pages; ?>">末页</a></li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        <?php else: ?>
                        <?php if ($page > $total_pages): ?>
                        <!-- 当前页面超出总页数 -->
                        <p>您访问的页面超出范围。</p>
                        <p><a href="?page=<?php echo $total_pages; ?>">跳转到最后一页</a> 或 <a href="?page=1">返回第一页</a></p>
                        <?php else: ?>
                        <!-- 用户确实有文件，但当前页面没有显示，应该是数据同步问题已解决 -->
                        <p>没有更多文件了。</p>
                        <?php endif; ?>
                        <?php endif; ?>
                    <?php else: ?>
                    <p>您还没有上传任何文件。</p>
                    <p><a href="./upload.php" class="btn btn-primary">立即上传文件</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
function deleteFile(hash, element) {
    var confirmobj = layer.confirm('删除文件后不可恢复，确定删除吗？', {
        btn: ['确定','取消'], icon: 0
    }, function(){
        var ii = layer.load(2);
        $.ajax({
            type : 'POST',
            url : './my_files.php',
            data : {action: 'delete', hash: hash, csrf_token: '<?php echo $csrf_token; ?>', ajax: 1},
            dataType : 'json',
            success : function(data) {
                layer.close(ii);
                if(data.code == 0){
                    layer.msg(data.msg, {icon: 1});
                    // 移除表格行
                    $(element).closest('tr').fadeOut(function() {
                        $(this).remove();
                        // 检查是否还有其他行
                        if ($('tbody tr').length === 0) {
                            // 如果没有其他行，刷新页面
                            location.reload();
                        }
                    });
                } else {
                    layer.msg(data.msg, {icon: 2});
                }
            },
            error:function(data){
                layer.close(ii);
                layer.msg('服务器错误');
            }
        });
    }, function(){
        layer.close(confirmobj);
    });
}
</script>
<?php
include SYSTEM_ROOT.'footer.php';
?>