<?php

declare(strict_types=1);

namespace CartComback\includes;

use CartComback\includes\CCB_Database;

if (!defined('ABSPATH')) {
    exit();
}

class CCB_Cron
{

    private CCB_Database $ccb_database;

    public function __construct()
    {
        $this->ccb_database = new CCB_Database();
    }


    /**
     * action:register_activation_hook
     */
    public function ccb_schedule_cron_on_activation(): void
    {
        if (!wp_next_scheduled('ccb_check_abadoned_carts')) {
            wp_schedule_event(time(), 'every_five_minutes', 'ccb_check_abandoned_carts');
        }
    }


    /**
     * filter: cron_schedules
     */
    public function ccb_add_cron_schedule(array $schedules): array
    {
        $schedules['every_five_minutes'] = ['interval' => 300, 'display' => 'Every 5 Minutes'];
        return $schedules;
    }

    public function ccb_run_abandoned_check(): void
    {

        $settings = (new \CartComback\admin\CCB_Admin)->ccb_get_settings();
        $cutoff_minutes = $settings['cut_off'] ?? 30;

        $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$cutoff_minutes} minutes"));

        $rows = $this->ccb_database->ccb_fetch_abandoned_carts('active', $cutoff_time);

        foreach ($rows as $row) {
            if ($row->user_id && $this->ccb_user_role_excluded($row->user_id, $settings)) {
                continue;
            }

            $this->ccb_database->ccb_update_abandoned_cart_table_to_change_status($row, 'abandoned');

            // Trigger your recovery email here
            do_action('ccb_cart_abandoned', $row);
        }
    }


    function ccb_user_role_excluded(string $user_id, array $settings)
    {
        $user = get_userdata($user_id);
        if (!$user) return false;

        $excluded_roles = $settings['disable_tracking_for'] ?? [];
        return (bool) array_intersect($user->roles, $excluded_roles);
    }
}
