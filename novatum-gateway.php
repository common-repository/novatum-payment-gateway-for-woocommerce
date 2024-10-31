<?php
/*
 * Plugin Name: Novatum Payment Gateway for WooCommerce
 * Plugin URI:
 * Description: Make payment through Novatum Payment Method.
 * Author: Novatum
 * Author URI: https://novatum.me/
 * Version: 1.0
 * WC requires at least: 4.9
 * WC tested up to: 5.2.*
 * Text Domain: novatum
 * Domain Path: /languages/
 */

// This action hook registers our PHP class as a WooCommerce payment gateway
add_filter('woocommerce_payment_gateways', 'novatum_add_gateway_class');

/**
 * @param $gateways
 * @return mixed
 */
function novatum_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Novatum_Gateway'; // Initialize payment gateway class
    return $gateways;
}

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action('plugins_loaded', array('NovatumPageTemplateCreator', 'get_instance'));

/**
 * Class NovatumPageTemplateCreator
 */
class NovatumPageTemplateCreator
{
    /**
     * A reference to an instance of this class.
     */
    private static $instance;

    /**
     * The array of templates that this plugin tracks.
     */
    protected $templates;

    /**
     * Initializes the plugin by setting filters and administration functions.
     */
    private function __construct()
    {
        $this->templates = array();

        // Add a filter to the attributes meta box to inject template into the cache.
        if (version_compare(floatval(get_bloginfo('version')), '4.7', '<')) {
            // 4.6 and older
            add_filter(
                'page_attributes_dropdown_pages_args',
                array($this, 'register_project_templates')
            );
        } else {
            // Add a filter to the wp 4.7 version attributes meta box
            add_filter(
                'theme_page_templates', array($this, 'add_new_template')
            );
        }

        // Add a filter to the save post to inject out template into the page cache
        add_filter(
            'wp_insert_post_data',
            array($this, 'register_project_templates')
        );

        // Add a filter to the template include to determine if the page has our
        // template assigned and return it's path
        add_filter(
            'template_include',
            array($this, 'view_project_template')
        );

        // Novatum Success/Failure/Notification Page Template.
        $this->templates = array(
            'includes/novatum-success-page-template.php' => 'Novatum Success Page',
            'includes/novatum-failure-page-template.php' => 'Novatum Failure Page',
//            'includes/novatum-notification-page-template.php' => 'Novatum Notification Page',
        );
    }

    /**
     * Returns an instance of this class.
     */
    public static function get_instance(): NovatumPageTemplateCreator
    {
        if (null == self::$instance) {
            self::$instance = new NovatumPageTemplateCreator();
        }
        return self::$instance;
    }

    /**
     * Adds our template to the page dropdown for v4.7+
     * @param $posts_templates
     * @return array
     */
    public function add_new_template($posts_templates): array
    {
        $posts_templates = array_merge($posts_templates, $this->templates);
        return $posts_templates;
    }

    /**
     * Adds our template to the pages cache in order to trick WordPress
     * into thinking the template file exists where it doesn't really exist.
     *
     * @param $attributes
     * @return mixed
     */
    public function register_project_templates($attributes)
    {
        // Create the key used for the themes cache
        $cache_key = 'page_templates-' . md5(get_theme_root() . '/' . get_stylesheet());

        // Retrieve the cache list.
        // If it doesn't exist, or it's empty prepare an array.
        $templates = wp_get_theme()->get_page_templates();
        if (empty($templates)) {
            $templates = array();
        }

        // New cache, therefore remove the old one
        wp_cache_delete($cache_key, 'themes');

        // Now add our template to the list of templates by merging our templates
        // with the existing templates array from the cache.
        $templates = array_merge($templates, $this->templates);

        // Add the modified cache to allow WordPress to pick it up for listing
        // available templates
        wp_cache_add($cache_key, $templates, 'themes', 1800);

        return $attributes;
    }

    /**
     * Checks if the template is assigned to the page
     * @param $template
     * @return string
     */
    public function view_project_template($template)
    {
        // Return the search template if we're searching (instead of the template for the first result)
        if (is_search()) {
            return $template;
        }

        // Get global post
        global $post;

        // Return template if post is empty
        if (!$post) {
            return $template;
        }

        // Return default template if we don't have a custom one defined
        if (!isset($this->templates[get_post_meta(
                $post->ID, '_wp_page_template', true
            )])) {
            return $template;
        }

        // Allows filtering of file path
        $filepath = apply_filters('page_templater_plugin_dir_path', plugin_dir_path(__FILE__));

        $file = $filepath . get_post_meta(
                $post->ID, '_wp_page_template', true
            );

        // Just to be safe, we check if the file exist first
        if (file_exists($file)) {
            return $file;
        } else {
            echo $file;
        }

        // Return template
        return $template;
    }
}

// Plugin defined function:
function woocommerce_novatum_admin_scripts() {
    wp_enqueue_script('woocommerce_novatum', plugins_url('assets/novatum_admin.js', __FILE__), [], 1.0, 'true');
}
// Add the functions to Admin loading list.
add_action( 'admin_enqueue_scripts', 'woocommerce_novatum_admin_scripts' );


// Plugin defined function:
function woocommerce_novatum_checkout_scripts() {
    wp_enqueue_script('woocommerce_novatum', plugins_url('assets/novatum_checkout.js', __FILE__), [], 1.0, 'true');
}
// Add the functions to WP loading list.
add_action( 'wp_enqueue_scripts', 'woocommerce_novatum_checkout_scripts' );

/******************************
 * Settings link for plugin
 ******************************/
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'woocommerce_novatum_plugin_settings_link' );

function woocommerce_novatum_plugin_settings_link( $links ) {
    $links[] = '<a href="'. esc_url( get_admin_url(null, 'options-general.php?page=wc-settings&tab=checkout') ) .'">Settings</a>';
    return $links;
}



/* Register activation hook. */
register_activation_hook( __FILE__, 'woccommerce_novatum_admin_notice_example_activation_hook' );

/**
 * Runs only when the plugin is activated.
 * @since 0.1.0
 */
function woccommerce_novatum_admin_notice_example_activation_hook() {

    /* Create transient data */
    set_transient( 'woocommerce-novatum-admin-notice', true, 5 );
}


/**
 * Admin Notice on Activation.
 * @since 0.1.0
 */
function woocommerce_novatum_admin_notice_notice(){

    /* Check transient, if available display notice */
    if( get_transient( 'woocommerce-novatum-admin-notice' ) ){
        ?>
        <div class="updated notice is-dismissible">
            <p>Thank you for using Novatum plugin! <strong>Please rate us if you like this plugin</strong>.</p>
        </div>
        <?php
        /* Delete transient, only display this notice once. */
        delete_transient( 'woocommerce-novatum-admin-notice' );
    }
}
/* Add admin notice */
add_action( 'admin_notices', 'woocommerce_novatum_admin_notice_notice' );


// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action('plugins_loaded', 'sun_cart_store_novatum_init');

/**
 * Include Payment gateway additional class files.
 */
function sun_cart_store_novatum_init()
{
    define('WC_NOVATUM_MAIN_FILE', __FILE__);
    // If the parent WC_Payment_Gateway class doesn't exist
    // it means WooCommerce is not installed on the site
    // so do nothing
    if (!class_exists('WC_Payment_Gateway')) return;

    //Include your logger code file.
    include_once('includes/class-wc-novatum-logger.php');

    // If we made it this far, then include our Gateway Class
    include_once('credit-card/class-novatum-payment-gateway-cc.php');
}
