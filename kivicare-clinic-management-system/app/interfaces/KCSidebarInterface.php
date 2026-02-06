<?php

namespace App\interfaces;

defined('ABSPATH') or die('Something went wrong');

/**
 * Interface KCSidebarInterface
 * 
 * Defines the contract for sidebar management functionality
 * 
 * @package App\interfaces
 */
interface KCSidebarInterface
{
    /**
     * Get sidebar configuration for current user
     * 
     * @param array|null $user_roles Optional user roles array
     * @return array
     */
    public function getDashboardSidebar($user_roles = null): array;

    /**
     * Save sidebar configuration for specific role
     * 
     * @param string $role User role
     * @param array $config Sidebar configuration
     * @return bool
     */
    public function saveSidebarConfig(string $role, array $config): bool;

    /**
     * Clear sidebar cache
     * 
     * @param string|null $role Specific role or all if null
     */
    public function clearCache(string|null $role = null): void;

    /**
     * Get all available roles
     * 
     * @return array
     */
    public function getAvailableRoles(): array;

    /**
     * Validate sidebar item structure
     * 
     * @param array $item Sidebar item
     * @return bool
     */
    public function validateSidebarItem(array $item): bool;

    /**
     * Add custom sidebar item for specific role
     * 
     * @param string $role User role
     * @param array $item Sidebar item
     * @param int|null $position Insert position (null for append)
     * @return bool
     */
    public function addCustomSidebarItem(string $role, array $item, int|null $position = null): bool;

    /**
     * Remove sidebar item by route class
     * 
     * @param string $role User role
     * @param string $routeClass Route class to remove
     * @return bool
     */
    public function removeSidebarItem(string $role, string $routeClass): bool;

    /**
     * Update existing sidebar item
     * 
     * @param string $role User role
     * @param string $routeClass Route class to update
     * @param array $updates Update data
     * @return bool
     */
    public function updateSidebarItem(string $role, string $routeClass, array $updates): bool;

    /**
     * Get sidebar item by route class
     * 
     * @param string $role User role
     * @param string $routeClass Route class
     * @return array|null
     */
    public function getSidebarItem(string $role, string $routeClass): ?array;

    /**
     * Reset sidebar to default configuration
     * 
     * @param string $role User role
     * @return bool
     */
    public function resetToDefault(string $role): bool;
}
