<?php
/**
 * Global helper functions
 */

function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

function config($key, $default = null) {
    $parts = explode('.', $key);
    $file = $parts[0];
    $configKey = $parts[1] ?? null;
    
    $configFile = __DIR__ . "/../../config/{$file}.php";
    if (!file_exists($configFile)) {
        return $default;
    }
    
    $config = require $configFile;
    
    if ($configKey) {
        return $config[$configKey] ?? $default;
    }
    
    return $config;
}

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
