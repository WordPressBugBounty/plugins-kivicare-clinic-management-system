<?php

namespace App\abstracts;

use App\baseClasses\KCBase;
use Kucrut\Vite;
use function Iqonic\Vite\iqonic_enqueue_asset;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

abstract class KCShortcodeAbstract
{
    /**
     * Shortcode tag
     *
     * @var string
     */
    protected $tag;

    /**
     * Default shortcode attributes
     *
     * @var array
     */
    protected $default_attrs = [];

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
     * Entry point for the shortcode's JavaScript
     * 
     * @var string
     */
    protected $js_entry;

    /**
     * Entry point for the shortcode's CSS
     * 
     * @var string
     */
    protected $css_entry;

    /**
     * Script dependencies
     *
     * @var array
     */
    protected $script_dependencies = [];

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
    protected $in_footer = false;


    /**
     * KCShortcodeAbstract constructor.
     */

    protected KCBase $kcbase;

    protected static $is_localize_enqueued = false;
    public function __construct()
    {
        // Set the default assets directory if not overridden
        if (empty($this->assets_dir)) {
            $this->assets_dir = KIVI_CARE_DIR . '/dist';
        }
        $this->kcbase = KCBase::get_instance();

        // Register shortcode
        add_shortcode($this->tag, [$this, 'renderShortcode']);

        // Register assets
        add_action('wp_enqueue_scripts', [$this, 'registerAssets']);
    }

    /**
     * Register assets for the shortcode using Vite
     *
     * @return void
     */
    public function registerAssets()
    {
        if (empty($this->js_entry) && empty($this->css_entry)) {
            return;
        }

        // Only register assets if the shortcode is used on the page
        if (!$this->isShortcodePresent()) {
            return;
        }

        // Enqueue JS if available
        if (!empty($this->js_entry)) {
            iqonic_enqueue_asset(
                $this->assets_dir,
                $this->js_entry,
                [
                    'handle' => $this->handle_prefix . $this->tag,
                    'dependencies' => $this->script_dependencies ?? [], // Use shortcode dependencies
                    'css-dependencies' => $this->css_dependencies ?? [], // Use shortcode CSS dependencies
                    'css-media' => 'all', // Optional.
                    'css-only' => false, // Optional. Set to true to only load style assets in production mode.
                    'in-footer' => $this->in_footer ?? false, // Use shortcode footer setting
                ]
            );
        }


        // Get JED locale data and add it inline
        $locale_data_kc = kc_get_jed_locale_data('kivicare-clinic-management-system');

        if (self::$is_localize_enqueued) {
            return;
        }
        wp_localize_script($this->handle_prefix . $this->tag, 'kc_frontend', [
            'rest_url' => rest_url(),
            'home_url' => home_url(),
            'nonce' => wp_create_nonce('wp_rest'),
            'locale_data' => $locale_data_kc,
            'prefix' => KIVI_CARE_PREFIX,
            'loader_image' => KIVI_CARE_DIR_URI . 'assets/images/loader.gif',
            'place_holder_img' => KIVI_CARE_DIR_URI . 'assets/images/demo-img.png',
            'current_user_id' => get_current_user_id(),
            'date_format' => get_option('date_format'),
            'start_of_week' => get_option('start_of_week'),
        ]);
        self::$is_localize_enqueued = true;
    }

    /**
     * Check if the shortcode is present in the current page content
     *
     * @return bool
     */
    protected function isShortcodePresent()
    {
        global $post;

        if (!is_a($post, 'WP_Post')) {
            return false;
        }

        // Check if shortcode is in post content
        if (has_shortcode($post->post_content, $this->tag)) {
            return true;
        }

        // Also check if shortcode is in widgets or other areas
        if (is_active_widget(false, false, 'text') || is_active_widget(false, false, 'custom_html')) {
            return true;
        }

        return false;
    }

    /**
     * Render the shortcode HTML
     *
     * @param array|string $atts Shortcode attributes
     * @param string|null $content Shortcode content
     * @return string
     */
    public function renderShortcode($atts, $content = null)
    {
        // Parse attributes with defaults
        $atts = shortcode_atts($this->default_attrs, $atts, $this->tag);

        // Get unique ID for this instance
        $id = 'kc-' . $this->tag . '-' . uniqid();

        // Start output buffering
        ob_start();

        // Render the shortcode content
        $this->render($id, $atts, $content);

        // Return the buffered output
        return ob_get_clean();
    }

    /**
     * Render the shortcode content
     * This method must be implemented by child classes
     *
     * @param string $id Unique ID for this shortcode instance
     * @param array $atts Shortcode attributes
     * @param string|null $content Shortcode content
     * @return void
     */
    abstract protected function render($id, $atts, $content = null);

    /**
     * Get shortcode tag
     *
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }
}