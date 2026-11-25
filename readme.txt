=== WordPress DeepSeek AI Assistant ===
Contributors: wordpress-ai-blog
Tags: ai, deepseek, assistant, content, chatbot
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

为 WordPress 提供 DeepSeek 前台问答和后台文章初稿生成功能。

== Description ==

主要功能：

* 使用 [deepseek_ai_chat] 短代码创建前台 AI 助手页面。
* 在 WordPress 后台根据主题、关键词和分类生成文章初稿。
* 生成内容经人工编辑后保存为草稿，不自动发布。
* 包含 Nonce 校验、输入过滤和简单访问频率限制。
* API Key 从服务器外部文件读取，不保存在插件源码中。

完整安装说明请查看 README.md。

== Installation ==

1. 将插件目录上传到 /wp-content/plugins/。
2. 将 DeepSeek API Key 写入 /etc/wordpress-deepseek.key，并限制文件权限。
3. 在 WordPress 后台启用插件。
4. 在页面正文加入 [deepseek_ai_chat]。

== Changelog ==

= 1.1.1 =

* 增加后台文章初稿生成和草稿保存功能。
* 增加前台问答界面、REST API、Nonce 校验及简单限流。
* API Key 改为从服务器外部文件读取。

