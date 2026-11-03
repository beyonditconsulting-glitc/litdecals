<?php
/**
 * Plugin Name: LitDecals Sandbox Connector Authentication
 * Description: Allows the sandbox publishing connector to authenticate independently of Directory Privacy.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;

function litdecals_sandbox_is_connector_request() {
    $root = realpath(ABSPATH);
    if (!$root || basename($root) !== 'sandbox-redesign' || !is_ssl()) {
        return false;
    }
    foreach (array(home_url('/'), site_url('/')) as $url) {
        if (rtrim((string) wp_parse_url($url, PHP_URL_PATH), '/') !== '/sandbox-redesign') {
            return false;
        }
    }
    if (empty($_SERVER['HTTP_X_LITDECALS_AUTHORIZATION'])) {
        return false;
    }
    $allowed = array(
        '/litdecals-sandbox/v1/status',
        '/litdecals-sandbox/v1/deploy',
        '/litdecals-sandbox/v1/preview',
    );
    if (isset($_GET['rest_route']) && is_string($_GET['rest_route'])) {
        return in_array(rtrim(wp_unslash($_GET['rest_route']), '/'), $allowed, true);
    }
    $path = wp_parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH);
    $prefix = '/sandbox-redesign/' . rest_get_url_prefix();
    foreach ($allowed as $route) {
        if (rtrim((string) $path, '/') === $prefix . $route) {
            return true;
        }
    }
    return false;
}

// The custom connector header establishes an API request even if WordPress
// resolves the user before REST_REQUEST is defined.
add_filter('application_password_is_api_request', function ($is_api_request) {
    return $is_api_request || litdecals_sandbox_is_connector_request();
});

// WordPress validates application passwords at priority 20. Authenticate the
// connector's separate header first, so Apache's Directory Privacy credentials
// are not mistakenly treated as the WordPress application password.
add_filter('determine_current_user', function ($user_id) {
    if ($user_id || !litdecals_sandbox_is_connector_request()) {
        return $user_id;
    }
    $header = $_SERVER['HTTP_X_LITDECALS_AUTHORIZATION'];
    if (!is_string($header) || strlen($header) > 1024 || stripos($header, 'Basic ') !== 0) {
        return $user_id;
    }
    $decoded = base64_decode(substr($header, 6), true);
    if ($decoded === false || strpos($decoded, ':') === false) {
        return $user_id;
    }
    list($username, $password) = explode(':', $decoded, 2);
    $user = wp_authenticate_application_password(null, $username, $password);
    unset($password, $decoded);
    if (!($user instanceof WP_User)) {
        return $user_id;
    }
    // Keep the connector's own administrator checks and sandbox checks intact.
    return $user->ID;
}, 19);
