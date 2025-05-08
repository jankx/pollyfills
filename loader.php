<?php

if (!defined('JANKX_WP_POLYFILL_ROOT')) {
    define('JANKX_WP_POLYFILL_ROOT', dirname(__FILE__));
}

if (!function_exists('jankx_polyfill_path')) {
    function jankx_polyfill_path($path = null) {
        if (!empty($path)) {
            return sprintf('%s/%s', JANKX_WP_POLYFILL_ROOT, $path);
        }
        return JANKX_WP_POLYFILL_ROOT;
    }
}

// functions
require_once jankx_polyfill_path('wp/wordpress-6.4-functions.php');
