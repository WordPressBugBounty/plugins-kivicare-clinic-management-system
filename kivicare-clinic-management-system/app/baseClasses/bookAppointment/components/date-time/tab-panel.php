<div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
    <div class="iq-kivi-tab-panel-title-animation">
        <h3 class="iq-kivi-tab-panel-title"> <?php echo esc_html__('Select Date and Time', 'kc-lang' ); ?> </h3>
    </div>
    <?php

    $timezone_string = get_option( 'timezone_string' );
   
	if ( $timezone_string ) {
		$timezone = $timezone_string;
	}
    else{
        $offset  = (float) get_option( 'gmt_offset' );
        $hours   = (int) $offset;
        $minutes = ( $offset - $hours );

        $sign      = ( $offset < 0 ) ? '-' : '+';
        $abs_hour  = abs( $hours );
        $abs_mins  = abs( $minutes * 60 );
        $tz_offset = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );
        $timezone = 'UTC' . $tz_offset;
    }

   
    ?>
    <div id="iq_kivi_timezone">
        <span style="font-weight: bold; font-size: 0.9rem;"><?php echo esc_html__('Time Zone: ', 'kc-lang'); ?></span><?php echo $timezone; ?>
    </div>
    <hr>
</div>
<div class="widget-content">    
    <div class="d-grid grid-template-2 card-list-data iq-kivi-calendar-slot" id="datepicker-grid">
        <input type="hidden" class="inline-flatpicker iq-inline-datepicker d-none" style="display:none">
        <div class="time-slots" id="time-slot">
            <div class="iq-card iq-bg-primary-light text-center" style="min-height: 100%; height:400px">
                <h5 id="selectedDate" name="selectedDate">
                    <?php echo esc_html__('Available time slots', 'kc-lang'); ?>
                </h5>
                <div class="grid-template-3 iq-calendar-card" id="timeSlotLists" name="timeSlotLists" style="height:100%">

                    <p class="loader-class"><?php echo esc_html__('Please Select Date','kc-lang');?></p>
                </div>
            </div>
        </div>
        <span class="d-none" id="selectedAppointmentDate">
        </span>
    </div>
    <div class="doctor-session-error loader-class">
        <p class="">
            <?php echo esc_html__('Select doctor session is not available with selected clinic, please select other doctor or other clinic','kc-lang'); ?>
        </p>
    </div>
    <span class="loader-class doctor-session-loader" id="doctor_loader">
        <?php  if(isLoaderCustomUrl()){?>
            <img src="<?php echo esc_url(kcAppointmentWidgetLoader()); ?>" alt="loader">
        
        <?php }else{  
            ?>   
            <div class="double-lines-spinner"></div>
        <?php } ?>
    </span>
</div>
