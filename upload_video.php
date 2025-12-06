<?php
include("./includes/common.php");

if(!$islogin2) {
    header('Location: login.php');
    exit;
}

$title = '上传视频 - '.$conf['title'];
include SYSTEM_ROOT.'header.php';

$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;

// Get allowed video extensions
$allowed_video_exts = explode('|', $conf['video_extensions'] ?? 'mp4|mov|avi|wmv|flv|f4v|webm|3gp|3gpp');
?>

<div class="container" id="app">
    <div class="row">
        <div class="col-sm-9">
            <div class="well infobox" align="center" id="fileInput" :style="{background: background}">
                <div style="min-height:50px;">
                    <div id="progressBar" v-if="showtype==1">
                        <div class="progress progress-striped active"><div class="progress-bar" style="width: 0%" :style="{ width: progress + '%' }">{{progress_tip}}</div></div><div class="row"><div class="col-xs-3" style="text-align:left;" id="percentage"><span v-if="progress>0">{{progress}}%</span></div><div class="col-xs-6 filename">{{filename}}</div><div class="col-xs-3" style="text-align:right;" id="uploadspeed">{{uploadspeed}}</div></div>
                    </div>
                    <div class="alert alert-dismissible" :class="'alert-'+alert.type" v-if="showtype==2">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{alert.msg}}</strong>
                    </div>
                </div>

                <br><br>
                <h1 style="color:#8d8b8b;" id="uploadTitle">{{uploadTitle}}</h1>

                <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo $csrf_token?>">
                <input type="file" id="file" name="videoFile" @change="selectFile" style="display:none" accept="<?php echo '.' . implode(',.', $allowed_video_exts); ?>"/>

                <div id="upload_frame">
                    <button id="uploadFile" class="btn btn-raised btn-primary" style="height:50px;font-size:20px;" @click="clickUpload"><i class="fa fa-upload"></i> 选择视频<div class="ripple-container"></div></button>

                    <div class="form-group mt-3">
                        <input type="text" class="form-control" id="video_title" placeholder="视频标题" v-model="input.title" maxlength="100">
                    </div>
                    <div class="form-group">
                        <textarea class="form-control" id="video_desc" placeholder="视频描述（可选）" v-model="input.description" maxlength="500" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" id="show" v-model="input.show"> 公开视频
                            </label>
                        </div>
                    </div>
                </div>

                <br><br><br><br>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-exclamation-circle"></i> 上传提示</h3>
                </div>
                <div class="list-group-item">
                    **您的IP是<?php echo $clientip?>，请不要上传违规视频！
                </div>
                <?php
                $video_size_limit = intval($conf['video_upload_size'] ?? $conf['upload_size'] ?? 1024);
                if($video_size_limit > 0){?>
                <div class="list-group-item">**仅支持视频格式：<?php echo strtoupper(str_replace('|', ', ', $conf['video_extensions'] ?? 'mp4,mov,avi')); ?>，单个视频文件最大支持<b><?php echo $video_size_limit?>MB</b>！
                </div>
                <?php }?>
                <div class="list-group-item">**请确保您上传的视频内容合法合规，不侵犯他人版权！
                </div>
            </div>
        </div>
    </div>
</div>


