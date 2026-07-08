<?php

namespace App\controllers\filters;

use App\admin\KCDashboardPermalinkHandler;

defined('ABSPATH') || exit;

/**
 * Excludes KiviCare dashboard pages and REST API responses from page-cache plugins.
 *
 * Keeping cache-plugin integrations here (rather than in KCDashboardPermalinkHandler)
 * means adding support for another cache plugin never touches routing code.
 *
 * Supported plugins
 * -----------------
 * - LiteSpeed Cache  (litespeed_init action  → \LiteSpeed\API::no_cache_for())
 * - WP Rocket        (rocket_cache_reject_uri filter)
 * - W3 Total Cache   (w3tc_pgcache_reject_uri filter)
 * - WP Super Cache   (wp_cache_request_uri filter / wpsc_init action)
 * - WP Fastest Cache (wpfc_cache_reject_uri filter)
 * - WP-Optimize      (wpo_should_cache_request filter)
 * - Autoptimize      (autoptimize_filter_noptimize filter)
 * - Hummingbird      (wphb_cache_request filter)
 */
class KCCacheExclusionFilter
{
    private static ?self $instance = null;

    /** @var KCDashboardPermalinkHandler */
    private KCDashboardPermalinkHandler $permalink_handler;

    private function __construct()
    {
        $this->permalink_handler = KCDashboardPermalinkHandler::instance();
        $this->register_hooks();
    }

    public static function get_instance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function register_hooks(): void
    {
        // LiteSpeed Cache
        add_action('litespeed_init', [$this, 'exclude_from_litespeed']);

        // WP Rocket
        add_filter('rocket_cache_reject_uri', [$this, 'append_uris_to_reject_list']);

        // W3 Total Cache
        add_filter('w3tc_pgcache_reject_uri', [$this, 'append_uris_to_reject_list']);

        // WP Fastest Cache
        add_filter('wpfc_cache_reject_uri', [$this, 'append_uris_to_reject_list']);

        // WP-Optimize
        add_filter('wpo_should_cache_request', [$this, 'prevent_wpo_caching']);

        // Autoptimize (skip JS/CSS optimisation on dashboard pages — avoids breaking the React app)
        add_filter('autoptimize_filter_noptimize', [$this, 'prevent_autoptimize']);

        // Hummingbird
        add_filter('wphb_cache_request', [$this, 'prevent_hummingbird_caching']);
    }

    // -------------------------------------------------------------------------
    // LiteSpeed Cache
    // -------------------------------------------------------------------------

    /**
     * Called on `litespeed_init` — the earliest safe point to mark a response
     * uncacheable before LSCWP decides whether to serve from cache.
     */
    public function exclude_from_litespeed(): void
    {
        $is_dashboard = $this->permalink_handler->is_dashboard_request();
        $is_kc_rest   = $this->is_kivicare_rest_request();

        if (!$is_dashboard && !$is_kc_rest) {
            return;
        }

        $reason = $is_dashboard ? 'kivicare_dashboard' : 'kivicare_rest_api';

        if (class_exists('\LiteSpeed\API')) {
            \LiteSpeed\API::no_cache_for($reason);
        } else {
            // Direct action-based API — works even if the class is not autoloaded.
            do_action('litespeed_control_set_nocache', $reason);
        }
    }

    // -------------------------------------------------------------------------
    // URI-list-based cache plugins (WP Rocket, W3TC, WPFC)
    // -------------------------------------------------------------------------

    /**
     * Appends all KiviCare dashboard slugs and the REST API path to any cache
     * plugin's "do not cache" URI list.
     *
     * @param  string[]|string $uris  Existing list (array or newline-separated string).
     * @return string[]|string
     */
    public function append_uris_to_reject_list($uris)
    {
        $kc_uris = $this->get_excluded_uri_patterns();

        if (\is_array($uris)) {
            return array_unique(array_merge($uris, $kc_uris));
        }

        // Newline-separated string format (some plugins use this)
        $existing = array_filter(array_map('trim', explode("\n", (string) $uris)));
        return implode("\n", array_unique(array_merge($existing, $kc_uris)));
    }

    // -------------------------------------------------------------------------
    // Boolean-flag cache plugins (WP-Optimize, Hummingbird)
    // -------------------------------------------------------------------------

    /**
     * @param  bool $should_cache
     * @return bool
     */
    public function prevent_wpo_caching(bool $should_cache): bool
    {
        if ($this->is_kivicare_page()) {
            return false;
        }
        return $should_cache;
    }

    /**
     * @param  bool $cache
     * @return bool
     */
    public function prevent_hummingbird_caching(bool $cache): bool
    {
        if ($this->is_kivicare_page()) {
            return false;
        }
        return $cache;
    }

    // -------------------------------------------------------------------------
    // Autoptimize
    // -------------------------------------------------------------------------

    /**
     * Disable Autoptimize JS/CSS minification on KiviCare dashboard pages.
     * React's build artefacts do not need — and break under — AO's processing.
     *
     * @param  bool $noptimize
     * @return bool
     */
    public function prevent_autoptimize(bool $noptimize): bool
    {
        if ($this->permalink_handler->is_dashboard_request()) {
            return true;
        }
        return $noptimize;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * True when the current request is either a dashboard page or a KiviCare REST call.
     */
    private function is_kivicare_page(): bool
    {
        return $this->permalink_handler->is_dashboard_request()
            || $this->is_kivicare_rest_request();
    }

    /**
     * True when the current request targets the KiviCare REST namespace.
     */
    private function is_kivicare_rest_request(): bool
    {
        if (!\defined('REST_REQUEST') || !REST_REQUEST) {
            return false;
        }

        $route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';

        return strpos($route, '/kivicare/') === 0;
    }

    /**
     * Returns URI prefix patterns for every KiviCare dashboard route plus the
     * REST API namespace, suitable for any "reject URI" list.
     *
     * @return string[]
     */
    private function get_excluded_uri_patterns(): array
    {
        $patterns = [];

        foreach ($this->permalink_handler->get_dashboard_routes() as $slug) {
            if (!empty($slug)) {
                $patterns[] = '/' . ltrim($slug, '/');
            }
        }

        // Match all KiviCare REST requests regardless of path depth
        $patterns[] = '/wp-json/kivicare/';

        return $patterns;
    }
}
