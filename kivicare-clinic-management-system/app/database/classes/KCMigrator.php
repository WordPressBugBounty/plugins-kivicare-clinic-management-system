<?php

namespace App\database\classes;

use App\baseClasses\KCErrorLogger;
use App\database\CLI\KCMigrate;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

class KCMigrator {

	/**
	 * @var KCMigrator
	 */
	private static $instance;

	protected $table_name = 'kc_migrations';

	/**
	 * @param string $command_name
	 *
	 * @return KCMigrator Instance
	 */
	public static function instance( $command_name = 'kc') {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof KCMigrator ) ) {
			self::$instance = new KCMigrator();
			self::$instance->init( $command_name );
		}

		return self::$instance;
	}

	/**
	 * @param string $command_name
	 */
	public function init( $command_name ) {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( $command_name . ' migrate', KCMigrate::class );
            \WP_CLI::add_command( 'scaffold kc-migration', KCScaffold::class );
		}
	}

	/**
	 * Set up the table needed for storing the migrations.
	 *
	 * @return bool
	 */
	public function setup() {
		global $wpdb;

		$table = $wpdb->prefix . $this->table_name;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ); 
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $wpdb->get_var( $sql ) === $table ) {
			return false;
		}

		$collation = ! $wpdb->has_cap( 'collation' ) ? '' : $wpdb->get_charset_collate();

		// Create migrations table
		$sql = "CREATE TABLE " . $table . " (
			id bigint(20) NOT NULL auto_increment,
			name varchar(255) NOT NULL,
			date_ran datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
			) {$collation};";

		dbDelta( $sql );

		return true;
	}

	/**
	 * Get all the migration files from multiple plugin paths
	 *
	 * @param array       $exclude   Filenames without extension to exclude
	 * @param string|null $migration Single migration class name to only perform the migration for
	 * @param bool        $rollback
	 *
	 * @return array
	 */
	protected function get_migrations( $exclude = array(), $migration = null, $rollback = false ) {
		$all_migrations = array();

		// Get all migration paths from multiple plugins
		$migration_paths = $this->get_all_migration_paths();
		
		$migrations = array();
		foreach ( $migration_paths as $plugin_info ) {
			$path = $plugin_info['path'];
			
			// Skip if directory doesn't exist
			if ( ! is_dir( $path ) ) {
				continue;
			}
			
			$path_migrations = glob( trailingslashit( $path ) . '*.php' );
			if ( ! empty( $path_migrations ) ) {
				// Add plugin info to each migration file
				foreach ( $path_migrations as $file ) {
					$migrations[] = array(
						'file' => $file,
						'plugin' => $plugin_info['name'],
						'plugin_key' => $plugin_info['key'],
						'path' => $path
					);
				}
			}
		}

		if ( empty( $migrations ) ) {
			return $all_migrations;
		}

		// Sort migrations by filename to ensure consistent execution order
		usort( $migrations, function( $a, $b ) {
			$filename_a = basename( $a['file'] );
			$filename_b = basename( $b['file'] );
			return strcmp( $filename_a, $filename_b );
		});

		foreach ( $migrations as $migration_info ) {
			$filename = $migration_info['file'];
			$name = basename( $filename, '.php' );
			
			if ( ! $rollback && in_array( $name, $exclude ) ) {
				// The migration can't have been run before
				continue;
			}

			if ( $rollback && ! in_array( $name, $exclude ) ) {
				// As we are rolling back, it must have been run before
				continue;
			}

			if ( $migration && $this->get_class_name( $name ) !== $migration ) {
				continue;
			}

			$all_migrations[ $filename ] = array(
				'name' => $name,
				'plugin' => $migration_info['plugin'],
				'plugin_key' => $migration_info['plugin_key'],
				'path' => $migration_info['path']
			);
		}

		return $all_migrations;
	}

	/**
	 * Get all migration paths from multiple plugins
	 *
	 * @return array Array of migration path configurations
	 */
	protected function get_all_migration_paths() {
		// Define migration paths for different plugins
		$migration_paths = array(
			array(
				'key' => 'kivicare',
				'name' => 'KiviCare',
				'path' => $this->get_migrations_path()
			)
		);
		// Allow Addons plugins to register their migration paths
		$migration_paths = apply_filters( 'kc_wp_migrations_paths_detailed', $migration_paths );

		// Also support the old filter format for backward compatibility
		$old_format_paths = apply_filters( 'kc_wp_migrations_paths', array( $this->get_migrations_path() ) );
		
		// Convert old format to new format if different from default
		foreach ( $old_format_paths as $index => $path ) {
			// Skip if it's already included in the new format
			$already_included = false;
			foreach ( $migration_paths as $new_path ) {
				if ( $new_path['path'] === $path ) {
					$already_included = true;
					break;
				}
			}
			
			if ( ! $already_included ) {
				$migration_paths[] = $path;
			}
		}

		return $migration_paths;
	}

	/**
	 * Get the default migrations folder path.
	 *
	 * @return string
	 */
	protected function get_migrations_path() {
		return apply_filters( 'kc_wp_migrations_path', KIVI_CARE_DIR . '/app/database/migrations' );
	}

	/**
	 * Get all the migrations to be run
	 *
	 * @param string|null $migration
	 * @param bool        $rollback
	 * @return array
	 */
	protected function get_migrations_to_run( $migration = null, $rollback = false ) {
		global $wpdb;
		$table = $wpdb->prefix . $this->table_name;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ran_migrations = $wpdb->get_col( "SELECT name FROM $table");

		$migrations = $this->get_migrations( $ran_migrations, $migration, $rollback );

		return $migrations;
	}

	/**
	 * Run the migrations
	 *
	 * @param string|null $migration
	 * @param bool        $rollback
	 *
	 * @return int
	 */
	public function run( $migration = null, $rollback = false ) {
		global $wpdb;
		$table = $wpdb->prefix . $this->table_name;
		$count      = 0;
		$migrations = $this->get_migrations_to_run( $migration, $rollback );
		
		if ( empty( $migrations ) ) {
			return $count;
		}

		foreach ( $migrations as $file => $migration_info ) {
			// Extract migration info
			$name = $migration_info['name'];
			$plugin = $migration_info['plugin'];
			$plugin_key = $migration_info['plugin_key'];
			
			require_once $file;

			$class_name    = $this->get_class_name( $name );
			$fq_class_name = $this->get_class_with_namespace( $class_name );
			
			if ( false === $fq_class_name ) {
				KCErrorLogger::instance()->error( "[KCMigrator] Migration class not found: {$class_name} in file {$file} (Plugin: {$plugin})" );
				continue;
			}

			$class     = $fq_class_name;
			$migration_instance = new $class;
			$method    = $rollback ? 'rollback' : 'run';
			
			if ( ! method_exists( $migration_instance, $method ) ) {
				KCErrorLogger::instance()->error( "[KCMigrator] Method '{$method}' not found in migration class: {$class_name} ({$file}) (Plugin: {$plugin})" );
				continue;
			}

			try {
				$migration_instance->{$method}();
				$count++;

				if ( $rollback ) {
					$wpdb->delete( $table, array( 'name' => $name ) );
					KCErrorLogger::instance()->error( "[KCMigrator] Successfully rolled back migration: {$name} (Plugin: {$plugin})" );
				} else {
					$wpdb->insert( $table, array( 
						'name' => $name, 
						'date_ran' => gmdate("Y-m-d H:i:s") 
					) );
					KCErrorLogger::instance()->error( "[KCMigrator] Successfully ran migration: {$name} (Plugin: {$plugin})" );
				}
			} catch ( \Exception $e ) {
				KCErrorLogger::instance()->error( "[KCMigrator] Error running migration {$name} (Plugin: {$plugin}): " . $e->getMessage() );
				// Continue with other migrations even if one fails
				continue;
			}
		}

		return $count;
	}

	/**
	 * Get migrations grouped by plugin for reporting
	 *
	 * @param string|null $migration
	 * @param bool        $rollback
	 * @return array
	 */
	public function get_migrations_by_plugin( $migration = null, $rollback = false ) {
		$migrations = $this->get_migrations_to_run( $migration, $rollback );
		$grouped = array();
		
		foreach ( $migrations as $file => $migration_info ) {
			$plugin_key = $migration_info['plugin_key'];
			$plugin_name = $migration_info['plugin'];
			
			if ( ! isset( $grouped[ $plugin_key ] ) ) {
				$grouped[ $plugin_key ] = array(
					'plugin_name' => $plugin_name,
					'migrations' => array()
				);
			}
			
			$grouped[ $plugin_key ]['migrations'][] = array(
				'name' => $migration_info['name'],
				'file' => $file
			);
		}
		
		return $grouped;
	}

	protected function get_class_with_namespace( $class_name ) {
		$all_classes = get_declared_classes();
		foreach ( $all_classes as $class ) {
			if ( substr( $class, - strlen( $class_name ) ) === $class_name ) {
				return $class;
			}
		}

		return false;
	}

	protected function get_class_name( $name ) {
		return $this->camel_case( substr( $name, 11 ) );
	}

	protected function camel_case( $string ) {
		$string = ucwords( str_replace( array( '-', '_' ), ' ', $string ) );

		return str_replace( ' ', '', $string );
	}

	/**
	 * Scaffold a new migration using the stub from the `stubs` directory.
	 *
	 * @param string $migration_name Camel cased migration name, e.g. myMigration.
	 * @param string $plugin_key Plugin key to determine which path to use (optional, defaults to 'kivicare')
	 *
	 * @return string|WP_Error Name of created migration file on success, WP_Error
	 *                         instance on failure.
	 */
	public function scaffold( $migration_name, $plugin_key = 'kivicare' ): string|WP_Error {
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		// Get migration paths
		$migration_paths = $this->get_all_migration_paths();
		
		// Find the target path
		$target_path = null;
		foreach ( $migration_paths as $path_info ) {
			if ( $path_info['key'] === $plugin_key ) {
				$target_path = $path_info['path'];
				break;
			}
		}
		
		// Fallback to default path if plugin not found
		if ( ! $target_path ) {
			$target_path = $this->get_migrations_path();
		}

		// Create migrations dir if it doesn't exist already.
		if ( ! $wp_filesystem->is_dir( $target_path ) ) {
			if ( ! $wp_filesystem->mkdir( $target_path, FS_CHMOD_DIR ) ) {
				KCErrorLogger::instance()->error( "[KCMigrator] Unable to create migrations folder {$target_path}" );
				return new \WP_Error(
					'migrations_folder_error',
					"Unable to create migrations folder {$target_path}"
				);
			}
		}

		$stub_dir  = KIVI_CARE_DIR . '/app/database/stubs';
		$stub_path = apply_filters( 'kc_migration_stub_path', "{$stub_dir}/migration.stub" );
		$stub      = file_get_contents( $stub_path );

		if ( ! $stub ) {
			KCErrorLogger::instance()->error( "[KCMigrator] Unable to read migration stub file: {$stub_path}" );
			return new \WP_Error(
				'stub_file_error',
				"Unable to create migration file: Couldn't read from stub {$stub_path}."
			);
		}

		$date        = gmdate( 'Y_m_d' );
		$filename    = "{$date}_{$migration_name}.php";
		$file_path   = "{$target_path}/{$filename}";
		$boilerplate = str_replace( '{{ class }}', $migration_name, $stub );

		if ( ! file_put_contents( $file_path, $boilerplate ) ) {
			KCErrorLogger::instance()->error( "[KCMigrator] Unable to create migration file: {$file_path}" );
			return new \WP_Error(
				'file_creation_error',
				"Unable to create migration file {$file_path}."
			);
		}

		KCErrorLogger::instance()->error( "[KCMigrator] Successfully created migration file: {$file_path} (Plugin: {$plugin_key})" );
		return $filename;
	}

	/**
	 * Protected constructor to prevent creating a new instance of the
	 * class via the `new` operator from outside of this class.
	 */
	protected function __construct() {
	}

	/**
	 * As this class is a singleton it should not be clone-able
	 */
	protected function __clone() {
	}

	/**
	 * As this class is a singleton it should not be able to be unserialized
	 */
	public function __wakeup() {
	}
}