<?php
namespace App\models\traits;

use App\baseClasses\KCQueryBuilder;
use App\models\KCUserMeta;
use WP_Error;
use WP_User;

defined('ABSPATH') or die('Something went wrong');

trait KCWPUser
{
    protected ?WP_User $wpUser = null;

    /**
     * Get the WordPress user object
     */
    public function getWPUser(): ?WP_User
    {
        if ($this->wpUser === null) {
            // Use $this->id instead of $this->user_id since KCPatient uses 'id' property
            $userId = $this->id ?? null;
            if ($userId) {
                $this->wpUser = get_user_by('id', $userId) ?: null;
            }
        }
        return $this->wpUser;
    }

    /**
     * Set user role
     */
    public function setRole(string $role): void
    {
        $user = $this->getWPUser();
        if ($user) {
            $user->set_role($role);
        }
    }

    public function getMeta(string $key, bool $single = true)
    {
        if (empty($this->id)) {
            return $single ? '' : [];
        }
        return get_user_meta($this->id, $key, $single);
    }

    /**
     * Update user meta
     */
    public function updateMeta(string $key, $value): bool
    {
        return update_user_meta($this->id, $key, $value);
    }

    /**
     * Create or update WordPress user
     */
    protected function saveWordPressUser(array $data): int|WP_Error
    {
        global $wpdb;

        if (empty($this->id)) {
            // Creating new user
            $userData = array_merge([
                'user_login' => $data['username'],
                'user_email' => $data['email'],
                'first_name' => $data['firstName'] ?? '',
                'last_name' => $data['lastName'] ?? '',
                'display_name' => $data['displayName'] ?? $data['username'],
            ], !empty($data['password']) ? ['user_pass' => $data['password']] : []);

            $userId = wp_insert_user($userData);

            if (is_wp_error($userId)) {
                return $userId;
            }

            $this->wpUser = get_user_by('id', $userId) ?: null;
            $this->id = $userId;

            // Save user status if provided
            if (isset($data['status'])) {
                $wpdb->update(
                    $wpdb->base_prefix . 'users',
                    ['user_status' => $data['status']],
                    ['ID' => (int) $this->id]
                );
            }

            return $this->id;
        } else {
            // Updating existing user
            $userData = array_merge([
                'ID' => $this->id,
                'user_email' => $data['email'],
                'first_name' => $data['firstName'] ?? '',
                'last_name' => $data['lastName'] ?? '',
                'display_name' => $data['displayName'] ?? null,
            ], !empty($data['password']) ? ['user_pass' => $data['password']] : []);

            // Update user status if provided
            if (isset($data['status'])) {
                $wpdb->update(
                    $wpdb->base_prefix . 'users',
                    ['user_status' => $data['status']],
                    ['ID' => (int) $this->id]
                );
            }

            $result = wp_update_user($userData);

            // wp_update_user returns WP_Error on failure, or the user ID on success
            if (is_wp_error($result)) {
                return $result;
            }

            return $this->id;
        }
    }

    /**
     * The `query` function returns a new instance of `KCQueryBuilder` using the current class.
     *
     * @return \App\baseClasses\KCQueryBuilder An instance of the `KCQueryBuilder` class is being returned.
     */
    public static function query(): KCQueryBuilder
    {
        $tableAlias = static::$tableAlias ?? 'user';
        global $wpdb;
        return (new KCQueryBuilder(static::class))
            ->setTableAlias($tableAlias)
            ->join(KCUserMeta::class, $tableAlias . '.ID', '=', 'um.user_id', 'um')
            ->where('um.meta_key', $wpdb->prefix.'capabilities')
            ->where('um.meta_value', 'LIKE', '%"' . self::$userRole . '"%');
    }
    public function delete(): bool
    {
        if (!empty($this->id)) {
            if (!function_exists('wp_delete_user')) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }
            return \wp_delete_user($this->id);
        }
        return false;
    }
}