<?php

/**
 * @package  KiviCarePlugin
 */

namespace App\baseClasses;
use WP_User;
use WP_User_Query;

class KCBase {

	public $plugin_path;

	public $nameSpace;

	public $plugin_url;

	public $plugin;

	public $dbConfig;

    public $pluginPrefix;

	public $doctorRole;

    public $patientRole;

    public $pluginVersion ;

    public $permission_message;

	public static $instance;

	public function __construct() {

        $this->permission_message = esc_html__('You do not have permission to access', 'kc-lang');

        add_action('init',function (){
            require_once( ABSPATH . "wp-includes/pluggable.php" );

            if (!function_exists('get_plugins')) {
                include_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $this->plugin_path = plugin_dir_path( dirname( __FILE__, 2 ) );
            $this->plugin_url  = plugin_dir_url( dirname( __FILE__, 2 ) );
        });

		if  (defined( 'KIVI_CARE_NAMESPACE' )) {
			$this->nameSpace    = KIVI_CARE_NAMESPACE;
		}

		if  (defined( 'KIVI_CARE_PREFIX' )) {
			$this->pluginPrefix    = KIVI_CARE_PREFIX;
			$this->doctorRole  = KIVI_CARE_PREFIX . "doctor";
			$this->patientRole = KIVI_CARE_PREFIX . "patient";
		}

		$this->plugin = plugin_basename( dirname( __FILE__, 3 ) ) . '/kivicare-clinic-management-system.php';

		$this->dbConfig = [
			'user' => DB_USER,
			'pass' => DB_PASSWORD,
			'db'   => DB_NAME,
			'host' => DB_HOST
		];

	}

	public static function get_instance()  {
		if(!isset(self::$instance) ){
			self::$instance= new self();
		}
		return self::$instance;
	}

	public function get_namespace() {
		return $this->nameSpace;
	}

	public function getPrefix() {
		return KIVI_CARE_PREFIX;
	}

	public function getSetupSteps() {
	    return KIVI_CARE_PREFIX . 'setup_steps';
    }

	public function getClinicAdminRole() {
		return KIVI_CARE_PREFIX . "clinic_admin";
	}

	public function getDoctorRole() {
		return KIVI_CARE_PREFIX . "doctor";
	}

	public function getPatientRole() {
		return KIVI_CARE_PREFIX . "patient";
	}
	public function KCGetRoles()
	{
		return [$this->getClinicAdminRole(),$this->getReceptionistRole(), $this->getDoctorRole(), $this->getPatientRole()];
	}

	public function teleMedAddOnName() {

		$plugins = get_plugins();
		
        foreach ($plugins as $key => $value) {
            if($value['TextDomain'] === 'kiviCare-telemed-addon') {
                return $key ;
            }
        }
		return 'kivicare-telemed-addon/kivicare-telemed-addon.php';
	}
	public function kiviCareProOnName() {
		
        $plugins = get_plugins();

        foreach ($plugins as $key => $value) {
            if($value['TextDomain'] === 'kiviCare-clinic-&-patient-management-system-pro') {
                return $key ;
            }
        }
	}
    public function isTeleMedActive () {
		$plugins = get_plugins();
        foreach ($plugins as $key => $value) {
			if($value['TextDomain'] === 'kiviCare-telemed-addon') {
				return (is_plugin_active($key) ? true : false);
			}
		}
	}

	public function isKcProActivated()
	{
		if (!function_exists('get_plugins')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

        foreach ($plugins as $key => $value) {
            if($value['TextDomain'] === 'kiviCare-clinic-&-patient-management-system-pro') {
				if(is_plugin_active($key)) {
					return	true ;
				}
            }
        }

		return false ;
	}
	
	public function isKiviProActive () {
		$plugins = get_plugins();
        foreach ($plugins as $key => $value) {
            if($value['TextDomain'] === 'kiviCare-clinic-&-patient-management-system-pro') {
                return true ;
            }
        }
        return false ;
	}

	public function isPaymentAvailable() {
		return true;
	}

	public function getPluginVersion () {
        return KIVI_CARE_VERSION;
    }

	public function getReceptionistRole() {
		return KIVI_CARE_PREFIX . "receptionist";
	}

	public function getSetupConfig() {
		return KIVI_CARE_PREFIX . 'setup_config';
	}

	public function getPluginPrefix() {
		return $this->pluginPrefix;
	}

	public function getLoginUserRole()
	{
		$user_id = get_current_user_id();
		$userObj = new WP_User($user_id);

		$kc_user_roles =	array_intersect(array_merge(
			['administrator'],
			$this->KCGetRoles()
		), $userObj->roles);
		$role = array_shift($kc_user_roles);
		return (!empty($role) ? $role  : '');
	}

	public function getUserRoleById($user_id){

		$userObj = new WP_User($user_id);

		$kc_user_roles =	array_intersect(array_merge(
			['administrator'],
			$this->KCGetRoles()
		), $userObj->roles);
		$role = array_shift($kc_user_roles);
		return (!empty($role) ? $role  : '');
	}
	public function getAllActivePlugin(){
		$activePlugins = get_option('active_plugins');

		$activated_plugins=array();
		foreach (get_plugins() as $key => $value) {
			if($value['TextDomain'] === 'kiviCare-clinic-&-patient-management-system-pro'){
				if(in_array($key,$activePlugins)){
					$activated_plugins = array(
						'text-domain'=> $key
					);
				}
			}
		}
		
		if($activated_plugins){
			return $activated_plugins['text-domain'];
		}else{
			return [];
		}
	}
    public function doctor_enable_calender($doctor_id, $verify_token = false){
        $enable = get_user_meta($doctor_id,'google_cal_access_token');
        if(count($enable) > 0){
            return true;
        }else{
            return false;
        }
    }

    public function getUserData($args,$search_condition){
        $patient_data_query = new WP_User_Query;
        $patient_data_query->prepare_query($args );
        $patient_data_query->query_where .= $search_condition;
        $patient_data_query->query();
        return [
            "total" =>$patient_data_query->get_total(),
            'list' => collect($patient_data_query->get_results())
        ];

    }

    /**
     * Checks if the given user has a Kivicare role or is an administrator.
     *
     * @param int $user_id The ID of the user to check. Defaults to 0, which means the current logged-in user.
     * @return bool True if the user has a Kivicare role or is an administrator, false otherwise.
     */
    public function userHasKivicareRole( int $user_id = 0): bool{
        // If no user ID is provided or the ID is invalid, get the role of the current logged-in user
        $current_user_role = $user_id > 0 ? $this->getUserRoleById($user_id) : $this->getLoginUserRole();

        // Get all Kivicare roles
        $all_roles = $this->KCGetRoles();

        // Add the 'administrator' role to the list of Kivicare roles
        $all_roles[] = 'administrator';

        // Check if the current user's role is in the list of Kivicare roles or is 'administrator'
        return in_array($current_user_role, $all_roles);
    }
}

