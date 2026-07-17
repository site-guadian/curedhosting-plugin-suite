<?php
if (!defined('ABSPATH')) {
    exit;
}

class CHPS_Logger {
    private static $instance = null;
    private $log_file;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->log_file = $this->get_log_file_path();
        $this->ensure_log_directory();
    }

    public function error($message, array $context = array()) {
        $this->log('error', $message, $context);
    }

    public function warning($message, array $context = array()) {
        $this->log('warning', $message, $context);
    }

    public function info($message, array $context = array()) {
        $this->log('info', $message, $context);
    }

    public function log($level, $message, array $context = array()) {
        if (!$this->should_log()) {
            return;
        }

        $this->maybe_rotate_log();

        $timestamp = gmdate('Y-m-d H:i:s');
        $context = array_filter($context, function ($value) {
            return $value !== null;
        });

        $entry = array(
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $context,
        );

        $line = wp_json_encode($entry) . "\n";
        @file_put_contents($this->log_file, $line, FILE_APPEND | LOCK_EX);
    }

    private function should_log() {
        return get_option('chps_debug_enabled', false);
    }

    private function maybe_rotate_log() {
        $max = 5 * 1024 * 1024; // 5MB
        if (!file_exists($this->log_file)) {
            return;
        }

        clearstatcache(true, $this->log_file);
        $size = @filesize($this->log_file);
        if ($size === false) {
            return;
        }

        if ($size > $max) {
            $rotated = $this->log_file . '.1';
            @rename($this->log_file, $rotated);
        }
    }

    public function get_log_file_path() {
        $upload_dir = wp_get_upload_dir();
        return trailingslashit($upload_dir['basedir']) . 'chps-error.log';
    }

    public function get_log_file_url() {
        $upload_dir = wp_get_upload_dir();
        return trailingslashit($upload_dir['baseurl']) . 'chps-error.log';
    }

    public function get_recent_entries($lines = 100) {
        if (!file_exists($this->log_file)) {
            return array();
        }

        $contents = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($contents === false) {
            return array();
        }

        $contents = array_slice($contents, -$lines);
        $entries = array();
        foreach ($contents as $line) {
            $data = json_decode($line, true);
            if (is_array($data)) {
                $entries[] = $data;
            } else {
                $entries[] = array('timestamp' => '', 'level' => 'error', 'message' => $line, 'context' => array());
            }
        }
        return $entries;
    }

    public function clear_log() {
        @file_put_contents($this->log_file, '');
    }

    private function ensure_log_directory() {
        $dir = dirname($this->log_file);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
}

function chps_log_error($message, array $context = array()) {
    if (!class_exists('CHPS_Logger')) {
        return;
    }
    CHPS_Logger::instance()->error($message, $context);
}

function chps_log_info($message, array $context = array()) {
    if (!class_exists('CHPS_Logger')) {
        return;
    }
    CHPS_Logger::instance()->info($message, $context);
}
