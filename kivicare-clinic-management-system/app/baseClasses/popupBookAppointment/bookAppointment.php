<?php
$printConfirmPage = !empty($_GET['confirm_page']) ? sanitize_text_field(wp_unslash($_GET['confirm_page'])) : 'off';
?>
<div class="kivi-widget kivi-widget-popup">
    <div class="widget-layout">
        <button class="iq-button iq-button-primary open-kivicare-bookappopintment-popup">
            <?php echo esc_html__("Book Appointment","kc-lang")?>
        </button>
    </div>
    <span id="kivicare-main-page-loader-popup" style="background:#fff; display: flex;align-items: center;justify-content: center;">
           <div class="double-lines-spinner"></div>
    </span>
</div>
<div class="mfp-hide white-popup" id="kivi-appointment-widget"></div>
<script>
    document.addEventListener('readystatechange', event => {
        if (event.target.readyState === "complete") {
            'use strict';
            (function ($) {

                $('#kivicare-main-page-loader-popup').addClass('d-none');
                $('.open-kivicare-bookappopintment-popup').removeClass('d-none');
                var contentButtonClick = false;
                $(document).on('click','.open-kivicare-bookappopintment-popup',function(event){
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
                                $('.open-kivicare-bookappopintment-popup').text("<?php echo esc_html__('Book Appointment','kc-lang'); ?>");
                                $('.open-kivicare-bookappopintment-popup').prop('disabled',false);
                                if (response.status !== undefined && response.status === true) {
                                    contentButtonClick = true;
                                    $('#kivi-appointment-widget').append(response.data);
                                }
                            },
                            error: function () {
                                $('.open-kivicare-bookappopintment-popup').text("<?php echo esc_html__('Book Appointment','kc-lang'); ?>");
                                $('.open-kivicare-bookappopintment-popup').prop('disabled',false);
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
                                        src: $('#kivi-appointment-widget')
                                    },
                                })
                            }
                        });
                    }else{
                        $('.open-kivicare-bookappopintment-popup').text("<?php echo esc_html__('Book Appointment','kc-lang'); ?>");
                        $('.open-kivicare-bookappopintment-popup').prop('disabled',false);
                        $.magnificPopup.open({
                            showCloseBtn:true,
                            mainClass: 'mfp-fade',
                            type:'inline',
                            closeBtnInside: true,
                            fixedContentPos: true,
                            midClick: true,
                            preloader: true,
                            items: {
                                src: $('#kivi-appointment-widget')
                            },
                        })
                    }
                });
                if('<?php echo $printConfirmPage !== 'off' ;?>'){
                    $('.open-kivicare-bookappopintment-popup').click()
                }
            })(window.jQuery)
        }
    });
</script>