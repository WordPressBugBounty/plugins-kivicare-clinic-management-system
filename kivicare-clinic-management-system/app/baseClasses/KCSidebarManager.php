<?php

namespace App\baseClasses;

use App\interfaces\KCSidebarInterface;
use App\models\KCOption;


defined('ABSPATH') or die('Something went wrong');

/**
 * Class KCSidebarManager
 * 
 * Manages sidebar navigation for different user roles in KiviCare
 * Implements OOP design patterns for sidebar configuration
 * 
 * @package App\baseClasses
 */
class KCSidebarManager implements KCSidebarInterface
{
    /**
     * @var KCSidebarManager Single instance of this class
     */
    private static $instance = null;

    /**
     * @var KCBase Instance of KCBase for role management
     */
    private $kcBase;

    /**
     * @var array Cache for sidebar configurations
     */
    private $sidebarCache = [];

    /**
     * @var array Default sidebar configuration structure
     */
    private $defaultItemStructure = [
        'label'      => '',
        'type'       => 'route', // route, parent, href
        'link'       => '',
        'iconClass'  => '',
        'routeClass' => '',
        'childrens'  => []
    ];

    /**
     * Constructor - Private to implement Singleton pattern
     */
    private function __construct()
    {
        $this->kcBase = KCBase::get_instance();
    }

    /**
     * Get singleton instance
     * 
     * @return KCSidebarManager
     */
    public static function getInstance(): KCSidebarManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get sidebar array for specific user roles
     * 
     * @param array|null $user_roles Optional user roles array (for future use)
     * @return array
     */
    public function getDashboardSidebar($user_roles = null): array
    {
        $role = $this->kcBase->getLoginUserRole();

        if (isset($this->sidebarCache[$role])) {
            return $this->sidebarCache[$role];
        }

        $sidebar = [];
        $savedConfig = $this->getSavedSidebarConfig($role);

        if (!empty($savedConfig)) {
            $sidebar = $savedConfig;
        } else {
            $sidebar = $this->generateSidebarForRole($role);
        }
        
        // Get module configuration from kivicare_modules option
        $modules_data = kcGetModules();
        $module_config = $modules_data['module_config'] ?? [];
        
        // Map module names to route classes
        $modules_to_check = [
            'receptionist'  => 'receptionist',
            'billing'       => 'billings',
        ];

        $disabled_route_classes = [];

        // Check each module's status
        foreach ($modules_to_check as $module_name => $route_class) {
            $module_enabled = false;
            
            // Find the module in the config array
            foreach ($module_config as $module) {
                if (isset($module['name']) && $module['name'] === $module_name) {
                    // Check if status is '1' (enabled)
                    $module_enabled = (isset($module['status']) && ($module['status'] === '1' || $module['status'] === 1));
                    break;
                }
            }
            
            // If module is disabled, add its route class to the disabled list
            if (!$module_enabled) {
                $disabled_route_classes[] = $route_class;
            }
        }

        // Filter out disabled modules from sidebar
        if (!empty($disabled_route_classes)) {
            $sidebar = array_filter($sidebar, function($item) use ($disabled_route_classes) {
                return !isset($item['routeClass']) || !in_array($item['routeClass'], $disabled_route_classes, true);
            });
            $sidebar = array_values($sidebar);
        }

        $this->sidebarCache[$role] = $sidebar;
        return $sidebar;
    }

    /**
     * Generate sidebar configuration for specific role
     * 
     * @param string $role User role
     * @return array
     */
    private function generateSidebarForRole(string $role): array
    {
        switch ($role) {
            case 'administrator':
                return $this->getAdminSidebar();

            case $this->kcBase->getClinicAdminRole():
                return $this->getClinicAdminSidebar();

            case $this->kcBase->getReceptionistRole():
                return $this->getReceptionistSidebar();

            case $this->kcBase->getDoctorRole():
                return $this->getDoctorSidebar();

            case $this->kcBase->getPatientRole():
                return $this->getPatientSidebar();

            default:
                return [];
        }
    }

    /**
     * Create a group header item
     * 
     * @param string $label Group header label
     * @return array
     */
    private function createGroupHeader(string $label): array
    {
        return [
            'label'      => $label,
            'type'       => 'group',
            'link'       => '',
            'iconClass'  => '',
            'routeClass' => 'group_' . sanitize_title($label),
            'childrens'  => []
        ];
    }

