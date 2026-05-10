<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class EBC_Holidays
{
    private const API_URL = 'https://holidays-jp.github.io/api/v1/date.json';
    private const CACHE_KEY = 'ebc_japanese_holidays';
    private const CACHE_TTL = DAY_IN_SECONDS;

    public static function get_holidays(): array
    {
        $cached = get_transient(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(self::API_URL, [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            return [];
        }

        $holidays = [];

        foreach ($data as $date => $name) {
            if (
                is_string($date) &&
                is_string($name) &&
                preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            ) {
                $holidays[$date] = sanitize_text_field($name);
            }
        }

        set_transient(self::CACHE_KEY, $holidays, self::CACHE_TTL);

        return $holidays;
    }


    public static function get_holiday_name(string $date): ?string
    {
        $holidays = self::get_holidays();

        return $holidays[$date] ?? null;
    }


    public static function is_holiday(string $date): bool
    {
        return self::get_holiday_name($date) !== null;
    }


    public static function clear_cache(): void
    {
        delete_transient(self::CACHE_KEY);
    }
}
