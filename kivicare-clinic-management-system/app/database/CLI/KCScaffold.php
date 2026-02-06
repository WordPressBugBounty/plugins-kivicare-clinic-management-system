<?php
namespace App\database\CLI;

use App\database\classes\KCMigrator;
use WP_CLI;

defined('ABSPATH') or die('Something went wrong');
class KCScaffold {

    /**
     * KCScaffold a new migration file.
     *
     * ## OPTIONS
     *
     * <name>
     * : The name of the migration to create.
     *
     * ## EXAMPLES
     *
     *     # Create a new migration
     *     $ wp scaffold kc-migration CreateClientsTable
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke( $args, $assoc_args ) {
        if ( empty( $args[0] ) ) {
            WP_CLI::error( 'Migration name is required.' );
        }

        $migration_name = $args[0];
        $migrator       = KCMigrator::instance();
        $result         = $migrator->scaffold( $migration_name );

        if ( is_wp_error( $result ) ) {
            WP_CLI::error( $result->get_error_message() );
        }

        WP_CLI::success( "Created migration: {$result}" );
    }
}