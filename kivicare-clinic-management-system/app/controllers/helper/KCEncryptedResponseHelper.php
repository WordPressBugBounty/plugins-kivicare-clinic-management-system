<?php

namespace App\controllers\helper;
use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Helper class for handling encrypted API responses
 * Supports bypass mode where encryption is disabled (e.g. for development or API compatibility)
 */
class KCEncryptedResponseHelper
{
    /**
     * Check if E2EE bypass mode is enabled.
     *
     * When true, ALL encryption/decryption is bypassed — responses are plain
     * JSON so you can read them directly in the browser DevTools network tab.
     *
     * Enable manually by adding to wp-config.php:
     *   define('KIVICARE_REST_API_E2EE_BYPASS', true);
     *
     * Also automatically enabled when KiviCare API plugin is active.
     *
     * NEVER enable in production unless necessary for mobile API compatibility.
     *
     * @return bool
     */
    public static function isDevelopmentMode(): bool
    {
        return defined('KIVICARE_REST_API_E2EE_BYPASS') && KIVICARE_REST_API_E2EE_BYPASS === true;
    }

    /**
     * Create an encrypted REST response (or plain response in dev mode)
     *
     * @param array $data The data to encrypt and return
     * @param int $user_id The user ID to get the client public key for
     * @param int $status_code HTTP status code (default: 200)
     * @return WP_REST_Response
     */
    public static function createEncryptedResponse($data, $user_id = null, $status_code = 200, $client_id = null)
    {
        try {
            // In development mode, return plain data
            if (self::isDevelopmentMode()) {
                return new WP_REST_Response($data, $status_code);
            }

            // Use current user if no user_id provided
            if ($user_id === null) {
                $user_id = get_current_user_id();
            }

            // If no user is logged in and no client_id was provided, return 401
            if (!$user_id && !$client_id) {
                return new WP_REST_Response([
                    'status' => false,
                    'message' => 'unauthorized',
                    'data' => ['error' => __('Client public key not found','kivicare-clinic-management-system')]
                ], 401);
            }

            // Get and validate server private key
            $server_private_key = self::getServerPrivateKey();
            if (is_wp_error($server_private_key)) {
                return new WP_REST_Response($server_private_key->get_error_data(), 500);
            }

            // Get and validate client public key
            $client_public_key = self::getClientPublicKey($user_id, $client_id);
            if (is_wp_error($client_public_key)) {
                // No client key registered yet — return plain data gracefully
                return new WP_REST_Response($data, $status_code);
            }

            // Encrypt the data - returns single base64 string
            $encrypted = self::encryptData($data, $server_private_key, $client_public_key);
            if (is_wp_error($encrypted)) {
                return new WP_REST_Response($encrypted->get_error_data(), 500);
            }

            // Return just the encrypted string
            return new WP_REST_Response($encrypted, $status_code);

        } catch (\Exception $e) {
            error_log('Encryption error: ' . $e->getMessage());
            return new WP_REST_Response(
                ['error' => 'Encryption failed: ' . $e->getMessage()],
                500
            );
        }
    }

    /**
     * Validate and decrypt request parameters (or return plain data in dev mode)
     *
     * @param WP_REST_Request $request
     * @param int $user_id
     * @return array|WP_Error Decrypted data or WP_Error on failure
     */
    public static function validateAndDecryptRequest(WP_REST_Request $request, $user_id = null, $client_id = null)
    {
        try {
            // In development mode, return plain parameters
            if (self::isDevelopmentMode()) {
                $params = $request->get_params();
                unset($params['dev_mode']);
                return $params;
            }

            if ($user_id === null) {
                $user_id = get_current_user_id();
            }

            if (!$user_id && !$client_id) {
                return new WP_Error(
                    'unauthorized',
                    __('Unauthorized request', 'kivicare-clinic-management-system'),
                    ['status' => 401]
                );
            }

            // Get the combined encrypted data from the raw body
            $combined_data = $request->get_body();
            if (empty($combined_data)) {
                // No encrypted body — return regular parameters (e.g. GET requests)
                return $request->get_params();
            }

            // Decrypt the combined data
            return self::decryptRequestData($combined_data, $user_id, $client_id);

        } catch (\Exception $e) {
            error_log('Request validation error: ' . $e->getMessage());
            return new WP_Error('validation_failed', 'Request validation failed: ' . $e->getMessage());
        }
    }

    /**
     * Get and validate server private key
     *
     * @return string|WP_Error
     */
    private static function getServerPrivateKey()
    {
        $server_private_key_b64 = get_option('kc_server_private_key');
        if (!$server_private_key_b64) {
            error_log('Server private key not found in wp_options');
            return new WP_Error('missing_key', 'Server private key not found', ['error' => 'Server private key not found']);
        }

        try {
            $server_private_key_bin = \ParagonIE_Sodium_Compat::base642bin($server_private_key_b64, SODIUM_BASE64_VARIANT_ORIGINAL);
            if (strlen($server_private_key_bin) !== \ParagonIE_Sodium_Compat::CRYPTO_BOX_SECRETKEYBYTES) {
                error_log("Invalid server private key length: " . strlen($server_private_key_bin));
                return new WP_Error('invalid_key', 'Invalid server private key length', ['error' => 'Invalid server private key length']);
            }
            return $server_private_key_bin;
        } catch (\Exception $e) {
            error_log("Invalid server private key base64: " . $e->getMessage());
            return new WP_Error('invalid_key', 'Invalid server private key: ' . $e->getMessage(), ['error' => 'Invalid server private key: ' . $e->getMessage()]);
        }
    }

