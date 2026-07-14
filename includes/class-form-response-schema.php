<?php
/**
 * Manages the database schema for form responses.
 *
 * @package PRC\Platform\Block_Forms
 */

namespace PRC\Platform\Block_Forms;

/**
 * Handles database table creation and schema migrations for form responses.
 *
 * Because the table name uses `$wpdb->prefix`, each multisite site gets its
 * own table, keeping responses per-site.
 *
 * @package PRC\Platform\Block_Forms
 */
class Form_Response_Schema {
	// Schema management necessarily uses direct queries against the dedicated responses table.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

	/**
	 * Database table name (without prefix).
	 */
	const TABLE_NAME = 'prc_form_responses';

	/**
	 * Option key storing the synchronized responses table schema version.
	 */
	const SCHEMA_VERSION_OPTION = 'prc_form_responses_schema_version';

	/**
	 * Current responses table schema version.
	 *
	 * v2: added `is_spam` flag + (is_spam, created_at) index for spam foldering.
	 * v3: added `is_unread` flag + (is_unread, created_at) index for read/unread.
	 */
	const SCHEMA_VERSION = '3';

	/**
	 * Ensures the responses table matches the current schema version.
	 */
	public function maybe_upgrade_table() {
		$previous = (string) get_option( self::SCHEMA_VERSION_OPTION, '' );
		if ( self::SCHEMA_VERSION === $previous ) {
			return;
		}

		$this->create_table();

		if ( ! $this->table_exists() ) {
			return;
		}

		// Existing rows inherit DEFAULT 1 from the new column; treat pre-v3
		// responses as already read so upgrades do not flood the unread badge.
		if ( '' !== $previous && version_compare( $previous, '3', '<' ) ) {
			$this->mark_existing_rows_read();
		}

		update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, false );
	}

	/**
	 * After adding is_unread, mark all current rows as read.
	 *
	 * New inserts keep DEFAULT 1 (unread).
	 */
	private function mark_existing_rows_read() {
		global $wpdb;

		$table_name = $this->get_table_name();
		$wpdb->query( "UPDATE {$table_name} SET is_unread = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Returns the full table name with prefix.
	 *
	 * @return string The prefixed table name.
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Checks whether the responses table already exists.
	 *
	 * @return bool True when the table exists.
	 */
	public function table_exists() {
		global $wpdb;

		$table_name     = $this->get_table_name();
		$existing_table = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $existing_table === $table_name;
	}

	/**
	 * Creates (or upgrades) the responses database table via dbDelta.
	 */
	private function create_table() {
		global $wpdb;

		$table_name      = $this->get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			form_id BIGINT UNSIGNED DEFAULT NULL,
			form_name VARCHAR(191) DEFAULT NULL,
			action VARCHAR(64) NOT NULL,
			status VARCHAR(32) NOT NULL DEFAULT 'received',
			email VARCHAR(191) DEFAULT NULL,
			from_name VARCHAR(191) DEFAULT NULL,
			fields LONGTEXT DEFAULT NULL,
			source_url VARCHAR(255) DEFAULT NULL,
			source_post_id BIGINT UNSIGNED DEFAULT NULL,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			user_agent VARCHAR(255) DEFAULT NULL,
			is_spam TINYINT(1) NOT NULL DEFAULT 0,
			is_unread TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY idx_created_at (created_at),
			KEY idx_form_id (form_id),
			KEY idx_form_name (form_name),
			KEY idx_email (email),
			KEY idx_status (status),
			KEY idx_is_spam_created_at (is_spam,created_at),
			KEY idx_is_unread_created_at (is_unread,created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
}
