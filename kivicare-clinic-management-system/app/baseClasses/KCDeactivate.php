<?php


namespace App\baseClasses;
use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;

class KCDeactivate extends KCBase{


    public $pluginName = 'kivicare_lite';

    public function __construct() {

        //hook to check current page plugin-network page
        add_action( 'current_screen', function () {
            if(in_array( get_current_screen()->id, [ 'plugins', 'plugins-network' ] )){
                //enqueue script for feedback model when plugin deactivate
                add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_feedback_dialog_scripts' ] );
                //add model html in page footer
                add_action( 'admin_footer', [ $this, 'print_deactivate_feedback_dialog' ] );
            }
        } );
    }


    public static function deActivate () {
        //clear cronjob when plugin deactivate
        wp_clear_scheduled_hook("kivicare_patient_appointment_reminder");
    }

    public function enqueue_feedback_dialog_scripts() {

        //enqueue feedback js/css
        wp_enqueue_style( 'kivicare-lite-admin-feedback', KIVI_CARE_DIR_URI . 'assets/css/register_login.css', [], KIVI_CARE_VERSION, false );
        wp_enqueue_script('kivicare-lite-admin-feedback', KIVI_CARE_DIR_URI . '/assets/js/plugin-deactivate.js', [], KIVI_CARE_VERSION, true);
        //enqueue magnific popup js/css
        wp_enqueue_style( 'kc_popup', KIVI_CARE_DIR_URI . 'assets/css/magnific-popup.min.css', [], KIVI_CARE_VERSION, false );
        wp_enqueue_script( 'kc_popup', KIVI_CARE_DIR_URI . 'assets/js/magnific-popup.min.js', ['jquery'], KIVI_CARE_VERSION,true );
    }

    public function print_deactivate_feedback_dialog() {

        $pluginName = "kivicare";

        //deactivate reasons options array
        $deactivate_reasons = [
            'no_longer_needed' => [
                'title' => esc_html__( 'I no longer need the plugin', 'kc-lang' ),
                'input_placeholder' => '',
            ],
            'found_a_better_plugin' => [
                'title' => esc_html__( 'I found a better plugin', 'kc-lang' ),
                'input_placeholder' => esc_html__( 'Please share which plugin', 'kc-lang' ),
            ],
            'couldnt_get_the_plugin_to_work' => [
                'title' => esc_html__( 'I couldn\'t get the plugin to work', 'kc-lang' ),
                'input_placeholder' => '',
            ],
            'temporary_deactivation' => [
                'title' => esc_html__( 'It\'s a temporary deactivation', 'kc-lang' ),
                'input_placeholder' => '',
            ],
            'pro_plugin' => [
                'title' => esc_html__( "I have kivicare Pro", 'kc-lang' ),
                'input_placeholder' => '',
                'alert' => esc_html__( "Note : kivicare is a Mandatory plugin for PRO version to work", 'kc-lang' ),
            ],
            'customization_issue' => [
                'title' => esc_html__( 'Not able To customize', 'kc-lang' ),
                'input_placeholder' => esc_html__( 'Please share the where you need customization', 'kc-lang' ),
            ],
            'other' => [
                'title' => esc_html__( 'Other', 'kc-lang' ),
                'input_placeholder' => esc_html__( 'Please share the reason', 'kc-lang' ),
            ],
        ];

        //modal html content
        ?>
        <div id="<?php echo esc_html($this->pluginName);?>_feedback_modal" class="kivi-widget white-popup mfp-hide">
            <div  class="d-flex justify-content-center">
                <img class="m-1" src="<?php echo esc_js(KIVI_CARE_DIR_URI.'/assets/images/sidebar-icon.svg')?>" >
                <h3 ><?php echo esc_html__( 'Quick Feedback', 'kc-lang' ); ?></h3>
            </div>
            <form  method="post" >
                <input type="hidden" name="product_name" value="<?php echo esc_html($this->pluginName);?>" />
                <input type="hidden" name="product_version" value="<?php echo esc_html(KIVI_CARE_VERSION);?>" />
                <h4 ><?php echo esc_html__( 'If you have a moment, please share why you are deactivating Kivicare:', 'kc-lang' ); ?></h4>
                <div>
                    <?php foreach ( $deactivate_reasons as $reason_key => $reason ) : ?>
                        <div class="form-group mb-2">
                            <input id="<?php echo esc_html($this->pluginName);?>-deactivate-feedback-<?php echo esc_attr( $reason_key ); ?>"  type="radio" name="reason_key" value="<?php echo esc_attr( $reason_key ); ?>" />
                            <label for="<?php echo esc_html($this->pluginName);?>-deactivate-feedback-<?php echo esc_attr( $reason_key ); ?>" class="form-label"><?php echo esc_html( $reason['title'] ); ?></label>
                            <?php if ( ! empty( $reason['input_placeholder'] ) ) : ?>
                                <input  type="text" style="width: 100%" name="reason_<?php echo esc_attr( $reason_key ); ?>" placeholder="<?php echo esc_attr( $reason['input_placeholder'] ); ?>" readonly/>
                            <?php endif; ?>
                            <?php if ( ! empty( $reason['alert'] ) ) : ?>
                                <div><?php echo esc_html( $reason['alert'] ); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="iq-button iq-button-primary">
                    <span class="d-none">
                        <?php echo esc_html__( 'Submitting','kc-lang' ); ?>
                    </span>
                    <span>
                        <?php echo esc_html__( 'Submit & Deactivate','kc-lang' ); ?>
                    </span>
                </button>
            </form>
        </div>

        <?php
    }

}