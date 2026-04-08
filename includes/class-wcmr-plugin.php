<?php
/**
 * Main plugin bootstrap.
 *
 * @package WooCommerceMinimumRules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCMR_Plugin {
	protected static $instance = null;
	protected $repository;
	protected $admin = null;
	protected $frontend = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {
		add_action( 'plugins_loaded', array( $this, 'bootstrap' ) );
	}

	public static function activate() {
		if ( self::is_woocommerce_active() ) {
			return;
		}

		deactivate_plugins( plugin_basename( WCMR_PLUGIN_FILE ) );

		wp_die(
			esc_html__( 'Minorda requires WooCommerce to be installed and active.', 'minorda' ),
			esc_html__( 'Plugin dependency check', 'minorda' ),
			array(
				'back_link' => true,
			)
		);
	}

	public function bootstrap() {
		if ( ! self::is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );
			return;
		}

		$this->repository = new WCMR_Rule_Repository();
		$this->admin      = new WCMR_Admin( $this->repository );
		$this->frontend   = new WCMR_Frontend( $this->repository );
	}

	public function render_missing_woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p><?php echo esc_html__( 'Minorda is inactive because WooCommerce is not active.', 'minorda' ); ?></p>
		</div>
		<?php
	}

	protected static function is_woocommerce_active() {
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$network_active_plugins = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
			$active_plugins         = array_merge( $active_plugins, $network_active_plugins );
		}

		return in_array( 'woocommerce/woocommerce.php', $active_plugins, true );
	}
}
