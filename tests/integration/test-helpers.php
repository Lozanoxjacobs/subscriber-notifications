<?php
/**
 * Shared helpers for WP-CLI eval-file integration tests.
 *
 * @package SubscriberNotifications
 */

if (!defined('ABSPATH')) {
    exit(1);
}

if (!function_exists('sn_test_assert')) {
    /**
     * @param string $label Test label.
     * @param bool   $condition Pass/fail.
     */
    function sn_test_assert(string $label, bool $condition): void {
        echo ($condition ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
        if (!$condition) {
            sn_test_fail($label);
        }
    }
}

if (!function_exists('sn_test_fail')) {
    function sn_test_fail(string $label): void {
        $GLOBALS['sn_test_failures'] = (int) ($GLOBALS['sn_test_failures'] ?? 0) + 1;
    }
}

if (!function_exists('sn_test_finish')) {
    function sn_test_finish(): void {
        $failures = (int) ($GLOBALS['sn_test_failures'] ?? 0);
        if ($failures > 0) {
            echo PHP_EOL . $failures . ' test(s) failed.' . PHP_EOL;
            exit(1);
        }
        echo PHP_EOL . 'All tests passed.' . PHP_EOL;
        exit(0);
    }
}

if (!function_exists('sn_test_datetime_near')) {
    /**
     * @param string $stored   Datetime from DB.
     * @param string $expected Expected datetime (site time).
     * @param int    $seconds  Allowed drift.
     */
    function sn_test_datetime_near(string $stored, string $expected, int $seconds = 120): bool {
        try {
            $tz = wp_timezone();
            $a  = new DateTimeImmutable($stored, $tz);
            $b  = new DateTimeImmutable($expected, $tz);
            return abs($a->getTimestamp() - $b->getTimestamp()) <= $seconds;
        } catch (Exception $e) {
            return false;
        }
    }
}
