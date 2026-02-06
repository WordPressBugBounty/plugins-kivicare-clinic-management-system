<?php

namespace App\controllers;

use App\baseClasses\KCModuleRegistry;
use App\controllers\api\frontend\KCBookAppoinmentShortcode;
use App\controllers\api\SettingsController\CustomNotification;
use App\interfaces\KCIController;
use App\controllers\api\DashboardController;
use App\controllers\api\AppointmentsController;
use App\controllers\api\DoctorSessionController;
use App\controllers\api\EncounterController;
use App\controllers\api\AuthController;
use App\controllers\api\ClinicController;
use App\controllers\api\ConfigController;
use App\controllers\api\DoctorController;
use App\controllers\api\PatientController;
use App\controllers\api\StaticDataController;
use App\controllers\api\ReceptionistsController;
use App\controllers\api\DoctorServiceController;
use App\controllers\api\SystemNoticesController;
use App\controllers\api\PrescriptionController;
use App\controllers\api\MedicalHistoryController;
use App\controllers\api\ClinicScheduleController;
use App\controllers\api\BugReportController;

use App\controllers\api\SetupWizardController;
use App\controllers\api\SettingsController\AppointmentSetting;
use App\controllers\api\SettingsController\Configurations;
use App\controllers\api\SettingsController\CustomFields;
use App\controllers\api\SettingsController\EmailTemplate;
use App\controllers\api\SettingsController\General;
use App\controllers\api\SettingsController\GoogleEventTemplate;
use App\controllers\api\SettingsController\HolidayList;
use App\controllers\api\SettingsController\ListingData;
use App\controllers\api\SettingsController\PatientSetting;
use App\controllers\api\SettingsController\Payment;
use App\controllers\api\SettingsController\WidgetSetting;
use App\controllers\api\KCPrintInvoiceController;
use App\controllers\api\BillController;
use App\baseClasses\KCErrorLogger;

defined("ABSPATH") or die("Something went wrong");

/**
 * Class KCRestAPI
 *
 * Main REST API controller that initializes all module-specific API controllers
 *
 * @package App\controllers
 * @since 4.0.0
 */
class KCRestAPI
{
    /**
     * Module registry instance
     *
     * @var KCModuleRegistry
     */
    private KCModuleRegistry $moduleRegistry;

    /**
     * Array of controller instances
     *
     * @var KCIController[]
     */
    private $controllers = [];


    private static ?KCRestAPI $instance = null;

    /**
     * Initialize REST API functionality
     */
    public function __construct()
    {
        $this->moduleRegistry = KCModuleRegistry::getInstance();

        // Register core modules
        $this->registerCoreModules();
        // Allow plugins to register additional modules
        do_action("kivicare_register_modules", $this->moduleRegistry);

        // Wait for WordPress init before setting up REST API
        add_action("rest_api_init", [$this, "initializeControllers"]);
    }


