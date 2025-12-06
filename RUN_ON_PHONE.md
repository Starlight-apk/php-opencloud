# 在手机上运行项目说明

## 手机端运行指南

要在此手机上运行这个PHP Wea应用程序，请按照以下步骤操作：

### 安装 PHP

您需要先在手机上安装 PHP。推荐使用 Termux 应用：

1. 在 F-Droid 或 Google Play 上安装 Termux
2. 打开 Termux 并运行以下命令：

```bash
pkg update && pkg upgrade
pkc install php
```

### 安装 Node.js

在 Termux 中安装 Node.js：

```bash
pkg install nodejs
```

### 安装项目依赖

在项目目录中运行：

```bash
npm install
```

### 启动服务器

要启动 PHP 服务器并在手机的 8080 端口上运行项目，请运行：

```bash
npm start
```

服务器将在 `0.0.0.0:8080` 端口启动，这意味着它可以从手机的 IP 地址访问。

### 访问应用

启动服务器后，您可以通过以下方式访问应用：

1. 在手机上的浏览器中访问：`http://localhost:8080`
2. 从同一网络的其他设备访问：`http://<手机IP地址>:8080`

### 数据库配置

此应用需要数据库才能正常运行。在 `config.php` 文件中配置数据库连接信息：

- `DB_HOST` - 数据库主机
- `DB_PORT` - 数据库端口
- `DB_USER` - 数据库用户名
- `DB_PWD` - 数据库密码
- `DB_NAME` - 数据库名称

### 安装应用

首次运行时，请访问 `http://<手机IP地址>:8080/install/` 完成安装向导。

### GitHub 部署

如果要将此项目部署到远程服务器，请确保在 GitHub 仓库的 Secrets 中设置以下变量：

- `SSH_PRIVATE_KEY` - 用于连接到远程服务器的 SSH 私钥
- `SSH_HOST` - 远程服务器的主机名或 IP 地址

该部署脚本会将项目复制到远程服务器的 `~/web-project` 目录，并在 8080 端口启动服务。