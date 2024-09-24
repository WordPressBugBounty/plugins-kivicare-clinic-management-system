<div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
<div class="iq-kivi-tab-panel-title-animation">
    <h3 class="iq-kivi-tab-panel-title"><?php echo esc_html__("More About Appointment", "kc-lang"); ?></h3>
    </div>
</div>
<hr>
<div class="widget-content">
    
    <div class="card-list-data pt-2 pe-2 mb-3">
    <?php if (kcCheckExtraTabConditionInAppointmentWidget('description')) {
        ?>
        <div id="appointment-descriptions" >
            <div class="form-group mb-2">
                <label class="form-label"
                       for="appointment-descriptions-field"> <?php echo esc_html__('Appointment Descriptions', 'kc-lang'); ?>
                </label>
                <textarea class="iq-kivicare-form-control"
                          id="appointment-descriptions-field"
                          placeholder="<?php echo esc_html__('Enter Appointment Descriptions', 'kc-lang'); ?>"></textarea>
            </div>
        </div>
        <?php
    } ?>
    <div>
        <div id="file-upload" >
            <?php
            if(kcAppointmentMultiFileUploadEnable()){
                ?>
                <div class="form-group mb-2 ">
                    <label class="form-label" for="addMedicalReport"> <?php echo esc_html__('Add Medical Report', 'kc-lang'); ?>
                    </label>
                    <input type="file" name="file_multi[]" class="iq-kivicare-form-control" id="kivicareaddMedicalReport"
                        placeholder="<?php echo esc_html__('Add Your Medical Report', 'kc-lang'); ?>"  <?php echo esc_html( isKiviCareProActive() ? 'multiple' : '');?> >
                </div>
                <div id="kivicare_file_upload_review">
                </div>
                <?php
            }
            ?>
        </div>
        <div  id="customFieldsListAppointment">

        </div>
    </div>
    </div>
</div>