    public static function get_instance(): KCRestAPI|null
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register all core module controllers
     *
     * @return void
     */
    private function registerCoreModules()
    {
        // Dashboard Module
        $this->moduleRegistry
            ->registerModule("dashboard")
            ->registerModuleController(
                "dashboard",
                "base",
                DashboardController::class,
            );

             // SrtupWizard Module
        $this->moduleRegistry
            ->registerModule("setupwizard")
            ->registerModuleController(
                "setupwizard",
                "setupwizard",
                SetupWizardController::class,
            );

        // Auth Module
        $this->moduleRegistry
            ->registerModule("auth")
            ->registerModuleController("auth", "base", AuthController::class);

        // Patient Module
        $this->moduleRegistry
            ->registerModule("patients")
            ->registerModuleController(
                "patients",
                "base",
                PatientController::class,
            );

        // Doctor Module
        $this->moduleRegistry
            ->registerModule("doctors")
            ->registerModuleController(
                "doctors",
                "base",
                DoctorController::class,
            )
            ->registerModuleController(
                "doctors",
                "services",
                DoctorServiceController::class,
            )
            ->registerModuleController(
                "doctors",
                "sessions",
                DoctorSessionController::class,
            );

        // Clinic Module
        $this->moduleRegistry
            ->registerModule("clinic")
            ->registerModuleController(
                "clinic",
                "base",
                ClinicController::class,
            )
            ->registerModuleController(
                "clinic",
                "schedule",
                ClinicScheduleController::class,
            );

        // Appointments Module
        $this->moduleRegistry
            ->registerModule("appointments")
            ->registerModuleController(
                "appointments",
                "base",
                AppointmentsController::class,
            )
            ->registerModuleController(
                "appointments",
                "print_invoice",
                KCPrintInvoiceController::class,
            );

        // Medical Records Module
        $this->moduleRegistry
            ->registerModule("medical")
            ->registerModuleController(
                "medical",
                "encounters",
                EncounterController::class,
            )
            ->registerModuleController(
                "medical",
                "prescriptions",
                PrescriptionController::class,
            )
            ->registerModuleController(
                "medical",
                "history",
                MedicalHistoryController::class,
            );



        // Staff Module
        $this->moduleRegistry
            ->registerModule("staff")
            ->registerModuleController(
                "staff",
                "receptionists",
                ReceptionistsController::class,
            );

        // System Module
        $this->moduleRegistry
            ->registerModule("system")
            ->registerModuleController(
                "system",
                "config",
                ConfigController::class,
            )
            ->registerModuleController(
                "system",
                "static_data",
                StaticDataController::class,
            )
            ->registerModuleController(
                "settsysteming",
                "notices",
                SystemNoticesController::class,
            )
            ->registerModuleController(
                "system",
                "bug_report",
                BugReportController::class,
            );


        // Setting Module
        $this->moduleRegistry
            ->registerModule("setting")
            ->registerModuleController(
                "setting",
                "general",
                General::class,
            )
            ->registerModuleController(
                "setting",
                "configuration",
                Configurations::class,
            )
            ->registerModuleController(
                "setting",
                "patient",
                PatientSetting::class,
            )
            ->registerModuleController(
                "setting",
                "widget",
                WidgetSetting::class,
            )
            ->registerModuleController(
                "setting",
                "google_event",
                GoogleEventTemplate::class,
            )
            ->registerModuleController(
                "setting",
                "appoinment",
                AppointmentSetting::class,
            )
            ->registerModuleController(
                "setting",
                "holiday_list",
                HolidayList::class,
            )
            ->registerModuleController(
                "setting",
                "listing_data",
                ListingData::class,
            )
            ->registerModuleController(
                "setting",
                "custom_fields",
                CustomFields::class,
            )
            ->registerModuleController(
                "setting",
                "payment",
                Payment::class,
            )
            ->registerModuleController(
                "setting",
                "email_template",
                EmailTemplate::class,
            )
            ->registerModuleController(
                "setting",
                "notification",
                CustomNotification::class,
            );

        $this->moduleRegistry->registerModule("frontend")
            ->registerModuleController(
                "frontend",
                "kc_book_appointment",
                KCBookAppoinmentShortcode::class,
            );
        $this->moduleRegistry->registerModule("bill")
            ->registerModuleController(
                "bill",
                "base",
                BillController::class,
            );
    }

    /**
     * Initialize all registered controllers
     *
     * @return void
     */
    public function initializeControllers()
    {
        $controllers = $this->moduleRegistry->getAllControllers();

        foreach ($controllers as $key => $controllerClass) {
            // Allow plugins to override controller class
            $controllerClass = apply_filters(
                "kivicare_controller_{$key}",
                $controllerClass,
            );

            $controller = new $controllerClass();
            if ($controller instanceof KCIController) {
                $this->controllers[$key] = $controller;

                // Allow modifying route registration
                // KCErrorLogger::instance()->error("kivicare_before_register_routes_{$key}");
                $registerRoutes = apply_filters(
                    "kivicare_before_register_routes_{$key}",
                    true,
                    $controller,
                );

                if ($registerRoutes) {
                    $controller->registerRoutes();
                    do_action(
                        "kivicare_after_register_routes_{$key}",
                        $controller,
                    );
                }
            }
        }

        /**
         * Action after all controllers are initialized
         *
         * @param array $controllers Array of controller instances
         * @param KCRestAPI $this Current KCRestAPI instance
         */
        do_action("kivicare_after_controllers_init", $this->controllers, $this);
    }

    /**
     * Get a specific controller instance
     *
     * @param string $key Controller key
     * @return KCIController|null Controller instance or null if not found
     */
    public function getController($key)
    {
        return $this->controllers[$key] ?? null;
    }

    /**
     * Get all registered controllers
     *
     * @return KCIController[] Array of controller instances
     */
    public function getControllers()
    {
        return $this->controllers;
    }
}