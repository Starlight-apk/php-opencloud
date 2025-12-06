<?php
// 用户注册页面 - 已重构
require_once './includes/common.php';

// 强制启用注册功能
$conf['enable_user_registration'] = true;

// 检查用户是否已登录
if ($islogin2 == 1) {
    header('Content-Type: text/html; charset=UTF-8');
    exit("<script language='javascript'>alert('您已登录！');window.location.href='./';</script>");
}

// 处理注册请求
if (isset($_POST['action']) && $_POST['action'] == 'register') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 输入验证
    if (empty($username) || empty($email) || empty($password)) {
        $reg_error = '所有字段都必须填写';
    } elseif (!validate_email($email)) {
        $reg_error = '邮箱格式不正确';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $reg_error = '用户名长度必须在3-20个字符之间';
    } elseif ($password !== $confirm_password) {
        $reg_error = '两次输入的密码不一致';
    } elseif (strlen($password) < 6) {
        $reg_error = '密码长度至少6位';
    }

    if (!isset($reg_error)) {
        // 检查数据库表结构
        $columns = $DB->getAll("DESCRIBE pre_user");
        $has_username_field = false;
        $has_email_field = false;
        $has_password_hash_field = false;

        foreach ($columns as $col) {
            if ($col['Field'] === 'username') $has_username_field = true;
            if ($col['Field'] === 'email') $has_email_field = true;
            if ($col['Field'] === 'password_hash') $has_password_hash_field = true;
        }

        if ($has_username_field && $has_email_field && $has_password_hash_field) {
            // 使用完整字段进行注册
            $existing_user = $DB->getRow("SELECT * FROM pre_user WHERE username = :username OR email = :email", [
                ':username' => $username,
                ':email' => $email
            ]);

            if ($existing_user) {
                $reg_error = '用户名或邮箱已被注册';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $result = $DB->exec(
                    "INSERT INTO pre_user (type, openid, username, email, password_hash, nickname, regip, addtime, lasttime, enable) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    ['sql', '', $username, $email, $password_hash, $username, $clientip, $date, $date, 1]
                );

                if ($result) {
                    $user_id = $DB->lastInsertId();
                    $session_token = md5($user_id . $password_hash . $password_hash);
                    $token_expire = time() + 2592000;
                    $auth_token = authcode("{$user_id}\t{$session_token}\t{$token_expire}", 'ENCODE', SYS_KEY);

                    setcookie("user_token", $auth_token, time() + 2592000, '/');

                    header('Content-Type: text/html; charset=UTF-8');
                    exit("<script language='javascript'>alert('注册成功！');window.location.href='./';</script>");
                } else {
                    $reg_error = '注册失败，请稍后重试: ' . $DB->error();
                }
            }
        } else {
            // 旧表结构注册
            $existing_user = $DB->getRow("SELECT * FROM pre_user WHERE nickname = :username", [':username' => $username]);

            if ($existing_user) {
                $reg_error = '用户名已被注册';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $result = $DB->exec(
                    "INSERT INTO pre_user (type, openid, nickname, regip, addtime, lasttime, enable) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    ['sql', '', $username, $clientip, $date, $date, 1]
                );

                if ($result) {
                    $user_id = $DB->lastInsertId();
                    $session_token = md5($user_id . $password_hash . $password_hash);
                    $token_expire = time() + 2592000;
                    $auth_token = authcode("{$user_id}\t{$session_token}\t{$token_expire}", 'ENCODE', SYS_KEY);

                    setcookie("user_token", $auth_token, time() + 2592000, '/');

                    header('Content-Type: text/html; charset=UTF-8');
                    exit("<script language='javascript'>alert('注册成功！（注意：数据库表结构未更新，邮箱和密码功能可能受限）');window.location.href='./';</script>");
                } else {
                    $reg_error = '注册失败，请稍后重试: ' . $DB->error() . ' - 请先运行更新脚本添加数据库字段';
                }
            }
        }
    }
}

