<?php
/**
 * Custom Error Logger for KiviCare
 *
 * Handles error logging to a custom directory with support for log levels
 * and global error capturing.
 *
 * @package KiviCare
 */

namespace App\baseClasses;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class KCErrorLogger
 */
class KCErrorLogger {

	/**
	 * Log Levels
	 */
	const DEBUG    = 'DEBUG';
	const INFO     = 'INFO';
	const WARNING  = 'WARNING';
	const ERROR    = 'ERROR';
	const CRITICAL = 'CRITICAL';

	/**
	 * Singleton instance
	 *
	 * @var KCErrorLogger|null
	 */
	private static $instance = null;

	/**
	 * Log directory path
	 *
	 * @var string
	 */
	private $log_dir;

	/**
	 * Logging enabled flag
	 *
	 * @var bool
	 */
	private $is_enabled = true;

	/**
	 * Get the singleton instance.
	 *
	 * @return KCErrorLogger
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 * Initialize log directory.
	 */
	private function __construct() {
		$upload_dir = wp_upload_dir();
		$this->log_dir = $upload_dir['basedir'] . '/kc-logs';

		if ( ! file_exists( $this->log_dir ) ) {
			// Create directory with secure permissions (0755)
			wp_mkdir_p( $this->log_dir );
			
			// Add .htaccess to prevent direct access if Apache
			$htaccess_file = $this->log_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				file_put_contents( $htaccess_file, 'Deny from all' );
			}
			
			// Add index.php to prevent directory listing
			$index_file = $this->log_dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, '<?php // Silence is golden' );
			}
		}

		// Check for disable constant
		if ( defined( 'KIVICARE_DISABLE_LOGGING' ) && KIVICARE_DISABLE_LOGGING ) {
			$this->is_enabled = false;
		}
	}

	/**
	 * Set logging enabled state.
	 *
	 * @param bool $enabled
	 */
	public function set_enabled( $enabled ) {
		$this->is_enabled = (bool) $enabled;
	}

	/**
	 * Initialize global error handlers.
	 *
	 * Call this method during plugin bootstrap.
	 */
	public function init() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( [ $this, 'handle_error' ] );
		register_shutdown_function( [ $this, 'handle_shutdown' ] );
	}

	/**
	 * Log a message.
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Optional structured context.
	 */
	public function log( $level, $message, $context = [] ) {
		// Check if logging is enabled
		if ( ! $this->is_enabled ) {
			return;
		}

		// Basic validation
		if ( empty( $message ) ) {
			return;
		}

		// Format timestamp
		$timestamp = gmdate( 'Y-m-d H:i:s' );

		// Format context
		$context_str = ! empty( $context ) ? ' ' . json_encode( $context ) : '';

		// Format log entry
		// [2023-10-27 10:00:00] [INFO] Message content {"key":"value"}
		$entry = sprintf( "[%s] [%s] %s%s" . PHP_EOL, $timestamp, $level, $message, $context_str );

		// Determine file path (daily rotation)
		$date = gmdate( 'Y-m-d' );
		$file_path = $this->log_dir . '/kc-log-' . $date . '.log';

		// Write to file (append, lock)
		// Suppress errors to avoid infinite loops if logging fails
		@file_put_contents( $file_path, $entry, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Helper: Log Debug
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	public function debug( $message, $context = [] ) {
		$this->log( self::DEBUG, $message, $context );
	}

	/**
	 * Helper: Log Info
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	public function info( $message, $context = [] ) {
		$this->log( self::INFO, $message, $context );
	}

	/**
	 * Helper: Log Warning
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	public function warning( $message, $context = [] ) {
		$this->log( self::WARNING, $message, $context );
	}

	/**
	 * Helper: Log Error
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	public function error( $message, $context = [] ) {
		$this->log( self::ERROR, $message, $context );
	}

	/**
	 * Helper: Log Critical
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	public function critical( $message, $context = [] ) {
		$this->log( self::CRITICAL, $message, $context );
	}

	/**
	 * Custom Error Handler
	 *
	 * @param int    $errno   Error number.
	 * @param string $errstr  Error string.
	 * @param string $errfile Error file.
	 * @param int    $errline Error line.
	 * @return bool False to let normal error handler continue.
	 */
	public function handle_error( $errno, $errstr, $errfile, $errline ) {
		// Map PHP error levels to log levels
		switch ( $errno ) {
			case E_NOTICE:
			case E_USER_NOTICE:
				$level = self::INFO;
				break;
			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
				$level = self::WARNING;
				break;
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
			case E_RECOVERABLE_ERROR:
				$level = self::CRITICAL;
				break;
			default:
				$level = self::ERROR;
				break;
		}

		$message = sprintf( '%s in %s:%d', $errstr, $errfile, $errline );
		$this->log( $level, $message, [ 'errno' => $errno ] );

		// Return false to allow standard PHP error handling to proceed
		return false;
	}

	/**
	 * Shutdown Handler
	 * Captures fatal errors that cannot be caught by set_error_handler.
	 */
	public function handle_shutdown() {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) ) {
			$message = sprintf( 'Fatal Error: %s in %s:%d', $error['message'], $error['file'], $error['line'] );
			$this->log( self::CRITICAL, $message, [ 'type' => $error['type'] ] );
		}
	}
}
