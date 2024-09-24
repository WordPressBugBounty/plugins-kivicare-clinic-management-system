<div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
    <div class="iq-kivi-tab-panel-title-animation">
        <h3 class="iq-kivi-tab-panel-title"><?php echo esc_html__("Select Service", "kc-lang"); ?></h3>
    </div>
    <div class="iq-kivi-search">
        <svg width="18" height="18" class="iq-kivi-icon" viewBox="0 0 24 24" fill="none"
             xmlns="http://www.w3.org/2000/svg">
            <circle cx="11.7669" cy="11.7666" r="8.98856" stroke="#727d93" fill="none" stroke-width="1.5"
                    stroke-linecap="round" stroke-linejoin="round"></circle>
            <path d="M18.0186 18.4851L21.5426 22" stroke="#727d93" stroke-width="1.5" stroke-linecap="round"
                  stroke-linejoin="round"></path>
        </svg>
        <input type="text" class="iq-kivicare-form-control iq-search-bg-color"
               placeholder="<?php echo esc_html__("Search....", "kc-lang"); ?>" id="serviceSearch">
    </div>
</div>
<hr>
<div class="widget-content">
    <div class="card-list-data flex-column gap-2 pe-2" id="serviceLists" name="serviceLists">
        <span class="loader-class">
            <?php if (isLoaderCustomUrl()) { ?>
                <img src="<?php echo esc_url(kcAppointmentWidgetLoader()); ?>">
            <?php } else { ?>
                <div class="double-lines-spinner"></div>
            <?php } ?>
        </span>
    </div>
</div>