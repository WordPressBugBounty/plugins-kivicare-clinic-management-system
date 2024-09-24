<?php


namespace App\models;

use App\baseClasses\KCModel;

class KCCustomField extends KCModel {

	public function __construct()
	{
		parent::__construct('custom_fields');
	}

    public static function kcGetCustomFields ($module_type) {

	    $fields = collect([]);
        $data =  (new self())->get_by([
            'module_type' => $module_type,
            'status' => 1
        ], '=', true);

        if (isset($data->fields)) {
            $fields = collect(json_decode($data->fields));
        }

        return $fields;
    }


    public static function getRequiredFields ($module_type) {
        $rules = [];
        $custom_fields = self::kcGetCustomFields($module_type)->where('isRequired', 1);

        if (count($custom_fields)) {
            foreach ($custom_fields as $field) {
                $rules[$field->name] = 'required';
            }
        }

        return $rules;
    }

}