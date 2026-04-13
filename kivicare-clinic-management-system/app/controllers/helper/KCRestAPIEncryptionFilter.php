<?php

namespace App\controllers\helper;

use App\controllers\helper\KCEncryptedResponseHelper;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Transparent REST API encryption/decryption filter.
 *
 * Encrypts ALL KiviCare REST API responses and decrypts incoming request bodies
 * using libsodium (X25519-XSalsa20-Poly1305) via paragonie/sodium_compat.
 *
 * ── WordPress REST serve order (class-wp-rest-server.php) ──────────────────
 *  1. dispatch()                          → controller callback runs
 *  2. rest_post_dispatch filter  (L462)   ← we encrypt HERE (before send_headers)
 *  3. send_headers($result->headers) (L472) → HTTP headers flushed
 *  4. rest_pre_serve_request filter (L515)  → too late for $result->header()
 *  5. echo wp_json_encode(response_to_data)
 *
 * Exclusions:
 *  - Routes with `'encryption' => false` in their route args (handshake endpoints)
 *  - Requests with no authenticated user (graceful plain fallback)
 *  - Requests where client has not yet registered their public key (graceful fallback)
 */
class KCRestAPIEncryptionFilter
{
    private static $instance = null;

    /** @var string[] Registered routes that should be encrypted */
    private $routes_to_encrypt = [];

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new KCRestAPIEncryptionFilter();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Build the encrypted-route list after all controllers have registered theirs
        add_action('rest_api_init', [$this, 'setupEncryptedRoutes'], 999);

        // Decrypt incoming request body before it reaches any controller
        add_filter('rest_pre_dispatch', [$this, 'decryptRequest'], 10, 3);

        // Encrypt outgoing response data.
        // MUST use rest_post_dispatch (not rest_pre_serve_request) because WP calls
        // send_headers() before rest_pre_serve_request fires — using $result->header()
        // in rest_pre_serve_request would set the header too late (never sent).
        add_filter('rest_post_dispatch', [$this, 'encryptResponse'], 10, 3);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Route setup
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Collect ALL KiviCare REST routes into $routes_to_encrypt.
     * Individual routes can opt-out by setting `'encryption' => false` in their args.
     */
    public function setupEncryptedRoutes()
    {
        global $wp_rest_server;

        if (!$wp_rest_server) {
            return;
        }

        foreach ($wp_rest_server->get_routes() as $route => $route_config) {
            // Match every route under the kivicare/v namespace
            if (strpos($route, 'kivicare/v') === 1) {
                $this->routes_to_encrypt[] = $route;
            }
        }

        /** Allow third-party code to add/remove routes from encryption */
        $this->routes_to_encrypt = apply_filters('kivicare_encrypted_routes', $this->routes_to_encrypt);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Decide whether a concrete request route should be encrypted.
     * Routes that declare `'encryption' => false` in their args are excluded.
     */
    private function shouldEncryptRoute($route)
    {
        global $wp_rest_server;

        // Check per-route opt-out flag
        $routes = $wp_rest_server->get_routes();
        if (isset($routes[$route])) {
            foreach ($routes[$route] as $route_obj) {
                if (isset($route_obj['encryption']) && $route_obj['encryption'] === false) {
                    return false;
                }
            }
        }

        // Check against the collected list
        foreach ($this->routes_to_encrypt as $protected_route) {
            $pattern = str_replace(
                ['/', '(?P<', '>[^/]+)'],
                ['\\/', '(?:',  '[^\\/]+)'],
                $protected_route
            );
            if (preg_match("#^{$pattern}#", $route)) {
                return true;
            }
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Filters
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Decrypt incoming encrypted request body and inject the plaintext params
     * back into the WP_REST_Request so controllers see normal parameters.
     *
     * Hook: rest_pre_dispatch (runs before controller callback)
     */
    public function decryptRequest($response, $handler, WP_REST_Request $request)
    {
        // Dev mode: skip all decryption — request body is plain JSON
        if (KCEncryptedResponseHelper::isDevelopmentMode()) {
            return $response;
        }

        if (!$this->shouldEncryptRoute($request->get_route())) {
            return $response;
        }

        try {
            $client_id = $request->get_header('x_kc_client_id');
            $decrypted = KCEncryptedResponseHelper::validateAndDecryptRequest($request, null, $client_id);

            if (is_wp_error($decrypted)) {
                $error_data = $decrypted->get_error_data();
                if (isset($error_data['status']) && $error_data['status'] === 401) {
                    return $decrypted;
                }
            } elseif (is_array($decrypted)) {
                $request->set_query_params($decrypted);
                $request->set_body_params($decrypted);
            }
        } catch (\Exception $e) {
            error_log('KiviCare E2E Decryption Error: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Encrypt outgoing response data.
     *
     * Hook: rest_post_dispatch — fires BEFORE send_headers(), so $result->header()
     * correctly stores the header for WP to flush via send_headers().
     *
     * Falls back silently (no header, plain data) when:
     *  - the user is not authenticated, or
     *  - the client has not yet registered their public key.
     *
     * @param WP_REST_Response $result
     * @param \WP_REST_Server  $server
     * @param WP_REST_Request  $request
     * @return WP_REST_Response
     */
    public function encryptResponse($result, $server, WP_REST_Request $request)
    {
        // Dev mode: return plain response so DevTools shows readable JSON
        if (KCEncryptedResponseHelper::isDevelopmentMode()) {
            return $result;
        }

        if (!$this->shouldEncryptRoute($request->get_route())) {
            return $result;
        }

        if (!($result instanceof WP_REST_Response)) {
            return $result;
        }

        try {
            $data               = $result->get_data();
            $client_id          = $request->get_header('x_kc_client_id');
            $encrypted_response = KCEncryptedResponseHelper::createEncryptedResponse($data, null, 200, $client_id);

            // Only replace the response body and mark as encrypted when the helper
            // actually returned an encrypted base64 string (not a plain fallback array).
            $encrypted_data = $encrypted_response->get_data();

            if ($encrypted_response->get_status() === 200 && is_string($encrypted_data)) {
                $result->set_data($encrypted_data);
                // $result->header() works here because rest_post_dispatch fires
                // before send_headers() — the header will be included in the
                // HTTP response when WP flushes headers.
                $result->header('X-KiviCare-Encrypted', 'true');
            }
            // If fallback occurred (missing client key / unauthenticated), $encrypted_data
            // is the original plain array — we leave $result untouched and add NO header.
        } catch (\Exception $e) {
            error_log('KiviCare E2E Encryption Error: ' . $e->getMessage());
        }

        return $result;
    }
}
