<?php
include("./includes/common.php");

$video_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$video_id) {
    exit('视频不存在');
}

// Get video info
$video = $DB->getRow("SELECT v.*, u.username, u.nickname, u.avatar, u.followers_count FROM pre_videos v 
                      JOIN pre_user u ON v.user_id = u.uid 
                      WHERE v.id = ? AND v.status = 1", [$video_id]);

if (!$video) {
    exit('视频不存在或已被删除');
}

// Update view count
$DB->exec("UPDATE pre_videos SET views = views + 1 WHERE id = ?", [$video_id]);

// Get comments for this video
$comments = $DB->getAll("SELECT c.*, u.username, u.nickname, u.avatar FROM pre_comments c 
                         JOIN pre_user u ON c.user_id = u.uid 
                         WHERE c.video_id = ? AND c.parent_id IS NULL 
                         ORDER BY c.addtime DESC", [$video_id]);

$title = $video['title'] . ' - ' . $conf['title'];
include SYSTEM_ROOT.'header.php';
?>

<style>
.video-container {
    position: relative;
    width: 100%;
    max-width: 800px;
    margin: 0 auto;
}

.video-container video {
    width: 100%;
    height: auto;
}

.user-info {
    display: flex;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    margin-right: 15px;
    object-fit: cover;
}

.user-details {
    flex: 1;
}

.username {
    font-weight: bold;
    font-size: 16px;
}

.follow-btn {
    margin-left: 10px;
}

.video-info {
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.video-title {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 10px;
}

.video-description {
    color: #666;
    margin-bottom: 10px;
}

.video-stats {
    display: flex;
    gap: 20px;
    color: #666;
    font-size: 14px;
}

.comment-section {
    padding: 15px;
}

.comment-form {
    margin-bottom: 20px;
}

.comment-item {
    display: flex;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.comment-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-right: 10px;
    object-fit: cover;
}

.comment-content {
    flex: 1;
}

.comment-author {
    font-weight: bold;
    margin-bottom: 5px;
}

.comment-text {
    margin-bottom: 5px;
}

.comment-time {
    font-size: 12px;
    color: #999;
}

.action-buttons {
    display: flex;
    justify-content: center;
    gap: 20px;
    padding: 15px;
    border-top: 1px solid #eee;
}

.action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #333;
    text-decoration: none;
}

.action-btn.active {
    color: #ff0000;
}

.action-btn i {
    font-size: 24px;
    margin-bottom: 5px;
}
</style>

<div class="container">
    <div class="video-container">
        <?php if ($video['file_hash']): ?>
            <video controls style="width:100%;max-width:800px;">
                <source src="down.php/<?php echo $video['file_hash']; ?>.<?php echo $video['type'] ?: 'mp4'; ?>" type="video/<?php echo $video['type'] ?: 'mp4'; ?>">
                您的浏览器不支持视频播放
            </video>
        <?php else: ?>
            <div style="display: flex; align-items: center; justify-content: center; height: 400px; background-color: #000; color: #fff;">
                视频暂不可用
            </div>
        <?php endif; ?>
        
        <!-- Video Info -->
        <div class="user-info">
            <?php if ($video['avatar']): ?>
                <img src="<?php echo $video['avatar']; ?>" alt="Avatar" class="user-avatar">
            <?php else: ?>
                <div style="width: 50px; height: 50px; background-color: #ccc; border-radius: 50%; margin-right: 15px;"></div>
            <?php endif; ?>
            <div class="user-details">
                <div class="username">@<?php echo htmlspecialchars($video['username'] ?: $video['nickname']); ?></div>
                <div class="followers"><?php echo $video['followers_count']; ?> 粉丝</div>
            </div>
            <?php if ($islogin2 && $uid != $video['user_id']): ?>
                <button class="btn btn-outline-primary follow-btn" onclick="toggleFollow(<?php echo $video['user_id']; ?>, this)">
                    <?php 
                    $is_following = $DB->getColumn("SELECT COUNT(*) FROM pre_follows WHERE follower_id = ? AND followed_id = ?", [$uid, $video['user_id']]);
                    echo $is_following ? '已关注' : '+ 关注';
                    ?>
                </button>
            <?php endif; ?>
        </div>
        
        <div class="video-info">
            <div class="video-title"><?php echo htmlspecialchars($video['title']); ?></div>
            <div class="video-description"><?php echo htmlspecialchars($video['description']); ?></div>
            <div class="video-stats">
                <span><i class="fa fa-eye"></i> <?php echo $video['views']; ?> 次播放</span>
                <span><i class="fa fa-heart"></i> <?php echo $video['likes']; ?> 赞</span>
                <span><i class="fa fa-comment"></i> <?php echo $video['comments']; ?> 评论</span>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="#" class="action-btn" onclick="likeVideo(<?php echo $video['id']; ?>, this)">
                <i class="fa fa-heart <?php echo $islogin2 && $DB->getColumn("SELECT COUNT(*) FROM pre_likes WHERE user_id = ? AND video_id = ?", [$uid, $video['id']]) ? 'text-danger' : ''; ?>"></i>
                <span>点赞</span>
            </a>
            <a href="#" class="action-btn">
                <i class="fa fa-comment"></i>
                <span>评论</span>
            </a>
            <a href="#" class="action-btn" onclick="shareVideo(<?php echo $video['id']; ?>)">
                <i class="fa fa-share"></i>
                <span>分享</span>
            </a>
        </div>
    </div>
    
    <!-- Comments Section -->
    <div class="comment-section">
        <h4>评论 (<?php echo count($comments); ?>)</h4>
        
        <?php if ($islogin2): ?>
            <div class="comment-form">
                <form method="post" action="ajax.php?act=comment_video" onsubmit="submitComment(event, <?php echo $video_id; ?>)">
                    <div class="form-group">
                        <textarea class="form-control" name="content" placeholder="写下你的评论..." required maxlength="500" rows="3"></textarea>
                        <input type="hidden" name="video_id" value="<?php echo $video_id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">发布评论</button>
                </form>
            </div>
        <?php else: ?>
            <p><a href="login.php">登录</a>后发表评论</p>
        <?php endif; ?>
        
        <div id="comments-list">
            <?php foreach ($comments as $comment): ?>
                <div class="comment-item">
                    <?php if ($comment['avatar']): ?>
                        <img src="<?php echo $comment['avatar']; ?>" alt="Avatar" class="comment-avatar">
                    <?php else: ?>
                        <div style="width: 40px; height: 40px; background-color: #ccc; border-radius: 50%; margin-right: 10px;"></div>
                    <?php endif; ?>
                    <div class="comment-content">
                        <div class="comment-author">@<?php echo htmlspecialchars($comment['username'] ?: $comment['nickname']); ?></div>
                        <div class="comment-text"><?php echo htmlspecialchars($comment['content']); ?></div>
                        <div class="comment-time"><?php echo $comment['addtime']; ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
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
            const icon = $(element).find('i');
            if (res.liked) {
                icon.removeClass('fa-heart').addClass('fa-heart text-danger');
            } else {
                icon.removeClass('fa-heart text-danger').addClass('fa-heart');
            }
            
            // Update like count in video stats
            const likeCountElement = $('.video-stats').find('span:contains("赞")');
            let currentCount = parseInt(likeCountElement.text().match(/\d+/)[0]);
            if (res.liked) {
                currentCount++;
            } else {
                currentCount--;
            }
            likeCountElement.html(`<i class="fa fa-heart"></i> ${currentCount} 赞`);
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
    
    const shareUrl = window.location.origin + '/view_video.php?id=' + videoId;
    if (navigator.share) {
        navigator.share({
            title: '<?php echo addslashes($video['title']); ?>',
            text: '<?php echo addslashes($video['description']); ?>',
            url: shareUrl
        });
    } else {
        navigator.clipboard.writeText(shareUrl).then(function() {
            alert('链接已复制到剪贴板');
        });
    }
}

function toggleFollow(userId, element) {
    if (!<?php echo $islogin2 ? 'true' : 'false'; ?>) {
        alert('请先登录');
        window.location.href = 'login.php';
        return;
    }
    
    $.post('ajax.php?act=toggle_follow', {
        user_id: userId,
        csrf_token: $('meta[name="csrf-token"]').attr('content') || ''
    }, function(res) {
        if (res.code === 0) {
            if (res.following) {
                $(element).text('已关注').removeClass('btn-outline-primary').addClass('btn-primary');
            } else {
                $(element).text('+ 关注').removeClass('btn-primary').addClass('btn-outline-primary');
            }
            
            // Update follower count
            const followerCount = parseInt($('.followers').text());
            $('.followers').text((res.following ? followerCount + 1 : followerCount - 1) + ' 粉丝');
        } else {
            alert(res.msg || '操作失败');
        }
    }, 'json');
}

function submitComment(event, videoId) {
    event.preventDefault();
    
    const form = event.target;
    const content = form.content.value;
    
    $.post('ajax.php?act=comment_video', {
        video_id: videoId,
        content: content,
        csrf_token: form.csrf_token.value
    }, function(res) {
        if (res.code === 0) {
            // Add comment to list
            const commentHtml = `
                <div class="comment-item">
                    <div style="width: 40px; height: 40px; background-color: #ccc; border-radius: 50%; margin-right: 10px;"></div>
                    <div class="comment-content">
                        <div class="comment-author">@<?php echo addslashes($userrow['username'] ?: $userrow['nickname']); ?></div>
                        <div class="comment-text">${content}</div>
                        <div class="comment-time">刚刚</div>
                    </div>
                </div>
            `;
            $('#comments-list').prepend(commentHtml);
            
            // Clear form
            form.content.value = '';
            
            // Update comment count
            const commentCountElement = $('.video-stats').find('span:contains("评论")');
            let currentCount = parseInt(commentCountElement.text().match(/\d+/)[0]);
            currentCount++;
            commentCountElement.html(`<i class="fa fa-comment"></i> ${currentCount} 评论`);
        } else {
            alert(res.msg || '评论失败');
        }
    }, 'json');
}
</script>

<?php include SYSTEM_ROOT.'footer.php'; ?>
</body>
</html>