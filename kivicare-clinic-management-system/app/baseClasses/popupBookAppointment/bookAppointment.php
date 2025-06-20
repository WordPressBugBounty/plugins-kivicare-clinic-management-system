<?php
$printConfirmPage = !empty($_GET['confirm_page']) ? sanitize_text_field(wp_unslash($_GET['confirm_page'])) : 'off';
$elementId = wp_generate_uuid4();
?>
<div class="kivi-widget kivi-widget-popup">
    <div class="widget-layout">
        <button class="iq-button iq-button-primary open-kivicare-bookappopintment-popup-<?php echo esc_attr($elementId); ?>">
            <?php echo esc_html__("Book Appointment","kc-lang")?>
        </button>
    </div>
    <span id="kivicare-main-page-loader-popup-<?php echo esc_attr($elementId); ?>" style="background:#fff; display: flex;align-items: center;justify-content: center;">
           <div class="double-lines-spinner"></div>
    </span>
</div>
<div class="mfp-hide white-popup" id="kivi-appointment-widget-<?php echo esc_attr($elementId); ?>"></div>
<script>
    document.addEventListener('readystatechange', event => {
        if (event.target.readyState === "complete") {
            'use strict';
            (function ($) {

                $('#kivicare-main-page-loader-popup-<?php echo esc_attr($elementId); ?>').addClass('d-none');
                $('.open-kivicare-bookappopintment-popup-<?php echo esc_attr($elementId); ?>').removeClass('d-none');
                var contentButtonClick = false;
                $(document).on('click','.open-kivicare-bookappopintment-popup-<?php echo esc_attr($elementId); ?>',function(event){
                    event.preventDefault();
                    $(this).text("<?php echo esc_html__('Loading...','kc-lang'); ?>");
                    $(this).prop('disabled',true);
                    if(!contentButtonClick){
                        jQuery.ajax({
                            url: '<?php echo esc_js(admin_url('admin-ajax.php'));?>',
                            type: "get",
                            dataType: "json",
                            data: {
                                action: "ajax_get",
                                _ajax_nonce:'<?php echo esc_js(wp_create_nonce('ajax_get'));?>',
                                route_name:'render_shortcode',
                                confirm_page:'<?php echo esc_html($printConfirmPage);?>'
                            },
                            success: function (response) {
                                $('.open-kivicare-bookappopintment-popup-<?php echo esc_attr($elementId); ?>').text("<?php echo esc_html__('Book Appointment','kc-lang'); ?>");
                                $('.open-kivicare-bookappopintment-popup-<?php echo esc_attr($elementId); ?>').prop('disabled',false);
                                if (response.status !== undefined && response.status === true) {
                                    contentButtonClick = true;
                                    $('#kivi-appointment-widget-<?php echo esc_attr($elementId); ?>').append(response.data);
                                }
                            },
                            error: function () {
                                $('.open-kivicare-bookappopintment-popup-<?php echo esc_attr($elementId); ?>').text("<?php echo esc_html__('Book Appointment','kc-lang'); ?>");
                                $('.open-kivicare-bookappopintment-popup-<?php echo esc_attr($elementId); ?>').prop('disabled',false);
                                console.log('fail');
                            },
                            complete(){
                                $.magnificPopup.open({
                                    showCloseBtn:true,
                                    mainClass: 'mfp-fade',
                                    type:'inline',
                                    closeBtnInside: true,
                                    fixedContentPos: true,
                                    midClick: true,
                                    preloader: true,
                                    items: {
                                        src: $('#kivi-appointment-widget-<?php echo esc_attr($elementId); ?>')
                                    },
                                })
                            }
                        });
                    }else{
                        $('.open-kivicare-bookappopintment-popup-<?php echo esc_attr($elementId); ?>').text("<?php echo esc_html__('Book Appointment','kc-lang'); ?>");
                        $('.open-kivicare-bookappopintment-popup-<?php echo esc_attr($elementId); ?>').prop('disabled',false);
                        $.magnificPopup.open({
                            showCloseBtn:true,
                            mainClass: 'mfp-fade',
                            type:'inline',
                            closeBtnInside: true,
                            fixedContentPos: true,
                            midClick: true,
                            preloader: true,
                            items: {
                                src: $('#kivi-appointment-widget-<?php echo esc_attr($elementId); ?>')
                            },
                        })
                    }
                });
                if('<?php echo $printConfirmPage !== 'off' ;?>'){
                    $('.open-kivicare-bookappopintment-popup-<?php echo esc_attr($elementId); ?>').click();
                }
            })(window.jQuery)
        }
    });
</script>