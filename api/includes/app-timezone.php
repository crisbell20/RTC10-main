<?php
/**
 * Application timezone — exam schedules/deadlines are stored as local wall-clock datetimes.
 */
const RTC_APP_TIMEZONE = 'Asia/Manila';
const RTC_MYSQL_TIME_ZONE = '+08:00';

if (!defined('RTC_APP_TIMEZONE_SET')) {
    define('RTC_APP_TIMEZONE_SET', true);
    date_default_timezone_set(RTC_APP_TIMEZONE);
}

function syncDatabaseSessionTimezone(PDO $pdo): void
{
    static $synced = false;
    if ($synced) {
        return;
    }

    try {
        $pdo->exec('SET time_zone = ' . $pdo->quote(RTC_MYSQL_TIME_ZONE));
        $synced = true;
    } catch (Exception $e) {
        // Ignore if the server cannot apply session time_zone.
    }
}
