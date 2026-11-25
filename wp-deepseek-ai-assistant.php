<?php
/**
 * Plugin Name: WordPress DeepSeek AI Assistant
 * Description: 为 WordPress AI 智能博客系统提供安全的 DeepSeek 前台问答和后台文章生成功能。
 * Version: 1.1.1
 * Author: WordPress AI智能博客系统
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-deepseek-ai-assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPDSAI_VERSION', '1.1.1');
define('WPDSAI_PLUGIN_URL', plugin_dir_url(__FILE__));

function wpdsai_register_assets() {
    wp_register_style(
        'wpdsai-chat',
        WPDSAI_PLUGIN_URL . 'assets/chat.css',
        array(),
        WPDSAI_VERSION
    );

    wp_register_script(
        'wpdsai-chat',
        WPDSAI_PLUGIN_URL . 'assets/chat.js',
        array(),
        WPDSAI_VERSION,
        true
    );

    if (is_singular()) {
        $post = get_post();
        if ($post && has_shortcode($post->post_content, 'deepseek_ai_chat')) {
            wpdsai_enqueue_assets();
        }
    }
}
add_action('wp_enqueue_scripts', 'wpdsai_register_assets');

function wpdsai_enqueue_assets() {
    static $localized = false;

    wp_enqueue_style('wpdsai-chat');
    wp_enqueue_script('wpdsai-chat');

    if (!$localized) {
        wp_localize_script('wpdsai-chat', 'wpDeepSeekAI', array(
            'endpoint' => esc_url_raw(rest_url('wp-deepseek-ai/v1/chat')),
            'nonce'    => wp_create_nonce('wp_rest'),
        ));
        $localized = true;
    }
}

function wpdsai_chat_shortcode() {
    wpdsai_enqueue_assets();

    ob_start();
    ?>
    <main
        class="wpdsai-wrap"
        data-endpoint="<?php echo esc_url(rest_url('wp-deepseek-ai/v1/chat')); ?>"
        data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>"
    >
        <section class="wpdsai-hero">
            <p class="wpdsai-eyebrow">DEEPSEEK · WORDPRESS · AI</p>
            <h1>AI智能助手</h1>
            <p>输入你的问题，让人工智能帮助你分析问题、整理思路和生成内容。</p>
        </section>

        <section class="wpdsai-chat" aria-labelledby="wpdsai-chat-title">
            <h2 id="wpdsai-chat-title">开始对话</h2>

            <div id="wpdsai-answer" class="wpdsai-answer" role="status" aria-live="polite">
                <span class="wpdsai-avatar" aria-hidden="true">AI</span>
                <div class="wpdsai-answer-text">你好！我是本站的 AI 助手，请在下方输入你的问题。</div>
            </div>

            <label for="wpdsai-question">你的问题</label>
            <textarea
                id="wpdsai-question"
                class="wpdsai-question"
                maxlength="2000"
                placeholder="例如：请介绍一下 LNMP 架构的组成和工作流程……"
            ></textarea>

            <div class="wpdsai-actions">
                <span id="wpdsai-counter">0 / 2000</span>
                <button id="wpdsai-send" class="wpdsai-send" type="button">发送问题</button>
            </div>

            <p class="wpdsai-note">AI 生成内容仅供参考，请核对重要信息。</p>
        </section>
    </main>
    <?php
    return ob_get_clean();
}
add_shortcode('deepseek_ai_chat', 'wpdsai_chat_shortcode');

function wpdsai_register_rest_routes() {
    register_rest_route('wp-deepseek-ai/v1', '/chat', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'wpdsai_handle_chat',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'wpdsai_register_rest_routes');

function wpdsai_client_ip() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    return preg_replace('/[^0-9a-fA-F:\.]/', '', $ip);
}

function wpdsai_rate_limit_check() {
    $key   = 'wpdsai_rate_' . md5(wpdsai_client_ip());
    $count = (int) get_transient($key);

    if ($count >= 10) {
        return false;
    }

    set_transient($key, $count + 1, 5 * MINUTE_IN_SECONDS);
    return true;
}

function wpdsai_read_api_key() {
    $key_file = apply_filters('wpdsai_key_file', '/etc/wordpress-deepseek.key');

    if (!is_readable($key_file)) {
        return new WP_Error('key_unreadable', '服务器无法读取 DeepSeek API Key 文件。');
    }

    $api_key = trim((string) file_get_contents($key_file));
    if ($api_key === '') {
        return new WP_Error('key_empty', 'DeepSeek API Key 文件为空。');
    }

    return $api_key;
}

function wpdsai_handle_chat(WP_REST_Request $request) {
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', '页面验证已失效，请刷新后重试。', array('status' => 403));
    }

    if (!wpdsai_rate_limit_check()) {
        return new WP_Error('rate_limited', '请求过于频繁，请五分钟后再试。', array('status' => 429));
    }

    $question = sanitize_textarea_field((string) $request->get_param('question'));
    $length   = function_exists('mb_strlen') ? mb_strlen($question, 'UTF-8') : strlen($question);

    if ($question === '') {
        return new WP_Error('empty_question', '请输入问题。', array('status' => 400));
    }

    if ($length > 2000) {
        return new WP_Error('question_too_long', '问题不能超过 2000 个字符。', array('status' => 400));
    }

    $api_key = wpdsai_read_api_key();
    if (is_wp_error($api_key)) {
        return new WP_Error($api_key->get_error_code(), $api_key->get_error_message(), array('status' => 500));
    }

    $body = array(
        'model'       => apply_filters('wpdsai_model', 'deepseek-flash'),
        'messages'    => array(
            array(
                'role'    => 'system',
                'content' => '你是 WordPress AI智能博客系统的中文助手。请准确、清晰、友好地回答用户问题；遇到不确定的信息要明确说明。',
            ),
            array(
                'role'    => 'user',
                'content' => $question,
            ),
        ),
        'stream'      => false,
        'max_tokens'  => 1024,
        'temperature' => 0.7,
    );

    $response = wp_remote_post('https://api.deepseek.com/chat/completions', array(
        'timeout' => 90,
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ),
        'body'    => wp_json_encode($body, JSON_UNESCAPED_UNICODE),
    ));

    unset($api_key);

    if (is_wp_error($response)) {
        return new WP_Error('api_connection_failed', '连接 DeepSeek 失败：' . $response->get_error_message(), array('status' => 502));
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $data   = json_decode(wp_remote_retrieve_body($response), true);

    if ($status < 200 || $status >= 300) {
        $message = isset($data['error']['message']) ? sanitize_text_field($data['error']['message']) : 'DeepSeek API 请求失败。';
        return new WP_Error('api_error', $message, array('status' => 502));
    }

    $answer = isset($data['choices'][0]['message']['content'])
        ? trim((string) $data['choices'][0]['message']['content'])
        : '';

    if ($answer === '') {
        return new WP_Error('empty_answer', 'DeepSeek 未返回正文，请重新提问。', array('status' => 502));
    }

    return rest_ensure_response(array(
        'answer' => $answer,
        'model'  => isset($data['model']) ? sanitize_text_field($data['model']) : 'deepseek-flash',
    ));
}

function wpdsai_admin_menu() {
    add_menu_page(
        'AI文章生成',
        'AI文章生成',
        'edit_posts',
        'wpdsai-article-generator',
        'wpdsai_render_article_generator',
        'dashicons-edit-page',
        26
    );
}
add_action('admin_menu', 'wpdsai_admin_menu');

function wpdsai_generate_article($topic, $keywords, $category_name) {
    $api_key = wpdsai_read_api_key();
    if (is_wp_error($api_key)) {
        return $api_key;
    }

    $prompt = "请围绕以下资料撰写一篇适合技术博客发布的中文文章。\n"
        . "主题：{$topic}\n"
        . "关键词：{$keywords}\n"
        . "分类：{$category_name}\n\n"
        . "要求：\n"
        . "1. 标题清晰专业，不夸张。\n"
        . "2. 摘要控制在 80 至 150 字。\n"
        . "3. 正文约 800 至 1200 字，结构完整，包含引言、分节说明和总结。\n"
        . "4. 正文使用简单 HTML，只允许 p、h2、h3、ul、ol、li、strong、code、pre 标签。\n"
        . "5. 不要编造测试数据、引用来源或不存在的项目成果。\n"
        . "6. 仅返回合法 JSON，不要使用 Markdown 代码围栏。格式必须是："
        . '{"title":"文章标题","excerpt":"文章摘要","content_html":"文章HTML正文"}';

    $payload = array(
        'model'       => apply_filters('wpdsai_article_model', 'deepseek-flash'),
        'messages'    => array(
            array(
                'role'    => 'system',
                'content' => '你是一名严谨的中文技术博客编辑。必须严格按照用户指定的 JSON 格式输出。',
            ),
            array(
                'role'    => 'user',
                'content' => $prompt,
            ),
        ),
        'stream'      => false,
        'max_tokens'  => 8192,
        'temperature' => 0.6,
    );

    $response = wp_remote_post('https://api.deepseek.com/chat/completions', array(
        'timeout' => 120,
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ),
        'body'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
    ));

    unset($api_key);

    if (is_wp_error($response)) {
        return new WP_Error('article_connection_failed', '连接 DeepSeek 失败：' . $response->get_error_message());
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $data   = json_decode(wp_remote_retrieve_body($response), true);

    if ($status < 200 || $status >= 300) {
        $message = isset($data['error']['message']) ? sanitize_text_field($data['error']['message']) : 'DeepSeek API 请求失败。';
        return new WP_Error('article_api_error', $message);
    }

    $raw = isset($data['choices'][0]['message']['content'])
        ? trim((string) $data['choices'][0]['message']['content'])
        : '';

    if ($raw === '') {
        return new WP_Error('article_empty', 'DeepSeek 未返回文章内容，请重试。');
    }

    $clean = preg_replace('/^```(?:json)?\s*/i', '', $raw);
    $clean = preg_replace('/\s*```$/', '', $clean);
    $article = json_decode($clean, true);

    if (!is_array($article) || empty($article['title']) || empty($article['content_html'])) {
        return new WP_Error('article_invalid_json', '模型返回格式异常，请重新生成。');
    }

    return array(
        'title'        => sanitize_text_field($article['title']),
        'excerpt'      => sanitize_textarea_field(isset($article['excerpt']) ? $article['excerpt'] : ''),
        'content_html' => wp_kses_post($article['content_html']),
    );
}

