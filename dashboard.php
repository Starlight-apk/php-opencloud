<?php
// 仪表盘页面 - 已重构
require_once './includes/common.php';

if (!$islogin2) {
    header("Location: login.php");
    exit;
}

// 用户仪表盘页面
$page_title = '控制面板 - ' . $conf['title'];
include SYSTEM_ROOT.'header.php';

// 获取用户统计信息
$user_file_count = $DB->getColumn("SELECT COUNT(*) FROM pre_file WHERE uid = ?", [$uid]);
$recent_user_files = $DB->getAll("SELECT * FROM pre_file WHERE uid = ? ORDER BY addtime DESC LIMIT 5", [$uid]);
$total_public_files = $DB->getColumn("SELECT COUNT(*) FROM pre_file WHERE hide = 0");
$total_platform_users = $DB->getColumn("SELECT COUNT(*) FROM pre_user");

// 今日上传统计
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$today_uploads = $DB->getColumn("SELECT COUNT(*) FROM pre_file WHERE uid = ? AND addtime BETWEEN ? AND ?", [$uid, $today_start, $today_end]);
?>
<div class="dashboard-wrapper">
    <div class="dashboard-header">
        <h1>欢迎回来，<?php echo htmlspecialchars($userrow['nickname']); ?>！</h1>
        <p>这里是您的个人云存储空间，可以管理您的所有文件</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-icon">
                <i class="fa fa-file-o"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $user_file_count; ?></h3>
                <p>我的文件</p>
            </div>
        </div>

        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fa fa-database"></i>
            </div>
            <div class="stat-content">
                <h3>-</h3>
                <p>已用空间</p>
            </div>
        </div>

        <div class="stat-card success">
            <div class="stat-icon">
                <i class="fa fa-upload"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $today_uploads; ?></h3>
                <p>今日上传</p>
            </div>
        </div>

        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="fa fa-user"></i>
            </div>
            <div class="stat-content">
                <h3>正常</h3>
                <p>账户状态</p>
            </div>
        </div>
    </div>

    <div class="dashboard-content">
        <div class="left-panel">
            <div class="panel">
                <div class="panel-header">
                    <h3><i class="fa fa-clock-o"></i> 最近上传</h3>
                </div>
                <div class="panel-body">
                    <?php if ($recent_user_files && count($recent_user_files) > 0): ?>
                    <div class="file-list">
                        <?php foreach ($recent_user_files as $file): ?>
                        <div class="file-item">
                            <div class="file-icon">
                                <i class="fa <?php echo type_to_icon($file['type']); ?> fa-fw"></i>
                            </div>
                            <div class="file-info">
                                <div class="file-name">
                                    <a href="./file.php?hash=<?php echo $file['hash']; ?>">
                                        <?php echo htmlspecialchars($file['name']); ?>
                                    </a>
                                </div>
                                <div class="file-meta">
                                    <span class="file-size"><?php echo size_format($file['size']); ?></span>
                                    <span class="file-time"><?php echo $file['addtime']; ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <?php if ($user_file_count > 0): ?>
                    <div class="empty-state">
                        <p>最近没有上传的文件</p>
                        <a href="./my_files.php" class="btn btn-primary">查看全部文件</a>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <p>您还没有上传任何文件</p>
                        <a href="./upload.php" class="btn btn-primary">上传第一个文件</a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="right-panel">
            <div class="panel">
                <div class="panel-header">
                    <h3><i class="fa fa-bolt"></i> 快速操作</h3>
                </div>
                <div class="panel-body">
                    <div class="quick-actions">
                        <a href="./upload.php" class="action-btn primary">
                            <i class="fa fa-upload"></i>
                            <span>上传文件</span>
                        </a>

                        <a href="./my_files.php" class="action-btn secondary">
                            <i class="fa fa-folder-open"></i>
                            <span>我的文件</span>
                        </a>

                        <a href="./user_center.php" class="action-btn success">
                            <i class="fa fa-user"></i>
                            <span>用户中心</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3><i class="fa fa-bullhorn"></i> 系统公告</h3>
                </div>
                <div class="panel-body">
                    <?php if (!empty($conf['gonggao'])): ?>
                    <div class="announcement">
                        <?php echo $conf['gonggao']; ?>
                    </div>
                    <?php else: ?>
                    <div class="no-announcement">
                        <p>暂无系统公告</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include SYSTEM_ROOT.'footer.php';
