<?php
include("./includes/common.php");

$title = $conf['title'];
include SYSTEM_ROOT.'header.php';

// Get videos for the feed (public videos ordered by newest)
$videos_sql = "SELECT v.*, u.username, u.nickname, u.avatar FROM pre_videos v 
               JOIN pre_user u ON v.user_id = u.uid 
               WHERE v.status = 1 AND v.hide = 0 
               ORDER BY v.addtime DESC LIMIT 20";

$videos = $DB->getAll($videos_sql);
?>

<style>
.video-container {
    position: relative;
    width: 100%;
    height: 500px;
    overflow: hidden;
    background-color: #000;
}

.video-container video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.video-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    padding: 20px;
    background: linear-gradient(transparent, rgba(0,0,0,0.7));
    color: white;
}

.user-info {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-right: 10px;
    object-fit: cover;
}

.video-actions {
    position: absolute;
    right: 20px;
    bottom: 80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 25px;
}

.action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: white;
    text-decoration: none;
}

.action-btn i {
    font-size: 24px;
    margin-bottom: 5px;
}

.action-count {
    font-size: 12px;
}

.feed-container {
    max-width: 500px;
    margin: 0 auto;
    background: #fff;
}

.video-card {
    position: relative;
    margin-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.video-description {
    padding: 15px;
    font-size: 16px;
}

.user-username {
    font-weight: bold;
    margin-bottom: 5px;
}

.video-stats {
    display: flex;
    gap: 15px;
    color: #666;
    font-size: 14px;
    margin-top: 10px;
}

.pagination {
    display: flex;
    justify-content: center;
    padding: 20px;
}
</style>

<!-- Add navigation -->
<nav class="navbar navbar-default">
    <div class="container-fluid">
        <div class="navbar-header">
            <a class="navbar-brand" href="./"><?php echo $conf['title']; ?></a>
        </div>
        <div class="navbar-collapse">
            <ul class="nav navbar-nav navbar-right">
                <?php if ($islogin2): ?>
                    <li><a href="upload_video.php">上传视频</a></li>
                    <li><a href="#"><?php echo htmlspecialchars($userrow['username'] ?: $userrow['nickname']); ?></a></li>
                    <li><a href="login.php?logout">退出</a></li>
                <?php else: ?>
                    <li><a href="login.php">登录</a></li>
                    <li><a href="register.php"><strong>注册</strong></a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="feed-container">
    <?php if ($videos): ?>
        <?php foreach ($videos as $video): ?>
            <div class="video-card">
                <div class="video-container">
                    <?php if ($video['file_hash']): ?>
                        <video controls preload="metadata" onclick="this.paused ? this.play() : this.pause();">
                            <source src="down.php/<?php echo $video['file_hash']; ?>.<?php echo $video['type'] ?: 'mp4'; ?>" type="video/<?php echo $video['type'] ?: 'mp4'; ?>">
                            您的浏览器不支持视频播放
                        </video>
                    <?php else: ?>
                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;">
                            视频暂不可用
                        </div>
                    <?php endif; ?>

                    <div class="video-overlay">
                        <div class="user-info">
                            <?php if ($video['avatar']): ?>
                                <img src="<?php echo $video['avatar']; ?>" alt="Avatar" class="user-avatar">
                            <?php else: ?>
                                <div style="width: 40px; height: 40px; background-color: #ccc; border-radius: 50%; margin-right: 10px;"></div>
                            <?php endif; ?>
                            <div>
                                <div class="user-username">@<?php echo htmlspecialchars($video['username'] ?: $video['nickname']); ?></div>
                                <div><?php echo htmlspecialchars($video['title']); ?></div>
                            </div>
                        </div>
                        <div class="video-description">
                            <?php echo htmlspecialchars($video['description']); ?>
                        </div>
                    </div>

                    <div class="video-actions">
                        <a href="#" class="action-btn" onclick="likeVideo(<?php echo $video['id']; ?>, this)">
                            <i class="fa fa-heart"></i>
                            <span class="action-count"><?php echo $video['likes']; ?></span>
                        </a>
                        <a href="view_video.php?id=<?php echo $video['id']; ?>" class="action-btn">
                            <i class="fa fa-comment"></i>
                            <span class="action-count"><?php echo $video['comments']; ?></span>
                        </a>
                        <a href="#" class="action-btn" onclick="shareVideo(<?php echo $video['id']; ?>)">
                            <i class="fa fa-share"></i>
                            <span class="action-count"><?php echo $video['shares']; ?></span>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="well text-center" style="padding: 50px;">
            <h4>还没有视频</h4>
            <p>快上传第一个视频吧！</p>
            <?php if ($islogin2): ?>
                <a href="upload_video.php" class="btn btn-primary">上传视频</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-success"><strong>立即注册</strong></a>
                <a href="login.php" class="btn btn-primary">登录</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function likeVideo(videoId, element) {
    if (!<?php echo $islogin2 ? 'true' : 'false'; ?>) {
        alert('请先登录');
        window.location.href = 'login.php';
        return;
    }
    
    $.post('ajax.php?act=like_video', {
        video_id: videoId,
        csrf_token: $('meta[name="csrf-token"]').attr('content') || ''
    }, function(res) {
        if (res.code === 0) {
            // Update like count
            const countElement = $(element).find('.action-count');
            let count = parseInt(countElement.text());
            if (res.liked) {
                count++;
                $(element).find('i').removeClass('fa-heart').addClass('fa-heart text-danger');
            } else {
                count--;
                $(element).find('i').removeClass('fa-heart text-danger').addClass('fa-heart');
            }
            countElement.text(count);
        } else {
            alert(res.msg || '操作失败');
        }
    }, 'json');
}

function shareVideo(videoId) {
    if (!<?php echo $islogin2 ? 'true' : 'false'; ?>) {
        alert('请先登录');
        window.location.href = 'login.php';
        return;
    }
    
    // Simple share functionality - could be expanded to actual sharing
    const shareUrl = window.location.origin + '/view_video.php?id=' + videoId;
    if (navigator.share) {
        navigator.share({
            title: '分享视频',
            url: shareUrl
        });
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(shareUrl).then(function() {
            alert('链接已复制到剪贴板');
        });
    }
}

// Auto-play/pause video when in viewport (TikTok-like behavior)
document.addEventListener('DOMContentLoaded', function() {
    const videos = document.querySelectorAll('video');
    
    function handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Pause all videos except the one in view
                videos.forEach(video => {
                    if (video !== entry.target) {
                        video.pause();
                    }
                });
                
                // Play the video in view
                entry.target.play();
            }
        });
    }
    
    const observer = new IntersectionObserver(handleIntersection, {
        threshold: 0.6 // Trigger when 60% of the video is visible
    });
    
    videos.forEach(video => {
        observer.observe(video);
    });
});
</script>

<?php include SYSTEM_ROOT.'footer.php'; ?>
</body>
</html>