// 处理登录请求
if (isset($_POST['action']) && $_POST['action'] == 'login') {
    $login_identifier = trim($_POST['login_identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_identifier) || empty($password)) {
        $login_error = '请输入账号和密码';
    } else {
        $columns = $DB->getAll("DESCRIBE pre_user");
        $has_username_field = false;
        $has_email_field = false;
        $has_password_hash_field = false;

        foreach ($columns as $col) {
            if ($col['Field'] === 'username') $has_username_field = true;
            if ($col['Field'] === 'email') $has_email_field = true;
            if ($col['Field'] === 'password_hash') $has_password_hash_field = true;
        }

        if ($has_username_field && $has_email_field && $has_password_hash_field) {
            $user_data = $DB->getRow(
                "SELECT * FROM pre_user WHERE username = :identifier OR email = :identifier",
                [':identifier' => $login_identifier]
            );

            if ($user_data && isset($user_data['password_hash']) && !empty($user_data['password_hash']) && password_verify($password, $user_data['password_hash'])) {
                if ($user_data['enable'] == 0) {
                    $login_error = '您的账户已被停用';
                } else {
                    $DB->exec("UPDATE pre_user SET loginip = :ip, lasttime = :time WHERE uid = :uid", [
                        ':ip' => $clientip,
                        ':time' => $date,
                        ':uid' => $user_data['uid']
                    ]);

                    $session_token = md5($user_data['uid'] . $user_data['password_hash'] . $password_hash);
                    $token_expire = time() + 2592000;
                    $auth_token = authcode("{$user_data['uid']}\t{$session_token}\t{$token_expire}", 'ENCODE', SYS_KEY);

                    setcookie("user_token", $auth_token, time() + 2592000, '/');

                    header('Content-Type: text/html; charset=UTF-8');
                    exit("<script language='javascript'>alert('登录成功！');window.location.href='./';</script>");
                }
            } else {
                $login_error = '账号或密码错误';
            }
        } else {
            $user_data = $DB->getRow(
                "SELECT * FROM pre_user WHERE nickname = :identifier AND type = 'sql'",
                [':identifier' => $login_identifier]
            );

            if ($user_data) {
                if ($user_data['enable'] == 0) {
                    $login_error = '您的账户已被停用';
                } else {
                    $DB->exec("UPDATE pre_user SET loginip = :ip, lasttime = :time WHERE uid = :uid", [
                        ':ip' => $clientip,
                        ':time' => $date,
                        ':uid' => $user_data['uid']
                    ]);

                    $session_token = md5($user_data['uid'] . $user_data['nickname'] . $password_hash);
                    $token_expire = time() + 2592000;
                    $auth_token = authcode("{$user_data['uid']}\t{$session_token}\t{$token_expire}", 'ENCODE', SYS_KEY);

                    setcookie("user_token", $auth_token, time() + 2592000, '/');

                    header('Content-Type: text/html; charset=UTF-8');
                    exit("<script language='javascript'>alert('登录成功！（注意：数据库表结构未更新，密码功能可能受限）');window.location.href='./';</script>");
                }
            } else {
                $login_error = '账号不存在或密码错误';
            }
        }
    }
}

$page_title = $conf['title'] . ' - 账户注册';
include SYSTEM_ROOT.'header.php';
?>

<div class="main-wrapper">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>创建账户</h2>
                <p>加入我们，开始云端存储体验</p>
            </div>

            <?php if (isset($reg_error)): ?>
                <div class="error-message"><?php echo $reg_error; ?></div>
            <?php endif; ?>

            <?php if (isset($login_error)): ?>
                <div class="error-message"><?php echo $login_error; ?></div>
            <?php endif; ?>

            <div class="auth-tabs">
                <button class="tab-btn active" onclick="showTab('register')">注册</button>
                <button class="tab-btn" onclick="showTab('login')">登录</button>
            </div>

            <form method="post" class="auth-form" id="registerForm" style="display: block;">
                <input type="hidden" name="action" value="register">

                <div class="input-group">
                    <label for="reg_username">用户名</label>
                    <input type="text"
                           class="form-control"
                           id="reg_username"
                           name="username"
                           placeholder="请输入3-20位用户名"
                           required>
                </div>

                <div class="input-group">
                    <label for="reg_email">邮箱</label>
                    <input type="email"
                           class="form-control"
                           id="reg_email"
                           name="email"
                           placeholder="请输入有效的邮箱地址"
                           required>
                </div>

                <div class="input-group">
                    <label for="reg_password">密码</label>
                    <input type="password"
                           class="form-control"
                           id="reg_password"
                           name="password"
                           placeholder="请输入至少6位密码"
                           required>
                </div>

                <div class="input-group">
                    <label for="reg_confirm_password">确认密码</label>
                    <input type="password"
                           class="form-control"
                           id="reg_confirm_password"
                           name="confirm_password"
                           placeholder="请再次输入密码"
                           required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-register">立即注册</button>
                </div>
            </form>

            <form method="post" class="auth-form" id="loginForm" style="display: none;">
                <input type="hidden" name="action" value="login">

                <div class="input-group">
                    <label for="login_identifier">账号</label>
                    <input type="text"
                           class="form-control"
                           id="login_identifier"
                           name="login_identifier"
                           placeholder="请输入用户名或邮箱"
                           required>
                </div>

                <div class="input-group">
                    <label for="login_password">密码</label>
                    <input type="password"
                           class="form-control"
                           id="login_password"
                           name="password"
                           placeholder="请输入密码"
                           required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-login">立即登录</button>
                </div>
            </form>

            <div class="auth-footer">
                已有账户？ <a href="javascript:showTab('login')">立即登录</a>
            </div>
        </div>
    </div>
</div>

<?php include SYSTEM_ROOT.'footer.php'; ?>

<style>
    .main-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px;
    }

    .auth-container {
        width: 100%;
        max-width: 400px;
    }

    .auth-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        overflow: hidden;
    }

    .auth-header {
        background: #2c3e50;
        color: white;
        padding: 30px;
        text-align: center;
    }

    .auth-header h2 {
        margin: 0 0 10px 0;
        font-size: 1.8em;
    }

    .auth-header p {
        margin: 0;
        opacity: 0.8;
    }

    .error-message {
        background: #f8d7da;
        color: #721c24;
        padding: 12px;
        margin: 15px;
        border: 1px solid #f5c6cb;
        border-radius: 4px;
        text-align: center;
    }

    .auth-tabs {
        display: flex;
        border-bottom: 1px solid #eee;
        margin: 0 20px;
    }

    .tab-btn {
        flex: 1;
        padding: 15px;
        border: none;
        background: none;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        color: #777;
        border-bottom: 2px solid transparent;
        transition: all 0.3s;
    }

    .tab-btn.active {
        color: #667eea;
        border-bottom: 2px solid #667eea;
    }

    .auth-form {
        padding: 30px;
    }

    .input-group {
        margin-bottom: 20px;
    }

    .input-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: #333;
    }

    .input-group input {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 16px;
        transition: border-color 0.3s;
    }

    .input-group input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
    }

    .form-actions {
        margin: 25px 0;
    }

    .btn-register, .btn-login {
        width: 100%;
        padding: 14px;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.3s;
    }

    .btn-register {
        background: #27ae60;
        color: white;
    }

    .btn-register:hover {
        background: #219a52;
    }

    .btn-login {
        background: #3498db;
        color: white;
    }

    .btn-login:hover {
        background: #2980b9;
    }

    .auth-footer {
        text-align: center;
        padding: 0 30px 30px;
        border-top: 1px solid #eee;
        margin-top: 10px;
    }

    .auth-footer a {
        color: #667eea;
        text-decoration: none;
        font-weight: 500;
    }
</style>

<script>
function showTab(tabName) {
    // 隐藏所有表单
    document.getElementById('registerForm').style.display = 'none';
    document.getElementById('loginForm').style.display = 'none';

    // 设置标签按钮状态
    const tabBtns = document.querySelectorAll('.tab-btn');
    tabBtns.forEach(btn => btn.classList.remove('active'));

    if (tabName === 'register') {
        document.getElementById('registerForm').style.display = 'block';
        document.querySelector('.tab-btn:nth-child(1)').classList.add('active');
    } else if (tabName === 'login') {
        document.getElementById('loginForm').style.display = 'block';
        document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
    }
}

// 从URL参数确定初始显示哪个标签
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab === 'login') {
        showTab('login');
    }
};
</script>
</body>
</html>