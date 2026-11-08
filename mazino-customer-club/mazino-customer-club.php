<?php
/**
 * Plugin Name:       Mazino Customer Club | باشگاه مشتریان
 * Plugin URI:        https://mazinograph.ir/
 * Description:       فرم عضویت در باشگاه مشتریان به‌صورت ویجت اختصاصی المنتور (کاملاً قابل ویرایش از داخل المنتور) به‌همراه فهرست اعضا، خروجی CSV/Excel و ارسال پیامک sms.ir. توسعه‌یافته توسط گروه نرم‌افزاری مازینو گراف.
 * Version:           1.5.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            گروه نرم‌افزاری مازینو گراف
 * Author URI:        https://mazinograph.ir/
 * Developer:         میلاد فروغی
 * Developer Phone:   09364753024
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mazino-customer-club
 *
 * @package Mazino_Customer_Club
 */

defined( 'ABSPATH' ) || exit;

define( 'MCC_VERSION', '1.5.0' );
define( 'MCC_FILE', __FILE__ );
define( 'MCC_PATH', plugin_dir_path( __FILE__ ) );
define( 'MCC_URL', plugin_dir_url( __FILE__ ) );
define( 'MCC_SLUG', 'mazino-customer-club' );

require_once MCC_PATH . 'includes/class-mcc-jalali.php';
require_once MCC_PATH . 'includes/class-mcc-helpers.php';
require_once MCC_PATH . 'includes/class-mcc-db.php';
require_once MCC_PATH . 'includes/class-mcc-form.php';
require_once MCC_PATH . 'includes/class-mcc-ajax.php';
require_once MCC_PATH . 'includes/class-mcc-export.php';
require_once MCC_PATH . 'includes/class-mcc-settings.php';
require_once MCC_PATH . 'includes/class-mcc-sms-api.php';
require_once MCC_PATH . 'includes/class-mcc-sms.php';
require_once MCC_PATH . 'includes/class-mcc-sms-admin.php';
require_once MCC_PATH . 'includes/class-mcc-admin.php';
require_once MCC_PATH . 'includes/class-mcc-elementor.php';
require_once MCC_PATH . 'includes/class-mcc-plugin.php';

register_activation_hook( __FILE__, array( 'MCC_DB', 'install' ) );
register_activation_hook( __FILE__, array( 'MCC_Plugin', 'on_activate' ) );
register_deactivation_hook( __FILE__, array( 'MCC_Plugin', 'on_deactivate' ) );

add_action( 'plugins_loaded', array( 'MCC_Plugin', 'instance' ) );