    /**
     * Get administrator sidebar configuration with groups
     * 
     * @return array
     */
    private function getAdminSidebar(): array
    {
        $sidebar = [
            // Core Group
            $this->createGroupHeader('Main'),
            $this->createSidebarItem('Dashboard', 'route', '/dashboard', 'ph ph-squares-four', 'dashboard'),
            $this->createSidebarItem('Appointments', 'route', '/appointments', 'ph ph-calendar-dots', 'appointment_list'),
            $this->createEncountersParentItem([
                $this->createSidebarItem('Encounters List', 'route', '/encounter', 'ph ph-list-dashes', 'patient_encounter_list'),
                $this->createSidebarItem('Encounter Templates', 'route', '/encounter-template', 'ph ph-layout', 'encounter_template'),
            ]),

            // User Management Group
            $this->createGroupHeader('Users'),
            $this->createSidebarItem('Patients', 'route', '/patient', 'ph ph-users-three', 'patient'),
            $this->createSidebarItem('Doctors', 'route', '/doctor', 'ph ph-stethoscope', 'doctor'),
            $this->createSidebarItem('Receptionists', 'route', '/receptionist', 'ph ph-user-circle-plus', 'receptionist'),

            // Clinic Management Group
            $this->createGroupHeader('Clinic'),
            $this->createSidebarItem('Clinics', 'route', '/clinic', 'ph ph-hospital', 'clinic'),
            $this->createSidebarItem('Services', 'route', '/service', 'ph ph-first-aid-kit', 'service'),
            $this->createSidebarItem('Doctor Sessions', 'route', '/doctor-session', 'ph ph-clock', 'doctor_session'),

            // Financial Group
            $this->createGroupHeader('Financial'),
            $this->createSidebarItem('Taxes', 'route', '/tax', 'ph ph-seal-percent', 'tax'),
            $this->createSidebarItem('Billing records', 'route', '/billings', 'ph ph-invoice', 'billings'),
            $this->createSidebarItem('Reports', 'route', '/clinic-revenue-reports', 'ph ph-file', 'clinic-revenue-reports'),

            // Settings Group
            $this->createGroupHeader('Settings'),
            $this->createSidebarItem('Settings', 'route', '/setting/general-setting', 'ph ph-gear-six', 'settings'),
        ];

        $hide_utility_links = KCOption::get('request_helper_status');
        // if hide utility links is not on then add support group
        if($hide_utility_links !== 'on') {
            // Support Group
            $sidebar[] = $this->createGroupHeader('Support');
            $sidebar[] = $this->createSidebarItem('Get help', 'route', '/get-help', 'ph ph-lifebuoy', 'get_help');
            if (!defined('KIVI_CARE_PRO_VERSION')) {
                $sidebar[] = $this->createSidebarItem('Get Pro', 'href', 'https://codecanyon.net/item/kivicare-pro-clinic-patient-management-system-ehr-addon/30690654', 'ph ph-crown-simple', 'get_pro');
            }
            $sidebar[] = $this->createSidebarItem('Request Features', 'href', 'https://iqonic.design/feature-request/?for_product=kivicare', 'ph ph-sliders-horizontal', 'request_feature');
        }

        return $sidebar;
    }

    /**
     * Get clinic admin sidebar configuration with groups
     * 
     * @return array
     */
    private function getClinicAdminSidebar(): array
    {
        return [
            // Core Group
            $this->createGroupHeader('Main'),
            $this->createSidebarItem('Dashboard', 'route', '/dashboard', 'ph ph-squares-four', 'dashboard'),
            $this->createSidebarItem('Appointments', 'route', '/appointments', 'ph ph-calendar-dots', 'appointment_list'),
            $this->createEncountersParentItem([
                $this->createSidebarItem('Encounters List', 'route', '/encounter', 'ph ph-list-dashes', 'patient_encounter_list'),
                $this->createSidebarItem('Encounter Templates', 'route', '/encounter-template', 'ph ph-layout', 'encounter_template'),
            ]),

            // User Management Group
            $this->createGroupHeader('Users'),
            $this->createSidebarItem('Patients', 'route', '/patient', 'ph ph-users-three', 'patient'),
            $this->createSidebarItem('Doctors', 'route', '/doctor', 'ph ph-stethoscope', 'doctor'),
            $this->createSidebarItem('Receptionists', 'route', '/receptionist', 'ph ph-user-circle-plus', 'receptionist'),

            // Clinic Management Group
            $this->createGroupHeader('Clinic'),
            $this->createSidebarItem('Services', 'route', '/service', 'ph ph-first-aid-kit', 'service'),
            $this->createSidebarItem('Doctor Sessions', 'route', '/doctor-session', 'ph ph-clock', 'doctor_session'),

            // Financial Group
            $this->createGroupHeader('Financial'),
            $this->createSidebarItem('Taxes', 'route', '/tax', 'ph ph-seal-percent', 'tax'),
            $this->createSidebarItem('Billing records', 'route', '/billings', 'ph ph-invoice', 'billings'),
            $this->createSidebarItem('Reports', 'route', '/clinic-revenue-reports', 'ph ph-file', 'clinic-revenue-reports'),

            // Settings Group
            $this->createGroupHeader('Settings'),
            $this->createSidebarItem('Settings', 'route', '/setting/holidays', 'ph ph-gear-six', 'clinic_settings'),
        ];
    }

