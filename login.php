<?php
// 用户登录页面 - 已重构
require_once './includes/common.php';

// 检查登录功能是否启用
if(!$conf['userlogin']){
    header('Content-Type: text/html; charset=UTF-8');
    exit("<script language='javascript'>alert('系统暂未开启登录功能');window.location.href='./';</script>");
}

// 处理登出请求
if(isset($_GET['logout'])){
    if(!checkRefererHost()) exit();
    setcookie("user_token", "", time() - 1, '/');
    header('Content-Type: text/html; charset=UTF-8');
    exit("<script language='javascript'>alert('账号已安全退出！');window.location.href='./login.php';</script>");
}
// 检查用户是否已登录
elseif($islogin2==1){
    header('Content-Type: text/html; charset=UTF-8');
    exit("<script language='javascript'>alert('账号已处于登录状态！');window.location.href='./';</script>");
}
// 处理第三方登录请求
elseif(isset($_GET['act']) && $_GET['act']=='connect' && ($conf['login_qq'] || $conf['login_wx'])){
    header('Content-Type: application/json; charset=UTF-8');
    $login_type = isset($_POST['type']) ? $_POST['type'] : die('{"code":-1,"msg":"登录类型参数错误"}');

    if(!$conf['login_apiurl'] || !$conf['login_appid'] || !$conf['login_appkey']){
        die('{"code":-1,"msg":"第三方登录接口配置不完整"}');
    }

    $auth_handler = new \lib\Oauth($conf['login_apiurl'], $conf['login_appid'], $conf['login_appkey']);
    $result = $auth_handler->login($login_type);

    if(isset($result['code']) && $result['code']==0){
        $response = ['code'=>0, 'url'=>$result['url']];
    }elseif(isset($result['code'])){
        $response = ['code'=>-1, 'msg'=>$result['msg']];
    }else{
        $response = ['code'=>-1, 'msg'=>'第三方登录接口请求失败'];
    }
    exit(json_encode($response));
}
// 处理第三方登录回调
elseif($_GET['code'] && $_GET['type'] && $_GET['state'] && ($conf['login_qq'] || $conf['login_wx'])){
    if($_GET['state'] != $_SESSION['Oauth_state']){
        sysmsg("<h2>CSRF验证失败，请重试！</h2>");
    }

    $login_type = $_GET['type'];
    $type_label = $login_type=='wx' ? '微信' : 'QQ';

    $auth_handler = new \lib\Oauth($conf['login_apiurl'], $conf['login_appid'], $conf['login_appkey']);
    $callback_data = $auth_handler->callback();

    if(isset($callback_data['code']) && $callback_data['code']==0){
        $openid = $callback_data['social_uid'];
        $access_token = $callback_data['access_token'];
        $username = trim($callback_data['nickname']);
        $avatar = $callback_data['faceimg'];

        if(empty($username) || $username=='-') {
            $username = $type_label.'用户';
        }
    }elseif(isset($callback_data['code'])){
        sysmsg('<h3>错误代码:</h3>'.$callback_data['errcode'].'<h3>错误信息:</h3>'.$callback_data['msg']);
    }else{
        sysmsg('获取登录信息失败');
    }

    // 查找或创建用户
    $existing_user = $DB->find('user','*',['type'=>$login_type, 'openid'=>$openid], null, '1');

    if(!$existing_user){
        if(!$DB->insert('user', [
            'type' => $login_type,
            'openid' => $openid,
            'nickname' => $username,
            'faceimg' => $avatar,
            'enable' => 1,
            'regip' => $clientip,
            'loginip' => $clientip,
            'addtime' => 'NOW()',
            'lasttime' => 'NOW()',
        ])) sysmsg('用户注册失败 '.$DB->error());

        $user_id = $DB->lastInsertId();
    }else{
        if($existing_user['enable']==0){
            $_SESSION['user_blocked'] = true;
            sysmsg('该账户已被限制登录');
        }

        $user_id = $existing_user['uid'];
        $DB->update('user', ['loginip' => $clientip, 'lasttime'=>'NOW()'], ['uid'=>$user_id]);
    }

    if($_SESSION['user_blocked']){
        $DB->update('user', ['enable' => 0], ['uid'=>$user_id]);
        sysmsg('当前账户已被限制登录');
    }

    if(isset($_SESSION['fileids']) && count($_SESSION['fileids'])>0){
        $file_ids = array_reverse($_SESSION['fileids']);
        if(count($file_ids) > 60){
            $file_ids = array_splice($file_ids, 0, 60);
        }
        $file_ids = implode(',',$file_ids);
        $DB->exec("UPDATE pre_file SET uid='{$user_id}' WHERE id IN ({$file_ids}) AND uid=0");
    }

    $session_key = md5($login_type.$openid.$password_hash);
    $expire_time = time() + 2592000; // 30天
    $auth_token = authcode("{$user_id}\t{$session_key}\t{$expire_time}", 'ENCODE', SYS_KEY);

    ob_clean();
    setcookie("user_token", $auth_token, time() + 2592000, '/');
    exit("<script language='javascript'>window.location.href='./';</script>");
}

