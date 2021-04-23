<?php

namespace WeDevs\DokanPro\Modules\SPMV;

class Module {

    /**
     * Load automatically when class initiate
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->define();

        $this->includes();

        $this->initiate();

        $this->hooks();

        add_action( 'dokan_activated_module_spmv', array( self::class, 'activate' ) );
        //Prevent Duplicate SKU for multiple save from various vendor
        add_action( 'woocommerce_product_duplicate_before_save', [ $this, 'prevent_duplicate_sku' ], 10, 2 );
    }

    /**
     * Hooks
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function define() {
        define( 'DOKAN_SPMV_DIR', dirname( __FILE__ ) );
        define( 'DOKAN_SPMV_INC_DIR', DOKAN_SPMV_DIR . '/includes' );
        define( 'DOKAN_SPMV_ASSETS_DIR', plugins_url( 'assets', __FILE__ ) );
        define( 'DOKAN_SPMV_VIEWS', DOKAN_SPMV_DIR . '/views' );
    }

    /**
     * Includes all necessary class a functions file
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function includes() {
        require_once DOKAN_SPMV_INC_DIR . '/functions.php';
        require_once DOKAN_SPMV_INC_DIR . '/product-duplicator.php';

        if ( is_admin() ) {
            require_once DOKAN_SPMV_INC_DIR . '/admin.php';
            require_once DOKAN_SPMV_INC_DIR . '/products-admin.php';
        }

        require_once DOKAN_SPMV_INC_DIR . '/products.php';
        require_once DOKAN_SPMV_INC_DIR . '/product-visibility.php';
    }

    /**
     * Initiate all classes
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function initiate() {
        if ( is_admin() ) {
            new \Dokan_SPMV_Admin();
        }

        new \Dokan_SPMV_Products();
    }

    /**
     * Init all hooks
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hooks() {
        $enable_option = dokan_get_option( 'enable_pricing', 'dokan_spmv', 'off' );

        if ( 'off' === $enable_option ) {
            return;
        }

        if ( is_admin() ) {
            new \Dokan_SPMV_Products_Admin();
        }

        new \Dokan_SPMV_Product_Visibility();
    }

    /**
     * Create Mapping table for product and vendor
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function activate() {
        global $wpdb;

        $sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}dokan_product_map` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `map_id` bigint(20) DEFAULT NULL,
                `product_id` bigint(20) DEFAULT NULL,
                `seller_id` bigint(20) DEFAULT NULL,
                `is_trash` tinyint(4) NOT NULL DEFAULT '0',
                `visibility` tinyint(1) DEFAULT '1',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        include_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Prevent duplicate sku when multiple vendor add same product
     *
     * @param $duplicate
     *
     * @return void
     */
    public function prevent_duplicate_sku( $duplicate, $product ) {
        $sku        = $duplicate->get_sku( 'edit' );
        $unique_sku = $this->get_unique_sku( $sku );
        $duplicate->set_sku( $unique_sku );
    }

    /**
     * Check recursively if sku exist
     *
     * @param $sku
     *
     * @return mixed
     */
    public function get_unique_sku( $sku ) {
        $unique_sku = $sku;
        global $wpdb;
        $result = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}postmeta WHERE meta_key='_sku' AND meta_value =%s ", $sku ) );
        if ( $result >= 1 ) {
            if ( strpos( $sku, '-' ) !== false ) {
                $arr                      = explode( '-', $sku );
                $arr[ count( $arr ) - 1 ] = $arr[ count( $arr ) - 1 ] + 1;
                $unique_sku               = implode( '-', $arr );
            } else {
                $unique_sku = $sku . '-1';
            }

            return $this->get_unique_sku( $unique_sku );
        } else {
            return $unique_sku;
        }
    }
}
