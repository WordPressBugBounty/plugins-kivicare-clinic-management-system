<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use WP_User;

class KCLanguageController extends KCBase
{
    /**
     * @var KCRequest
     */
    private $request;

    public function __construct()
    {
        $this->request = new KCRequest();

        parent::__construct();

		if($this->getLoginUserRole() !== 'administrator'){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
    }



    public function editConfig(){
        $response = apply_filters('kcpro_edit_sms_config', [
            'current_user' => get_current_user_id(),
        ]);
	    wp_send_json($response);
    }
    
    public function getJosnFile(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_get_json_file_data', [
            'fileUrl'=> $request_data['filePath'],
            'currentFile'=> $request_data['current']
        ]);
	    wp_send_json($response);
    }
    public function saveJsonData(){
        $request_data = $this->request->getInputs();
        if(count($request_data['data']) == 0) {
            $upload_dir = wp_upload_dir();
            $dir_name = KIVI_CARE_PREFIX.'lang';
            $user_dirname = $upload_dir['basedir'] . '/' . $dir_name;
            $current_file = $user_dirname.'/temp.json';
            $request_data['data'] = json_decode(file_get_contents($current_file), TRUE);
        }
        $response = apply_filters('kcpro_save_json_file_data', [
            'jsonData'=> $request_data['data'],
            'filename'=>$request_data['file_name'],
            'langName'=>$request_data['langTitle']
        ]);
	    wp_send_json($response);
    }
    public function getAllLang(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_get_all_lang', []);
	    wp_send_json($response);
    }
    public function saveLocoTranslate(){
        $request_data = $this->request->getInputs();
        $status = update_option(KIVI_CARE_PREFIX.'locoTranslateState', $request_data['locoState']);
        if($status){
            $response = [
                'status' => true,
                'message' => esc_html__('Loco Translation setting saved successfully', 'kc-lang'),
            ];
        }else{
            $response = [   
                'status' => false,
                'message' => esc_html__('Failed to update Loco Translation setting', 'kc-lang'),
            ];
        }
	    wp_send_json($response);
    }
    public function getLocoTranslate(){
        $request_data = $this->request->getInputs();
        $status = get_option(KIVI_CARE_PREFIX.'locoTranslateState', $request_data['locoState']);
        $response = [
            'status' => true,
            'data' => $status === 1 || $status === '1' ? 1 : 0,
            'message' => esc_html__('Loco Translation setting data', 'kc-lang'),
        ];
	    wp_send_json($response);
    }

    public function iUnderstand(){
        $request_data = $this->request->getInputs();
        $status = update_option(KIVI_CARE_PREFIX.'i_understnad_loco_translate', true);
        $response = [
            'status' => true,
            'data' => $status === 1 || $status === '1' ? 1 : 0,
            'message' => esc_html__('Loco Translation setting data', 'kc-lang'),
        ];
	    wp_send_json($response);
    }

    public function updateLang(){
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_update_language', [
            'user_id' => $request_data['user_id'],
            'lang' => $request_data['lang'],
        ]);
	    wp_send_json($response);
    }
}
