<?php
/**
 * Schedule calculator: single source of truth for next-send-date math.
 *
 * Consolidates logic that previously lived in three places:
 *   - SubscriberNotifications_Admin::calculate_next_send_date()
 *   - SubscriberNotifications_Scheduler::calculate_next_recurring_date()
 *   - SubscriberNotifications_Database::calculate_next_send_date_for_existing()
 *
 * All returned datetimes are formatted as MySQL 'Y-m-d H:i:s' strings in the
 * WordPress site timezone (matching how `next_send_date` is stored).
 *
 * @package SubscriberNotifications
 * @since 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SubscriberNotifications_Schedule_Calculator {

    /**
     * Map weekday option values to numeric weekday (0 = Sunday).
     */
    const WEEKDAY_MAP = array(
        'sunday'    => 0,
        'monday'    => 1,
        'tuesday'   => 2,
        'wednesday' => 3,
        'thursday'  => 4,
        'friday'    => 5,
        'saturday'  => 6,
    );

    /**
     * First scheduled send for a brand-new one-time notification.
     *
     * Returns the next occurrence of the configured send time for the given
     * frequency. If today's slot is still in the future, today is returned;
     * otherwise the next valid slot is returned (tomorrow / next target weekday /
     * next month).
     *
     * @param string $frequency 'daily' | 'weekly' | 'monthly'.
     * @return string|null MySQL datetime in site timezone, or null for unknown frequency.
     */
    public function next_one_time($frequency) {
        $reference = new DateTimeImmutable('now', wp_timezone());

        return $this->next_slot_after($frequency, $reference);
    }

    /**
     * Next scheduled send for a recurring notification.
     *
     * Returns the next slot strictly after max(now, $last_sent). For daily this
     * is always +1 day from "today's slot if still upcoming else tomorrow",
     * which collapses to "the day after the last send" once `$last_sent` is
     * recorded.
     *
     * @param string      $frequency 'daily' | 'weekly' | 'monthly'.
     * @param string|null $last_sent Optional MySQL datetime (site timezone) of the most recent send.
     * @return string|null MySQL datetime in site timezone, or null for unknown frequency.
     */
    public function next_recurring($frequency, $last_sent = null) {
        $tz        = wp_timezone();
        $reference = new DateTimeImmutable('now', $tz);

        if ($last_sent) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $last_sent, $tz);
            if ($parsed instanceof DateTimeImmutable && $parsed > $reference) {
                $reference = $parsed;
            }
        }

        return $this->next_slot_after($frequency, $reference);
    }

    /**
     * Return the next configured slot strictly after $reference.
     *
     * @param string            $frequency 'daily' | 'weekly' | 'monthly'.
     * @param DateTimeImmutable $reference Reference time (assumed in site timezone).
     * @return string|null
     */
    private function next_slot_after($frequency, DateTimeImmutable $reference) {
        switch ($frequency) {
            case 'daily':
                return $this->next_daily_slot($reference);
            case 'weekly':
                return $this->next_weekly_slot($reference);
            case 'monthly':
                return $this->next_monthly_slot($reference);
        }

        return null;
    }

    /**
     * Next daily slot strictly after $reference.
     */
    private function next_daily_slot(DateTimeImmutable $reference) {
        list($hour, $minute) = $this->parse_hm(subscriber_notifications_get_option('daily_send_time', '09:00'));

        $candidate = $reference->setTime($hour, $minute, 0);
        if ($candidate <= $reference) {
            $candidate = $candidate->modify('+1 day');
        }

        return $candidate->format('Y-m-d H:i:s');
    }

    /**
     * Next weekly slot strictly after $reference.
     */
    private function next_weekly_slot(DateTimeImmutable $reference) {
        list($hour, $minute) = $this->parse_hm(subscriber_notifications_get_option('weekly_send_time', '14:00'));

        $day_key    = strtolower(subscriber_notifications_get_option('weekly_send_day', 'tuesday'));
        $target_day = isset(self::WEEKDAY_MAP[$day_key]) ? self::WEEKDAY_MAP[$day_key] : 2;

        $current_day = (int) $reference->format('w');
        $days_until  = ($target_day - $current_day + 7) % 7;

        $candidate = $reference->modify('+' . $days_until . ' days')->setTime($hour, $minute, 0);
        if ($candidate <= $reference) {
            $candidate = $candidate->modify('+7 days');
        }

        return $candidate->format('Y-m-d H:i:s');
    }

    /**
     * Next monthly slot strictly after $reference.
     */
    private function next_monthly_slot(DateTimeImmutable $reference) {
        list($hour, $minute) = $this->parse_hm(subscriber_notifications_get_option('monthly_send_time', '14:00'));
        $monthly_day = (int) subscriber_notifications_get_option('monthly_send_day', 15);

        $candidate = $this->month_target($reference, $monthly_day, $hour, $minute);
        if ($candidate <= $reference) {
            $next_month_start = $reference
                ->setDate((int) $reference->format('Y'), (int) $reference->format('n'), 1)
                ->modify('+1 month');
            $candidate = $this->month_target($next_month_start, $monthly_day, $hour, $minute);
        }

        return $candidate->format('Y-m-d H:i:s');
    }

    /**
     * Compute the target day-of-month for the month of $reference, clamping to
     * the last day of that month when needed (e.g., day 31 in February).
     */
    private function month_target(DateTimeImmutable $reference, $monthly_day, $hour, $minute) {
        $year          = (int) $reference->format('Y');
        $month         = (int) $reference->format('n');
        $days_in_month = (int) $reference->setDate($year, $month, 1)->format('t');
        $day           = min(max(1, (int) $monthly_day), $days_in_month);

        return $reference->setDate($year, $month, $day)->setTime((int) $hour, (int) $minute, 0);
    }

    /**
     * Parse 'HH:MM' option value into [hour, minute] ints, clamped to valid range.
     */
    private function parse_hm($time_string) {
        $parts  = explode(':', (string) $time_string);
        $hour   = isset($parts[0]) ? max(0, min(23, intval($parts[0]))) : 0;
        $minute = isset($parts[1]) ? max(0, min(59, intval($parts[1]))) : 0;

        return array($hour, $minute);
    }
}
