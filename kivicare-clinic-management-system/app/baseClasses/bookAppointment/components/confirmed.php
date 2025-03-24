<?php
$url = $popup ? (string)wp_get_referer() : false;
$paymentComplete = true;
$appointment_id = !empty($_GET['confirm_page']) ? sanitize_text_field(wp_unslash($_GET['confirm_page'])) : 'off';
if(kcWoocommercePaymentGatewayEnable() === 'on' && $appointment_id !== 'off'){
    $appointment_id = (int)$appointment_id;
    $order_id = kcAppointmentIsWoocommerceOrder($appointment_id);
    if(!empty($order_id)){
        $order = wc_get_order( $order_id );
        $paymentComplete = false;
        if($order->get_status() === 'completed'){
            $paymentComplete = true;
        }
    }
}
?>
<div class="text-center">
    <div class="my-4 d-flex justify-content-center">
        <svg width="100" height="100" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="50" r="48.5" fill="#13C39C" stroke="#25FFAE" stroke-width="3"/>
            <path fill-rule="evenodd" clip-rule="evenodd"
                  d="M75.1743 34.1417L46.514 69.977L24 51.2131L28.2479 46.1156L45.5582 60.5386L69.9971 30L75.1743 34.1417Z"
                  fill="white"/>
        </svg>
    </div>
    <div>
        <h2><?php echo esc_html__('Your Appointment is Booked Sucessfully!','kc-lang');?></h2>
        <?php if($paymentComplete){
            ?>
            <h6 class="iq-color-body my-3 fw-normal kc-check-email"><?php echo esc_html__('Please check your email for verification','kc-lang');?></h6>
            <?php
        }else{
            ?>
            <h6 class="iq-color-body my-3 fw-normal kc-check-email"><?php echo esc_html__('Verification email will receive after payment complete','kc-lang');?></h6>
            <?php
        }?>
    </div>
    <hr class="my-4 kc-confirmation-hr">
    <div class="d-flex flex-wrap gap-1 justify-content-center kc-confirmation-buttons">
        <?php 
            $paramsToRemove = ['confirm_page','kivicare_payment', 'appointment_id', 'paymentId', 'token', 'PayerID', 'kivicare_stripe_payment'];
        ?>
        <a href="<?php echo esc_url(remove_query_arg($paramsToRemove,$url)); ?>">
            <button type="button"
                    class="iq-button iq-button-primary"><?php echo esc_html__('Book More Appointments', 'kc-lang'); ?>
            </button>
        </a>
        <?php
        if(kcGetSingleWidgetSetting('widget_print')){
            ?>
            <button type="button" id='kivicare_print_detail'
                    class="iq-button iq-button-secondary d-none"><?php echo esc_html__('Print Detail', 'kc-lang'); ?>
            </button>
            <?php
        }?>
        <button type="button" class="iq-button iq-button-primary d-none"  id="kivicare_add_to_calendar">
            <?php echo esc_html__('Add To Calendar', 'kc-lang'); ?>
        </button>
        <add-to-calendar-button
            name="Title"
            options="'Apple','Google'"
            location="World Wide Web"
            startDate="2024-03-15"
            endDate="2024-03-15"
            startTime="10:15"
            endTime="23:30"
            timeZone="America/Los_Angeles"
        ></add-to-calendar-button>
    </div>
</div>