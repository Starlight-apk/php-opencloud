<?php
// 主页面入口 - 已重构
require_once './includes/common.php';

// 根据用户登录状态决定显示内容
if (!$islogin2) {
    // 非登录用户视图
    $page_title = $conf['title'];
    include SYSTEM_ROOT . 'header.php';

    // 获取热门公开文件列表
    $popular_files = $DB->getAll("SELECT * FROM pre_file WHERE hide = 0 ORDER BY count DESC, addtime DESC LIMIT 6");

    // 统计数据获取
    $public_file_count = $DB->getColumn("SELECT COUNT(*) FROM pre_file WHERE hide = 0");
    $user_count = $DB->getColumn("SELECT COUNT(*) FROM pre_user");

    // 页面内容渲染
    ?>
    <div class="container-fluid">
        <div class="landing-page">
            <div class="jumbotron hero-section">
                <h1 class="text-center main-title"><?php echo htmlspecialchars($conf['title']); ?></h1>
                <p class="lead text-center subtitle">安全可靠的云存储解决方案</p>
                <p class="text-center description">快速上传下载，安全分享文件，支持多种格式</p>

                <div class="stats-container">
                    <div class="stat-card">
                        <h3 class="stat-value"><?php echo $public_file_count; ?></h3>
                        <p class="stat-label">共享文件</p>
                    </div>
                    <div class="stat-card">
                        <h3 class="stat-value"><?php echo $user_count; ?></h3>
                        <p class="stat-label">平台用户</p>
                    </div>
                    <div class="stat-card">
                        <h3 class="stat-value"><?php echo date('Y'); ?></h3>
                        <p class="stat-label">服务年份</p>
                    </div>
                </div>

                <div class="text-center action-buttons">
                    <a href="login.php" class="btn btn-lg btn-success login-btn" role="button">登录账户</a>
                    <?php if($conf['enable_user_registration']){ ?>
                    <a href="register.php" class="btn btn-lg btn-primary register-btn" role="button">创建账户</a>
                    <?php } ?>
                </div>
            </div>

            <div class="features-section">
                <div class="section-header">
                    <h2 class="text-center">平台优势</h2>
                    <hr class="divider">
                </div>

                <div class="row feature-grid">
                    <div class="col-md-3 feature-item">
                        <div class="feature-content text-center">
                            <i class="fa fa-lock fa-3x security-icon" style="color: #3498db;"></i>
                            <h4>数据安全</h4>
                            <p>多层加密保障，确保您的文件安全可靠，隐私得到充分保护。</p>
                        </div>
                    </div>
                    <div class="col-md-3 feature-item">
                        <div class="feature-content text-center">
                            <i class="fa fa-rocket fa-3x speed-icon" style="color: #2ecc71;"></i>
                            <h4>高速传输</h4>
                            <p>优化的传输协议，支持大文件分块上传，确保快速稳定的传输体验。</p>
                        </div>
                    </div>
                    <div class="col-md-3 feature-item">
                        <div class="feature-content text-center">
                            <i class="fa fa-folder fa-3x manage-icon" style="color: #f39c12;"></i>
                            <h4>轻松管理</h4>
                            <p>简洁直观的文件管理界面，支持文件分类、搜索和批量操作。</p>
                        </div>
                    </div>
                    <div class="col-md-3 feature-item">
                        <div class="feature-content text-center">
                            <i class="fa fa-users fa-3x share-icon" style="color: #e74c3c;"></i>
                            <h4>便捷共享</h4>
                            <p>简单分享功能，可设置权限和有效期限，分享更安全可控。</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="popular-files-section">
                <div class="section-header">
                    <h2>热门共享资源</h2>
                    <p>社区用户分享的高人气资源</p>
                </div>

                <?php if ($popular_files): ?>
                <div class="row files-grid">
                    <?php foreach ($popular_files as $file): ?>
                    <div class="col-md-4 file-item">
                        <div class="file-card">
                            <div class="file-info">
                                <h4 class="file-name">
                                    <i class="fa <?php echo type_to_icon($file['type']); ?> fa-fw"></i>
                                    <?php echo htmlspecialchars($file['name']); ?>
                                </h4>
                                <p class="file-meta">
                                    <span class="file-size">大小: <?php echo size_format($file['size']); ?></span>
                                    <span class="download-count">下载: <?php echo $file['count']; ?> 次</span>
                                    <span class="upload-time">上传于: <?php echo $file['addtime']; ?></span>
                                </p>
                                <div class="file-actions">
                                    <a href="./down.php/<?php echo $file['hash']; ?>.<?php echo $file['type'] ?: 'file'; ?>"
                                       class="btn btn-primary download-btn" role="button">
                                       立即下载
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <p>暂无热门资源，登录后可上传您的第一个文件！</p>
                </div>
                <?php endif; ?>

                <div class="text-center more-actions">
                    <a href="login.php" class="btn btn-info">登录获取更多资源</a>
                    <?php if($conf['enable_user_registration']){ ?>
                    <a href="register.php" class="btn btn-warning">注册开通更多功能</a>
                    <?php } ?>
                </div>
            </div>

            <div class="how-to-use-section">
                <div class="section-header">
                    <h2>使用指南</h2>
                    <hr class="divider">
                </div>

                <div class="row steps-container">
                    <div class="col-md-4 step-item">
                        <div class="step-content text-center">
                            <div class="step-badge">1</div>
                            <h4>账户创建</h4>
                            <p>点击注册按钮创建您的个人账户，开始云存储服务体验</p>
                        </div>
                    </div>
                    <div class="col-md-4 step-item">
                        <div class="step-content text-center">
                            <div class="step-badge">2</div>
                            <h4>文件上传</h4>
                            <p>登录后使用上传功能，将本地文件安全存储到云端</p>
                        </div>
                    </div>
                    <div class="col-md-4 step-item">
                        <div class="step-content text-center">
                            <div class="step-badge">3</div>
                            <h4>分享管理</h4>
                            <p>灵活管理您的云端文件，并按需生成分享链接</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .landing-page {
            padding: 20px 0;
        }

        .hero-section {
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 40px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .main-title {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .subtitle {
            font-size: 1.4em;
            margin-bottom: 15px;
        }

        .description {
            font-size: 1.1em;
            margin-bottom: 25px;
        }

        .stats-container {
            display: flex;
            justify-content: space-around;
            margin: 30px 0;
        }

        .stat-card {
            text-align: center;
            padding: 10px 20px;
            flex: 1;
        }

        .stat-value {
            margin: 0;
            font-size: 2.2em;
            font-weight: bold;
            color: #2c3e50;
        }

        .stat-label {
            margin: 0;
            color: #7f8c8d;
        }

        .action-buttons {
            margin-top: 20px;
        }

        .login-btn, .register-btn {
            margin: 0 10px;
        }

        .features-section, .popular-files-section, .how-to-use-section {
            margin: 40px 0;
        }

        .section-header {
            margin-bottom: 30px;
        }

        .section-header h2 {
            font-size: 2em;
            margin-bottom: 10px;
        }

        .divider {
            width: 100px;
            height: 4px;
            background: #3498db;
            margin: 10px auto;
            border: none;
            border-radius: 2px;
        }

        .feature-grid, .files-grid {
            margin-left: -15px;
            margin-right: -15px;
        }

        .feature-item, .file-item {
            padding: 0 15px;
            margin-bottom: 20px;
        }

        .feature-content, .step-content {
            padding: 20px;
            text-align: center;
        }

        .file-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .file-name {
            margin-top: 0;
            word-break: break-all;
        }

        .file-meta {
            font-size: 0.9em;
            color: #777;
            margin: 10px 0;
        }

        .file-meta span {
            display: block;
            margin-bottom: 5px;
        }

        .file-actions {
            margin-top: auto;
        }

        .steps-container {
            margin-top: 30px;
        }

        .step-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #3498db;
            color: white;
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .step-item {
            text-align: center;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #95a5a6;
        }

        @media (max-width: 768px) {
            .stats-container {
                flex-direction: column;
            }

            .stat-card {
                margin-bottom: 20px;
            }
        }
    </style>
    <?php
    include SYSTEM_ROOT.'footer.php';
    exit;
} else {
    // 已登录用户跳转至控制面板
    header("Location: dashboard.php");
    exit;
}
?>