// 处理用户名密码登录
if (isset($_POST['action']) && $_POST['action'] == 'login') {
    $login_identifier = trim($_POST['login_identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_identifier) || empty($password)) {
        $error_message = '请完整填写账号和密码';
    } else {
        $user_data = $DB->getRow(
            "SELECT * FROM pre_user WHERE username = :identifier OR email = :identifier",
            [':identifier' => $login_identifier]
        );

        if ($user_data && isset($user_data['password_hash']) && !empty($user_data['password_hash']) && password_verify($password, $user_data['password_hash'])) {
            if ($user_data['enable'] == 0) {
                $error_message = '该账户已被停用';
            } else {
                // 更新登录信息
                $DB->exec("UPDATE pre_user SET loginip = :ip, lasttime = :time WHERE uid = :uid", [
                    ':ip' => $clientip,
                    ':time' => $date,
                    ':uid' => $user_data['uid']
                ]);

                // 生成登录令牌
                $session_token = md5($user_data['uid'] . $user_data['password_hash'] . $password_hash);
                $token_expire = time() + 2592000;
                $auth_token = authcode("{$user_data['uid']}\t{$session_token}\t{$token_expire}", 'ENCODE', SYS_KEY);

                setcookie("user_token", $auth_token, time() + 2592000, '/');

                header('Content-Type: text/html; charset=UTF-8');
                exit("<script language='javascript'>alert('登录成功！');window.location.href='./';</script>");
            }
        } else {
            $error_message = '账号或密码不正确';
        }
    }
}

$page_title = '账户登录 - ' . $conf['title'];
include SYSTEM_ROOT.'header.php';
?>
<div class="main-wrapper">
    <div class="login-container">
        <div class="login-card">
            <div class="card-header">
                <h3>欢迎回来</h3>
                <p>请登录您的账户</p>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form method="post" class="login-form">
                <input type="hidden" name="action" value="login">

                <div class="input-group">
                    <label for="account">账号</label>
                    <input type="text"
                           class="form-control"
                           id="account"
                           name="login_identifier"
                           placeholder="请输入用户名或邮箱"
                           required>
                </div>

                <div class="input-group">
                    <label for="password">密码</label>
                    <input type="password"
                           class="form-control"
                           id="password"
                           name="password"
                           placeholder="请输入密码"
                           required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-login">立即登录</button>
                </div>
            </form>

            <?php if ($conf['enable_user_registration']): ?>
                <div class="signup-link">
                    还没有账户？ <a href="register.php">立即注册</a>
                </div>
            <?php endif; ?>

            <?php if($conf['login_qq'] || $conf['login_wx']): ?>
            <div class="divider">
                <span>或使用第三方账号登录</span>
            </div>

            <div class="social-login">
                <?php if($conf['login_qq']): ?>
                    <button class="social-btn qq-btn" onclick="socialLogin('qq')">
                        <i class="fa fa-qq"></i> QQ登录
                    </button>
                <?php endif; ?>

                <?php if($conf['login_wx']): ?>
                    <button class="social-btn wx-btn" onclick="socialLogin('wx')">
                        <i class="fa fa-wechat"></i> 微信登录
                    </button>
                <?php endif; ?>
            </div>

            <div class="social-note">
                <small>新用户通过社交账号登录将自动创建平台账户</small>
            </div>
            <?php endif; ?>
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

    .login-container {
        width: 100%;
        max-width: 400px;
    }

    .login-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        overflow: hidden;
    }

    .card-header {
        background: #2c3e50;
        color: white;
        padding: 30px;
        text-align: center;
    }

    .card-header h3 {
        margin: 0 0 10px 0;
        font-size: 1.8em;
    }

    .card-header p {
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
    }

    .login-form {
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

    .btn-login {
        width: 100%;
        padding: 14px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.3s;
    }

    .btn-login:hover {
        background: #5a6fd8;
    }

    .signup-link {
        text-align: center;
        padding: 0 30px 20px;
        border-top: 1px solid #eee;
        margin-top: 10px;
    }

    .signup-link a {
        color: #667eea;
        text-decoration: none;
        font-weight: 500;
    }

    .divider {
        text-align: center;
        position: relative;
        margin: 30px 30px 20px;
        color: #777;
    }

    .divider::before,
    .divider::after {
        content: '';
        position: absolute;
        top: 50%;
        width: 40%;
        height: 1px;
        background: #ddd;
    }

    .divider::before {
        left: 0;
    }

    .divider::after {
        right: 0;
    }

    .social-login {
        display: flex;
        justify-content: center;
        gap: 15px;
        padding: 0 30px 20px;
    }

    .social-btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        color: white;
        font-size: 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: transform 0.2s;
    }

    .social-btn:hover {
        transform: translateY(-2px);
    }

    .qq-btn {
        background: #12b7f5;
    }

    .wx-btn {
        background: #07c160;
    }

    .social-note {
        text-align: center;
        padding: 0 30px 30px;
        color: #999;
    }
</style>

<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
function socialLogin(type) {
    var loading = layer.load(2, {shade:[0.1,'#fff']});

    $.ajax({
        type: "POST",
        url: "login.php?act=connect",
        data: {type: type},
        dataType: 'json',
        success: function(response) {
            layer.close(loading);
            if(response.code == 0) {
                window.location.href = response.url;
            } else {
                layer.alert(response.msg, {icon: 2});
            }
        },
        error: function() {
            layer.close(loading);
            layer.alert('请求失败，请稍后重试', {icon: 2});
        }
    });
}
</script>
</body>
</html>