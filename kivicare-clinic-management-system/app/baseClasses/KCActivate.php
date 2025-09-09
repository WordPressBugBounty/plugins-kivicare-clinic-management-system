<?php

namespace App\baseClasses;

use App\controllers\KCPaymentController;
use App\models\KCAppointment;
use App\models\KCClinic;
use App\models\KCDoctorClinicMapping;
use App\models\KCServiceDoctorMapping;
use WP_Error;
use WP_Query;
use function Clue\StreamFilter\fun;


class KCActivate extends KCBase {

    private $request;
    private $db;

	public static function activate() {

		// Migrate database and other data...
		self::migrateDatabase();

		// following function call is only for development purpose remove in production mode.
		(new self())->migratePermissions();
//		(new self())->addDefaultPosts();
		(new self())->addDefaultOptions();
		(new self())->addDefaultModuleConfig();
		(new self())->addAdministratorPermission();
		(new self())->tableAlterFiled();
        (new self())->createShortcodePage();
        (new self())->migrateSidebar();
        (new self())->addNewPosts();
	}
	public function init() {

        $version = KIVI_CARE_VERSION;
        $prefix = KIVI_CARE_PREFIX;
        $config_options = kc_get_multiple_option("
        '{$prefix}telemed_enable_added_to_service_table',
        '{$prefix}doctor_service_duration_column',
        '{$prefix}new-permissions-migrate{$version}',
        '{$prefix}permissions-migrate-1.0{$version}',
        '{$prefix}lang_option',
        '{$prefix}widgetSetting',
        '{$prefix}widget_order_list',
        '{$prefix}local_payment_status',
        '{$prefix}patient_mapping_table_1',
        '{$prefix}patient_review_table_1',
        '{$prefix}patient_mapping_table_1',
        '{$prefix}service_mapping_new_column',
        '{$prefix}payment_appointment_table_insert_1',
        '{$prefix}copyrightText_save',
        'is_lang_version_2.3.7',
        'kivicare_version_2_3_0',
        '{$prefix}logout_redirect',
        'is_kivicarepro_upgrade_lang',
        '{$prefix}email_appointment_reminder',
        '{$prefix}showServiceImageFirst',
        '{$prefix}tax_table_migrate',
        '{$prefix}custom_notification_dynamic_keys_update',
        '{$prefix}custom_form_table'
        ");
        
		add_action( 'set_logged_in_cookie', function( $logged_in_cookie ){
			$_COOKIE[LOGGED_IN_COOKIE] = $logged_in_cookie;
		} );

        //hook call after all plugins loaded
        add_action( 'init', function (){
            //translate language
            load_plugin_textdomain( 'kc-lang', false, dirname( KIVI_CARE_BASE_NAME ) . '/languages' );
        });

        //security hook
        add_filter( 'xmlrpc_methods', function ( $methods ) {
            return array();
        } );

        //add user_status parameter in get_user function
		add_action( 'pre_user_query', function ( $query ) {
			if ( isset( $query->query_vars['user_status'] ) ) {
				$query->query_where .= " AND user_status = " . (int) $query->query_vars['user_status'];
			}
		} );

        add_filter( 'user_search_columns', function( $search_columns ) {
            $search_columns[] = 'display_name';
            $search_columns[] = 'user_status';
            return $search_columns;
        } );

        add_action('pre_get_posts', array($this, 'wpb_remove_products_from_shop_listing'), 90, 1);

        add_action('init',function () use ($config_options){
            if(empty($config_options[KIVI_CARE_PREFIX.'telemed_enable_added_to_service_table'])){
                global $wpdb;                
                kcUpdateFields($wpdb->prefix.'kc_service_doctor_mapping',['telemed_service' => 'varchar(10)']);
                $telemed_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}kc_services WHERE LOWER(name) = 'telemed'");
                $all_service = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}kc_service_doctor_mapping ");
                foreach ($all_service as $service){
                    $wpdb->update($wpdb->prefix.'kc_service_doctor_mapping',['telemed_service' => (int)$service->service_id == (int)$telemed_id ? 'yes' : 'no'],['id' => $service->id]);
                }
                update_option(KIVI_CARE_PREFIX.'telemed_enable_added_to_service_table','yes');
            }
            $this->migrateDoctorServiceTableData();
            if(empty($config_options[KIVI_CARE_PREFIX.'doctor_service_duration_column'])){
                global $wpdb;
                kcUpdateFields($wpdb->prefix.'kc_service_doctor_mapping',['duration' => 'int(5)']);
                update_option(KIVI_CARE_PREFIX.'doctor_service_duration_column','yes');
            }
        });

