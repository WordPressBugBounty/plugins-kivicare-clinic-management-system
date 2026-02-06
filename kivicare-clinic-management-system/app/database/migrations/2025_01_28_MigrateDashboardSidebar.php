<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;
use App\models\KCOption;

defined('ABSPATH') or die('Something went wrong');

class MigrateDashboardSidebar extends KCAbstractMigration
{
    public function run()
    {
        $this->migrateOldSidebarData();
    }

    public function rollback()
    {
        // Rollback logic can be implemented here
    }

    /**
     * Migrate old sidebar data to new structure with groups
     */
    private function migrateOldSidebarData()
    {
        $roles = [
            'administrator' => 'administrator_dashboard_sidebar_data',
            'clinic_admin'  => 'clinic_admin_dashboard_sidebar_data',
            'receptionist'  => 'receptionist_dashboard_sidebar_data',
            'doctor'        => 'doctor_dashboard_sidebar_data',
            'patient'       => 'patient_dashboard_sidebar_data'
        ];

        foreach ($roles as $role => $option_key) {
            $old_data = KCOption::get($option_key);

            if ($old_data && is_array($old_data)) {
                $migrated_data = $this->migrateRoleSidebar($role, $old_data);
                KCOption::set($option_key . '_4.0', $migrated_data);
            }
        }
    }

    /**
     * Migrate sidebar data for specific role
     */
    private function migrateRoleSidebar(string $role, array $old_sidebar): array
    {
        switch ($role) {
            case 'administrator':
                return $this->migrateAdminSidebar($old_sidebar);
            case 'clinic_admin':
                return $this->migrateClinicAdminSidebar($old_sidebar);
            case 'receptionist':
                return $this->migrateReceptionistSidebar($old_sidebar);
            case 'doctor':
                return $this->migrateDoctorSidebar($old_sidebar);
            case 'patient':
                return $this->migratePatientSidebar($old_sidebar);
            default:
                return $old_sidebar;
        }
    }

    /**
     * Create group header item
     */
    private function createGroupHeader(string $label): array
    {
        return [
            'label' => $label,
            'type' => 'group',
            'link' => '',
            'iconClass' => '',
            'routeClass' => 'group_' . sanitize_title($label),
            'childrens' => []
        ];
    }

    /**
     * Create sidebar item
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
            'label' => $label,
            'type' => $type,
            'link' => $link,
            'iconClass' => $iconClass,
            'routeClass' => $routeClass,
            'childrens' => $childrens
        ];
    }

    /**
     * Migrate administrator sidebar
     */
    private function migrateAdminSidebar(array $old_sidebar): array
    {
        $new_sidebar = [];

        // Core Group
        $new_sidebar[] = $this->createGroupHeader('Main');
        $new_sidebar[] = $this->migrateItem($old_sidebar[0] ?? []);  // Dashboard
        $new_sidebar[] = $this->migrateItem($old_sidebar[1] ?? []);  // Appointments

        // Migrate Encounters parent item
        if (isset($old_sidebar[2]) && $old_sidebar[2]['type'] === 'parent') {
            $new_sidebar[] = $this->migrateParentItem(
                $old_sidebar[2],
                [
                    'routeClass' => 'parent',
                    'iconClass' => 'ph ph-clock-counter-clockwise'
                ]
            );
        }

        // User Management Group
        $new_sidebar[] = $this->createGroupHeader('Users');
        $new_sidebar[] = $this->migrateItem($old_sidebar[4] ?? []);  // Patients
        $new_sidebar[] = $this->migrateItem($old_sidebar[5] ?? []);  // Doctors
        $new_sidebar[] = $this->migrateItem($old_sidebar[6] ?? []);  // Receptionist

        // Clinic Management Group
        $new_sidebar[] = $this->createGroupHeader('Clinic');
        $new_sidebar[] = $this->migrateItem($old_sidebar[3] ?? []);  // Clinic
        $new_sidebar[] = $this->migrateItem($old_sidebar[7] ?? []);  // Services
        $new_sidebar[] = $this->migrateItem($old_sidebar[8] ?? []);  // Doctor Sessions

        // Financial Group
        $new_sidebar[] = $this->createGroupHeader('Financial');
        $new_sidebar[] = $this->migrateItem($old_sidebar[9] ?? []);  // Taxes
        $new_sidebar[] = $this->migrateItem($old_sidebar[10] ?? []); // Billing records
        $new_sidebar[] = $this->migrateItem($old_sidebar[11] ?? []); // Reports

        // Settings Group
        $new_sidebar[] = $this->createGroupHeader('Settings');
        $new_sidebar[] = $this->migrateItem($old_sidebar[12] ?? []); // Settings

        // Support Group
        $new_sidebar[] = $this->createGroupHeader('Support');
        $new_sidebar[] = $this->migrateItem($old_sidebar[13] ?? []); // Get help
        $new_sidebar[] = $this->migrateItem($old_sidebar[14] ?? []); // Get Pro

        // Request Features
        if (isset($old_sidebar[15]) && $old_sidebar[15]['type'] === 'href') {
            $new_sidebar[] = $this->migrateItem($old_sidebar[15], [
                'routeClass' => 'request_feature',
                'iconClass' => 'ph ph-sliders-horizontal'
            ]);
        }

        return $new_sidebar;
    }

