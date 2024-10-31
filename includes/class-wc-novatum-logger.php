<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Log all things!
 *
 * @since 4.0.0
 * @version 4.0.0
 */
class WC_Novatum_Logger {

    public static $logger;

    /**
     * Utilize WC logger class
     *
     * @since 4.0.0
     * @version 4.0.0
     */
    public static function log( $message ) {
        define('WC_NOVATUM_LOG_FILENAME', plugin_dir_path(__FILE__) . 'debug.log');
        if ( ! class_exists( 'WC_Logger' ) ) {
            return;
        }

        if ( apply_filters( 'wc_novatum_logging', true, $message ) ) {
            if ( empty( self::$logger ) ) {
                self::$logger = wc_get_logger();
            }

            if(is_array($message)) {
                $message = json_encode($message);
            }

            $log_entry = '====Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";

            $file = fopen(WC_NOVATUM_LOG_FILENAME,"a");
            fwrite($file, "\n" . date('Y-m-d h:i:s') . " :: " . $log_entry);
            fclose($file);
        }
    }
}
