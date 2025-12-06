# php-opencloud

这是一个基于 PHP 的云存储和文件分享平台。

## 安装和运行

### 前提条件

- PHP >= 7.4 (需先安装 PHP 并确保 php 指令可用)
- Node.js >= 14.0.0
- npm >= 6.0.0

### 安装

1. 克隆项目到本地：

```bash
git clone https://github.com/Starlight-apk/php-opencloud.git
cd php-opencloud
```

2. 安装依赖：

```bash
npm install
```

### 运行

要启动开发服务器，请运行：

```bash
npm start
```

这将在 `0.0.0.0:8080` 端口启动 PHP 内置服务器，使您可以通过手机或其他设备访问该应用。

如果遇到 "Address already in use" 错误，说明端口 8080 已被占用，请先停止占用该端口的进程，或修改 package.json 中的端口号。

### 端口占用问题解决

如果遇到 `[Sat Dec 6 20:31:05 2025] Failed to listen on 0.0.0.0:8080 (reason: Address already in use)` 错误：

1. 查找占用端口的进程：
   ```bash
   lsof -i :8080
   ```

2. 终止占用端口的进程：
   ```bash
   kill -9 [进程ID]
   ```

3. 或者修改 package.json 文件，使用其他端口：
   ``bjson
   {
     "scripts": {
       "start": "php -S 0.0.0.0:8081",
       "dev": "php -S 0.0.0.0:8081"
     }
   }
   ```

## 配置

项目需要配置数据库连接信息，这些信息可以在 `config.php` 文件中设置。

## 部署

项目包含 GitHub Actions 配置，用于自动部署到服务器。

## 脚本说明

- `npm start` - 启动 PHP 内置服务器，监听 8080 端口
- `npm dev` - 开发模式，启动 PHP 内置服务器
- `npm install` - 安装依赖并完成项目设置

## 项目结构

- `admin/` - 管理后台相关文件
- `assets/` - 静态资源文件
- `data/` - 数据存储目录
- `includes/` - 公共包含文件
- `install/` - 安装程序
- 根目录 - PHP 页面文件
- `.github/workflows/` - GitHub Actions 配置

## 特性

- 用户注册和登录
- 文件上传和下载
- 文件分享功能
- 管理后台
- 支持多种云存储服务
- 响应式设计，适配手机端

## 注意事项

由于这是一个 PHP Web 应用，需要配置数据库才能完全运行。请确保在部署前完成数据库配置。