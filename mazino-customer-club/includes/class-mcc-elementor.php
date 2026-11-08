<?php
/**
 * Elementor integration (category + widget registration).
 *
 * @package Mazino_Customer_Club
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'MCC_Elementor' ) ) {
	return;
}

/**
 * Hooks the widget into Elementor when it is available.
 */
class MCC_Elementor {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_elementor_notice' ) );
	}

	/**
	 * Add the panel category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elements manager.
	 * @return void
	 */
	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'mazino',
			array(
				'title' => 'باشگاه مشتریان',
				'icon'  => 'eicon-users',
			)
		);
	}

	/**
	 * Register the widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
	 * @return void
	 */
	public function register_widget( $widgets_manager ) {
		require_once MCC_PATH . 'includes/widgets/class-mcc-widget.php';

		if ( ! class_exists( 'MCC_Elementor_Widget' ) ) {
			return;
		}

		$widget = new MCC_Elementor_Widget();

		if ( method_exists( $widgets_manager, 'register' ) ) {
			$widgets_manager->register( $widget );
		} elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
			$widgets_manager->register_widget_type( $widget );
		}
	}

	/**
	 * Tell the shop owner that the widget needs Elementor.
	 *
	 * @return void
	 */
	public function maybe_show_elementor_notice() {
		if ( did_action( 'elementor/loaded' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		echo '<div class="notice notice-info"><p>افزونهٔ «باشگاه مشتریان مازینو» برای ویجت المنتور به افزونهٔ <strong>Elementor</strong> نیاز دارد. تا زمان فعال‌سازی المنتور می‌توانید از شورت‌کد <code>[mazino_customer_club]</code> استفاده کنید.</p></div>';
	}
}