<?php include SYSTEM_ROOT.'footer.php';?>
<script src="https://s4.zstatic.net/ajax/libs/vue/2.6.14/vue.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/spark-md5/3.0.2/spark-md5.min.js"></script>
<script>
var upload_max_filesize = <?php echo intval($conf['video_upload_size'] ?? $conf['upload_size'] ?? 1024) ?>;
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Vue({
        el: '#app',
        data: {
            uploadTitle: '点击上方按钮选择视频',
            background: '#8d8b8b',
            progress: 0,
            showtype: 0,
            alert: {type: '', msg: ''},
            filename: '',
            progress_tip: '',
            uploadspeed: '',
            input: {
                title: '',
                description: '',
                show: true
            }
        },
        methods: {
            clickUpload: function() {
                document.getElementById('file').click();
            },
            selectFile: function(e) {
                const file = e.target.files[0];
                if (!file) return;

                // Check file type
                const allowedExtensions = ['<?php echo implode("','", $allowed_video_exts); ?>'];
                const fileExt = file.name.split('.').pop().toLowerCase();
                if (!allowedExtensions.includes(fileExt)) {
                    this.showAlert('danger', '不支持的视频格式！支持的格式：<?php echo strtoupper(str_replace("|", ", ", $conf['video_extensions'] ?? 'mp4|mov|avi')); ?>');
                    return;
                }

                // Check file size
                const effectiveMaxSize = upload_max_filesize > 0 ? upload_max_filesize : 1024; // Default to 1GB if not set
                const maxSize = effectiveMaxSize * 1024 * 1024; // Convert to bytes
                if (file.size > maxSize) {
                    this.showAlert('danger', `文件大小不能超过 ${effectiveMaxSize}MB`);
                    return;
                }

                this.filename = file.name;
                this.uploadTitle = '准备上传: ' + file.name;
                this.beginUpload(file);
            },
            beginUpload: function(file) {
                const self = this;
                
                // Calculate file hash
                const blobSlice = File.prototype.slice;
                const chunkSize = 2097152; // Read in chunks of 2MB
                const chunks = Math.ceil(file.size / chunkSize);
                let currentChunk = 0;
                const spark = new SparkMD5.ArrayBuffer();
                const fileReader = new FileReader();

                this.showtype = 1; // Show progress bar
                this.progress = 0;
                
                fileReader.onload = function(e) {
                    spark.append(e.target.result); // Append array buffer
                    currentChunk++;

                    self.progress = Math.round((currentChunk / chunks) * 100);
                    self.progress_tip = `正在校验文件 ${currentChunk}/${chunks}`;

                    if (currentChunk < chunks) {
                        loadNext();
                    } else {
                        const hash = spark.end();
                        self.uploadFileToServer(file, hash);
                    }
                };

                fileReader.onerror = function() {
                    self.showAlert('danger', '文件读取出错');
                    self.showtype = 0;
                };

                function loadNext() {
                    const start = currentChunk * chunkSize;
                    const end = ((start + chunkSize) >= file.size) ? file.size : start + chunkSize;

                    fileReader.readAsArrayBuffer(blobSlice.call(file, start, end));
                }

                loadNext();
            },
            uploadFileToServer: function(file, hash) {
                const self = this;
                
                // Check if file already exists
                $.ajax({
                    url: 'ajax.php?act=pre_upload',
                    type: 'POST',
                    data: {
                        name: file.name,
                        hash: hash,
                        size: file.size,
                        show: this.input.show ? 1 : 0,
                        csrf_token: $('#csrf_token').val()
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.code === 1 && response.exists === 1) {
                            // File already exists
                            self.showAlert('warning', '视频已存在，无需重复上传');
                            self.showtype = 0;
                            self.uploadTitle = '上传完成';
                        } else if (response.code === 0) {
                            // File doesn't exist, start upload
                            self.startFileUpload(file, hash, response);
                        } else {
                            self.showAlert('danger', response.msg || '预上传检查失败');
                            self.showtype = 0;
                        }
                    },
                    error: function() {
                        self.showAlert('danger', '上传请求失败');
                        self.showtype = 0;
                    }
                });
            },
            startFileUpload: function(file, hash, response) {
                const self = this;
                
                if (response.third) {
                    // Handle third-party upload
                    self.uploadViaAPI(file, hash);
                } else {
                    // Handle direct upload
                    self.uploadDirectly(file, hash, response);
                }
            },
            uploadDirectly: function(file, hash, response) {
                const self = this;
                const chunkSize = response.chunksize || 8 * 1024 * 1024; // 8MB default
                const chunks = response.chunks;
                let currentChunk = 0;
                const formData = new FormData();
                
                function uploadChunk() {
                    const start = currentChunk * chunkSize;
                    const end = Math.min(start + chunkSize, file.size);
                    const chunk = file.slice(start, end);
                    
                    formData.append('file', chunk);
                    formData.append('hash', hash);
                    formData.append('chunk', currentChunk + 1);
                    formData.append('chunks', chunks);
                    formData.append('csrf_token', $('#csrf_token').val());
                    
                    $.ajax({
                        url: 'ajax.php?act=upload_part',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(res) {
                            if (res.code === 0) {
                                // Continue with next chunk
                                currentChunk++;
                                self.progress = Math.round((currentChunk / chunks) * 100);
                                self.progress_tip = `上传中... ${currentChunk}/${chunks}`;
                                
                                if (currentChunk < chunks) {
                                    formData.delete('file');
                                    formData.delete('hash');
                                    formData.delete('chunk');
                                    formData.delete('chunks');
                                    formData.delete('csrf_token');
                                    uploadChunk();
                                } else {
                                    // Finalize upload
                                    self.finalizeUpload(hash);
                                }
                            } else {
                                self.showAlert('danger', res.msg || '上传失败');
                                self.showtype = 0;
                            }
                        },
                        error: function() {
                            self.showAlert('danger', '上传失败，请重试');
                            self.showtype = 0;
                        }
                    });
                }
                
                uploadChunk();
            },
            uploadViaAPI: function(file, hash) {
                // Implementation for API-based upload would go here
                // This maintains compatibility with existing storage systems
                const self = this;
                const formData = new FormData();
                
                formData.append('file', file);
                formData.append('hash', hash);
                formData.append('name', file.name);
                formData.append('size', file.size);
                formData.append('show', this.input.show ? 1 : 0);
                formData.append('title', this.input.title);
                formData.append('description', this.input.description);
                formData.append('csrf_token', $('#csrf_token').val());
                
                $.ajax({
                    url: 'ajax.php?act=upload_part',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: function() {
                        const xhr = new window.XMLHttpRequest();
                        // Upload progress
                        xhr.upload.addEventListener("progress", function(evt) {
                            if (evt.lengthComputable) {
                                const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                                self.progress = percentComplete;
                                self.progress_tip = `上传中... ${percentComplete}%`;
                            }
                        }, false);
                        return xhr;
                    },
                    success: function(res) {
                        if (res.code === 1) {
                            self.showAlert('success', '视频上传成功！');
                            self.showtype = 0;
                            self.uploadTitle = '上传完成';
                            
                            // Redirect to video list or profile after a delay
                            setTimeout(function() {
                                window.location.href = './';
                            }, 2000);
                        } else {
                            self.showAlert('danger', res.msg || '上传失败');
                            self.showtype = 0;
                        }
                    },
                    error: function() {
                        self.showAlert('danger', '上传请求失败');
                        self.showtype = 0;
                    }
                });
            },
            finalizeUpload: function(hash) {
                const self = this;
                
                $.post('ajax.php?act=complete_upload', {
                    hash: hash,
                    csrf_token: $('#csrf_token').val(),
                    title: this.input.title,
                    description: this.input.description
                }, function(res) {
                    if (res.code === 1) {
                        self.showAlert('success', '视频上传成功！');
                        self.showtype = 0;
                        self.uploadTitle = '上传完成';
                        
                        // Redirect to video list or profile after a delay
                        setTimeout(function() {
                            window.location.href = './';
                        }, 2000);
                    } else {
                        self.showAlert('danger', res.msg || '完成上传失败');
                        self.showtype = 0;
                    }
                }, 'json');
            },
            showAlert: function(type, msg) {
                this.alert = {type: type, msg: msg};
                this.showtype = 2; // Show alert
                
                // Auto hide after 5 seconds
                setTimeout(() => {
                    this.showtype = 0;
                }, 5000);
            }
        }
    });
});
</script>
</body>
</html>