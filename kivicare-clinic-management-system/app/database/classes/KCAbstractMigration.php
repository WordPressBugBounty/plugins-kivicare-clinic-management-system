<?php

namespace App\database\classes;


defined('ABSPATH') or die('Something went wrong');
abstract class KCAbstractMigration {
    /**
     * Get database collation.
     *
     * @return string
     */
    protected function get_collation() {
        global $wpdb;
        if ( ! $wpdb->has_cap( 'collation' ) ) {
            return '';
        }
        return $wpdb->get_charset_collate();
    }

    /**
     * Run the migration.
     *
     * @return mixed
     */
    abstract function run();

    /**
     * Rollback the migration (optional).
     *
     * @return mixed
     */
    public function rollback() {
        // This method is optional and can be overridden in child classes
        return true;
    }
}