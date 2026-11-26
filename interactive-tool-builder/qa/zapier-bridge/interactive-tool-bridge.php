<?php
/**
 * Plugin Name: Interactive Tool Zapier Bridge (QA)
 * Description: Test candidate for forwarding interactive tool results to a private Zapier webhook.
 * Version: 0.0.1
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) { exit; }

const SKS_BRIDGE_OPTION = 'sks_interactive_tool_bridge_settings';

function sks_bridge_valid_url($value) {
    if (!is_string($value) || strlen($value) > 512) { return false; }
    $url = wp_parse_url(trim($value));
    if (!$url || strtolower($url['scheme'] ?? '') !== 'https' || strtolower($url['host'] ?? '') !== 'hooks.zapier.com') { return false; }
    if (isset($url['user']) || isset($url['pass']) || isset($url['port']) || isset($url['query']) || isset($url['fragment'])) { return false; }
    return (bool) preg_match('#^/hooks/catch/[0-9]+/[A-Za-z0-9]+/?$#', $url['path'] ?? '');
}

add_action('admin_menu', function () {
    add_options_page('Interactive Tool Connection', 'Interactive Tool Connection', 'manage_options', 'sks-interactive-bridge', 'sks_bridge_admin_page');
});

function sks_bridge_admin_page() {
    if (!current_user_can('manage_options')) { return; }
    $settings = get_option(SKS_BRIDGE_OPTION, []);
    $enabled = !empty($settings['enabled']);
    $has_url = !empty($settings['webhook']);
    ?>
    <div class="wrap"><h1>Interactive Tool Connection</h1>
    <p>Connect an interactive tool to a Zapier Catch Hook. The webhook stays on this site and is not printed into your page.</p>
    <p><?php echo $has_url ? 'A webhook is saved.' : 'No webhook is saved yet.'; ?></p>
    <p>Your site's submission address: <code><?php echo esc_html(rest_url('sks-interactive/v1/submit')); ?></code></p>
    <p>Any public submission endpoint can receive unwanted traffic. Rate limiting and a hidden field reduce some abuse but don't guarantee protection.</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('sks_bridge_save'); ?>
    <input type="hidden" name="action" value="sks_bridge_save">
    <label for="sks-hook"><strong>Zapier Catch Hook URL</strong></label><br>
    <input id="sks-hook" type="password" name="webhook" class="regular-text" autocomplete="off" value="">
    <p>Paste a new URL to save or replace it. Leave blank to keep the current URL.</p>
    <label><input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>> Allow submissions</label>
    <?php submit_button('Save connection'); ?>
    </form></div>
    <?php
}

add_action('admin_post_sks_bridge_save', function () {
    if (!current_user_can('manage_options')) { wp_die('Not allowed.', '', ['response' => 403]); }
    check_admin_referer('sks_bridge_save');
    $settings = get_option(SKS_BRIDGE_OPTION, []);
    $url = isset($_POST['webhook']) ? trim(wp_unslash($_POST['webhook'])) : '';
    if ($url !== '') {
        if (!sks_bridge_valid_url($url)) { wp_die('Enter a valid Zapier Catch Hook URL.', '', ['response' => 400]); }
        $settings['webhook'] = $url;
    }
    $settings['enabled'] = !empty($_POST['enabled']) && !empty($settings['webhook']);
    update_option(SKS_BRIDGE_OPTION, $settings, false);
    wp_safe_redirect(admin_url('options-general.php?page=sks-interactive-bridge'));
    exit;
});

add_action('rest_api_init', function () {
    register_rest_route('sks-interactive/v1', '/submit', [
        'methods' => 'POST',
        'callback' => 'sks_bridge_submit',
        'permission_callback' => '__return_true',
    ]);
});

function sks_bridge_submit($request) {
    $settings = get_option(SKS_BRIDGE_OPTION, []);
    if (empty($settings['enabled']) || !sks_bridge_valid_url($settings['webhook'] ?? '')) {
        return new WP_Error('unavailable', 'The connection is not available.', ['status' => 503]);
    }
    if (strlen($request->get_body()) > 8192) {
        return new WP_Error('too_large', 'The submission is too large.', ['status' => 413]);
    }
    $data = $request->get_json_params();
    if (!is_array($data) || !empty($data['website']) || ($data['send_confirmed'] ?? null) !== true) {
        return new WP_Error('invalid', 'Check the submission and try again.', ['status' => 400]);
    }
    $name = $data['tool_name'] ?? '';
    $result = $data['displayed_result'] ?? '';
    $answers = $data['answers'] ?? null;
    if (!is_string($name) || !is_string($result) || strlen($name) > 160 || strlen($result) > 1000 || trim($result) === '' || !is_array($answers) || count($answers) > 20) {
        return new WP_Error('invalid', 'Check the submission and try again.', ['status' => 400]);
    }
    $safe_answers = [];
    foreach ($answers as $key => $value) {
        if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_]{1,40}$/', $key) || !is_scalar($value) || strlen((string) $value) > 500) {
            return new WP_Error('invalid', 'Check the submission and try again.', ['status' => 400]);
        }
        $safe_answers[$key] = sanitize_text_field((string) $value);
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return new WP_Error('unavailable', 'The connection is temporarily unavailable.', ['status' => 503]);
    }
    $rate_key = 'sks_ibr_' . substr(hash_hmac('sha256', $ip, wp_salt('auth')), 0, 32);
    $count = (int) get_transient($rate_key);
    if ($count >= 20) { return new WP_Error('slow_down', 'Please try again later.', ['status' => 429]); }
    set_transient($rate_key, $count + 1, HOUR_IN_SECONDS);
    $payload = ['tool_name' => sanitize_text_field($name), 'displayed_result' => sanitize_text_field($result), 'answers' => $safe_answers];
    $response = wp_safe_remote_post($settings['webhook'], [
        'timeout' => 10,
        'redirection' => 0,
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($payload),
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) < 200 || wp_remote_retrieve_response_code($response) >= 300) {
        return new WP_Error('send_failed', 'The result could not be sent. Please try again.', ['status' => 502]);
    }
    return rest_ensure_response(['received' => true]);
}
