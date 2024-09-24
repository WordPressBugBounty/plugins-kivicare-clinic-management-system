<html>
    <head></head>
    <body>
        <h1 class="text-center mt-3"> <?php echo esc_html__('Language files not found - (404)','kc-lang');?> </h1>
        <p class="text-center mt-3"> <b> <?php echo esc_html__('Error info :','kc-lang');?> </b><?php echo esc_html__('Directory permission issue. Your setup does not have permission on','kc-lang');?> <b> <?php echo esc_html__('wp-content/','kc-lang');?> </b> </p>
        <p class="text-center mt-3"> <b><?php echo esc_html__('Solution :','kc-lang');?>  </b><?php echo esc_html__('Create folder','kc-lang');?>  <b><?php echo esc_html__('kiviCare_lang','kc-lang');?>  </b> <?php echo esc_html__('on mentioned path ','kc-lang');?><b> <?php echo esc_html__('wp-content/uploads/','kc-lang');?> </b>  </p>
        <p class="text-center mt-3"> <?php echo esc_html__('And Copy all language json files from','kc-lang');?> <b> <?php echo esc_html__('(Plugin) kivicare-clinic-management-system/resources/assets/lang','kc-lang');?> </b> <?php echo esc_html__('and paste it to','kc-lang');?> <b> <?php echo esc_html__('wp-content/uploads/kiviCare_lang ','kc-lang');?></b> . </p>
        <br>
        <h1 class="text-center mt-3"> <?php echo esc_html__('Or','kc-lang');?> </h1>
        <p class="text-center mt-3"> <b><a href="<?php echo  esc_url(get_home_url() . '?kcEnableLocoTranslation');?>" ><?php echo esc_html__('Enable Loco Translation','kc-lang');?> </a></p>
    </body>
</html>