function wpdsai_render_article_generator() {
    if (!current_user_can('edit_posts')) {
        wp_die(esc_html__('您没有权限访问此页面。'));
    }

    $generated = null;
    $message = '';
    $message_type = 'success';
    $topic = '';
    $keywords = '';
    $category_id = 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpdsai_action'])) {
        $action = sanitize_key(wp_unslash($_POST['wpdsai_action']));

        if ($action === 'generate') {
            check_admin_referer('wpdsai_generate_article');

            $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
            $keywords = isset($_POST['keywords']) ? sanitize_text_field(wp_unslash($_POST['keywords'])) : '';
            $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
            $category = $category_id ? get_category($category_id) : null;
            $category_name = $category && !is_wp_error($category) ? $category->name : '未分类';

            if ($topic === '') {
                $message = '请输入文章主题。';
                $message_type = 'error';
            } else {
                $generated = wpdsai_generate_article($topic, $keywords, $category_name);
                if (is_wp_error($generated)) {
                    $message = $generated->get_error_message();
                    $message_type = 'error';
                    $generated = null;
                } else {
                    $message = '文章已生成。请检查并修改内容，然后保存为草稿。';
                }
            }
        }

        if ($action === 'save_draft') {
            check_admin_referer('wpdsai_save_article');

            $title = isset($_POST['generated_title']) ? sanitize_text_field(wp_unslash($_POST['generated_title'])) : '';
            $excerpt = isset($_POST['generated_excerpt']) ? sanitize_textarea_field(wp_unslash($_POST['generated_excerpt'])) : '';
            $content = isset($_POST['generated_content']) ? wp_kses_post(wp_unslash($_POST['generated_content'])) : '';
            $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;

            if ($title === '' || $content === '') {
                $message = '标题和正文不能为空。';
                $message_type = 'error';
                $generated = array('title' => $title, 'excerpt' => $excerpt, 'content_html' => $content);
            } else {
                $post_data = array(
                    'post_title'    => $title,
                    'post_excerpt'  => $excerpt,
                    'post_content'  => $content,
                    'post_status'   => 'draft',
                    'post_type'     => 'post',
                    'post_author'   => get_current_user_id(),
                    'post_category' => $category_id ? array($category_id) : array(),
                );

                $post_id = wp_insert_post($post_data, true);
                if (is_wp_error($post_id)) {
                    $message = '保存草稿失败：' . $post_id->get_error_message();
                    $message_type = 'error';
                    $generated = array('title' => $title, 'excerpt' => $excerpt, 'content_html' => $content);
                } else {
                    $message = '草稿已保存。<a href="' . esc_url(get_edit_post_link($post_id)) . '">打开文章编辑器</a>';
                }
            }
        }
    }

    $categories = get_categories(array('hide_empty' => false));
    ?>
    <div class="wrap wpdsai-admin-wrap">
        <h1>AI文章生成</h1>
        <p>输入文章主题和关键词，由 DeepSeek 生成初稿。生成结果必须经过人工检查后再发布。</p>

        <?php if ($message !== '') : ?>
            <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible"><p><?php echo wp_kses_post($message); ?></p></div>
        <?php endif; ?>

        <div class="wpdsai-admin-card">
            <h2>第一步：设置文章主题</h2>
            <form method="post">
                <?php wp_nonce_field('wpdsai_generate_article'); ?>
                <input type="hidden" name="wpdsai_action" value="generate">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wpdsai-topic">文章主题</label></th>
                        <td><input id="wpdsai-topic" name="topic" type="text" class="regular-text" required value="<?php echo esc_attr($topic); ?>" placeholder="例如：LNMP架构下部署WordPress"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wpdsai-keywords">关键词</label></th>
                        <td><input id="wpdsai-keywords" name="keywords" type="text" class="regular-text" value="<?php echo esc_attr($keywords); ?>" placeholder="例如：Linux、Nginx、MariaDB、PHP"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wpdsai-category">文章分类</label></th>
                        <td>
                            <select id="wpdsai-category" name="category_id">
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected($category_id, $category->term_id); ?>><?php echo esc_html($category->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button('生成文章初稿', 'primary', 'submit', false); ?>
                <span class="spinner" style="float:none"></span>
            </form>
        </div>

        <?php if (is_array($generated)) : ?>
            <div class="wpdsai-admin-card">
                <h2>第二步：检查并保存草稿</h2>
                <form method="post">
                    <?php wp_nonce_field('wpdsai_save_article'); ?>
                    <input type="hidden" name="wpdsai_action" value="save_draft">
                    <input type="hidden" name="category_id" value="<?php echo esc_attr($category_id); ?>">

                    <p>
                        <label for="wpdsai-generated-title"><strong>标题</strong></label><br>
                        <input id="wpdsai-generated-title" name="generated_title" type="text" class="large-text" required value="<?php echo esc_attr($generated['title']); ?>">
                    </p>
                    <p>
                        <label for="wpdsai-generated-excerpt"><strong>摘要</strong></label><br>
                        <textarea id="wpdsai-generated-excerpt" name="generated_excerpt" class="large-text" rows="4"><?php echo esc_textarea($generated['excerpt']); ?></textarea>
                    </p>
                    <p><strong>正文</strong></p>
                    <?php
                    wp_editor($generated['content_html'], 'wpdsai-generated-content', array(
                        'textarea_name' => 'generated_content',
                        'textarea_rows' => 18,
                        'media_buttons' => false,
                    ));
                    ?>
                    <?php submit_button('保存为草稿'); ?>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .wpdsai-admin-wrap { max-width: 1050px; }
        .wpdsai-admin-card { margin-top: 22px; padding: 24px; border: 1px solid #dcdcde; border-radius: 10px; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.04); }
        .wpdsai-admin-card h2 { margin-top: 0; }
    </style>
    <script>
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (form.querySelector('input[name="wpdsai_action"][value="generate"]')) {
                var button = form.querySelector('input[type="submit"]');
                var spinner = form.querySelector('.spinner');
                if (button) { button.disabled = true; button.value = 'AI 正在生成，请稍候…'; }
                if (spinner) { spinner.classList.add('is-active'); }
            }
        });
    </script>
    <?php
}

