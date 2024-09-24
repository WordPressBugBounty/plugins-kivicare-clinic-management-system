<div class="iq-kivi-tab-panel-title-animation">
<h3 class="iq-kivi-tab-panel-title"> <?php echo esc_html__('Confirmation Detail', 'kc-lang'); ?> </h3>
</div>
<hr>
<div class="widget-content" style="position: relative">
    <div class="kivi-row card-list-data d-none" id="kivi_confirm_page" style="height: 470px; position:absolute;">
        
    </div>
    
    <span id="confirm_loader" class="kivi-overlay-spinner" style="position:absolute;top:40%;left:40%">
        <?php if (isLoaderCustomUrl()) { ?>
            <img src="<?php echo esc_url(kcAppointmentWidgetLoader()); ?>" alt="loader">
        <?php } else { ?>
            <div class="double-lines-spinner"></div>
        <?php } ?>
    </span>
</div>