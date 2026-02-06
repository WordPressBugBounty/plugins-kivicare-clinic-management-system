<?php

namespace App\baseClasses;

use App\database\CLI\KCSeed;

defined('ABSPATH') or die('Something went wrong');

class KCSeedCommand {
    
    /**
     * Initialize the seeder command
     */
    public static function init() {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('kivicare seed', new KCSeed());
        }
    }
}