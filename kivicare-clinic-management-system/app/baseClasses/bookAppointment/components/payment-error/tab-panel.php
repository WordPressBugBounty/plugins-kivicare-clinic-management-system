<div class="widget-content">
    <div class="card-list-data text-center pt-2 pe-2 mb-3 d-flex align-items-center flex-column justify-content-center ">
        <img src="<?php echo esc_url(KIVI_CARE_DIR_URI . 'assets/images/payment-error.svg');?>" />
        <h2 class="mt-3">
            <?php echo esc_html__("Payment Transaction Failed. Please, try again.","kc-lang"); ?>
        </h2>
    </div>
</div>
<div class="card-widget-footer">
    <div class="d-flex justify-content-end gap-1-5 " style="margin-left: auto;">
        <a href="<?php echo esc_url(get_permalink()); ?>">
            <button type="button" class="iq-button iq-button-primary"
                    ><?php echo esc_html__('Try Again', 'kc-lang'); ?>
            </button>
        </a>
    </div>
</div>