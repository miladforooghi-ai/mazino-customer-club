<?php
/**
 * Plugin bootstrap.
 *
 * @package Mazino_Customer_Club
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'MCC_Plugin' ) ) {
	return;
}

/**
 * Wires the plugin pieces together.
 */
final class MCC_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var MCC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return MCC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'mcc_birthday_cron', array( 'MCC_SMS', 'handle_cron' ) );
		add_action( 'mcc_sms_worker', array( 'MCC_SMS', 'run_worker' ) );

		add_shortcode( 'mazino_customer_club', array( $this, 'shortcode' ) );

		MCC_DB::maybe_upgrade();
		MCC_Ajax::init();

		if ( is_admin() ) {
			MCC_Admin::init();
			MCC_SMS_Admin::init();
			add_action( 'admin_init', array( 'MCC_SMS', 'sync_schedule' ) );
			add_action( 'admin_init', array( $this, 'keep_queue_running' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( MCC_FILE ), array( $this, 'action_links' ) );
		}

		// Elementor hooks are no-ops when Elementor is not installed.
		new MCC_Elementor();
	}

	/**
	 * Activation tasks.
	 *
	 * @return void
	 */
	public static function on_activate() {
		MCC_SMS::sync_schedule();
	}

	/**
	 * Make sure the SMS queue drains even when WP-Cron is disabled.
	 *
	 * @return void
	 */
	public function keep_queue_running() {
		if ( ! MCC_SMS::has_pending() ) {
			return;
		}

		MCC_SMS::schedule_worker( 60 );
		MCC_SMS::run_worker();
	}

	/**
	 * Deactivation tasks.
	 *
	 * @return void
	 */
	public static function on_deactivate() {
		wp_clear_scheduled_hook( 'mcc_birthday_cron' );
	}

	/**
	 * Register frontend assets so Elementor can enqueue them on demand.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style( 'mcc-frontend', MCC_URL . 'assets/css/frontend.css', array(), MCC_VERSION );
		wp_register_script( 'mcc-frontend', MCC_URL . 'assets/js/frontend.js', array(), MCC_VERSION, true );

		wp_localize_script(
			'mcc-frontend',
			'MCC_DATA',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'required'      => 'این فیلد را کامل کنید.',
					'nameShort'     => 'نام و نام خانوادگی را کامل وارد کنید.',
					'phone'         => 'شماره تلفن همراه معتبر نیست. نمونهٔ درست: ۰۹۱۲۱۲۳۴۵۶۷',
					'birth'         => 'تاریخ تولد را کامل انتخاب کنید.',
					'consent'       => 'برای ادامه باید موافقت خود را اعلام کنید.',
					'sending'       => 'در حال ثبت…',
					'network'       => 'ارتباط با سرور برقرار نشد. اتصال اینترنت را بررسی کنید.',
					'generic'       => 'ثبت‌نام انجام نشد. لطفاً دوباره تلاش کنید.',
				),
			)
		);

		if ( is_singular() ) {
			$post = get_post();

			if ( $post && has_shortcode( (string) $post->post_content, 'mazino_customer_club' ) ) {
				wp_enqueue_style( 'mcc-frontend' );
				wp_enqueue_script( 'mcc-frontend' );
			}
		}
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param string[] $links Action links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$members = add_query_arg( 'page', 'mcc-members', admin_url( 'admin.php' ) );
		$about   = add_query_arg( 'page', 'mcc-about', admin_url( 'admin.php' ) );

		array_unshift(
			$links,
			'<a href="' . esc_url( $about ) . '">درباره ما</a>',
			'<a href="' . esc_url( add_query_arg( 'page', 'mcc-sms', admin_url( 'admin.php' ) ) ) . '">ارسال پیامک</a>',
			'<a href="' . esc_url( $members ) . '">اعضای باشگاه</a>'
		);

		return $links;
	}

	/**
	 * Shortcode fallback for sites without Elementor.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'        => '',
				'description'  => '',
				'image'        => '',
				'button_text'  => '',
				'show_name'    => 'yes',
				'show_phone'   => 'yes',
				'show_birth'   => 'yes',
				'show_consent' => 'no',
				'consent_text' => '',
				'thanks_title' => '',
				'thanks_text'  => '',
			),
			$atts,
			'mazino_customer_club'
		);

		wp_enqueue_style( 'mcc-frontend' );
		wp_enqueue_script( 'mcc-frontend' );

		$args = array(
			'show_name'    => in_array( strtolower( (string) $atts['show_name'] ), array( 'yes', '1', 'true' ), true ),
			'show_phone'   => in_array( strtolower( (string) $atts['show_phone'] ), array( 'yes', '1', 'true' ), true ),
			'show_birth'   => in_array( strtolower( (string) $atts['show_birth'] ), array( 'yes', '1', 'true' ), true ),
			'show_consent' => in_array( strtolower( (string) $atts['show_consent'] ), array( 'yes', '1', 'true' ), true ),
			'image_url'    => '',
			'image_id'     => 0,
		);

		$map = array(
			'title'        => 'heading',
			'description'  => 'description',
			'image'        => 'image_url',
			'button_text'  => 'button_text',
			'consent_text' => 'consent_text',
			'thanks_title' => 'thanks_title',
			'thanks_text'  => 'thanks_message',
		);

		foreach ( $map as $att => $arg ) {
			if ( '' !== trim( (string) $atts[ $att ] ) ) {
				$args[ $arg ] = $atts[ $att ];
			}
		}

		$args['show_image'] = '' !== trim( (string) $args['image_url'] );

		return MCC_Form::render( $args );
	}
}