    /**
     * Migrate clinic admin sidebar
     */
    private function migrateClinicAdminSidebar(array $old_sidebar): array
    {
        $new_sidebar = [];

        // Core Group
        $new_sidebar[] = $this->createGroupHeader('Main');
        $new_sidebar[] = $this->migrateItem($old_sidebar[0] ?? []);  // Dashboard
        $new_sidebar[] = $this->migrateItem($old_sidebar[1] ?? []);  // Appointments

        // Migrate Encounters parent item
        if (isset($old_sidebar[2]) && $old_sidebar[2]['type'] === 'parent') {
            $new_sidebar[] = $this->migrateParentItem(
                $old_sidebar[2],
                [
                    'routeClass' => 'parent',
                    'iconClass' => 'ph ph-clock-counter-clockwise'
                ]
            );
        }

        // User Management Group
        $new_sidebar[] = $this->createGroupHeader('Users');
        $new_sidebar[] = $this->migrateItem($old_sidebar[3] ?? []);  // Patients
        $new_sidebar[] = $this->migrateItem($old_sidebar[4] ?? []);  // Doctors
        $new_sidebar[] = $this->migrateItem($old_sidebar[5] ?? []);  // Receptionist

        // Clinic Management Group
        $new_sidebar[] = $this->createGroupHeader('Clinic');
        $new_sidebar[] = $this->migrateItem($old_sidebar[6] ?? []);  // Services
        $new_sidebar[] = $this->migrateItem($old_sidebar[7] ?? []);  // Doctor Sessions

        // Financial Group
        $new_sidebar[] = $this->createGroupHeader('Financial');
        $new_sidebar[] = $this->migrateItem($old_sidebar[8] ?? []);  // Taxes
        $new_sidebar[] = $this->migrateItem($old_sidebar[9] ?? []);  // Billing records
        $new_sidebar[] = $this->migrateItem($old_sidebar[10] ?? []); // Reports

        // Settings Group
        $new_sidebar[] = $this->createGroupHeader('Settings');
        $new_sidebar[] = $this->migrateItem($old_sidebar[11] ?? [], [
            'routeClass' => 'clinic_settings',
            'link' => 'setting/holidays'
        ]);

        return $new_sidebar;
    }

    /**
     * Migrate receptionist sidebar
     */
    private function migrateReceptionistSidebar(array $old_sidebar): array
    {
        $new_sidebar = [];

        // Core Group
        $new_sidebar[] = $this->createGroupHeader('Main');
        $new_sidebar[] = $this->migrateItem($old_sidebar[0] ?? []);  // Dashboard
        $new_sidebar[] = $this->migrateItem($old_sidebar[1] ?? []);  // Appointments

        // Migrate Encounters parent item
        if (isset($old_sidebar[2]) && $old_sidebar[2]['type'] === 'parent') {
            $new_sidebar[] = $this->migrateParentItem(
                $old_sidebar[2],
                [
                    'routeClass' => 'parent',
                    'iconClass' => 'ph ph-clock-counter-clockwise'
                ]
            );
        }

        // User Management Group
        $new_sidebar[] = $this->createGroupHeader('Users');
        $new_sidebar[] = $this->migrateItem($old_sidebar[3] ?? []);  // Patients
        $new_sidebar[] = $this->migrateItem($old_sidebar[4] ?? []);  // Doctors

        // Clinic Management Group
        $new_sidebar[] = $this->createGroupHeader('Clinic');
        $new_sidebar[] = $this->migrateItem($old_sidebar[5] ?? []);  // Services

        // Financial Group
        $new_sidebar[] = $this->createGroupHeader('Financial');
        $new_sidebar[] = $this->migrateItem($old_sidebar[6] ?? []);  // Billing records

        // Settings Group
        $new_sidebar[] = $this->createGroupHeader('Settings');
        $new_sidebar[] = $this->migrateItem($old_sidebar[7] ?? [], [
            'routeClass' => 'clinic_settings',
            'link' => 'setting/holidays'
        ]);

        return $new_sidebar;
    }