?>

<style>
    .dashboard-wrapper {
        padding: 20px;
        background-color: #f8f9fa;
        min-height: calc(100vh - 60px);
    }

    .dashboard-header {
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 25px;
        text-align: center;
    }

    .dashboard-header h1 {
        margin: 0 0 10px 0;
        color: #2c3e50;
    }

    .dashboard-header p {
        margin: 0;
        color: #7f8c8d;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 20px;
        display: flex;
        align-items: center;
        transition: transform 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-card.primary { border-top: 4px solid #3498db; }
    .stat-card.info { border-top: 4px solid #2980b9; }
    .stat-card.success { border-top: 4px solid #2ecc71; }
    .stat-card.warning { border-top: 4px solid #f39c12; }

    .stat-icon {
        font-size: 2em;
        margin-right: 15px;
        color: #7f8c8d;
    }

    .stat-content h3 {
        margin: 0 0 5px 0;
        font-size: 1.8em;
        color: #2c3e50;
    }

    .stat-content p {
        margin: 0;
        color: #7f8c8d;
        font-size: 0.9em;
    }

    .dashboard-content {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 25px;
    }

    .panel {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden;
        margin-bottom: 25px;
    }

    .panel-header {
        padding: 15px 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #eee;
    }

    .panel-header h3 {
        margin: 0;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .panel-body {
        padding: 20px;
    }

    .file-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .file-item {
        display: flex;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #eee;
    }

    .file-item:last-child {
        border-bottom: none;
    }

    .file-icon {
        font-size: 1.2em;
        margin-right: 10px;
        color: #7f8c8d;
    }

    .file-info {
        flex: 1;
    }

    .file-name a {
        color: #2c3e50;
        text-decoration: none;
        word-break: break-all;
        font-weight: 500;
    }

    .file-name a:hover {
        color: #3498db;
    }

    .file-meta {
        display: flex;
        gap: 15px;
        font-size: 0.85em;
        color: #7f8c8d;
        margin-top: 5px;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #95a5a6;
    }

    .empty-state p {
        margin-bottom: 20px;
    }

    .quick-actions {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .action-btn {
        display: flex;
        align-items: center;
        padding: 15px;
        border-radius: 6px;
        text-decoration: none;
        color: white;
        transition: transform 0.3s;
        text-align: center;
    }

    .action-btn:hover {
        transform: translateX(5px);
        text-decoration: none;
        color: white;
    }

    .action-btn.primary { background: #3498db; }
    .action-btn.secondary { background: #2980b9; }
    .action-btn.success { background: #2ecc71; }

    .action-btn i {
        font-size: 1.2em;
        margin-right: 10px;
    }

    .action-btn span {
        font-weight: 500;
    }

    .announcement {
        background: #d6eaf8;
        padding: 15px;
        border-radius: 6px;
        border-left: 4px solid #3498db;
        color: #2c3e50;
    }

    .no-announcement {
        text-align: center;
        color: #95a5a6;
        padding: 20px 0;
    }

    @media (max-width: 768px) {
        .dashboard-content {
            grid-template-columns: 1fr;
        }

        .file-meta {
            flex-direction: column;
            gap: 3px;
        }

        .action-btn {
            justify-content: center;
        }

        .action-btn i {
            margin-right: 0;
            margin-bottom: 5px;
            display: block;
        }
    }
</style>
</body>
</html>