    /**
     * Get and validate client public key
     *
     * @param int $user_id
     * @return string|WP_Error
     */
    private static function getClientPublicKey($user_id, $client_id = null)
    {
        $client_public_key_b64 = '';
        if ($user_id) {
            $client_public_key_b64 = get_user_meta($user_id, 'client_public_key', true);
        } elseif ($client_id) {
            $client_public_key_b64 = get_transient('kc_e2e_guest_' . $client_id);
        }

        if (!$client_public_key_b64) {
            error_log('Client public key not found for user ID: ' . $user_id . ' / Client ID: ' . ($client_id ?: 'null'));
            return new WP_Error('missing_key', 'Client public key not found', ['error' => 'Client public key not found']);
        }

        try {
            $client_public_key = \ParagonIE_Sodium_Compat::base642bin($client_public_key_b64, SODIUM_BASE64_VARIANT_ORIGINAL);
            if (strlen($client_public_key) !== \ParagonIE_Sodium_Compat::CRYPTO_BOX_PUBLICKEYBYTES) {
                error_log("Invalid client public key length: " . strlen($client_public_key));
                return new WP_Error('invalid_key', 'Invalid client public key length', ['error' => 'Invalid client public key length']);
            }
            return $client_public_key;
        } catch (\Exception $e) {
            error_log("Invalid client public key base64: " . $e->getMessage());
            return new WP_Error('invalid_key', 'Invalid client public key: ' . $e->getMessage(), ['error' => 'Invalid client public key: ' . $e->getMessage()]);
        }
    }

    /**
     * Encrypt data using libsodium
     *
     * @param mixed $data
     * @param string $server_private_key
     * @param string $client_public_key
     * @return string|WP_Error
     */
    private static function encryptData($data, $server_private_key, $client_public_key)
    {
        try {
            // Generate random nonce
            $nonce = \ParagonIE_Sodium_Compat::randombytes_buf(\ParagonIE_Sodium_Compat::CRYPTO_BOX_NONCEBYTES);

            // Encrypt the data
            $encrypted_response = \ParagonIE_Sodium_Compat::crypto_box(
                json_encode($data),
                $nonce,
                \ParagonIE_Sodium_Compat::crypto_box_keypair_from_secretkey_and_publickey(
                    $server_private_key,
                    $client_public_key
                )
            );

            // Combine nonce + encrypted data and return as single base64 string
            $combined = $nonce . $encrypted_response;
            return \ParagonIE_Sodium_Compat::bin2base64($combined, SODIUM_BASE64_VARIANT_ORIGINAL);

        } catch (\Exception $e) {
            error_log("Encryption failed: " . $e->getMessage());
            return new WP_Error('encryption_failed', 'Data encryption failed: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt request data
     *
     * @param string $combined_data_b64 Base64 encoded string containing nonce + encrypted data
     * @param int $user_id
     * @return array|WP_Error
     */
    private static function decryptRequestData($combined_data_b64, $user_id, $client_id = null)
    {
        try {
            $server_private_key = self::getServerPrivateKey();
            if (is_wp_error($server_private_key)) {
                return $server_private_key;
            }

            $client_public_key = self::getClientPublicKey($user_id, $client_id);
            if (is_wp_error($client_public_key)) {
                return $client_public_key;
            }

            // Remove surrounding quotes if present (JSON-encoded string)
            $combined_data_b64 = trim($combined_data_b64, '"');

            // Decode base64 to binary
            $combined_bin = \ParagonIE_Sodium_Compat::base642bin(
                $combined_data_b64,
                SODIUM_BASE64_VARIANT_ORIGINAL
            );

            // Split into nonce + ciphertext
            $nonce          = substr($combined_bin, 0, \ParagonIE_Sodium_Compat::CRYPTO_BOX_NONCEBYTES);
            $encrypted_data = substr($combined_bin, \ParagonIE_Sodium_Compat::CRYPTO_BOX_NONCEBYTES);

            // Create keypair
            $keypair = \ParagonIE_Sodium_Compat::crypto_box_keypair_from_secretkey_and_publickey(
                $server_private_key,
                $client_public_key
            );

            // Decrypt
            $decrypted = \ParagonIE_Sodium_Compat::crypto_box_open(
                $encrypted_data,
                $nonce,
                $keypair
            );

            if ($decrypted === false) {
                error_log('Decryption failed');
                return new WP_Error('decryption_failed', 'Failed to decrypt data');
            }

            // Parse JSON
            $decoded = json_decode($decrypted, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Invalid JSON: ' . json_last_error_msg());
                return new WP_Error('invalid_json', 'Invalid JSON in decrypted data');
            }

            return $decoded;

        } catch (\Exception $e) {
            error_log("Decryption failed: " . $e->getMessage());
            return new WP_Error('decryption_failed', 'Data decryption failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a simple error response (unencrypted)
     *
     * @param string $message
     * @param int $status_code
     * @return WP_REST_Response
     */
    public static function createErrorResponse($message, $status_code = 400)
    {
        return new WP_REST_Response(['error' => $message], $status_code);
    }
}
