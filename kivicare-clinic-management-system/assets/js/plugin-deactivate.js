(function ($) {
    document.addEventListener('readystatechange', event => {
        if (event.target.readyState === "complete") {
            'use strict';
            const pluginName = 'kivicare_lite'
            const apiUrl = 'https://wordpress.iqonic.design/product/main/feedback-api/wp-json/iqonic-product/v1/';
            const deactivateEle = $('#the-list').find('[data-slug="kivicare-clinic-management-system"] span.deactivate a');

            deactivateEle.on('click',function(event){
                event.preventDefault();
                $.magnificPopup.open({
                    showCloseBtn:true,
                    mainClass: 'mfp-fade',
                    type:'inline',
                    closeBtnInside: true,
                    fixedContentPos: true,
                    midClick: true,
                    preloader: true,
                    items: {
                        src: $("#"+pluginName+"_feedback_modal")
                    },
                })
            });

            $("#"+pluginName+"_feedback_modal form input[type=radio]").on('change',function(event){
                event.preventDefault();
                let allTextField = $("#"+pluginName+"_feedback_modal form input[type=text]");
                if(allTextField.length > 0){
                    allTextField.prop('readonly', true)
                }
                if($(this).is(':checked')){
                    let radioTextField = $(this).parent().find('input[type=text]');
                    if(radioTextField.length > 0){
                        radioTextField.prop('readonly', false)
                    }
                }
            })

            $("#"+pluginName+"_feedback_modal form").on('submit',function(event){
                let buttonTextFirst = $(this).find("button[type=submit] span:nth-child(1)")
                let buttonTextSecond = $(this).find(" button[type=submit] span:nth-child(2)")
                event.preventDefault();
                $(this).find("button[type=submit]").prop('disabled',true);
                buttonTextFirst.removeClass('d-none');
                buttonTextSecond.addClass('d-none');

                jQuery.ajax({
                    url: apiUrl+'feedback',
                    type: "post",
                    timeout: 30000,
                    data: jQuery(this).serialize(),
                    success: function(response) {
                        $(this).find("button[type=submit]").prop('disabled',false);
                        buttonTextFirst.addClass('d-none');
                        buttonTextSecond.removeClass('d-none');
                        location.href = deactivateEle.attr('href');
                    },
                    error: function (error) {
                        console.log(error);
                        location.href = deactivateEle.attr('href');
                    }
                });
            });
        }
    })
})(jQuery);