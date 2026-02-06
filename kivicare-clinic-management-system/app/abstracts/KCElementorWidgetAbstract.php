<?php

namespace App\abstracts;

use App\baseClasses\KCBase;
use Elementor\Widget_Base;

use Kucrut\Vite;
use function Iqonic\Vite\iqonic_enqueue_asset;

abstract class KCElementorWidgetAbstract extends Widget_Base
{
    /**
     * Asset handle prefix
     *
     * @var string
     */
    protected $handle_prefix = KIVI_CARE_NAME;

    /**
     * Directory path where Vite assets are located
     * 
     * @var string
     */
    protected $assets_dir;

    /**
     * Entry point for the widget's JavaScript
     * 
     * @var string
     */
    protected $js_entry;

    /**
     * Entry point for the widget's CSS
     * 
     * @var string
     */
    protected $css_entry;

    /**
     * Script dependencies
     *
     * @var array
     */
    protected $script_dependencies = ['jquery'];

    /**
     * CSS dependencies
     *
     * @var array
     */
    protected $css_dependencies = [];

    /**
     * Load scripts in footer
     *
     * @var bool
     */
    protected $in_footer = true;

    /**
     * CSS media type
     *
     * @var string
     */
    protected $css_media = 'all';

    /**
     * KCBase instance
     */
    protected KCBase $kcbase;

    /**
     * Static flag to prevent duplicate localization
     */
    protected static $is_localize_enqueued = false;

    /**
     * KCElementorWidgetAbstract constructor.
     */
    public function __construct($data = [], $args = null)
    {
        parent::__construct($data, $args);

        // Set the default assets directory if not overridden
        if (empty($this->assets_dir)) {
            $this->assets_dir = KIVI_CARE_DIR . '/dist';
        }
        
        $this->kcbase = KCBase::get_instance();
    }

    /**
     * Register assets for the widget using Vite
     *
     * @return void
     */
    public function registerAssets()
    {
        if (empty($this->js_entry) && empty($this->css_entry)) {
            return;
        }

        // Enqueue JS if available
        if (!empty($this->js_entry)) {
            iqonic_enqueue_asset(
                $this->assets_dir,
                $this->js_entry,
                [
                    'handle' => $this->handle_prefix . $this->getWidgetName(),
                    'dependencies' => $this->script_dependencies,
                    'css-dependencies' => $this->css_dependencies,
                    'css-media' => $this->css_media,
                    'css-only' => false,
                    'in-footer' => $this->in_footer,
                ]
            );
        }


        // Get JED locale data and add it inline
        $locale_data_kc = kc_get_jed_locale_data('kivicare-clinic-management-system');

        if (self::$is_localize_enqueued) {
            return;
        }
        
        wp_localize_script($this->handle_prefix . $this->getWidgetName(), 'kc_frontend', [
            'rest_url' => rest_url(),
            'home_url' => home_url(),
            'nonce' => wp_create_nonce('wp_rest'),
            'locale_data' => $locale_data_kc,
            'prefix' => KIVI_CARE_PREFIX,
            'loader_image' => KIVI_CARE_DIR_URI . 'assets/images/loader.gif',
            'place_holder_img' => KIVI_CARE_DIR_URI . 'assets/images/demo-img.png',
            'current_user_id' => get_current_user_id(),
        ]);
        self::$is_localize_enqueued = true;
    }

    /**
     * Get widget name for asset handles
     * This method should be implemented by child classes
     *
     * @return string
     */
    abstract protected function getWidgetName();
}