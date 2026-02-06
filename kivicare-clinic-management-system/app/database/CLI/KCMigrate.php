<?php
namespace App\database\CLI;

use App\database\classes\KCMigrator;
use WP_CLI;

defined('ABSPATH') or die('Something went wrong');
class KCMigrate {

    /**
     * Run the outstanding migrations or a single migration.
     *
     * ## OPTIONS
     *
     * [<migration>]
     * : The class name of the migration to run.
     *
     * [--rollback]
     * : Rollback the migration.
     *
     * ## EXAMPLES
     *
     *     # Run all outstanding migrations
     *     $ wp kc migrate
     *
     *     # Run a specific migration
     *     $ wp kc migrate CreateClientsTable
     *
     *     # Rollback a specific migration
     *     $ wp kc migrate CreateClientsTable --rollback
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke( $args, $assoc_args ) {
        $migration = isset( $args[0] ) ? $args[0] : null;
        $rollback  = isset( $assoc_args['rollback'] ) ? true : false;

        // Create the migrations table if it doesn't exist
        $migrator = KCMigrator::instance();
        $migrator->setup();

        // Run migrations
        $count = $migrator->run( $migration, $rollback );

        if ( $count === 0 ) {
            if ( $migration ) {
                $message = $rollback ? "Migration {$migration} not found or already rolled back." : "Migration {$migration} not found or already run.";
            } else {
                $message = $rollback ? "No migrations to rollback." : "No migrations to run.";
            }

            WP_CLI::success( $message );
            return;
        }

        $action = $rollback ? 'Rolled back' : 'Ran';
        if ( $migration ) {
            WP_CLI::success( "{$action} migration {$migration}." );
        } else {
            WP_CLI::success( "{$action} {$count} " . _n( 'migration.', 'migrations.', $count, 'kivicare-clinic-management-system' ) );
        }
    }
}