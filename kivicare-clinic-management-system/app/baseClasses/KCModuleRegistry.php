<?php

namespace App\baseClasses;

class KCModuleRegistry
{
    private static $instance = null;
    private $modules = [];
    private $controllers = [];

    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function registerModule($moduleId, $config = [])
    {
        $defaultConfig = [
            'controllers' => [],
            'permissions' => [],
            'settings' => []
        ];

        $this->modules[$moduleId] = wp_parse_args($config, $defaultConfig);

        // Allow other plugins to modify module configuration
        $this->modules[$moduleId] = apply_filters("kivicare_module_{$moduleId}_config", $this->modules[$moduleId]);

        return $this;
    }

    public function registerModuleController($moduleId, $controllerId, $controllerClass)
    {
        if (!isset($this->modules[$moduleId])) {
            $this->registerModule($moduleId);
        }

        $this->modules[$moduleId]['controllers'][$controllerId] = $controllerClass;

        // Allow other plugins to override/modify controller class
        $controllerClass = apply_filters(
            "kivicare_module_{$moduleId}_controller_{$controllerId}",
            $controllerClass
        );

        $this->controllers[$moduleId . '/' . $controllerId] = $controllerClass;

        return $this;
    }

    public function getModuleControllers($moduleId)
    {
        return isset($this->modules[$moduleId]['controllers'])
            ? $this->modules[$moduleId]['controllers']
            : [];
    }

    public function getAllControllers()
    {
        return $this->controllers;
    }
}