    /**
     * Get receptionist sidebar configuration with groups
     * 
     * @return array
     */
    private function getReceptionistSidebar(): array
    {
        return [
            // Core Group
            $this->createGroupHeader('Main'),
            $this->createSidebarItem('Dashboard', 'route', '/dashboard', 'ph ph-squares-four', 'dashboard'),
            $this->createSidebarItem('Appointments', 'route', '/appointments', 'ph ph-calendar-dots', 'appointment_list'),
            $this->createEncountersParentItem([
                $this->createSidebarItem('Encounters List', 'route', '/encounter', 'ph ph-list-dashes', 'patient_encounter_list'),
                $this->createSidebarItem('Encounter Templates', 'route', '/encounter-template', 'ph ph-layout', 'encounter_template'),
            ]),

            // User Management Group
            $this->createGroupHeader('Users'),
            $this->createSidebarItem('Patients', 'route', '/patient', 'ph ph-users-three', 'patient'),
            $this->createSidebarItem('Doctors', 'route', '/doctor', 'ph ph-stethoscope', 'doctor'),

            // Clinic Management Group
            $this->createGroupHeader('Clinic'),
            $this->createSidebarItem('Services', 'route', '/service', 'ph ph-first-aid-kit', 'service'),

            // Financial Group
            $this->createGroupHeader('Financial'),
            $this->createSidebarItem('Billing records', 'route', '/billings', 'ph ph-invoice', 'billings'),

            // Settings Group
            $this->createGroupHeader('Settings'),
            $this->createSidebarItem('Settings', 'route', '/setting/holidays', 'ph ph-gear-six', 'clinic_settings'),
        ];
    }

    /**
     * Get doctor sidebar configuration with groups
     * 
     * @return array
     */
    private function getDoctorSidebar(): array
    {
        return [
            // Core Group
            $this->createGroupHeader('Main'),
            $this->createSidebarItem('Dashboard', 'route', '/dashboard', 'ph ph-squares-four', 'dashboard'),
            $this->createSidebarItem('Appointments', 'route', '/appointments', 'ph ph-calendar-dots', 'appointment_list'),
            $this->createEncountersParentItem([
                $this->createSidebarItem('Encounters List', 'route', '/encounter', 'ph ph-list-dashes', 'patient_encounter_list'),
                $this->createSidebarItem('Encounter Templates', 'route', '/encounter-template', 'ph ph-layout', 'encounters_template_list'),
            ]),

            // User Management Group
            $this->createGroupHeader('Users'),
            $this->createSidebarItem('Patients', 'route', '/patient', 'ph ph-users-three', 'patient'),

            // Clinic Management Group
            $this->createGroupHeader('Clinic'),
            $this->createSidebarItem('Services', 'route', '/service', 'ph ph-first-aid-kit', 'service'),

            // Financial Group
            $this->createGroupHeader('Financial'),
            $this->createSidebarItem('Billing records', 'route', '/billings', 'ph ph-invoice', 'billings'),

            // Settings Group
            $this->createGroupHeader('Settings'),
            $this->createSidebarItem('Settings', 'route', '/setting/holidays', 'ph ph-gear-six', 'clinic_settings'),
        ];
    }

    /**
     * Get patient sidebar configuration
     * 
     * @return array
     */
    private function getPatientSidebar(): array
    {
        return [
            $this->createSidebarItem('Home', 'href', get_home_url(), 'ph ph-house', 'home'),
            $this->createSidebarItem('Dashboard', 'route', '/dashboard', 'ph ph-squares-four', 'dashboard'),
            $this->createSidebarItem('Appointments', 'route', '/appointments', 'ph ph-calendar-dots', 'appointment_list'),
            $this->createSidebarItem('Encounters', 'route', '/encounter', 'ph ph-clock-counter-clockwise', 'patient_encounter_list'),
            $this->createSidebarItem('Billing records', 'route', '/billings', 'ph ph-invoice', 'billings'),
            $this->createSidebarItem('Medical Reports', 'route', '/patient-medical-report_id', 'ph ph-file', 'patient_report'),
        ];
    }

