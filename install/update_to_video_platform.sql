-- Update database schema for TikTok-like video platform

-- Add columns to pre_user table for TikTok-like features
ALTER TABLE `pre_user` 
ADD COLUMN `username` VARCHAR(50) UNIQUE,
ADD COLUMN `email` VARCHAR(100) UNIQUE,
ADD COLUMN `password_hash` VARCHAR(255),
ADD COLUMN `bio` TEXT,
ADD COLUMN `avatar` VARCHAR(255),
ADD COLUMN `followers_count` INT DEFAULT 0,
ADD COLUMN `following_count` INT DEFAULT 0,
ADD COLUMN `videos_count` INT DEFAULT 0,
ADD COLUMN `verified` TINYINT DEFAULT 0,
ADD COLUMN `is_private` TINYINT DEFAULT 0;

-- Create table for videos
CREATE TABLE `pre_videos` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `file_hash` varchar(32) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) unsigned NOT NULL,
  `duration` int(11) DEFAULT 0, -- in seconds
  `thumbnail` varchar(255),
  `views` int(11) unsigned NOT NULL DEFAULT '0',
  `likes` int(11) unsigned NOT NULL DEFAULT '0',
  `shares` int(11) unsigned NOT NULL DEFAULT '0',
  `comments` int(11) unsigned NOT NULL DEFAULT '0',
  `addtime` datetime NOT NULL,
  `lasttime` datetime DEFAULT NULL,
  `ip` varchar(15) NOT NULL,
  `hide` int(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1', -- 1:active, 0:inactive, 2:blocked
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `addtime` (`addtime`),
  KEY `status` (`status`),
  CONSTRAINT `fk_videos_user` FOREIGN KEY (`user_id`) REFERENCES `pre_user` (`uid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for video likes
CREATE TABLE `pre_likes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `video_id` int(11) unsigned NOT NULL,
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_video` (`user_id`, `video_id`),
  KEY `video_id` (`video_id`),
  CONSTRAINT `fk_likes_user` FOREIGN KEY (`user_id`) REFERENCES `pre_user` (`uid`) ON DELETE CASCADE,
  CONSTRAINT `fk_likes_video` FOREIGN KEY (`video_id`) REFERENCES `pre_videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for video comments
CREATE TABLE `pre_comments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `video_id` int(11) unsigned NOT NULL,
  `content` text NOT NULL,
  `addtime` datetime NOT NULL,
  `parent_id` int(11) DEFAULT NULL, -- for reply to comment
  PRIMARY KEY (`id`),
  KEY `video_id` (`video_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `pre_user` (`uid`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_video` FOREIGN KEY (`video_id`) REFERENCES `pre_videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for user follows
CREATE TABLE `pre_follows` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `follower_id` int(11) unsigned NOT NULL,
  `followed_id` int(11) unsigned NOT NULL,
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_follower_followed` (`follower_id`, `followed_id`),
  KEY `followed_id` (`followed_id`),
  CONSTRAINT `fk_follows_follower` FOREIGN KEY (`follower_id`) REFERENCES `pre_user` (`uid`) ON DELETE CASCADE,
  CONSTRAINT `fk_follows_followed` FOREIGN KEY (`followed_id`) REFERENCES `pre_user` (`uid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for video shares
CREATE TABLE `pre_shares` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `video_id` int(11) unsigned NOT NULL,
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `video_id` (`video_id`),
  CONSTRAINT `fk_shares_user` FOREIGN KEY (`user_id`) REFERENCES `pre_user` (`uid`) ON DELETE CASCADE,
  CONSTRAINT `fk_shares_video` FOREIGN KEY (`video_id`) REFERENCES `pre_videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update pre_config table to reflect changes
INSERT INTO `pre_config` (`k`, `v`) VALUES 
('site_type', 'video'),
('video_upload_size', '100'), -- 100MB default
('video_extensions', 'mp4|mov|avi|wmv|flv|f4v|webm|3gp|3gpp'),
('enable_user_registration', '1'),
('require_email_verification', '0'),
('default_avatar', 'assets/img/default_avatar.png');