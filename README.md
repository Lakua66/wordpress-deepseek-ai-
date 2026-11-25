# WordPress DeepSeek AI Assistant

一个面向 WordPress 的轻量级 DeepSeek 插件，提供前台 AI 问答和后台文章初稿生成功能。

## 功能

- 使用短代码生成前台 AI 助手页面
- 通过 WordPress REST API 转发 DeepSeek 请求
- 后台根据主题、关键词和分类生成文章初稿
- 生成结果可人工修改并保存为草稿
- Nonce 校验、输入过滤和基于访问 IP 的简单限流
- API Key 保存在 Web 根目录之外，不写入插件源码和数据库

## 运行环境

- WordPress 5.8 或更高版本
- PHP 7.4 或更高版本
- 服务器可以通过 HTTPS 访问 `api.deepseek.com`
- 有效的 DeepSeek API Key

## 安装

1. 下载本项目并将目录命名为 `wp-deepseek-ai-assistant`。
2. 上传到 WordPress 的 `wp-content/plugins/` 目录。
3. 在服务器创建密钥文件：

   ```bash
   sudo install -m 640 -o root -g nginx /dev/null /etc/wordpress-deepseek.key
   sudo sh -c 'printf "%s" "你的_DeepSeek_API_Key" > /etc/wordpress-deepseek.key'
   ```

   如果 PHP-FPM 不是以 `nginx` 组运行，请把上面的组名改为实际运行组。

4. 在 WordPress 后台启用“WordPress DeepSeek AI Assistant”。

## 前台问答

新建一个 WordPress 页面，在正文中加入：

```text
[deepseek_ai_chat]
```

发布后即可显示 AI 问答界面。前端按 `Ctrl + Enter` 或点击“发送问题”提交。

## 后台文章生成

启用插件后，后台菜单会出现“AI文章生成”。拥有 `edit_posts` 权限的用户可以：

1. 输入文章主题、关键词并选择分类；
2. 调用 DeepSeek 生成标题、摘要和 HTML 正文；
3. 人工检查和编辑内容；
4. 保存为 WordPress 草稿。

插件不会自动发布 AI 内容。

## 可选配置

可通过 WordPress 过滤器修改默认模型或密钥文件位置：

```php
add_filter('wpdsai_model', function () {
    return 'deepseek-chat';
});

add_filter('wpdsai_article_model', function () {
    return 'deepseek-chat';
});

add_filter('wpdsai_key_file', function () {
    return '/secure/path/deepseek.key';
});
```

## 安全说明

- 不要把 API Key 写入 PHP、JavaScript、README、提交记录或公开 Issue。
- 建议仅允许 PHP-FPM 运行用户读取密钥文件。
- 生产环境应启用 HTTPS、防火墙和访问日志审计。
- 当前限流使用 WordPress Transient，适合单站点或低并发环境；高并发部署建议接入 Redis 或网关限流。
- AI 输出可能存在错误，发布前应由人工核对。

## 目录结构

```text
wp-deepseek-ai-assistant/
├─ assets/
│  ├─ chat.css
│  └─ chat.js
├─ .gitignore
├─ LICENSE
├─ README.md
├─ readme.txt
└─ wp-deepseek-ai-assistant.php
```

## 上传到 GitHub

在本目录打开终端后执行：

```bash
git init
git add .
git commit -m "Initial release"
git branch -M main
git remote add origin https://github.com/你的用户名/wp-deepseek-ai-assistant.git
git push -u origin main
```

上传前请再次确认目录中不存在 API Key、服务器密码、`.env` 文件或数据库备份。

## 许可证

本项目使用 GPL-2.0-or-later 许可证。