    /**
     * Create a sidebar item with default structure
     * 
     * @param string $label Item label
     * @param string $type Item type (route, parent, href)
     * @param string $link Item link
     * @param string $iconClass Icon CSS class
     * @param string $routeClass Route CSS class
     * @param array $childrens Child items (for parent type)
     * @return array
     */
    private function createSidebarItem(
        string $label,
        string $type = 'route',
        string $link = '',
        string $iconClass = '',
        string $routeClass = '',
        array $childrens = []
    ): array {
        return [
            'label'      => $label,
            'type'       => $type,
            'link'       => $link,
            'iconClass'  => $iconClass,
            'routeClass' => $routeClass,
            'childrens'  => $childrens
        ];
    }

    /**
     * Create encounters parent item with children
     * 
     * @param array $children Child items
     * @return array
     */
    private function createEncountersParentItem(array $children): array
    {
        return $this->createSidebarItem(
            'Encounters',
            'parent',
            '/encounter',
            'ph ph-clock-counter-clockwise',
            'parent',
            $children
        );
    }

    /**
     * Get saved sidebar configuration from database
     * 
     * @param string $role User role
     * @return array|null
     */
    private function getSavedSidebarConfig(string $role): ?array
    {
        $option_key = $role === 'administrator' 
            ? KIVI_CARE_PREFIX . "{$role}_dashboard_sidebar_data_4.0"
            : "{$role}_dashboard_sidebar_data_4.0";
            
        $option_data = get_option($option_key);

        if (!empty($option_data) && is_array($option_data)) {
            return $this->sanitizeSidebarIcons($option_data);
        }

        return null;
    }
 /**
     * Sanitize sidebar icons to replace legacy classes with new ones
     * 
     * @param array $sidebar
     * @return array
     */
    private function sanitizeSidebarIcons(array $sidebar): array
    {
        $mapping = [
            'fa fa-tachometer-alt' => 'ph ph-squares-four',
            'fas fa-calendar-week' => 'ph ph-calendar-dots',
            'far fa-calendar-times' => 'ph ph-clock-counter-clockwise',
            'fas fa-hospital' => 'ph ph-hospital',
            'fas fa-hospital-user' => 'ph ph-users-three',
            'fa fa-user-md' => 'ph ph-stethoscope',
            'fa fa-users' => 'ph ph-user-circle-plus',
            'fa fa-server' => 'ph ph-first-aid-kit',
            'fa fa-calendar' => 'ph ph-clock',
            'fas fa-donate' => 'ph ph-currency-circle-dollar',
            'fa fa-file-invoice' => 'ph ph-invoice',
            'fas fa-chart-line' => 'ph ph-chart-line-up',
            'fa fa-cogs' => 'ph ph-gear-six',
            'fas fa-question-circle' => 'ph ph-question',
            'fas fa-external-link-alt' => 'ph ph-arrow-square-out',
            'fas fa-home' => 'ph ph-house',
            'fa fa-file' => 'ph ph-file-text',
        ];

      // Force icons for specific route classes (IDs)
        $routeClassMapping = [
            'patient_encounter_list' => 'ph ph-list-dashes',
            'encounter_template'     => 'ph ph-layout',
            'encounters_template_list' => 'ph ph-layout', // Doctor role variation
        ];

        foreach ($sidebar as &$item) {
            // Legacy icon mapping
            if (isset($item['iconClass']) && isset($mapping[$item['iconClass']])) {
                $item['iconClass'] = $mapping[$item['iconClass']];
            }

            // Force icon based on routeClass
            if (isset($item['routeClass']) && isset($routeClassMapping[$item['routeClass']])) {
                $item['iconClass'] = $routeClassMapping[$item['routeClass']];
            }
            // Fallback: Force icon based on Link + Type (if routeClass didn't match or is missing)
            else if (isset($item['link'])) {
                // Encounters List (Child) - check type != parent to avoid changing the parent icon
                if ($item['link'] === '/encounter' && ($item['type'] ?? '') !== 'parent') {
                     $item['iconClass'] = 'ph ph-list-dashes';
                }
                // Encounter Templates (Child)
                elseif ($item['link'] === '/encounter-template') {
                     $item['iconClass'] = 'ph ph-layout';
                }
            }
            
            
            if (isset($item['childrens']) && is_array($item['childrens'])) {
                $item['childrens'] = $this->sanitizeSidebarIcons($item['childrens']);
            }
        }
        
        return $sidebar;
    }
    /**
     * Save sidebar configuration to database
     * 
     * @param string $role User role
     * @param array $config Sidebar configuration
     * @return bool
     */
    public function saveSidebarConfig(string $role, array $config): bool
    {
        $option_key = $role === 'administrator'
            ? KIVI_CARE_PREFIX . "{$role}_dashboard_sidebar_data"
            : "{$role}_dashboard_sidebar_data";

        $result = update_option($option_key, $config);

        if ($result) {
            // Update cache
            $this->sidebarCache[$role] = $config;
        }

        return $result;
    }

