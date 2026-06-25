<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SPW_Admin {
    protected $sale_service;

    public function __construct( $sale_service ) {
        $this->sale_service = $sale_service;
    }

    public function hooks() {
        add_action( 'admin_menu', array( $this, 'register_menu' ), 99 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'woocommerce_product_options_pricing', array( $this, 'add_product_fields' ) );
        add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_fields' ) );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( 'woocommerce_page_spw-settings' !== $hook && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'spw-admin-style', SPW_PLUGIN_URL . 'assets/css/spw-admin.css', array(), SPW_VERSION );
        wp_enqueue_script( 'spw-admin-script', SPW_PLUGIN_URL . 'assets/js/spw-admin.js', array( 'jquery' ), SPW_VERSION, true );
    }

    public function register_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'نبض حراجی', 'flash-sale-pulse-for-woocommerce' ),
            __( 'نبض حراجی', 'flash-sale-pulse-for-woocommerce' ),
            'manage_woocommerce',
            'spw-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'spw_settings_group', 'spw_settings', array( $this, 'sanitize_settings' ) );
    }

    public function sanitize_settings( $input ) {
        $defaults = SPW_Helpers::get_settings();
        $output = array();

        $output['timer_title'] = sanitize_text_field( $input['timer_title'] ?? $defaults['timer_title'] );
        $output['button_text'] = sanitize_text_field( $input['button_text'] ?? $defaults['button_text'] );
        $output['products_per_page'] = absint( $input['products_per_page'] ?? $defaults['products_per_page'] );
        $output['cleanup_batch'] = absint( $input['cleanup_batch'] ?? $defaults['cleanup_batch'] );
        
        $allowed_orderby = array( 'ending_soon', 'date', 'title' );
        $output['default_orderby'] = in_array( $input['default_orderby'], $allowed_orderby ) ? $input['default_orderby'] : $defaults['default_orderby'];

        $output['hide_oos'] = 'yes';
        $output['db_version'] = SPW_DB_VERSION;

        return $output;
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        $settings = SPW_Helpers::get_settings();
        ?>
        <div class="wrap spw-admin-wrap" dir="rtl">
            <h1><?php echo esc_html__( 'تنظیمات نبض حراجی', 'flash-sale-pulse-for-woocommerce' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'spw_settings_group' );
                do_settings_sections( 'spw_settings_group' );
                ?>
                <h2 class="nav-tab-wrapper">
                    <a href="#spw-tab-general" class="nav-tab nav-tab-active"><?php _e( 'عمومی', 'flash-sale-pulse-for-woocommerce' ); ?></a>
                    <a href="#spw-tab-texts" class="nav-tab"><?php _e( 'متن‌ها', 'flash-sale-pulse-for-woocommerce' ); ?></a>
                    <a href="#spw-tab-shortcodes" class="nav-tab"><?php _e( 'شورت‌کدها', 'flash-sale-pulse-for-woocommerce' ); ?></a>
                </h2>

                <div id="spw-tab-general" class="spw-tab-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e( 'تعداد محصول در هر بار نمایش', 'flash-sale-pulse-for-woocommerce' ); ?></th>
                            <td><input type="number" name="spw_settings[products_per_page]" value="<?php echo esc_attr( $settings['products_per_page'] ); ?>" class="small-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'تعداد پردازش در هر نوبت (Batch)', 'flash-sale-pulse-for-woocommerce' ); ?></th>
                            <td>
                                <input type="number" name="spw_settings[cleanup_batch]" value="<?php echo esc_attr( $settings['cleanup_batch'] ); ?>" class="small-text">
                                <p class="description"><?php _e( 'برای جلوگیری از فشار به سرور در سایت‌های بزرگ.', 'flash-sale-pulse-for-woocommerce' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div id="spw-tab-texts" class="spw-tab-content" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e( 'عنوان تایمر', 'flash-sale-pulse-for-woocommerce' ); ?></th>
                            <td><input type="text" name="spw_settings[timer_title]" value="<?php echo esc_attr( $settings['timer_title'] ); ?>" class="regular-text"></td>
                        </tr>
                    </table>
                </div>

                <?php submit_button( __( 'ذخیره تغییرات', 'flash-sale-pulse-for-woocommerce' ) ); ?>
            </form>
        </div>
        <?php
    }

    public function add_product_fields() {
        global $post;
        $product_id = $post ? $post->ID : 0;
        $window     = SPW_Helpers::get_product_sale_window( $product_id );
        $start      = SPW_Helpers::timestamp_to_jalali_datetime( $window['start'] );
        $end        = SPW_Helpers::timestamp_to_jalali_datetime( $window['end'] );

        echo '<div class="options_group spw-product-fields" style="border-top: 1px solid #eee; padding-top: 10px;">';
        echo '<h4 style="margin-right: 10px;">' . __( 'تنظیمات نبض حراجی (شمسی)', 'flash-sale-pulse-for-woocommerce' ) . '</h4>';

        woocommerce_wp_text_input( array(
            'id'          => '_spw_sale_start_jalali',
            'label'       => __( 'شروع حراجی', 'flash-sale-pulse-for-woocommerce' ),
            'placeholder' => '1402/10/01 12:00',
            'value'       => $start,
            'desc_tip'    => true,
            'description' => __( 'زمان شروع نمایش تایمر و اعمال قیمت حراجی.', 'flash-sale-pulse-for-woocommerce' ),
        ) );

        woocommerce_wp_text_input( array(
            'id'          => '_spw_sale_end_jalali',
            'label'       => __( 'پایان حراجی', 'flash-sale-pulse-for-woocommerce' ),
            'placeholder' => '1402/10/05 23:59',
            'value'       => $end,
            'desc_tip'    => true,
            'description' => __( 'پس از این تاریخ، محصول خودکار از لیست حراج خارج می‌شود.', 'flash-sale-pulse-for-woocommerce' ),
        ) );
        echo '</div>';
    }

    public function save_product_fields( $product ) {
        $start_raw = isset( $_POST['_spw_sale_start_jalali'] ) ? sanitize_text_field( $_POST['_spw_sale_start_jalali'] ) : '';
        $end_raw   = isset( $_POST['_spw_sale_end_jalali'] ) ? sanitize_text_field( $_POST['_spw_sale_end_jalali'] ) : '';

        if ( ! empty( $start_raw ) ) {
            $product->update_meta_data( '_spw_sale_start', SPW_Helpers::jalali_datetime_to_timestamp( $start_raw ) );
        } else {
            $product->delete_meta_data( '_spw_sale_start' );
        }

        if ( ! empty( $end_raw ) ) {
            $product->update_meta_data( '_spw_sale_end', SPW_Helpers::jalali_datetime_to_timestamp( $end_raw ) );
        } else {
            $product->delete_meta_data( '_spw_sale_end' );
        }
    }
}