    /**
     * Migrate doctor sidebar
     */
    private function migrateDoctorSidebar(array $old_sidebar): array
    {
        $new_sidebar = [];

        // Core Group
        $new_sidebar[] = $this->createGroupHeader('Main');
        $new_sidebar[] = $this->migrateItem($old_sidebar[0] ?? []);  // Dashboard
        $new_sidebar[] = $this->migrateItem($old_sidebar[1] ?? []);  // Appointments

        // Migrate Encounters parent item
        if (isset($old_sidebar[2]) && $old_sidebar[2]['type'] === 'parent') {
            $new_sidebar[] = $this->migrateParentItem(
                $old_sidebar[2],
                [
                    'routeClass' => 'parent',
                    'iconClass' => 'ph ph-clock-counter-clockwise'
                ]
            );
        }

        // User Management Group
        $new_sidebar[] = $this->createGroupHeader('Users');
        $new_sidebar[] = $this->migrateItem($old_sidebar[3] ?? []);  // Patients

        // Clinic Management Group
        $new_sidebar[] = $this->createGroupHeader('Clinic');
        $new_sidebar[] = $this->migrateItem($old_sidebar[4] ?? []);  // Services

        // Financial Group
        $new_sidebar[] = $this->createGroupHeader('Financial');
        $new_sidebar[] = $this->migrateItem($old_sidebar[5] ?? []);  // Billing records

        // Settings Group
        $new_sidebar[] = $this->createGroupHeader('Settings');
        $new_sidebar[] = $this->migrateItem($old_sidebar[6] ?? [], [
            'routeClass' => 'clinic_settings',
            'link' => 'setting/holidays'
        ]);

        return $new_sidebar;
    }

    /**
     * Migrate patient sidebar
     */
    private function migratePatientSidebar(array $old_sidebar): array
    {
        $new_sidebar = [];

        foreach ($old_sidebar as $item) {
            $migrated = $this->migrateItem($item);

            // Special handling for home item
            if (($item['routeClass'] ?? '') === 'home') {
                $migrated['link'] = get_home_url();
            }

            // Update encounter route
            if (($item['routeClass'] ?? '') === 'patient_encounter_list') {
                $migrated['link'] = '/encounter';
            }

            $new_sidebar[] = $migrated;
        }

        return $new_sidebar;
    }

    /**
     * Migrate a single sidebar item
     */
    private function migrateItem(array $item, array $overrides = []): array
    {
        $defaults = [
            'label' => $item['label'] ?? '',
            'type' => $item['type'] ?? 'route',
            'link' => $item['link'] ?? '',
            'iconClass' => $item['iconClass'] ?? '',
            'routeClass' => $item['routeClass'] ?? '',
            'childrens' => []
        ];

        // Convert icon classes to new icon system
        if (!empty($defaults['iconClass'])) {
            $defaults['iconClass'] = $this->migrateIconClass($defaults['iconClass']);
        }

        // Convert old link paths to new SPA routes
        if (!empty($defaults['link']) && $defaults['type'] !== 'href') {
            $defaults['link'] = $this->migrateLinkPath($defaults['link']);
        }
        // Apply any overrides
        return array_merge($defaults, $overrides);
    }

    /**
     * Migrate a parent sidebar item
     */
    private function migrateParentItem(array $parent, array $overrides = []): array
    {
        $migrated = $this->migrateItem($parent, $overrides);

        // Migrate children
        if (isset($parent['childrens']) && is_array($parent['childrens'])) {
            foreach ($parent['childrens'] as $child) {
                $migrated['childrens'][] = $this->migrateItem($child);
            }
        }

        return $migrated;
    }

    /**
     * Migrate old icon classes to new icon system
     */
    private function migrateIconClass(string $old_class): string
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

        return $mapping[$old_class] ?? $old_class;
    }

    /**
     * Migrate old link paths to new SPA routes
     */
    private function migrateLinkPath(string $old_link): string
    {
        $mapping = [
            // Based on actual old database values
            'dashboard' => '/dashboard',
            'appointment-list.index' => '/appointments',
            'encounter' => '/encounter',
            'encounter-list' => '/encounter',
            'encounter-template' => '/encounter-template',
            'clinic' => '/clinic',
            'patient' => '/patient',
            'doctor' => '/doctor',
            'receptionist' => '/receptionist',
            'service' => '/service',
            'doctor-session.create' => '/doctor-session',
            'tax' => '/tax',
            'billings' => '/billings',
            'clinic-revenue-reports' => '/clinic-revenue-reports',
            'setting.general-setting' => '/setting/general-setting',
            'get_help' => '/get-help',
            'get_pro' => '/get-pro',
        ];

        return $mapping[$old_link] ?? $old_link;
    }

}
