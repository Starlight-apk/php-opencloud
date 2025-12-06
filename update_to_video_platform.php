<?php
// Update script to transform the file hosting platform to a TikTok-like video platform
include("./includes/common.php");

if(!$islogin) {
    exit('需要管理员权限');
}

echo "<h2>正在更新数据库结构...</h2>";

try {
    // Add columns to pre_user table
    $columns_to_add = [
        'username VARCHAR(50)',
        'email VARCHAR(100)', 
        'password_hash VARCHAR(255)',
        'bio TEXT',
        'avatar VARCHAR(255)',
        'followers_count INT DEFAULT 0',
        'following_count INT DEFAULT 0',
        'videos_count INT DEFAULT 0',
        'verified TINYINT DEFAULT 0',
        'is_private TINYINT DEFAULT 0'
    ];
    
    foreach($columns_to_add as $column) {
        $col_name = explode(' ', $column)[0];
        $result = $DB->getColumn("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                  WHERE TABLE_SCHEMA = :dbname AND TABLE_NAME = 'pre_user' AND COLUMN_NAME = :col_name", 
                                  [':dbname'=>$dbconfig['dbname'], ':col_name'=>$col_name]);
        if (!$result) {
            $DB->exec("ALTER TABLE `pre_user` ADD COLUMN {$column}");
            echo "添加列 {$col_name} 到 pre_user 表<br>";
        } else {
            echo "列 {$col_name} 已存在于 pre_user 表<br>";
        }
    }
    
    // Create videos table if it doesn't exist
    $table_exists = $DB->getColumn("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
                                    WHERE TABLE_SCHEMA = :dbname AND TABLE_NAME = 'pre_videos'", 
                                    [':dbname'=>$dbconfig['dbname']]);
    if (!$table_exists) {
        $DB->exec("CREATE TABLE `pre_videos` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(11) unsigned NOT NULL,
          `title` varchar(255) NOT NULL,
          `description` text,
          `file_hash` varchar(32) NOT NULL,
          `file_name` varchar(255) NOT NULL,
          `file_size` int(11) unsigned NOT NULL,
          `duration` int(11) DEFAULT 0,
          `thumbnail` varchar(255),
          `views` int(11) unsigned NOT NULL DEFAULT '0',
          `likes` int(11) unsigned NOT NULL DEFAULT '0',
          `shares` int(11) unsigned NOT NULL DEFAULT '0',
          `comments` int(11) unsigned NOT NULL DEFAULT '0',
          `addtime` datetime NOT NULL,
          `lasttime` datetime DEFAULT NULL,
          `ip` varchar(15) NOT NULL,
          `hide` int(1) NOT NULL DEFAULT '0',
          `status` tinyint(1) NOT NULL DEFAULT '1',
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `addtime` (`addtime`),
          KEY `status` (`status`),
          CONSTRAINT `fk_videos_user` FOREIGN KEY (`user_id`) REFERENCES `pre_user` (`uid`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "创建 pre_videos 表<br>";
    } else {
        echo "pre_videos 表已存在<br>";
    }
    
    // Create likes table if it doesn't exist
    $table_exists = $DB->getColumn("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
                                    WHERE TABLE_SCHEMA = :dbname AND TABLE_NAME = 'pre_likes'", 
                                    [':dbname'=>$dbconfig['dbname']]);
    if (!$table_exists) {
        $DB->exec("CREATE TABLE `pre_likes` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(11) unsigned NOT NULL,
          `video_id` int(11) unsigned NOT NULL,
          `addtime` datetime NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_user_video` (`user_id`, `video_id`),
          KEY `video_id` (`video_id`),
          CONSTRAINT `fk_likes_user` FOREIGN KEY (`user_id`) REFERENCES `pre_user` (`uid`) ON DELETE CASCADE,
          CONSTRAINT `fk_likes_video` FOREIGN KEY (`video_id`) REFERENCES `pre_videos` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "创建 pre_likes 表<br>";
    } else {
        echo "pre_likes 表已存在<br>";
    }
    
    // Create comments table if it doesn't exist
    $table_exists = $DB->getColumn("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
                                    WHERE TABLE_SCHEMA = :dbname AND TABLE_NAME = 'pre_comments'", 
                                    [':dbname'=>$dbconfig['dbname']]);
    if (!$table_exists) {
        $DB->exec("CREATE TABLE `pre_comments` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(11) unsigned NOT NULL,
          `video_id` int(11) unsigned NOT NULL,
          `content` text NOT NULL,
          `addtime` datetime NOT NULL,
          `parent_id` int(11) DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `video_id` (`video_id`),
          KEY `parent_id` (`parent_id`),
          CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `pre_user` (`uid`) ON DELETE CASCADE,
          CONSTRAINT `fk_comments_video` FOREIGN KEY (`video_id`) REFERENCES `pre_videos` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "创建 pre_comments 表<br>";
    } else {
        echo "pre_comments 表已存在<br>";
    }
    
    // Create follows table if it doesn't exist
    $table_exists = $DB->getColumn("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
                                    WHERE TABLE_SCHEMA = :dbname AND TABLE_NAME = 'pre_follows'", 
                                    [':dbname'=>$dbconfig['dbname']]);
    if (!$table_exists) {
        $DB->exec("CREATE TABLE `pre_follows` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `follower_id` int(11) unsigned NOT NULL,
          `followed_id` int(11) unsigned NOT NULL,
          `addtime` datetime NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_follower_followed` (`follower_id`, `followed_id`),
          KEY `followed_id` (`followed_id`),
          CONSTRAINT `fk_follows_follower` FOREIGN KEY (`follower_id`) REFERENCES `pre_user` (`uid`) ON DELETE CASCADE,
          CONSTRAINT `fk_follows_followed` FOREIGN KEY (`followed_id`) REFERENCES `pre_user` (`uid`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "创建 pre_follows 表<br>";
    } else {
        echo "pre_follows 表已存在<br>";
    }
    
    // Create shares table if it doesn't exist
    $table_exists = $DB->getColumn("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
                                    WHERE TABLE_SCHEMA = :dbname AND TABLE_NAME = 'pre_shares'", 
                                    [':dbname'=>$dbconfig['dbname']]);
    if (!$table_exists) {
        $DB->exec("CREATE TABLE `pre_shares` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(11) unsigned NOT NULL,
          `video_id` int(11) unsigned NOT NULL,
          `addtime` datetime NOT NULL,
          PRIMARY KEY (`id`),
          KEY `video_id` (`video_id`),
          CONSTRAINT `fk_shares_user` FOREIGN KEY (`user_id`) REFERENCES `pre_user` (`uid`) ON DELETE CASCADE,
          CONSTRAINT `fk_shares_video` FOREIGN KEY (`video_id`) REFERENCES `pre_videos` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "创建 pre_shares 表<br>";
    } else {
        echo "pre_shares 表已存在<br>";
    }
    
    // Update configuration settings
    $config_updates = [
        'site_type' => 'video',
        'video_upload_size' => '100',
        'video_extensions' => 'mp4|mov|avi|wmv|flv|f4v|webm|3gp|3gpp',
        'enable_user_registration' => '1',
        'require_email_verification' => '0',
        'default_avatar' => 'assets/img/default_avatar.png',
        'title' => '短视频分享平台'
    ];
    
    foreach($config_updates as $key => $value) {
        $DB->exec("INSERT INTO `pre_config` (`k`, `v`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `v` = ?", [$key, $value, $value]);
        echo "更新配置项 {$key} = {$value}<br>";
    }
    
    echo "<h3>数据库更新完成！</h3>";
    echo "<p>现在您可以访问 <a href='./'>首页</a> 来体验新的短视频平台</p>";
    
} catch (Exception $e) {
    echo "<h3>更新失败：</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>