<?php
/**
 * Database layer for club members and SMS campaigns.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery -- the plugin owns dedicated tables,
 * so there is no core API or object-cache group to use instead.
 *
 * @package Mazino_Customer_Club
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'MCC_DB' ) ) {
	return;
}

/**
 * All persistence of club members goes through this class.
 */
class MCC_DB {

	/**
	 * Bump when the table schema changes.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.5.0';

	/**
	 * Option holding the installed schema version.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'mcc_db_version';

	/**
	 * Table name without the prefix.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'mcc_members';
	}

	/**
	 * Create or update the tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			phone varchar(20) NOT NULL DEFAULT '',
			birth_date varchar(10) NOT NULL DEFAULT '',
			birth_year smallint(6) NOT NULL DEFAULT 0,
			birth_month tinyint(4) NOT NULL DEFAULT 0,
			birth_day tinyint(4) NOT NULL DEFAULT 0,
			source varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			ip_hash char(64) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY phone (phone),
			KEY created_at (created_at)
		) {$collate};";

		dbDelta( $sql );

		$campaigns = $wpdb->prefix . 'mcc_sms_campaigns';
		$messages  = $wpdb->prefix . 'mcc_sms_messages';

		$sql_campaigns = "CREATE TABLE {$campaigns} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			kind varchar(20) NOT NULL DEFAULT 'bulk',
			title varchar(191) NOT NULL DEFAULT '',
			message text NOT NULL,
			line varchar(30) NOT NULL DEFAULT '',
			mode varchar(10) NOT NULL DEFAULT 'line',
			template_id bigint(20) unsigned NOT NULL DEFAULT 0,
			params text NULL,
			total int(11) NOT NULL DEFAULT 0,
			sent_count int(11) NOT NULL DEFAULT 0,
			failed_count int(11) NOT NULL DEFAULT 0,
			cost decimal(10,2) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'draft',
			pack_ids text NULL,
			scheduled_at datetime NULL DEFAULT NULL,
			sent_at datetime NULL DEFAULT NULL,
			error text NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at)
		) {$collate};";

		$sql_messages = "CREATE TABLE {$messages} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			member_id bigint(20) unsigned NOT NULL DEFAULT 0,
			mobile varchar(20) NOT NULL DEFAULT '',
			message text NOT NULL,
			params text NULL,
			message_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			cost decimal(10,2) NOT NULL DEFAULT 0,
			error varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY campaign_id (campaign_id),
			KEY message_id (message_id),
			KEY status (status)
		) {$collate};";

		dbDelta( $sql_campaigns );
		dbDelta( $sql_messages );

		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Run the schema installer when the stored version is stale.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Column formats for $wpdb->insert().
	 *
	 * @param array $data Row data.
	 * @return string[]
	 */
	private static function formats( array $data ) {
		$numeric = array( 'id', 'birth_year', 'birth_month', 'birth_day' );
		$formats = array();

		foreach ( array_keys( $data ) as $key ) {
			$formats[] = in_array( $key, $numeric, true ) ? '%d' : '%s';
		}

		return $formats;
	}

	/**
	 * Insert a member.
	 *
	 * @param array $data Row data.
	 * @return int|WP_Error Insert id or error.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$inserted = $wpdb->insert( self::table(), $data, self::formats( $data ) );

		if ( false === $inserted ) {
			return new WP_Error( 'mcc_db_error', 'ثبت در دیتابیس انجام نشد.' );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Whether a phone number is already registered.
	 *
	 * @param string $phone Normalised phone.
	 * @return bool
	 */
	public static function phone_exists( $phone ) {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared on the next line.
		$found = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE phone = %s LIMIT 1', $table, $phone ) );

		return null !== $found;
	}

	/**
	 * Build the shared WHERE clause.
	 *
	 * @param string $search Search term.
	 * @return array{0:string,1:array}
	 */
	private static function where( $search ) {
		global $wpdb;

		$search = trim( (string) $search );
		$where  = 'WHERE 1=1';
		$params = array();

		if ( '' !== $search ) {
			$name_like  = '%' . $wpdb->esc_like( $search ) . '%';
			$phone      = self::normalize_like_phone( $search );
			$phone_like = '%' . $wpdb->esc_like( $phone ) . '%';

			$where   .= ' AND ( name LIKE %s OR phone LIKE %s )';
			$params[] = $name_like;
			$params[] = $phone_like;
		}

		return array( $where, $params );
	}

	/**
	 * Turn a search term into something comparable with the phone column.
	 *
	 * @param string $search Search term.
	 * @return string
	 */
	private static function normalize_like_phone( $search ) {
		$digits = preg_replace( '/\D+/', '', MCC_Helpers::to_english_digits( $search ) );

		if ( '' === $digits ) {
			return $search;
		}

		if ( 0 === strpos( $digits, '98' ) && strlen( $digits ) > 10 ) {
			$digits = '0' . substr( $digits, 2 );
		}

		return $digits;
	}

	/**
	 * Query members.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function get_members( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'orderby'  => 'created_at',
				'order'    => 'DESC',
				'per_page' => 20,
				'page'     => 1,
			)
		);

		$allowed_orderby = array( 'id', 'name', 'phone', 'birth_year', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = max( 1, (int) $args['per_page'] );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		list( $where, $params ) = self::where( $args['search'] );

		$table = self::table();
		$sql   = "SELECT * FROM %i {$where} ORDER BY {$orderby} {$order}, id {$order} LIMIT {$per_page} OFFSET {$offset}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared below.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...array_merge( array( $table ), $params ) ) );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count members matching a search term.
	 *
	 * @param string $search Search term.
	 * @return int
	 */
	public static function count( $search = '' ) {
		global $wpdb;

		list( $where, $params ) = self::where( $search );

		$table = self::table();
		$sql   = "SELECT COUNT(*) FROM %i {$where}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared below.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...array_merge( array( $table ), $params ) ) );
	}

	/**
	 * All members matching a search term, for exports.
	 *
	 * @param string $search Search term.
	 * @return array
	 */
	public static function get_all( $search = '' ) {
		global $wpdb;

		list( $where, $params ) = self::where( $search );

		$table = self::table();
		$sql   = "SELECT * FROM %i {$where} ORDER BY id DESC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared below.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...array_merge( array( $table ), $params ) ) );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Total member count.
	 *
	 * @return int
	 */
	public static function total() {
		return self::count( '' );
	}

	/**
	 * Members created since a given MySQL datetime.
	 *
	 * @param string $since Datetime.
	 * @return int
	 */
	public static function count_since( $since ) {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared below.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE created_at >= %s', $table, $since ) );
	}

	/**
	 * Delete members by id.
	 *
	 * @param int[] $ids Ids.
	 * @return int Deleted rows.
	 */
	public static function delete( array $ids ) {
		global $wpdb;

		$deleted = 0;

		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			if ( $id < 1 ) {
				continue;
			}

			$result = $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );

			if ( false !== $result ) {
				$deleted += (int) $result;
			}
		}

		return $deleted;
	}
}