    /**
     * Clear sidebar cache
     * 
     * @param string|null $role Specific role or all if null
     */
    public function clearCache(string|null $role = null): void
    {
        if ($role === null) {
            $this->sidebarCache = [];
        } else {
            unset($this->sidebarCache[$role]);
        }
    }

    /**
     * Get all available roles
     * 
     * @return array
     */
    public function getAvailableRoles(): array
    {
        return [
            'administrator',
            $this->kcBase->getClinicAdminRole(),
            $this->kcBase->getReceptionistRole(),
            $this->kcBase->getDoctorRole(),
            $this->kcBase->getPatientRole(),
        ];
    }

    /**
     * Validate sidebar item structure
     * 
     * @param array $item Sidebar item
     * @return bool
     */
    public function validateSidebarItem(array $item): bool
    {
        $requiredKeys = ['label', 'type', 'link', 'iconClass', 'routeClass'];

        foreach ($requiredKeys as $key) {
            if (!isset($item[$key])) {
                return false;
            }
        }

        // Validate type (added 'group' type)
        if (!in_array($item['type'], ['route', 'parent', 'href', 'group'])) {
            return false;
        }

        // If parent type, must have childrens
        if ($item['type'] === 'parent' && (!isset($item['childrens']) || !is_array($item['childrens']))) {
            return false;
        }

        return true;
    }

    /**
     * Add custom sidebar item for specific role
     * 
     * @param string $role User role
     * @param array $item Sidebar item
     * @param int|null $position Insert position (null for append)
     * @return bool
     */
    public function addCustomSidebarItem(string $role, array $item, int|null $position = null): bool
    {
        if (!$this->validateSidebarItem($item)) {
            return false;
        }

        $sidebar = $this->getDashboardSidebar();

        if ($position === null) {
            $sidebar[] = $item;
        } else {
            array_splice($sidebar, $position, 0, [$item]);
        }

        return $this->saveSidebarConfig($role, $sidebar);
    }

    /**
     * Remove sidebar item by route class
     * 
     * @param string $role User role
     * @param string $routeClass Route class to remove
     * @return bool
     */
    public function removeSidebarItem(string $role, string $routeClass): bool
    {
        $sidebar = $this->getDashboardSidebar();

        foreach ($sidebar as $index => $item) {
            if ($item['routeClass'] === $routeClass) {
                unset($sidebar[$index]);
                $sidebar = array_values($sidebar); // Re-index array
                return $this->saveSidebarConfig($role, $sidebar);
            }
        }

        return false;
    }

    /**
     * Update existing sidebar item
     * 
     * @param string $role User role
     * @param string $routeClass Route class to update
     * @param array $updates Update data
     * @return bool
     */
    public function updateSidebarItem(string $role, string $routeClass, array $updates): bool
    {
        $sidebar = $this->getDashboardSidebar();

        foreach ($sidebar as $index => $item) {
            if ($item['routeClass'] === $routeClass) {
                $sidebar[$index] = array_merge($item, $updates);
                return $this->saveSidebarConfig($role, $sidebar);
            }
        }

        return false;
    }

    /**
     * Get sidebar item by route class
     * 
     * @param string $role User role
     * @param string $routeClass Route class
     * @return array|null
     */
    public function getSidebarItem(string $role, string $routeClass): ?array
    {
        $sidebar = $this->getDashboardSidebar();

        foreach ($sidebar as $item) {
            if ($item['routeClass'] === $routeClass) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Reset sidebar to default configuration
     * 
     * @param string $role User role
     * @return bool
     */
    public function resetToDefault(string $role): bool
    {
        $option_key = $role === 'administrator'
            ? KIVI_CARE_PREFIX . "{$role}_dashboard_sidebar_data"
            : "{$role}_dashboard_sidebar_data";

        $result = delete_option($option_key);

        if ($result) {
            $this->clearCache($role);
        }

        return $result;
    }
}