        add_action('admin_init', function () use ($config_options){
            $allRole = ['administrator',$this->getPatientRole(),
                $this->getDoctorRole(),
                $this->getReceptionistRole(),
                $this->getClinicAdminRole()];           
            
      
            if (empty($config_options[KIVI_CARE_PREFIX . 'new-permissions-migrate' . KIVI_CARE_VERSION])) {
 
                $editable_roles = get_editable_roles();

                if(!empty($editable_roles) && is_array($editable_roles)){
                
                    $editable_roles = array_keys($editable_roles);
                    $containsSearch = count(array_intersect($allRole, $editable_roles)) === count($allRole);

                    if($containsSearch){
                        foreach ($allRole as $role) {
                            $subscriber = get_role($role);
                            $subscriber->add_cap('upload_files',kcGetUserDefaultPermission($subscriber,'upload_files',true));
                            $subscriber->add_cap('edit_published_pages',kcGetUserDefaultPermission($subscriber,'edit_published_pages',true));
                            $subscriber->add_cap(KIVI_CARE_PREFIX . 'home_page', kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'home_page',$role === $this->getPatientRole()));
                            $subscriber->add_cap(KIVI_CARE_PREFIX . 'patient_report',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'patient_report',true));
                            $subscriber->add_cap(KIVI_CARE_PREFIX . 'patient_report_add',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'patient_report_add',$role !== $this->getPatientRole()));
                            $subscriber->add_cap(KIVI_CARE_PREFIX . 'patient_report_view',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'patient_report_view',true));
                            $subscriber->add_cap(KIVI_CARE_PREFIX . 'patient_report_delete' ,kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'patient_report_delete', $role !== $this->getPatientRole()));
                            $subscriber->add_cap(KIVI_CARE_PREFIX . 'patient_clinic', kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'patient_clinic', $role === $this->getPatientRole()));
                            $subscriber->add_cap(KIVI_CARE_PREFIX . 'appointment_export',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'appointment_export',true));
                            $subscriber->add_cap(KIVI_CARE_PREFIX . 'patient_encounter_export',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'patient_encounter_export',true));
                            $subscriber->add_cap(KIVI_CARE_PREFIX . 'prescription_export',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'prescription_export',true));
                            $subscriber->add_cap(KIVI_CARE_PREFIX . 'patient_bill_export',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'patient_bill_export',true));

                        
                            if(in_array($role,['administrator', $this->getClinicAdminRole()])){
                                if($role === 'administrator'){
                                    $subscriber->add_cap(KIVI_CARE_PREFIX . 'clinic_export',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'clinic_export',true));
                                }
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'receptionist_export',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'receptionist_export',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'custom_field_export',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'custom_field_export',true));

                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'tax_edit',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'tax_edit',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'tax_add',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'tax_add',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'tax_list',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'tax_list',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'tax_delete',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'tax_delete',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'tax_export',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'tax_export',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'custom_form_add',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'custom_form_add',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'custom_form_edit',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'custom_form_edit',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'custom_form_delete',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'custom_form_delete',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'custom_form_list',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'custom_form_list',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'receptionist_resend_credential',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'receptionist_resend_credential',true));                     
                            }

                            if(in_array($role,['administrator', $this->getClinicAdminRole(),$this->getReceptionistRole()])){
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'doctor_export',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'doctor_export',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'dashboard_total_patient',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'dashboard_total_patient',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'dashboard_total_doctor',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'dashboard_total_doctor',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'dashboard_total_appointment',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'dashboard_total_appointment',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'dashboard_total_revenue',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'dashboard_total_revenue',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'patient_profile',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'patient_profile',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'clinic_resend_credential',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'clinic_resend_credential',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'doctor_resend_credential',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'doctor_resend_credential',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'patient_resend_credential',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'patient_resend_credential',true));
                            }

                            if($role === $this->getDoctorRole()){
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'dashboard_total_patient',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'dashboard_total_patient',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'dashboard_total_appointment',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'dashboard_total_appointment',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'dashboard_total_today_appointment',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'dashboard_total_today_appointment',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'dashboard_total_service',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'dashboard_total_service',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'patient_profile',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'patient_profile',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'patient_resend_credential',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'patient_resend_credential',true));
                            }

                            if($role !== $this->getPatientRole()){
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'doctor_session_add',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'doctor_session_add',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'doctor_session_edit',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'doctor_session_edit',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'doctor_session_delete',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'doctor_session_delete',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'doctor_session_list',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'doctor_session_list',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'doctor_session_export',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'doctor_session_export',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'clinic_schedule_add',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'clinic_schedule_add',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'clinic_schedule_edit',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'clinic_schedule_edit',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'clinic_schedule_delete',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'clinic_schedule_delete',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'patient_export',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'patient_export',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'service_export',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'service_export',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'clinic_schedule_export',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'clinic_schedule_export',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'static_data_export',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'static_data_export',true));
                            }

                            $subscriber->add_cap(KIVI_CARE_PREFIX . 'static_data_add',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'static_data_add',$role !== $this->getPatientRole()));
                            
                            if (in_array($role, ['administrator', $this->getDoctorRole(), $this->getReceptionistRole(), $this->getClinicAdminRole()])) {
                                $subscriber->remove_cap(KIVI_CARE_PREFIX . 'home_page');
                                $subscriber->remove_cap(KIVI_CARE_PREFIX . 'patient_review_add');
                                $subscriber->remove_cap(KIVI_CARE_PREFIX . 'patient_review_edit');
                                $subscriber->remove_cap(KIVI_CARE_PREFIX . 'patient_review_delete');
                                $subscriber->remove_cap(KIVI_CARE_PREFIX . 'patient_bill_delete');
                            }
                            if (in_array($role, [$this->getPatientRole()])) {
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'appointment_cancel',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'appointment_cancel',true));
                                $subscriber->remove_cap(KIVI_CARE_PREFIX . 'appointment_delete');
                                $subscriber->remove_cap(KIVI_CARE_PREFIX . 'patient_bill_edit');
                            }
                            
                            do_action( 'kivicare_new_permissions_migrate', $role, $subscriber );

                        }
                        
                        update_option(KIVI_CARE_PREFIX . 'new-permissions-migrate' . KIVI_CARE_VERSION, 'yes');
                    }
                }
            }

            if (empty($config_options[KIVI_CARE_PREFIX . 'permissions-migrate-1.0' . KIVI_CARE_VERSION])) {
                $editable_roles = get_editable_roles();
                if(!empty($editable_roles) && is_array($editable_roles)){
                    $editable_roles = array_keys($editable_roles);
                    $containsSearch = count(array_intersect($allRole, $editable_roles)) === count($allRole);
                    if($containsSearch){
                        foreach ($allRole as $role) {
                            $subscriber = get_role($role);
                            if($role === $this->getPatientRole()){
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'patient_report_delete',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'patient_report_delete',true));
                                $subscriber->add_cap(KIVI_CARE_PREFIX . 'patient_report_edit',kcGetUserDefaultPermission($subscriber,KIVI_CARE_PREFIX .'patient_report_edit',true));
                            }
                        }
                        update_option(KIVI_CARE_PREFIX . 'permissions-migrate-1.0' . KIVI_CARE_VERSION, 'yes');
                    }
                }
            }
        });

        add_filter( 'ajax_query_attachments_args',function( $query ) {
            if( in_array($this->getLoginUserRole(), [$this->getPatientRole(),
                $this->getDoctorRole(),
                $this->getReceptionistRole(),
                $this->getClinicAdminRole()]) ) {
                $user_id = get_current_user_id();
                if ( $user_id ) {
                    $query['author'] = $user_id;
                }
                return $query;
            }
            return $query;
        } );

		// Check language options is added in option table (deprecated language)
		if(empty($config_options[KIVI_CARE_PREFIX.'lang_option'])) {

            //condition to check user is new user or exists user
            update_option(KIVI_CARE_PREFIX.'new_user',1);

			$lang_option = [
				'lang_option' => [
					[
						'label' => 'English',
						'id' => 'en'
					],
					[
						'label' => 'Arabic',
						'id' => 'ar'
					],
					[
						'label' => 'Greek',
						'id' => 'gr'
					],
					[
						'label' => 'Franch',
						'id' => 'fr'
					],
					[
						'label' => 'Hindi',
						'id' => 'hi'
					]
				],
			];

            //add language option in options table
			add_option(KIVI_CARE_PREFIX.'lang_option', json_encode($lang_option));

		}

        //add default sidebar data
        add_action('init',function (){
            $this->sidebarData();
        });

        //new appointment shortcode default value add in options table
		if(empty($config_options[KIVI_CARE_PREFIX.'widgetSetting'])){
		    $this->widgetSettingLoad();
            $config_options[KIVI_CARE_PREFIX.'widgetSetting'] = get_option(KIVI_CARE_PREFIX.'widgetSetting');
        }

        //new appointment shortcode sidebar sequence order default value add in options table
        if(empty($config_options[KIVI_CARE_PREFIX.'widget_order_list'])){
            update_option(KIVI_CARE_PREFIX.'widget_order_list',kcDefaultAppointmentWidgetOrder());
        }

        //add local/offline payment value default enable in options table
        if(empty($config_options[KIVI_CARE_PREFIX.'local_payment_status'])){
            update_option(KIVI_CARE_PREFIX.'local_payment_status', 'on');
        }

        //enable loco translate if user is new and hide deprecated language module from dashboard
        if(kcToCheckUserIsNew()){
            update_option(KIVI_CARE_PREFIX.'locoTranslateState',1);
        }

        //add patient clinic table if not available
        if(empty($config_options[KIVI_CARE_PREFIX.'patient_mapping_table_1'])){
            update_option(KIVI_CARE_PREFIX.'patient_mapping_table_1','yes');
            require_once ABSPATH . '/wp-admin/includes/upgrade.php';
            require KIVI_CARE_DIR . 'app/database/kc-patient-clinic-mapping-db.php';
        }

        //add patient clinic table if not available
        if(empty($config_options[KIVI_CARE_PREFIX.'patient_review_table_1'])){
            update_option(KIVI_CARE_PREFIX.'patient_review_table_1','yes');
            require_once ABSPATH . '/wp-admin/includes/upgrade.php';
            require KIVI_CARE_DIR . 'app/database/kc-patient-review-db.php';
        }

        add_action('init',function() use ($config_options){
            if(empty($config_options[KIVI_CARE_PREFIX.'patient_mapping_table_1'])){
                global $wpdb;
                kcUpdateFields($wpdb->prefix.'kc_patient_review',['review_description' => 'longtext',
                    'created_at' => 'datetime NOT NULL DEFAULT current_timestamp()',
                    'updated_at' => 'datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()']);
                update_option(KIVI_CARE_PREFIX.'patient_mapping_table_1','yes');
            }
            if(empty($config_options[KIVI_CARE_PREFIX.'service_mapping_new_column'])){
                global $wpdb;
                //add new column in table
                kcUpdateFields($wpdb->prefix . 'kc_service_doctor_mapping',[
                        'service_name_alias' => 'varchar(191)',
                        'multiple' => 'varchar(191)',
                        'image' => 'bigint']
                );
                update_option(KIVI_CARE_PREFIX.'service_mapping_new_column','yes');
            }
        });

        //add PayPal payment method  and appointment mappings table
        if(empty($config_options[KIVI_CARE_PREFIX.'payment_appointment_table_insert_1'])){
            update_option(KIVI_CARE_PREFIX.'payment_appointment_table_insert_1','yes');
            require_once ABSPATH . '/wp-admin/includes/upgrade.php';
            require KIVI_CARE_DIR.'app/database/kc-payments-appointment-mapping-db.php';
        }

        //add default copyright text of dashboard
        if(empty($config_options[KIVI_CARE_PREFIX.'copyrightText_save'])){
            update_option(KIVI_CARE_PREFIX.'copyrightText_save','yes');
            update_option(KIVI_CARE_PREFIX.'copyrightText',__('KiviCare - Clinic and Patient Management System (EHR)','kc-lang'));
        }

        // add new language translation key in 2.2.8 (deprecated)
        if ( is_admin() && empty( $config_options['is_lang_version_2.3.7'])) {
            add_option('is_lang_version_2.3.7', 1);
            $this->getLangCopy();
            $this->mergeJson();
        }

        // kivicare version 2.3.0
        if ( is_admin() && empty( $config_options['kivicare_version_2_3_0'])) {
            add_option('kivicare_version_2_3_0', 1);
            update_option(KIVI_CARE_PREFIX.'request_helper_status', 'off');
        }

        //add default logout redirect page url in options table
        if(empty( $config_options[KIVI_CARE_PREFIX.'logout_redirect'])){
            $logout_url = wp_login_url();
            update_option(KIVI_CARE_PREFIX . 'logout_redirect', [
                "clinic_admin" => $logout_url,
                "patient" => $logout_url,
                "receptionist" => $logout_url,
                "doctor" => $logout_url
            ]);
        }

        // (new self())->versionUpgradePatches();

        //check if request is kivicare dashboard
		if (isset($_REQUEST['page']) && $_REQUEST['page'] === "dashboard") {
            $this->dashboardPage();
		}

		// Enqueue/register Front-end assets
		add_action( 'wp_enqueue_scripts', array($this,'enqueueFrontScripts'));

        // Append meta tags to header...
		add_action( 'wp_head', array($this,'appendToHeader') );
		add_action( 'admin_head', array($this,'appendToHeader') );

        // Enable route classes
        (new KCRoutesHandler('App\\controllers\\'))->init();

        //call shortcode init class
        ( new WidgetHandler )->init();

		(new self())->load_plugin();
		
		//hook to add plugin  sidebar menu in WordPress dashboard
		add_action( 'admin_menu', array($this, 'adminMenu'));

        //hook to remove WordPress icon from WordPress dashboard
        add_action( 'wp_before_admin_bar_render', array($this, 'kc_remove_logo_wp_admin'), 0 );

        //add kivicare widget category in gutenberg
        add_filter( 'block_categories_all', array($this,'addBlockCategories'), 10, 2 );

        //add shortcode gutenberg widget js/css assets
        add_action( 'enqueue_block_editor_assets', array($this, 'kivicareShortcodeWidgetBlock'));

		// hook to set email header...
		add_filter( 'wp_mail_content_type', function() {
            return 'text/html';
        } );

		// hook to check login is allowed by admin  if not  return error response
		add_filter( 'authenticate', array($this, 'validateAuthUser'), 20, 3 );

		// Redirect user to dashboard after login
		add_filter( 'login_redirect', array($this, 'redirectUserToDashboard'), 10, 3 );

        //hook to restrict woocommerce agent
		add_filter( 'woocommerce_prevent_admin_access', array($this, 'kivicare_agent_admin_access') , 20, 1 );

        //hook to enqueue wordpress login page script...
		add_action( 'login_enqueue_scripts', array($this, 'loginPageStyles'), 11 );

		// Hide admin bar...
		add_action('after_setup_theme', array($this, 'removeAdminBar'));

        //update plugin language file (deprecated language)
		if ( is_admin() && empty( $config_options['is_kivicarepro_upgrade_lang'])) {
			add_option('is_kivicarepro_upgrade_lang', 1);
			$this->updateLangFile();
		}

        //kivicare elementor widget register and add doctor and clinic widget
        add_action( 'elementor/elements/categories_registered', [$this,'kcElementerCategoryRegistered'] );
        add_action( 'elementor/widgets/widgets_registered', [$this,'kcAddElementorWidget']);

        //add patient appointment reminder cron job
        if (!empty($config_options[KIVI_CARE_PREFIX . 'email_appointment_reminder'])) {
            $appointment_reminder_data = unserialize($config_options[KIVI_CARE_PREFIX . 'email_appointment_reminder']);
            if( isset($appointment_reminder_data['time']) && ((isset($appointment_reminder_data['status'])
            && $appointment_reminder_data['status'] == 'on') || (isset($appointment_reminder_data['sms_status'])
            && $appointment_reminder_data['sms_status'] =='on') || (isset($appointment_reminder_data['whatapp_status'])
            && $appointment_reminder_data['whatapp_status'] == 'on'))) {
                kcAddCronJob('kivicare_patient_appointment_reminder','patientAndDoctorAppointmentReminder');
            }
        }

        //hook to change old langauge translate to loco translate from language error page (resources/views/kc_notice.php)
        add_action( 'template_redirect', [ $this, 'kcEnableLocoTranslate' ] );

        //hook to register plugin custom posttype (sms/email/calendar and googlemeet)
        add_action('init',[ $this, 'addCustomPostType' ]);

        //hook to change WordPress login page icon
        if(kcWordpressLogostatusAndImage('status')){
            add_action( 'login_enqueue_scripts', [$this,'kcChangeWordpressLogo'] );
        }

        // hook to check login is valid user  if not  return error response
        add_filter( 'wp_authenticate_user', [$this,'kccheckActiveUser'],10,2);

        // Add a post display state for special kivicare pages.
        add_filter( 'display_post_states', array( $this, 'add_display_post_states' ), 10, 2 );

        add_action('elementor/editor/before_enqueue_scripts', function (){
            wp_enqueue_style( 'kc_font_awesome', $this->plugin_url . 'assets/css/font-awesome-all.min.css', array(), KIVI_CARE_VERSION ,false);
        });

        if(!isKiviCareProActive()){
            add_filter( "plugin_action_links_".KIVI_CARE_BASE_NAME, function ( $links ) {
                // Build and escape the URL.
                $url = esc_url( add_query_arg(
                    'page',
                    'kc_go_pro_settings_link',
                    'https://codecanyon.net/item/kivicare-pro-clinic-patient-management-system-ehr-addon/30690654'
                ) );
                // Create the link.
                $settings_link = "<a href='".esc_url($url)."' class='kivicare-plugin-gopro' target='_blank' style='color: #93003c;'><b>".esc_html__('Go Pro','kc-lang')."</b></a>";
                // Adds the link to the end of the array.
                $links[] = $settings_link;
                return $links;
            } );
        }

        if(empty($config_options[KIVI_CARE_PREFIX.'showServiceImageFirst'])){
            if(empty($config_options[KIVI_CARE_PREFIX.'widgetSetting'])){
                // $widgetSettingTest = json_decode($config_options[KIVI_CARE_PREFIX.'widgetSetting'], true)??[];
                $widgetSettingTest = [];
                $showServiceImage1 = ['showServiceImage'=>1];
                $KCfinalArray = $widgetSettingTest + $showServiceImage1;
    
                update_option(KIVI_CARE_PREFIX.'widgetSetting', json_encode($KCfinalArray));
                update_option(KIVI_CARE_PREFIX.'showServiceImageFirst', 'added');
            }
        }

        $this->woocommerce_shop_order_add_column();

        //add patient clinic table if not available
        if(empty($config_options[KIVI_CARE_PREFIX.'tax_table_migrate'])){
            require_once ABSPATH . '/wp-admin/includes/upgrade.php';
            require KIVI_CARE_DIR . 'app/database/kc-tax-db.php';
            require KIVI_CARE_DIR . 'app/database/kc-tax-data-db.php';
            update_option(KIVI_CARE_PREFIX.'tax_table_migrate','yes');
        }

        if(empty($config_options[KIVI_CARE_PREFIX.'custom_notification_dynamic_keys_update'])){
            delete_option('custom_notification_dynamic_keys');
            update_option(KIVI_CARE_PREFIX.'custom_notification_dynamic_keys_update', [
                ['key' => '[api_key]', 'value' => ''],
                ['key' => '[sender_number]', 'value' => ''],
                ['key' => '[receiver_number]', 'value' => '[dynamic_receiver_number]'],
                ['key' => '[sms_body]', 'value' => '[dynamic_sms_body]']
            ]);
        }

        if(empty($config_options[KIVI_CARE_PREFIX.'custom_form_table'])){
            require_once ABSPATH . '/wp-admin/includes/upgrade.php';
            require KIVI_CARE_DIR . 'app/database/kc-custom-form-db.php';
            require KIVI_CARE_DIR . 'app/database/kc-custom-form-data-db.php';
            update_option(KIVI_CARE_PREFIX.'custom_form_table','yes');
        }
        
        add_action('admin_notices', [$this, 'iqonic_sale_banner_notice']);
        add_action('wp_ajax_iq_dismiss_notice', [$this, 'iq_dismiss_notice']);

        add_action('wp_ajax_kc_attachment',function(){

            if ( ! kcCheckPermission( 'patient_report_view' ) ) {
                wp_send_json(kcUnauthorizeAccessResponse(403));
            }
            $c = base64_decode($_REQUEST['key']);
            $ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
            $iv = substr($c, 0, $ivlen);
            $hmac = substr($c, $ivlen, $sha2len=32);
            $ciphertext_raw = substr($c, $ivlen+$sha2len);
            $attachment_id = openssl_decrypt($ciphertext_raw, $cipher, AUTH_KEY, $options=OPENSSL_RAW_DATA, $iv);
            $calcmac = hash_hmac('sha256', $ciphertext_raw, AUTH_KEY, $as_binary = true);
            if (hash_equals($hmac, $calcmac)) // timing attack safe comparison
            {
                if ($file_url = get_attached_file( (int)$attachment_id )) {

                    header('Content-Description: File Transfer');
                    header("Content-type:".get_post_mime_type($attachment_id ));
                    header('Content-Disposition: filename="'.basename($file_url).'"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($file_url));


                    while (ob_get_level()) {
                        ob_end_clean();
                    }
                    readfile($file_url);
                    exit();
                } else {
                    echo "Invalid attachment or attachment not found.";
                }
            }
            die;
        });

        add_filter( 'woocommerce_login_redirect', [$this,'custom_redirect_after_password_reset'], 20, 2 );
    }
    
    public function custom_redirect_after_password_reset( $redirect_to, $user ) {
        // Check if the user is coming from the password reset form
        if (in_array( KIVI_CARE_PREFIX . "patient", $user->roles )) {
            $redirect_to = kcGetLogoinRedirectSetting('patient');
            return $redirect_to;
        }
    }

    public function wpb_remove_products_from_shop_listing(WP_Query $query)
    {
        if (is_admin()) {
            return $query;
        }
        
        if ($query->get('post_type') !== 'product') {
            return $query;
        }

        global $wpdb;
        //  phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery 
        $kivicare_products = $wpdb->get_col("SELECT DISTINCT `posts`.`ID` FROM `{$wpdb->posts}` AS `posts` 
                INNER JOIN `{$wpdb->postmeta}` AS `meta` ON ( `posts`.`ID` = `meta`.`post_id` AND `meta`.`meta_key` = 'kivicare_service_id' )
                WHERE `posts`.`post_type` = 'product'
                AND `posts`.`post_status` = 'publish'");

        if (!$kivicare_products) {
            return $query;
        }

        $post__no_in = (array) $query->get('post__not_in');

        $query->set('post__not_in', array_merge($post__no_in, $kivicare_products));

        return $query;
    }

    public function widgetSettingLoad(){

        $data  = [
            'showClinicImage' => '1',
            'showClinicAddress' => '1',
            'clinicContactDetails' => [
                'id' => '3',
                'label' => 'Show email address'
            ],
            'showDoctorImage' => '1',
            'showDoctorExperience' => '1',
            'doctorContactDetails' => [
                'id' => '3',
                'label' => 'Show email address'
            ],
            'showDoctorSpeciality' => '1',
            'showDoctorDegree' => '1',
            'showDoctorRating' => '1',
            'showServiceImage' => '1',
            'showServicetype' => '1',
            'showServicePrice' => '1',
            'showServiceDuration' => '1',
            'primaryColor' => '#7093e5',
            'primaryHoverColor' => '#4367b9',
            'secondaryColor' => '#f68685',
            'secondaryHoverColor' => '#df504e',
            'widget_print' => '1',
            'afterWoocommerceRedirect' => '1',
        ];

        update_option(KIVI_CARE_PREFIX.'widgetSetting', json_encode($data));
    }

    public function dashboardPage(){
        //filter to update browser tab title
        add_filter( 'admin_title', function ( $admin_title, $title ) {
            return get_bloginfo('name');
        }, 10, 2 );

        // Enqueue Admin-side assets...
        add_action( 'admin_enqueue_scripts', array($this,'enqueueStyles'));
        add_action( 'admin_enqueue_scripts', array($this,'enqueueScripts'));
        add_action( 'admin_enqueue_scripts', function (){
            global $wp_scripts, $wp_styles;
            // Loop through the registered scripts
            foreach ( $wp_scripts->registered as $handle => $script ) {
                // Check if the script was registered by another plugin
                if ( strpos( $script->src??"", '/plugins/' ) !== false && strpos( $script->src??"", '/kivicare-clinic-management-system/' ) === false ) {
                    // Unregister the script
                    if(!in_array($handle,['kcrp_razorpay_payment','kivicare_razorpay_checkout'])){
                        wp_deregister_script( $handle );
                    }
                }
            }

            // Loop through the registered styles
            foreach ( $wp_styles->registered as $handle => $style ) {
                // Check if the style was registered by another plugin
                if ( strpos( $style->src, '/plugins/' ) !== false && strpos( $style->src, '/kivicare-clinic-management-system/' ) === false ) {
                    // Unregister the style
                    wp_deregister_style( $handle );
                }
            }
        },PHP_INT_MAX);

        // hook to remove hide sidebar and top-bar...
        add_action('admin_head', array($this, 'hideSideBar'));

        //language translate object pass by window variable
        add_action( 'admin_head', array($this,'appendLanguageInHead') );

        // Increase the heartbeat interval to 60 seconds
        add_filter('heartbeat_settings', function ($settings) {
            $settings['interval'] = 60;
            return $settings;
        });
    }

	public function load_plugin () {
		if ( is_admin() && !get_option( 'is_upgrade_2.1.5')) {
            add_option('is_upgrade_2.1.5', 0);
            require_once ABSPATH . '/wp-admin/includes/upgrade.php';
			require KIVI_CARE_DIR . 'app/database/kc-custom-field-data-db.php';
		}
	}
	public function tableAlterFiled(){
        global $wpdb;
        $table_patient_encounter = $wpdb->prefix . 'kc_patient_encounters';
        $new_fields = [
            'clinic_id' => 'bigint',
        ];

        //add new column in existing table
        kcUpdateFields($table_patient_encounter,$new_fields);

        $table_billing = $wpdb->prefix . 'kc_bills';

        //add new column in existing table
        kcUpdateFields($table_billing,$new_fields);

        $table_clinics = $wpdb->prefix . 'kc_clinics';
        $new_fields_clinics = [
            'country_code' => 'varchar(10)',
            'country_calling_code' => 'varchar(10)',
        ];
        //add new column in existing table
        kcUpdateFields($table_clinics, $new_fields_clinics);

        //alter medical_report_table name field
		if(!get_option( KIVI_CARE_PREFIX.'medical_report_table_name_alter') ) {

            $medical_report_table_name = $wpdb->prefix . 'kc_patient_medical_report';
    
            if ($wpdb->get_var("SHOW TABLES LIKE '$medical_report_table_name'") == $medical_report_table_name) {
                $wpdb->query("ALTER TABLE $medical_report_table_name MODIFY COLUMN name TEXT;");
            }

            update_option(KIVI_CARE_PREFIX.'medical_report_table_name_alter', 'yes');
        }
        
        if ( ! get_option( KIVI_CARE_PREFIX . 'medical_history_table_name_alter' ) ) {

            $medical_history_table_name = $wpdb->prefix . 'kc_medical_history';

            if ($wpdb->get_var("SHOW TABLES LIKE '$medical_history_table_name'") == $medical_history_table_name) {
                $wpdb->query( "ALTER TABLE $medical_history_table_name MODIFY title TEXT" );
            }

            update_option( KIVI_CARE_PREFIX . 'medical_history_table_name_alter', 'yes' );
        }

        if ( ! get_option( KIVI_CARE_PREFIX . 'service_table_column_alter' ) ) {

            $table_services = $wpdb->prefix . 'kc_services';

            $new_fields_services = [
                'price' => 'varchar(50)',
            ];

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_services'") == $table_services) {
                //modify column in existing table
                kcUpdateFieldsDataType($table_services, $new_fields_services);
            }
            
            update_option( KIVI_CARE_PREFIX . 'service_table_column_alter', 'yes' );
        }
        
        if ( ! get_option( KIVI_CARE_PREFIX . 'bill_table_column_alter' ) ) {

            $new_fields_bills = [
                'total_amount' => 'varchar(50)',
                'discount' => 'varchar(50)',
                'actual_amount' => 'varchar(50)',
            ];

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_billing'") == $table_billing) {
                //modify column in existing table
                kcUpdateFieldsDataType($table_billing, $new_fields_bills);
            }

            update_option( KIVI_CARE_PREFIX . 'bill_table_column_alter', 'yes' );
        }   
        
        if ( ! get_option( KIVI_CARE_PREFIX . 'service_doctor_mapping_table_column_alter' ) ) {

            $table_service_doctor_mapping = $wpdb->prefix . 'kc_service_doctor_mapping';

            $new_fields_service_doctor_mapping = [
                'charges' => 'varchar(50)',
            ];

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_service_doctor_mapping'") == $table_service_doctor_mapping) {
                //modify column in existing table
                kcUpdateFieldsDataType($table_service_doctor_mapping, $new_fields_service_doctor_mapping);
            }

            update_option( KIVI_CARE_PREFIX . 'service_doctor_mapping_table_column_alter', 'yes' );
        }
        if ( ! get_option( KIVI_CARE_PREFIX . 'bill_items_table_column_alter' ) ) {

            $table_bill_items = $wpdb->prefix . 'kc_bill_items';

            $new_fields_bill_items = [
                'price' => 'varchar(50)',
            ];

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_bill_items'") == $table_bill_items) {
                //modify column in existing table
                kcUpdateFieldsDataType($table_bill_items, $new_fields_bill_items);
            }

            update_option( KIVI_CARE_PREFIX . 'bill_items_table_column_alter', 'yes' );
        } 
         
        if ( ! get_option( KIVI_CARE_PREFIX . 'static_data_table_column_alter' ) ) {

            $table_static_data = $wpdb->prefix . 'kc_static_data';

            $new_fields_static_data = [
                'label' => 'text',
                'value' => 'text',
            ];

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_static_data'") == $table_static_data) {
                //modify column in existing table
                kcUpdateFieldsDataType($table_static_data, $new_fields_static_data);
            }

            update_option( KIVI_CARE_PREFIX . 'static_data_table_column_alter', 'yes' );
        } 
        if ( ! get_option( KIVI_CARE_PREFIX . 'prescription_table_column_alter' ) ) {

            $table_prescription = $wpdb->prefix . 'kc_prescription';

            $new_fields_prescription = [
                'name' => 'text',
            ];

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_prescription'") == $table_prescription) {
                //modify column in existing table
                kcUpdateFieldsDataType($table_prescription, $new_fields_prescription);
            }
            
            update_option( KIVI_CARE_PREFIX . 'prescription_table_column_alter', 'yes' );
        } 
    }
	public function updateLangFile(){

        //deprecated language module
		$temp_file = KIVI_CARE_DIR_URI.'resources/assets/lang/temp.json';

		$dir_name = KIVI_CARE_PREFIX.'lang';
		
		$upload_dir = wp_upload_dir(); 
		$user_dirname = $upload_dir['basedir'] . '/' . $dir_name;
		$get_user_lang = get_option(KIVI_CARE_PREFIX.'lang_option');
		$data = json_decode($get_user_lang,true);
		if(!file_exists($user_dirname)) {
			wp_mkdir_p( $user_dirname ); 
		}
		foreach ($data['lang_option'] as $key => $value) {
			$current_file = KIVI_CARE_DIR_URI.'resources/assets/lang/'.$value['id'].'.json';
			$old_file  = $user_dirname.'/'.$value['id'].'.json';
			if(!file_exists($old_file)){
				$data = file_get_contents($current_file);
				file_put_contents($user_dirname.'/'.$value['id'].'.json', $data);
				chmod($old_file, 0777); 
			}else{
				$old_file_contente = file_get_contents($old_file);
				$new_file = KIVI_CARE_DIR_URI.'resources/assets/lang/en.json';
				$new_file_contente = file_get_contents($new_file);
				file_put_contents($user_dirname.'/'.$value['id'].'.json', json_encode(array_merge(json_decode($new_file_contente, true),json_decode($old_file_contente, true))));
			}
		}
		if(!file_exists($temp_file)){
			$media_temp = $user_dirname.'/temp.json';
			$temp_file_data = file_get_contents($temp_file);
			file_put_contents($user_dirname.'/temp.json', $temp_file_data);
			chmod($media_temp, 0777); 
		}
	}
	public function validateAuthUser( $user ) {
        // check login user status
		if( isset($user->data->user_status) && (int)$user->data->user_status === 1 ) {
			$error = new WP_Error();
			$error->add( 403, __('Login has been disabled. please contact you system administrator. ','kc-lang') );
			return $error;
		}
		return $user;
	}
	public function removeAdminBar() {
		//hide admin bar if user is not admin
        if (!current_user_can('administrator') && !is_admin()) {
			show_admin_bar(false);
		}
	}

    function kc_remove_logo_wp_admin() {
        //remove WordPress logo from WordPress dashboard if user is plugin user
        $user_role = get_userdata(get_current_user_id());
        if(!empty($user_role->roles[0])){
            $user_role = $user_role->roles[0];
            if(in_array($user_role,[
                $this->getPatientRole(),
                $this->getDoctorRole(),
                $this->getReceptionistRole(),
                $this->getClinicAdminRole()
            ]) ){
                global $wp_admin_bar;
                $wp_admin_bar->remove_menu( 'wp-logo' );
            }
        }
    }

	public function adminMenu () {
        //add plugin menu in sidebar
		$site_title = get_bloginfo('name');
        $kivi_current_theme = get_stylesheet();
        if(!empty($kivi_current_theme) && $kivi_current_theme == 'kivicare'){
            add_menu_page( $site_title, _x('KiviCare', 'admin-menu', 'kc-lang') , kcGetPermission('dashboard'), 'dashboard/', [$this, 'adminDashboard'], $this->plugin_url . 'assets/images/sidebar-icon.svg', 4);
        }else{
            add_menu_page( $site_title, _x('KiviCare', 'admin-menu', 'kc-lang'), kcGetPermission('dashboard'), 'dashboard/', [$this, 'adminDashboard'], $this->plugin_url . 'assets/images/sidebar-icon.svg', 99);
        }
        $user_role = get_userdata(get_current_user_id());
        if(!empty($user_role->roles[0])){
            $user_role = $user_role->roles[0];
            if(in_array($user_role,[
                $this->getPatientRole(),
                $this->getDoctorRole(),
                $this->getReceptionistRole(),
                $this->getClinicAdminRole()
            ]) ){
                //remove WordPress notice and error message from dashboard
                remove_all_actions('admin_notices');
                remove_all_actions('all_admin_notices');
                remove_menu_page( 'index.php' );
            }
        }
	}
	public function adminDashboard() {
        // return dashboard page based on condition
        $langType = get_option(KIVI_CARE_PREFIX.'locoTranslateState');
		if(isKiviCareProActive() && ($langType != 1 || $langType != '1')) {
			$upload_dir = wp_upload_dir();
			$dir_name = KIVI_CARE_PREFIX.'lang';
			$user_dirname = $upload_dir['basedir'] . '/' . $dir_name;
			$current_lang_file = $user_dirname.'/temp.json';
			if(file_exists($current_lang_file) && filesize($current_lang_file) > 2000) { 
				include(KIVI_CARE_DIR . 'resources/views/kc_dashboard.php');
			} else {
				include(KIVI_CARE_DIR . 'resources/views/kc_notice.php');
			}
		} else {
			include(KIVI_CARE_DIR . 'resources/views/kc_dashboard.php');
		}
	}
	public function enqueueStyles() {

        global $kivicare_options;

        //enqueue dashboard css
		wp_enqueue_style( 'kc_google_fonts', $this->plugin_url . 'assets/css/poppins-google-fonts.css', array(), KIVI_CARE_VERSION );
		wp_enqueue_style( 'kc_app_min_style', $this->plugin_url . 'assets/css/app.min.css' , array(), KIVI_CARE_VERSION );
		wp_enqueue_style( 'kc_font_awesome', $this->plugin_url . 'assets/css/font-awesome-all.min.css' , array(), KIVI_CARE_VERSION );
        wp_dequeue_style( 'stylesheet' );
        wp_deregister_style('wp-admin');

        if (!empty($kivicare_options['kivi_dash_css_code'])) {
            $custom_style = $kivicare_options['kivi_dash_css_code'];
            wp_add_inline_style('kc_app_min_style', $custom_style);
        }
    }
    public function loginPageStyles() {
        //enqueue wordpress login page style
	    wp_enqueue_style( 'kc_app_min_style',  'http://localhost:8080/assets/css/app.min.css', array(), KIVI_CARE_VERSION  );
    }

	public function enqueueScripts() {

        global $kivicare_options;

        if (!empty($kivicare_options['kivi_dash_js_code'])) {
            $custom_js = $kivicare_options['kivi_dash_js_code'];
            wp_register_script('kivicare-dashboard-custom-js', '', [], '', true);
            wp_enqueue_script('kivicare-dashboard-custom-js');
            wp_add_inline_script('kivicare-dashboard-custom-js', wp_specialchars_decode($custom_js));
        }

        // Condition for Development Enviroment Hot Reload
        if(file_exists(KIVI_CARE_DIR."hot")){
            $path= file_get_contents(KIVI_CARE_DIR."hot");
        }else{
            $path=$this->plugin_url;
        }
        
        //enqueue dashboard js
        wp_enqueue_script( 'jquery' );
        wp_enqueue_media();
        wp_add_inline_script( 'jquery-core', 'var wp = window.wp;' );
        wp_register_script( 'google-platform', 'https://accounts.google.com/gsi/client', array(), KIVI_CARE_VERSION,true );	
		wp_enqueue_script( 'kc_js_bundle', $path . 'assets/js/app.min.js', ['jquery','media-models','google-platform'], KIVI_CARE_VERSION,true);
        wp_enqueue_script( 'kc_custom', $path . 'assets/js/custom.js', ['jquery', 'media-models'], KIVI_CARE_VERSION,true );
        wp_localize_script( 'kc_custom', 'kc_custom_request_data', [
                'support_mime_type' => get_allowed_mime_types()
        ]);
        //pass date to app.min.js file by localize
        wp_localize_script( 'kc_js_bundle', 'request_data', $this->getLocalizeScriptData('dashboard'));
		wp_enqueue_script( 'Js_bundle' );
        do_action('kivicare_enqueue_script','dashboard');
	}
	public function enqueueFrontScripts() {
        wp_enqueue_script('jquery');
        $localizeArray = $this->getLocalizeScriptData('frontend');
        //register frontend fontawesome css , enqueue when required
        wp_register_style( 'kc_font_awesome', $this->plugin_url . 'assets/css/font-awesome-all.min.css', array(), KIVI_CARE_VERSION ,false);

        //register frontend vue shortcode css , enqueue when required
        wp_register_style( 'kc_front_app_min_style', $this->plugin_url . 'assets/css/front-app.min.css', array(), KIVI_CARE_VERSION,false );

        //register frontend vue shortcode js , enqueue when required
        wp_register_script( 'kc_front_js_bundle', $this->plugin_url . 'assets/js/front-app.min.js', ['jquery'], KIVI_CARE_VERSION, true );
        //pass date to front-app.min.js file by localize
        wp_localize_script( 'kc_front_js_bundle', 'ajaxData', $localizeArray);
        wp_localize_script( 'kc_front_js_bundle', 'request_data', $localizeArray );

        //register custom js , enqueue when required
        wp_register_script( 'kc_custom', $this->plugin_url . 'assets/js/custom.js', ['jquery', 'media-models'], KIVI_CARE_VERSION, true );

        //register axios js for ajax in shortcode, enqueue when required
        wp_register_script( 'kc_axios',$this->plugin_url . 'assets/js/axios.min.js' ,['jquery'], KIVI_CARE_VERSION, true );

        //register flatpicker js and css for date select in appointment shortcode, enqueue when required
        wp_register_script( 'kc_flatpicker',$this->plugin_url . 'assets/js/flatpicker.min.js' ,[], KIVI_CARE_VERSION, true );
        wp_register_style( 'kc_flatpicker',$this->plugin_url . 'assets/css/flatpicker.min.css' ,[], KIVI_CARE_VERSION, false );

        //register new appointment shortcode main css, enqueue when required
        wp_register_style( 'kc_book_appointment',$this->plugin_url . 'assets/css/book-appointment.css' ,[], KIVI_CARE_VERSION, false );

        //register css for login/register shortcode, enqueue when required
        wp_register_style( 'kc_register_login',$this->plugin_url . 'assets/css/register_login.css' ,[], KIVI_CARE_VERSION, false );

        //register add-to-calender button js and css for new appointment shortcode, enqueue when required
        wp_register_script( 'kc_calendar',$this->plugin_url . 'assets/js/calendar.min.js' ,[], KIVI_CARE_VERSION, true );
        wp_register_style( 'kc_calendar',$this->plugin_url . 'assets/css/calendar.min.css' ,[], KIVI_CARE_VERSION, false );

        //register print jquery js  for new appointment shortcode, enqueue when required
        wp_register_script( 'kc_print',$this->plugin_url . 'assets/js/jquery-print.min.js' ,['jquery'], KIVI_CARE_VERSION, true );

        //register magnific popup js/css  for  appointment button shortcode, enqueue when required
        wp_register_style( 'kc_popup', $this->plugin_url . 'assets/css/magnific-popup.min.css', [], KIVI_CARE_VERSION, false );
        wp_register_script( 'kc_popup', $this->plugin_url . 'assets/js/magnific-popup.min.js', ['jquery'], KIVI_CARE_VERSION,true );
        wp_register_script( 'kc_bookappointment_widget', $this->plugin_url . 'assets/js/book-appointment-widget.js', ['jquery'], KIVI_CARE_VERSION,true );
        do_action('kivicare_enqueue_script','shortcode_register');
    }

	public function appendToHeader () {

        //append required data in page meta
        $prefix = KIVI_CARE_PREFIX;
        $upload_dir = wp_upload_dir();
        $dir_name = $prefix .'lang';
        $user_dirname = $upload_dir['baseurl'] . '/' . $dir_name;
        $get_config =   get_option( KIVI_CARE_PREFIX . 'google_cal_setting',true);
        if(gettype($get_config) != 'boolean'){
            $client_id = $get_config['client_id'];
        }else{
            $client_id = '';
        }
        echo '<meta name="pluginBASEURL" content="' . esc_html($this->plugin_url) .'" />';
        echo '<meta name="pluginPREFIX" content="' . esc_html($this->getPluginPrefix()) .'" />';
        echo '<meta name="pluginMediaPath" content="' .esc_html($user_dirname) .'" />';
        echo '<meta name="google-signin-client_id" content="'.esc_html($client_id).'" />';

        //add root primary color ,if proactive
        $color = get_option(KIVI_CARE_PREFIX.'theme_color');
        if(!empty($color) && gettype($color) !== 'boolean' && isKiviCareProActive()){
            ?>
            <script> document.documentElement.style.setProperty("--primary", '<?php echo esc_js($color);?>');</script>
            <?php
        }
	}

	public function addAdministratorPermission () {
        //add permission to plugin user
		$admin_permissions = kcGetAdminPermissions()->pluck('name')->toArray();
		if (count($admin_permissions)) {
			$admin_role = get_role( 'administrator' );
			foreach ($admin_permissions as $permission) {
				$admin_role->add_cap( $permission, true );
			}
		}
	}
    public function migratePermissions(){

        //migrate permisssion to plugin user

        //migrate permisssion to plugin user
        if (!get_option(KIVI_CARE_PREFIX . 'permissions-migrate')) {

            remove_role($this->getClinicAdminRole());
            remove_role($this->getDoctorRole());
            remove_role($this->getPatientRole());
            remove_role($this->getReceptionistRole());

            $clinic_admin_permissions = kcGetAdminPermissions()->pluck('name')->toArray();
            $doctor_permissions = kcGetDoctorPermission()->pluck('name')->toArray();
            $patient_permissions = kcGetPatientPermissions()->pluck('name')->toArray();
            $receptionist_permissions = kcGetReceptionistPermission()->pluck('name')->toArray();

            // Assign permission to Clinic admin role...
            add_role($this->getClinicAdminRole(), 'Clinic admin', array_fill_keys($clinic_admin_permissions, 1));

            // Assign permission to Doctor role...
            add_role($this->getDoctorRole(), 'Doctor', array_fill_keys($doctor_permissions, 1));

            // Assign permission to Patient role...
            add_role($this->getPatientRole(), 'Patient', array_fill_keys($patient_permissions, 1));

            // Assign permission to Receptionist role...
            add_role($this->getReceptionistRole(), 'Receptionist', array_fill_keys($receptionist_permissions, 1));

            update_option(KIVI_CARE_PREFIX . 'permissions-migrate', 'yes');
        }

        return true;
    }
	public function hideSideBar() {
        //hide sidebar and notice from dashboard page
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        echo '<style type="text/css">
					#wpcontent, #footer { margin-left: 0px !important;padding-left: 0px !important; }
					html.wp-toolbar { padding-top: 0px !important; }
					#adminmenuback, #adminmenuwrap, #wpadminbar, #wpfooter,#adminmenumain, #screen-meta { display: none !important; }
					#wpcontent .notice {
                     display:none;
                    }
				</style>';
	}
    public function kivicareShortcodeWidgetBlock() {

        global $pagenow;
        $script_dep = array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-components');
        if ( 'widgets.php' !== $pagenow ) {
            $script_dep[] = 'wp-editor';
        }

        //vue appointment book shortcode script enqueue
        wp_enqueue_script(
            'kivi-care-appointment-widget',
            $this->plugin_url . 'assets/js/KC-appointment-block.js',
            $script_dep, KIVI_CARE_VERSION, true
        );

        //vue patient dashboard shortcode script enqueue
        wp_enqueue_script(
            'kivi-care-patient-dashboard-widget',
            $this->plugin_url . 'assets/js/kc-patient-dashboard-block.js',
            $script_dep,
            KIVI_CARE_VERSION, true
        );

        //new appointment book shortcode script enqueue
        wp_enqueue_script(
            'kivi-care-book-appointment-widget',
            $this->plugin_url . 'assets/js/KC-book-appointment-block.js',
            $script_dep, KIVI_CARE_VERSION, true
        );

        //new popup/button appointment book shortcode script enqueue
        wp_enqueue_script(
            'kivi-care-popup-book-appointment-widget',
            $this->plugin_url . 'assets/js/KC-popup-book-appointment-block.js',
            $script_dep,
            KIVI_CARE_VERSION, true
        );

        //login/register shortcode script enqueue
        wp_enqueue_script(
            'kivi-care-register-login-widget',
            $this->plugin_url . 'assets/js/kc-register-login-block.js',
            $script_dep,
            KIVI_CARE_VERSION, true
        );

		global $wpdb;
        $this->db = $wpdb;
        $this->request = new KCRequest();

        $clinicsmappingForShortCode = [];
        if(isKiviCareProActive()){
            //doctors clinic list
            $clinicsmappingForShortCode = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}kc_doctor_clinic_mappings ", ARRAY_A);
            //all clinic list
            $clinicsListForShortCode = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}kc_clinics", ARRAY_A);
        }else{
            //get default clinic detail
            $clinicsListForShortCode = kcGetDefaultClinic();
        }

        //all doctors list who status is active
        $doctorsListForShortCode = get_users([
            'role' => $this->getDoctorRole(),
            'user_status' => '0'
        ]);
        $doctorsListForShortCode = (array) $doctorsListForShortCode;

        //pass data to widget js file
        wp_localize_script( 'kivi-care-appointment-widget', 'clincData', array(
            'clinics' => $clinicsListForShortCode,
            'doctors' => $doctorsListForShortCode,
            'mappingData' => $clinicsmappingForShortCode,
            'proActive' => isKiviCareProActive()
        ));

        //pass data to widget js file
        wp_localize_script( 'kivi-care-book-appointment-widget', 'clincDataNew', array(
            'clinics' => $clinicsListForShortCode,
            'doctors' => $doctorsListForShortCode,
            'mappingData' => $clinicsmappingForShortCode,
            'proActive' => isKiviCareProActive()
        ));

    }
	
    public function addBlockCategories( $categories ) {

        //add plugin widget in gutenberg
        return array_merge( array(
            array(
                'slug'  => 'kivi-appointment-widget',
                'title' => 'KiVi Care',
            ), ),
            $categories
        );
    }
	public function redirectUserToDashboard( $redirect_to, $request, $user ) {

        //redirect plugin user to dashboard
		if ( isset( $user->roles ) && is_array( $user->roles ) ) {
			// check for other user roles...
			if (in_array( $this->getClinicAdminRole(), $user->roles ) ) {
                return kcGetLogoinRedirectSetting('clinic_admin');
			} elseif (in_array( $this->getReceptionistRole(), $user->roles )) {
                return kcGetLogoinRedirectSetting('receptionist');
			} elseif (in_array( $this->getDoctorRole(), $user->roles )) {
                return kcGetLogoinRedirectSetting('doctor');
			} elseif (in_array( $this->getPatientRole(), $user->roles )) {
                return kcGetLogoinRedirectSetting('patient');
			}
		}

		return $redirect_to;
	}
	public static function migrateDatabase () {
        //include all databases file
        $databases = glob(KIVI_CARE_DIR . 'app/database/*.php');
        require_once ABSPATH . '/wp-admin/includes/upgrade.php';
        foreach ($databases as $key => $file){
            if(file_exists($file)){
                require $file;
            }
        }
	}
	public function addDefaultOptions () {

        //add all default option when plugin activate
        $steps = $this->getSetupSteps();
		$moduleSetting = [
            $steps => 4,
			'common_setting' => [
				'patient_reminder' =>
					[
						'label' => 'Patient appointment reminder switch',
						'status' => 1
					]
			]
		];

		foreach ($moduleSetting as $key => $value) {
			add_option($key , $value);
		}

		$setup_config_name = KIVI_CARE_PREFIX . 'setup_config';
		add_option($setup_config_name, json_encode(kcGetSetupWizardOptions()));

		if (!get_option( 'is_kivicarepro_upgrade_lang')) {
			add_option('is_kivicarepro_upgrade_lang', 1);
			$this->updateLangFile();
		}

		if (!get_option( 'is_lang_version_2')) {
			add_option('is_lang_version_2', 1);
			$this->getLangCopy();
			$this->mergeJson();
		}
		   
		if(!get_option( KIVI_CARE_PREFIX . 'woocommerce_payment' )) {
			update_option( KIVI_CARE_PREFIX . 'woocommerce_payment', 'off', 'no' );
		}
        
        if(!get_option( KIVI_CARE_PREFIX . 'country_code' )) {
			update_option(KIVI_CARE_PREFIX . 'country_code', 'GB');
		}

        if(!get_option( KIVI_CARE_PREFIX . 'country_calling_code' )) {
			update_option(KIVI_CARE_PREFIX . 'country_calling_code', '44');
		}

        $user_role = [
            'kiviCare_patient' => 'on',
            'kiviCare_doctor' => 'on',
            'kiviCare_receptionist' => 'on'
        ];

        if(!get_option( KIVI_CARE_PREFIX . 'user_registration_shortcode_role_setting' )) {
            update_option(KIVI_CARE_PREFIX . 'user_registration_shortcode_role_setting', $user_role);
		}
        
        if(!get_option( KIVI_CARE_PREFIX . 'appointment_cancellation_buffer' )) {
            update_option(KIVI_CARE_PREFIX . 'appointment_cancellation_buffer',[
                "status" => 'off',
                "time" => '']
            );
		}
        
	}
	public function addDefaultModuleConfig() {

        //add default option for module configuration
		$prefix = $this->getPluginPrefix();

		$modules = [
			'module_config' => [
				[
					'name' => 'receptionist',
					'label' => 'Receptionist',
					'status' => '1'
				],
				[
					'name' => 'billing',
					'label' => 'Billing',
					'status' => '1'
				],
				[
					'name' => 'custom_fields',
					'label' => 'Custom Fields',
					'status' => '1'
				]
			],
			'common_setting' => [],
			'notification' => []
		];

		delete_option($prefix.'modules');
		add_option( $prefix.'modules', json_encode($modules));
		
	}
	public function versionUpgradePatches () {
        //not use function
		require KIVI_CARE_DIR . 'app/upgrade/kc-default-value-upgrade.php';
    }
	public function kivicare_agent_admin_access( $prevent_access ) {
        //restrict woocommerce user access
        if( current_user_can('read') ) $prevent_access = false;
        return $prevent_access; 
    }
	public function mergeJson(){

        //new language key add to old language file (deprecated language )
		//upload dir
		$upload_dir = wp_upload_dir(); 
		$dir_name = KIVI_CARE_PREFIX.'lang';
		$user_dirname = $upload_dir['basedir'] . '/' . $dir_name;

		//get latest key from the en.json
		$newEn = KIVI_CARE_DIR_URI.'resources/assets/lang/en.json';
		$enContent =  file_get_contents($newEn);
		
		//get all lang of user from the database.
		$get_user_lang = get_option(KIVI_CARE_PREFIX.'lang_option');
		$data = json_decode($get_user_lang,true); 

		//store merge data.
		$output = [];

		//merge new en content in all database file.
		foreach ($data['lang_option'] as $key => $value) {
			
			//get all file based on databse lang value.
			$all_database_file  = $user_dirname.'/'.$value['id'].'.json';
            if(file_exists($all_database_file)){
                $all_file_content = file_get_contents($all_database_file);

                // get file value in multidimention array.
                $value_arry = json_decode($all_file_content, true);
    
                //merge new value in all database file.
                $temp_new = json_decode($enContent,true);
                if(!empty($temp_new) && is_array($temp_new)){
    
                    foreach (json_decode($enContent,true) as $key => $lang) {
                        $output[$key] = array_merge($lang,!empty($value_arry[$key]) ? $value_arry[$key] : []);
                    }
    
                    //put all new keys in all file.
                    file_put_contents($user_dirname.'/'.$value['id'].'.json', json_encode($output));
                }
            }
			
		}
		
		//get temp file
		$current_lang_file = $user_dirname.'/temp.json';
		if(file_exists($current_lang_file)){
			//put all temp content in below array.
			$temp_output = [];

			//get temp file value
			$get_current_lang_content = file_get_contents($current_lang_file);

			foreach (json_decode($enContent,true) as $key => $lang) {

				// merge all key in temp
				$temp_output[$key] = array_merge($lang,!empty($get_current_lang_content[$key]) ? $get_current_lang_content[$key] : []);
			}

			//put all key in temp file.
			file_put_contents($current_lang_file, json_encode($temp_output));
		}else{
            $directory = dirname($current_lang_file); // Extract the directory from the file path.

            if (!is_dir($directory)) {
                // The directory doesn't exist, so create it.
                mkdir($directory, 0755);
            }

            // Now, you can proceed with writing the file.
            file_put_contents($current_lang_file, json_encode($enContent));
		}
	}
	public function getLangCopy(){

        //copy language folder as a backup (deprecated language)
		$upload_dir = wp_upload_dir(); 
		$backup_dir_name = $upload_dir['basedir'].'/'.KIVI_CARE_PREFIX.'backup';
		$backup_folder =  $upload_dir['basedir'].'/'.KIVI_CARE_PREFIX.'backup/'.current_time('Y-m-d') .'_lang';

		$dir_name = KIVI_CARE_PREFIX.'lang';
		$user_dirname = $upload_dir['basedir'] . '/' . $dir_name;

		if(!file_exists($backup_dir_name)) {
			wp_mkdir_p( $backup_dir_name ); 
		}
		if(!file_exists($backup_folder)){
			wp_mkdir_p( $backup_folder ); 
			$get_user_lang = get_option(KIVI_CARE_PREFIX.'lang_option');
			$data = json_decode($get_user_lang,true); 
			foreach ($data['lang_option'] as $key => $value) {
				$old_file  = $user_dirname.'/'.$value['id'].'.json';
				$new = $backup_folder.'/'.$value['id'].'.json';
                if (file_exists($old_file)) {
                    $data = file_get_contents($old_file);
                    if ($data !== false) {
                        file_put_contents($new,$data);
                        chmod($new, 0777); 
                    }
                }
			}
			$old_temp  = $user_dirname.'/temp.json';
			$new_temp = $backup_folder.'/temp.json';
            if (file_exists($old_temp)) {
                $temp_data = file_get_contents($old_temp);
                if ($temp_data !== false) {
                    file_put_contents($new_temp,$temp_data);
                    chmod($new_temp, 0777); 
                }
            }
		}
	}

   public function kcEnableLocoTranslate(){

        //redirect when user change deprecate language translate to loco translate for notice page
       if(isset($_GET['kcEnableLocoTranslation'])) {
           update_option(KIVI_CARE_PREFIX.'locoTranslateState', 1);
           wp_redirect(admin_url( 'admin.php?page=dashboard' ));
       }

       //redirect after appointment payment by PayPal gateway
       if(isset($_GET['kivicare_payment'])){
           if($_GET['kivicare_payment'] === 'failed'){
               if($this->getLoginUserRole() === $this->getPatientRole()){
	               (new KCPaymentController())->paymentFailedPage();
	               //if payment failed
               }
           }elseif ($_GET['kivicare_payment'] === 'success'){
	           if($this->getLoginUserRole() === $this->getPatientRole()){
		           //if payment success
		           (new KCPaymentController())->paymentSuccess();
	           }
           }
       }
   }

   public function addCustomPostType(){
       /**
        * function to register custom post type
        */
       register_post_type(KIVI_CARE_PREFIX.'sms_tmp',
           array(
               'labels' => array(
                   'name' => 'KivicareSms',
                   'singular_name' => 'kivicaresms'
               ),
               'public' => true,
               'has_archive' => 'false',
               'rewrite' => array('slug' => 'kivicaresms'),
               'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'author'),
               'description' => esc_html__('Custom kivicaresms Posts','kc-lang'),
               'show_ui' => false,
               'show_in_menu' => false,
               'map_meta_cap' => true,
               'capability_type'     => 'post',
           )
       );

       register_post_type(KIVI_CARE_PREFIX.'mail_tmp',
           array(
               'labels' => array(
                   'name' => 'KivicareMail',
                   'singular_name' => 'kivicaremail'
               ),
               'public' => true,
               'has_archive' => 'false',
               'rewrite' => array('slug' => 'kivicaremail'),
               'supports' => array('title', 'editor', 'thumbnail', 'excerpt','author'),
               'description' => esc_html__('Custom kivicaremail Posts','kc-lang'),
               'show_ui' => false,
               'show_in_menu' => false,
               'map_meta_cap' => true,
               'capability_type' => 'post',

           )
       );

       register_post_type(KIVI_CARE_PREFIX.'gcal_tmp',
           array(
               'labels' => array(
                   'name' => 'KivicareGoogleEvent',
                   'singular_name' => 'KivicareGoogleEvent'
               ),
               'public' => true,
               'has_archive' => 'false',
               'rewrite' => array('slug' => 'Kivicaregoogleevent'),
               'supports' => array('title', 'editor', 'thumbnail', 'excerpt','author'),
               'description' => esc_html__('Custom kivicare Google Event Posts','kc-lang'),
               'show_ui' => false,
               'show_in_menu' => false,
               'map_meta_cap' => true,
               'capability_type' => 'post',

           )
       );

       register_post_type(KIVI_CARE_PREFIX.'gmeet_tmp',
           array(
               'labels' => array(
                   'name' => 'KivicareGoogleMeetEvent',
                   'singular_name' => 'KivicareGoogleMeetEvent'
               ),
               'public' => true,
               'has_archive' => 'false',
               'rewrite' => array('slug' => 'Kivicaregooglemeetevent'),
               'supports' => array('title', 'editor', 'thumbnail', 'excerpt','author'),
               'description' => esc_html__('Custom kivicare Google Meet Event Posts','kc-lang'),
               'show_ui' => false,
               'show_in_menu' => false,
               'map_meta_cap' => true,
               'capability_type' => 'post',

           )
       );

       if ( !get_option(KIVI_CARE_PREFIX.'doctor_appointment_remainder_template_added')){
            update_option(KIVI_CARE_PREFIX.'doctor_appointment_remainder_template_added','yes');
            $this->addDoctorEmailTemplate();
       }

       if(!get_option( KIVI_CARE_PREFIX.'notification_template_added')){
           update_option(KIVI_CARE_PREFIX.'notification_template_added','yes');
           $this->addDefaultPosts();
        }
   }

   public function addDoctorEmailTemplate () {
        //doctor appointment remainder template
        $template = [
            [
                'post_name' => KIVI_CARE_PREFIX . 'book_appointment_reminder_for_doctor',
                'post_content' => '<p> Welcome to KiviCare ,</p><p> You Have appointment  on </p><p> {{appointment_date}}  , Time : {{appointment_time}}  , Patient : {{patient_name}} </p><p> Thank you. </p>',
                'post_title' => 'Doctor Appointment Reminder',
                'post_type' => KIVI_CARE_PREFIX.'mail_tmp',
                'post_status' => 'publish',
            ],
            [
                'post_name' => KIVI_CARE_PREFIX . 'book_appointment_reminder_for_doctor',
                'post_content' => '<p> Welcome to KiviCare ,</p><p> You Have appointment  on </p><p> {{appointment_date}}  , Time : {{appointment_time}}  , Patient : {{patient_name}} </p><p> Thank you. </p>',
                'post_title' => 'Doctor Appointment Reminder',
                'post_type' => KIVI_CARE_PREFIX.'sms_tmp',
                'post_status' => 'publish',
            ],
        ];
        kcAddMailSmsPosts($template);
   }

    public function addNewPosts () {
        //google event template
        $commonTemplate = [
            [
                'post_name' => KIVI_CARE_PREFIX . 'patient_invoice',
                'post_content' => '<p> Welcome to KiviCare ,</p><p> Find your Invoice in attachment </p><p> Thank you. </p>',
                'post_title' => 'Patient Invoice',
                'post_type' => KIVI_CARE_PREFIX.'mail_tmp',
                'post_status' => 'publish'
            ],
        ];
        kcAddMailSmsPosts($commonTemplate);
    }
    
    public function addDefaultPosts () {

        //email template
        kcAddMailSmsPosts(kcCommonTemplate('mail'));

        //sms template
        kcAddMailSmsPosts(kcCommonTemplate('sms'));

        //google event template
        $commonTemplate = [
            [
                'post_title' => '{{service_name}}',
                'post_content'  =>' Appointment booked at {{clinic_name}}',
                'post_status'   => 'publish',
                'post_type' => KIVI_CARE_PREFIX.'gcal_tmp',
                'post_name' => KIVI_CARE_PREFIX.'default_event_template',
            ]
        ];
        kcAddMailSmsPosts($commonTemplate);

        //googlemeet event template
        $commonTemplate = [array(
            'post_title' => '{{service_name}}',
            'post_content'  => '<p> New appointment </p><p> Your have new appointment on </p><p> Date: {{appointment_date}}  , Time : {{appointment_time}} ,Patient : {{patient_name}} </p><p> Clinic: {{clinic_name}}. </p><p> Appointment Description: {{appointment_desc}}. </p><p> Thank you. </p>',
            'post_status'   => 'publish',
            'post_type' => KIVI_CARE_PREFIX.'gmeet_tmp',
            'post_name' => KIVI_CARE_PREFIX.'doctor_gm_event_template',
        )];

        kcAddMailSmsPosts($commonTemplate);
    }

   public function appendLanguageInHead(){
       //browser tab icon change (use for plugin sandbox)
       if(gettype(get_option('mp_demo_sandbox_id',true)) !== 'boolean' && is_multisite()){
           $iconUrl = KIVI_CARE_DIR_URI.'assets/images/favicon-1.ico';
           ?>
           <link rel="shortcut icon" href="<?php echo esc_url($iconUrl);?>"/>
           <link rel="apple-touch-icon" href="<?php echo esc_url($iconUrl);?>">
           <?php
       }

       //append translated language object to page by global window variable
       kcAppendLanguageInHead();
   }

    public function kccheckActiveUser($user, $password){
        $user_data = $user->data;
        $user_id = $user_data->ID;
        $user_role = get_userdata($user_id);
        if(empty($user_role->roles[0])){
            return $user;
        }
        $user_role = $user_role->roles[0];
        //check plugin login user status ,if status is not active return error response
        if(in_array($user_role,[
            $this->getPatientRole(),
            $this->getDoctorRole(),
            $this->getReceptionistRole(),
            $this->getClinicAdminRole()
        ]) ){
            global $wpdb;
            $user_status = $wpdb->get_var("SELECT user_status FROM {$wpdb->base_prefix}users WHERE ID = {$user_id}");
            if( $user_status == 1 ){
                return new WP_Error('disabled_account', __('This User account is Not Activated','kc-lang'));
            }
        }
        return  $user;
    }

    public function getLocalizeScriptData($type){
        $prefix = KIVI_CARE_PREFIX;
        $config_options = kc_get_multiple_option("
            '{$prefix}appointment_description_config_data',
            '{$prefix}appointment_patient_info_config_data',
            '{$prefix}copyrightText',
            '{$prefix}site_logo',
            '{$prefix}request_helper_status',
            '{$prefix}theme_color',
            'mp_demo_sandbox_id'
        ");
        //localize data
        $user_locale = get_user_locale();
        $lang = explode('_', $user_locale);
        $lang = !empty($lang[0]) ? $lang[0] : 'en';
        $enableAppointmentDescription = !empty($config_options[KIVI_CARE_PREFIX.'appointment_description_config_data']) ? $config_options[KIVI_CARE_PREFIX.'appointment_description_config_data'] : 'off';
        $enablePatientInfo = !empty($config_options[KIVI_CARE_PREFIX.'appointment_patient_info_config_data']) ? $config_options[KIVI_CARE_PREFIX.'appointment_patient_info_config_data'] : 'off';
        $time_format = get_option('time_format');
        $data = [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce('ajax_post'),
            'get_nonce' => wp_create_nonce('ajax_get'),
            'kiviCarePluginURL' => $this->plugin_url,
            'loaderImage' =>  kcPluginLoader(),
            'homePage' => get_home_url(),
            'appointment_time_format' => $time_format,
            'current_user_role' => $this->getLoginUserRole(),
            'current_wordpress_lang' => $lang,
            'proActive' => isKiviCareProActive(),
            'appointment_restrict' => kcAppointmentRestrictionData(),
            'date_format' => kcGetDateFormat(),
            'logout_redirect_url' =>kcGetLogoutRedirectSetting('all'),
            'copyrightText' => !empty($config_options[KIVI_CARE_PREFIX.'copyrightText']) ? $config_options[KIVI_CARE_PREFIX.'copyrightText'] : '',
            'file_upload_status' => kcAppointmentMultiFileUploadEnable() ? 'on' : 'off',
            'description_status' => $enableAppointmentDescription,
            'patient_detail_info_status' => $enablePatientInfo,
            'menu_url' => admin_url('admin.php?page=dashboard'),
            'wp_timezone' => wp_timezone_string()
        ];

        if($type === 'frontend'){
            $site_logo  = !empty($config_options[KIVI_CARE_PREFIX.'site_logo'])
                ? wp_get_attachment_url($config_options[KIVI_CARE_PREFIX.'site_logo']) : $this->plugin_url.'assets/images/logo-banner.png';
            $temp = [
                'site_logo' => $site_logo,
                'forget_password_page' => apply_filters( 'kivicare_custom_lost_password_url', wp_lostpassword_url() ),
                'default_clinic_id'   => kcGetDefaultClinicId(),
            ];
            $data = array_merge($data,$temp);
        }

        if($type === 'dashboard'){
            $temp = [
                'new_user' => kcToCheckUserIsNew() ? 'true' : 'false',
                'adminUrl' => admin_url(),
                "color" => !empty($config_options[KIVI_CARE_PREFIX.'theme_color']) ? $config_options[KIVI_CARE_PREFIX.'theme_color']: '#4874dc',
                'link_show_hide' => !empty($config_options[KIVI_CARE_PREFIX.'request_helper_status']) && $config_options[KIVI_CARE_PREFIX.'request_helper_status'] == 'on' ? 'on' : 'off',
                'understand_loco_translate' => kcGetiUnderstand(),
                'time_zone_data' => kcGetTimeZoneOption(),
                'allClinicHaveSession' => kcGetAllClinicHaveSession(),
                'wordpress_logo' => kcWordpressLogostatusAndImage('image'),
                'demo_plugin_active' => !empty($config_options['mp_demo_sandbox_id']) && is_multisite(),
                'wordpress_logo_status' => kcWordpressLogostatusAndImage('status')
            ];
            $data = array_merge($data,$temp);
        }
        return apply_filters( 'kivicare_localize_request_data', $data, $type );
    }

    public function kcElementerCategoryRegistered($elements_manager){
        $elements_manager->add_category(
            'kivicare-widget-category',
            [
                'title' => __( 'Kivicare', 'kc-lang' ),
                'icon' => 'fas fa-clinic-medical',
            ]
        );
    }

    public function kcAddElementorWidget(){
        require_once(KIVI_CARE_DIR . 'app/baseClasses/KCElementor/KCElementorClinicWiseDoctor.php' );
        require_once(KIVI_CARE_DIR . 'app/baseClasses/KCElementor/KCElementorClinicCard.php' );
    }

    public function kcChangeWordpressLogo() {
        ?>
        <style type="text/css">
            body.login div#login h1 a {
                background-image: url(<?php echo esc_url(kcWordpressLogostatusAndImage('image')); ?>);
                padding-bottom: 30px;
            }
        </style>
        <?php
    }

    public static function createShortcodePage(){
        $pages = apply_filters(
            'kivicare_create_pages',
            array(
                'appointment'           => array(
                    'name'    => _x( 'appointment', 'Page slug', 'kc-lang' ),
                    'title'   => _x( 'Appointment', 'Page title', 'kc-lang' ),
                    'content' => '<!-- wp:shortcode -->[kivicareBookAppointment]<!-- /wp:shortcode -->',
                    'search_value' => '[kivicareBookAppointment'
                ),
                'patient_dashboard'       => array(
                    'name'    => _x( 'patient-dashboard', 'Page slug', 'kc-lang' ),
                    'title'   => _x( 'Patient Dashboard', 'Page title', 'kc-lang' ),
                    'content' => '<!-- wp:shortcode -->[patientDashboard]<!-- /wp:shortcode -->',
                    'search_value' => '[patientDashboard]'
                ),
                'register_login'       => array(
                    'name'    => _x( 'register-login', 'Page slug', 'kc-lang' ),
                    'title'   => _x( 'Register Login user', 'Page title', 'kc-lang' ),
                    'content' => '<!-- wp:shortcode -->[kivicareRegisterLogin]<!-- /wp:shortcode -->',
                    'search_value' => '[kivicareRegisterLogin]'
                ),
            )
        );

        foreach ( $pages as $key => $page ) {
            self::createPage(
                esc_sql( $page['name'] ),
                'kivicare_' . $key . '_page_id',
                $page['title'],
                $page['content'],
                ! empty( $page['post_status'] ) ? $page['post_status'] : 'publish',
                $page['search_value']
            );
        }
    }

    public static function createPage( $slug, $option = '', $page_title = '', $page_content = '', $post_status = 'publish',$searchValue ='' ) {
        global $wpdb;

        $option_value = get_option( $option );

        if ( $option_value > 0 ) {
            $page_object = get_post( $option_value );
            if ( $page_object && 'page' === $page_object->post_type && ! in_array( $page_object->post_status, array( 'pending', 'trash', 'future', 'auto-draft' ), true ) ) {
                // Valid page is already in place.
                return;
            }
        }

        if ( strlen( $searchValue ) > 0 ) {
            // Search for an existing page with the specified page content (typically a shortcode).
            $valid_page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='page' AND post_status NOT IN ( 'pending', 'trash', 'future', 'auto-draft' ) AND post_content LIKE %s LIMIT 1;", "%{$searchValue}%" ) );
            if(!empty($valid_page_found)){
                update_option( $option, $valid_page_found );
                return ;
            }
        }


        // Search for a matching valid trashed page.
        if ( strlen( $searchValue ) > 0 ) {
            // Search for an existing page with the specified page content (typically a shortcode).
            $trashed_page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status = 'trash' AND post_content LIKE %s LIMIT 1;", "%{$searchValue}%" ) );
        } else {
            // Search for an existing page with the specified page slug.
            $trashed_page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status = 'trash' AND post_name = %s LIMIT 1;", $slug ) );
        }

        if ( !empty($trashed_page_found) ) {
            $page_id   = $trashed_page_found;
            $page_data = array(
                'ID'          => $page_id,
                'post_status' => $post_status,
            );
            wp_update_post( $page_data );
        } else {
            $page_data = array(
                'post_status'    => $post_status,
                'post_type'      => 'page',
                'post_author'    => get_current_user_id(),
                'post_name'      => $slug,
                'post_title'     => $page_title,
                'post_content'   => $page_content,
                'comment_status' => 'closed',
            );
            $page_id   = wp_insert_post( $page_data );
        }

        update_option( $option, $page_id );

        return;
    }

    public function add_display_post_states( $post_states, $post ) {

        if ( $this->kc_get_page_id( 'appointment' ) === $post->ID ) {
            $post_states['appointment'] = __( 'Appointment Booking Page', 'kc-lang' );
        }

        if (  $this->kc_get_page_id( 'patient_dashboard' ) === $post->ID ) {
            $post_states['patient_dashboard'] = __( 'Patient Dashboard Page', 'kc-lang' );
        }

        if (  $this->kc_get_page_id( 'register_login' ) === $post->ID ) {
            $post_states['register_login'] = __( 'User Register Login Page', 'kc-lang' );
        }

        return $post_states;
    }

    public function kc_get_page_id($page){
        $page = get_option( 'kivicare_' . $page . '_page_id' );
        return $page ? absint( $page ) : -1;
    }

    public function woocommerce_shop_order_add_column(){
        add_action( 'admin_init', function () {
            // Just to make clear how the filters work
            $posttype = ["woocommerce_page_wc-orders",'product' ];
            
            foreach ($posttype as $post){
                // Priority 20, with 1 parameter (the 1 here is optional)
                add_filter( "manage_edit-{$post}_columns", function ( $columns )use($post) {
                    if($post === 'product'){
                        return $this->woocommerceAddNewColumnInTable(['key' => 'service_id','value' =>esc_html__("Service ID","kc-lang") ],'product_tag',$columns);
                    }else{
                        return $this->woocommerceAddNewColumnInTable(['key' => 'appointment_id','value' =>esc_html__("Appointment ID","kc-lang") ],'order_status',$columns);
                    }
                }, 20, 11 );
                add_filter( "manage_{$post}_columns", function ( $columns )use($post) {
                    if($post === 'product'){
                        return $this->woocommerceAddNewColumnInTable(['key' => 'service_id','value' =>esc_html__("Service ID","kc-lang") ],'product_tag',$columns);
                    }else{
                        return $this->woocommerceAddNewColumnInTable(['key' => 'appointment_id','value' =>esc_html__("Appointment ID","kc-lang") ],'order_status',$columns);
                    }
                }, 20, 11 );

                // Priority 20, with 2 parameters
                add_action( "manage_{$post}_custom_column", function ( $column_name, $order ) use($post){
                    if ( $post === 'woocommerce_page_wc-orders' && 'appointment_id' != $column_name ){
                        return;
                    }
                    if ( $post === 'product' && 'service_id' != $column_name ){
                        return;
                    }
                    $output = "<strong class='order-view'> - </strong>";

                    if( $post === 'product'){
                        $serviceId = get_post_meta($order->get_id(),'kivicare_service_id',true);
                        $doctor_id = get_post_meta($order->get_id(),'kivicare_doctor_id',true);
                        if(!empty($serviceId) && !empty($doctor_id)){
                            $service_details = (new KCServiceDoctorMapping())->get_by(['id' => (int)$serviceId],'=',true);
                            if(!empty($service_details)){
                                $url = esc_url(admin_url('admin.php?page=dashboard#/service/edit/').$serviceId);
                                $serviceId = esc_html('#'.$serviceId);
                                if(!empty($service_details->telemed_service) && $service_details->telemed_service === 'yes'){
                                    $doctor_telemed_active = kcDoctorTelemedServiceEnable($doctor_id);
                                    if($doctor_telemed_active){
                                        $output ="<a href='{$url}' target='_blank' class='order-view'><strong>{$serviceId}</strong></a>";
                                    }
                                }else{
                                    $output ="<a href='{$url}' target='_blank' class='order-view'><strong>{$serviceId}</strong></a>";
                                }
                            }
                        }
                    }else{
                        $appointment_id = get_post_meta($order->get_id(),'kivicare_appointment_id',true);
                        if(!empty($appointment_id)){
                            $appointment_detail = (new KCAppointment())->get_by(['id' => (int)$appointment_id],'=',true);
                            $text = esc_html('#'.$appointment_id.(!empty($appointment_detail->description) ? ' '.$appointment_detail->description : ' ' ));
                            if(!empty($appointment_detail)){
                                $output ="<a  class='order-view'><strong>{$text}</strong></a>";
                            }
                        }
                    }
                    echo $output;
                }, 20, 2 );
            }
        } );

    }

    public function woocommerceAddNewColumnInTable($new_column,$after_column,$columns){
        $order_column_index = array_search($after_column, array_keys($columns)) + 1;
        if(!empty($order_column_index)){
            $total_column = count($columns);
            if($total_column >= $order_column_index){
                return array_slice($columns, 0, $order_column_index, true) +
                    array($new_column['key'] => $new_column['value']) +
                    array_slice($columns, $order_column_index, $total_column - $order_column_index, true);
            }
        }
        return $columns;
    }

    public function sidebarData() {
        // Retrieve existing sidebar data for different user roles
        $administrator = get_option(KIVI_CARE_PREFIX . 'administrator_dashboard_sidebar_data');
        $clinic_admin = get_option(KIVI_CARE_PREFIX . 'clinic_admin_dashboard_sidebar_data');
        $receptionist = get_option(KIVI_CARE_PREFIX . 'receptionist_dashboard_sidebar_data');
        $doctor = get_option(KIVI_CARE_PREFIX . 'doctor_dashboard_sidebar_data');
        $patient = get_option(KIVI_CARE_PREFIX . 'patient_dashboard_sidebar_data');

        // Generate default sidebar data if not already available
        if (!$administrator) {
            $administrator = kcAdminSidebarArray();
            update_option(KIVI_CARE_PREFIX . 'administrator_dashboard_sidebar_data', $administrator);
        }
        if (!$clinic_admin) {
            $clinic_admin = kcClinicAdminSidebarArray();
            update_option(KIVI_CARE_PREFIX . 'clinic_admin_dashboard_sidebar_data', $clinic_admin);
        }
        if (!$receptionist) {
            $receptionist = kcReceptionistSidebarArray();
            update_option(KIVI_CARE_PREFIX . 'receptionist_dashboard_sidebar_data', $receptionist);
        }
        if (!$doctor) {
            $doctor = kcDoctorSidebarArray();
            update_option(KIVI_CARE_PREFIX . 'doctor_dashboard_sidebar_data', $doctor);
        }
        if (!$patient) {
            $patient = kcPatientSidebarArray();
            update_option(KIVI_CARE_PREFIX . 'patient_dashboard_sidebar_data', $patient);
        }

        // Integrate the tax module into appropriate sections of the sidebar
        if (!get_option(KIVI_CARE_PREFIX . 'tax_module_sidebar3')) {
            // Define a mapping of user roles to their respective sidebar data
            $user_sidebar = [
                KIVI_CARE_PREFIX . 'administrator_dashboard_sidebar_data' => $administrator,
                KIVI_CARE_PREFIX . 'clinic_admin_dashboard_sidebar_data' => $clinic_admin
            ];

            // Define the tax module information
            $tax_module = [
                "label" => "Taxes",
                "type" => "route",
                "link" => "tax",
                "iconClass" => "fas fa-donate",
                "routeClass" => "tax"
            ];

             // Loop through user roles and integrate the tax module into appropriate sections
            foreach ($user_sidebar as $key => &$sidebar) {
                foreach ($sidebar as $tab_key => $tab) {
                    if ($tab['link'] == "tax") {
                        // Tax module link already exists, no need to add
                        continue 2; // Skip to the next user role
                    }
                    if ($tab['link'] == "billings") {
                        // Insert the tax module information into the sidebar
                        array_splice($sidebar, $tab_key + 1, 0, [$tax_module]); // Insert after the 'billings' link
                        update_option($key, $sidebar);
                        break; // Break the loop after integration
                    }
                }
            }

            // Mark the tax module integration as completed
            update_option(KIVI_CARE_PREFIX . 'tax_module_sidebar3', 'yes');
        }
    }

    public function migrateDoctorServiceTableData() {
        // Check if the option to remove the default clinic from the service table is set
        if (!get_option(KIVI_CARE_PREFIX.'remove_default_clinic_from_service_table')) {
            $service_doctor_mapping = new KCServiceDoctorMapping();
            
            // Get all service-doctor mappings
            $all_service_doctor = $service_doctor_mapping->get_all();
            
            if (!empty($all_service_doctor)) {
                $default_clinic_id = kcGetDefaultClinicId();
                
                // Get all clinic IDs
                $all_clinics = collect((new KCClinic())->get_all(NULL, 'id'))->pluck('id')->toArray();
                
                // Get all clinic-doctor mappings grouped by doctor ID
                $all_clinic_doctor = collect((new KCDoctorClinicMapping())->get_all(NULL, 'doctor_id, clinic_id'))
                    ->groupBy('doctor_id')
                    ->map(function ($v) {
                        return collect($v)->map(function ($t) {
                            return $t->clinic_id;
                        })->toArray();
                    })->toArray();
                
                foreach ($all_service_doctor as $service) {
                    $service = (array)$service;
                    $service['extra'] = '';
                    
                    $product_id = kivicareGetProductIdOfService($service['id']);
                    
                    // Delete the corresponding product if it exists
                    if (!empty($product_id) && get_post_status($product_id)) {
                        do_action('kc_woocoomerce_service_delete', $product_id);
                        wp_delete_post($product_id);
                    }
                    
                    // Check if KiviCare Pro is active
                    if (isKiviCareProActive()) {
                        if (!empty($all_clinic_doctor[$service['doctor_id']])) {
                            $doctor_clinics = $all_clinic_doctor[$service['doctor_id']];
                            
                            // Delete the current service-doctor mapping
                            $service_doctor_mapping->delete(['id' => $service['id']]);
                            
                            unset($service['id']);
                            
                            // Insert new service-doctor mappings for each valid clinic
                            foreach ($doctor_clinics as $clinic) {
                                if (in_array($clinic, $all_clinics)) {
                                    $service['clinic_id'] = $clinic;
                                    $service_doctor_mapping->insert($service);
                                }
                            }
                        }
                    } else {
                        // Update the clinic ID and extra field for the service
                        $service_doctor_mapping->update(
                            ['clinic_id' => $default_clinic_id, 'extra' => ''],
                            ['id' => $service['id']]
                        );
                    }
                }
            }
            
            // Update the option value to indicate the migration has been performed
            update_option(KIVI_CARE_PREFIX.'remove_default_clinic_from_service_table', 'yes');
        }
    }
    public function migrateSidebar()
    {
        if (!get_option(KIVI_CARE_PREFIX . 'migrate_sidebar_item_childrens')) {
            $kc_roles_sidebar = [
                KIVI_CARE_PREFIX . 'administrator_dashboard_sidebar_data',
                KIVI_CARE_PREFIX . 'clinic_admin_dashboard_sidebar_data',
                KIVI_CARE_PREFIX . 'receptionist_dashboard_sidebar_data',
                KIVI_CARE_PREFIX . 'doctor_dashboard_sidebar_data',
            ];
            foreach ($kc_roles_sidebar as $key => $sidebar) {
                if (!empty(get_option($sidebar))) {
                    update_option($sidebar, array_map(function ($row) {
                        if ($row['link'] == "encounter-list") {
                            if (!isset($row['childrens'])) {
                                $row['childrens'] = [
                                    [
                                        'label' => esc_html__('Encounters', 'kc-lang'),
                                        'type' => 'route',
                                        'link' => 'encounter-list',
                                        'iconClass' => 'far fa-calendar-times',
                                        'routeClass' => 'patient_encounter_list',
                                    ],
                                    [
                                        'label' => esc_html__('Encounter Templates', 'kc-lang'),
                                        'type' => 'route',
                                        'link' => 'encounter-template',
                                        'iconClass' => 'far fa-calendar',
                                        'routeClass' => 'encounter_template',
                                    ],
                                ];
                                $row['link'] = "encounter";
                                $row['routeClass'] = "parent";
                                $row['type'] = "parent";
                            }
                        }
                        return $row;
                    }, get_option($sidebar)));
                }
            }
            
            update_option(KIVI_CARE_PREFIX . 'migrate_sidebar_item_childrens', true);
        }
    }
    public function iqonic_sale_banner_notice()
    {
        $type="plugins" ;
        $product="kivicare"; 
        $get_sale_detail= get_transient('iq-notice');
        if(is_null($get_sale_detail) || $get_sale_detail===false ){
            $get_sale_detail =wp_remote_get("https://assets.iqonic.design/wp-product-notices/notices.json?ver=" . wp_rand()) ;
            set_transient('iq-notice',$get_sale_detail ,3600)  ;
        }

        if (!is_wp_error($get_sale_detail) && $content = json_decode(wp_remote_retrieve_body($get_sale_detail), true)) {
            if(get_user_meta(get_current_user_id(),$content['data']['notice-id'],true)) return;
            
            $currentTime =  current_datetime();
            if (($content['data']['start-sale-timestamp']  < $currentTime->getTimestamp() && $currentTime->getTimestamp() < $content['data']['end-sale-timestamp'] )&& isset($content[$type][$product])){

            ?>
            <div class="iq-notice notice notice-success is-dismissible" style="padding: 0;">
                <a target="_blank" href="<?php echo esc_url($content[$type][$product]['sale-ink']??"#")  ?>">
                    <img src="<?php echo esc_url($content[$type][$product]['banner-img'] ??"#" )  ?>" style="object-fit: contain;padding: 0;margin: 0;display: block;" width="100%" alt="">
                </a>
                <input type="hidden" id="iq-notice-id" value="<?php echo esc_html($content['data']['notice-id']) ?>">
                <input type="hidden" id="iq-notice-nounce" value="<?php echo wp_create_nonce('iq-dismiss-notice') ?>">
            </div>
            <?php
                wp_enqueue_script('iq-admin-notice',KIVI_CARE_DIR_URI."assets/js/iq-admin-notice.js",['jquery'],false,true);
            }
        }
    }
    public function iq_dismiss_notice() {
        if(wp_verify_nonce($_GET['nounce'],'iq-dismiss-notice')){
            update_user_meta(get_current_user_id(),$_GET['key'],1);
        }
    }
    
}
