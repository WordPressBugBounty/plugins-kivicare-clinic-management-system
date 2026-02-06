<?php

namespace App\models;

use App\baseClasses\KCBaseModel;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

use WP_User;

class KCUser extends KCBaseModel
{

    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array
    {
        global $wpdb;
        return [
            'user_table_name' => $wpdb->users,
            'primary_key' => 'ID',
            'columns' => [
                'id' => [
                    'column' => 'ID',
                    'type' => 'int',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'username' => [
                    'column' => 'user_login',
                    'type' => 'string',
                    'nullable' => false,
                    'sanitizers' => ['sanitize_user'],
                    'validators' => [
                        fn($value) => !empty($value) ? true : 'Username is required'
                    ],
                ],
                'email' => [
                    'column' => 'user_email',
                    'type' => 'string',
                    'nullable' => false,
                    'sanitizers' => ['sanitize_email'],
                    'validators' => [
                        fn($value) => is_email($value) ? true : 'Invalid email format'
                    ],
                ],
                'password' => [
                    'column' => 'user_pass',
                    'type' => 'string',
                    'nullable' => true, // Allow null for existing users
                ],
                'firstName' => [
                    'column' => 'first_name',
                    'type' => 'string',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'lastName' => [
                    'column' => 'last_name',
                    'type' => 'string',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'displayName' => [
                    'column' => 'display_name',
                    'type' => 'string',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'registeredDate' => [
                    'column' => 'user_registered',
                    'type' => 'datetime',
                    'nullable' => false,
                ],
                'status' => [
                    'column' => 'user_status',
                    'type' => 'int',
                    'nullable' => false,
                    'default' => 0,
                ],
            ],
            'timestamps' => false, // WordPress handles this
            'soft_deletes' => false, // WordPress handles this
        ];
    }

    protected ?WP_User $wpUser = null;

    /**
     * Create a new WordPress user
     */
    public function save(): int|WP_Error
    {
        if (empty($this->attributes['id'])) {
            // Creating new user
            $userData = [
                'user_login' => $this->username,
                'user_email' => $this->email,
                'first_name' => $this->firstName ?? '',
                'last_name' => $this->lastName ?? '',
                'display_name' => $this->displayName ?? $this->username,
            ];

            if (!empty($this->password)) {
                $userData['user_pass'] = $this->password;
            }

            $userId = wp_insert_user($userData);

            if (is_wp_error($userId)) {
                return $userId;
            }

            $this->attributes['id'] = $userId;
            return $userId;
        } else {
            // Updating existing user
            $userData = [
                'ID' => $this->id,
                'user_email' => $this->email,
                'first_name' => $this->firstName ?? '',
                'last_name' => $this->lastName ?? '',
                'display_name' => $this->displayName ?? $this->username,
            ];

            if (!empty($this->password)) {
                $userData['user_pass'] = $this->password;
            }

            $result = wp_update_user($userData);
            return is_wp_error($result);
        }
    }

    /**
     * Delete the WordPress user
     */
    public function delete(): bool
    {
        if (!empty($this->attributes['id'])) {
            return wp_delete_user($this->attributes['id']);
        }
        return false;
    }

    /**
     * Get the underlying WP_User object
     */
    public function getWPUser(): ?WP_User
    {
        if ($this->wpUser === null && !empty($this->attributes['id'])) {
            $this->wpUser = get_user_by('id', $this->attributes['id']);
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

    /**
     * Get user meta
     */
    public function getMeta(string $key, bool $single = true)
    {
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
     * Update user_status for all users with a given role
     *
     * @param string $role
     * @param int $status
     * @return int Number of users updated
     */
    public static function updateStatusByRole(string $role, int $status): int
    {
        $users = get_users(['role' => $role]);
        $updatedCount = 0;

        foreach ($users as $user) {
            $kcUser = new self(['id' => $user->ID]);
            $kcUser->status = $status;

            if (!$kcUser->save() instanceof \WP_Error) {
                $updatedCount++;
            }
        }

        return $updatedCount;
    }
}
