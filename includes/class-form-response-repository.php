<?php
/**
 * Repository for form response CRUD operations.
 *
 * @package PRC\Platform\Block_Forms
 */

namespace PRC\Platform\Block_Forms;

/**
 * Handles storage and retrieval of form responses. This is the only class
 * that touches the responses table.
 *
 * @package PRC\Platform\Block_Forms
 */
class Form_Response_Repository {
	// Direct queries are intentional in this repository because it owns a dedicated responses table.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	/**
	 * Maximum rows deleted per batch during retention cleanup.
	 *
	 * @var int
	 */
	const DELETE_BATCH_SIZE = 500;

	/**
	 * Every status a row may hold. The column is VARCHAR(32), so a free-form
	 * status would be cut short or rejected.
	 *
	 * - received: stored with no email step (logResponse).
	 * - pending: stored, email not yet sent. A row that stays here means the request died.
	 * - sent / send_failed: the email result.
	 * - send_unknown: the mailer did not answer in time. The email may or may not have been delivered, and it is never sent again.
	 * - spam: classified as spam at submit. Stored, not emailed.
	 * - send_blocked_non_production, subscribed, subscribe_failed: set by other actions.
	 *
	 * @var string[]
	 */
	const STATUSES = array(
		'received',
		'pending',
		'sent',
		'send_failed',
		'send_unknown',
		'spam',
		'send_blocked_non_production',
		'subscribed',
		'subscribe_failed',
	);

	/**
	 * The schema manager instance.
	 *
	 * @var \PRC\Platform\Block_Forms\Form_Response_Schema
	 */
	private $schema;

	/**
	 * Constructor.
	 *
	 * @param \PRC\Platform\Block_Forms\Form_Response_Schema|null $schema The schema manager.
	 */
	public function __construct( $schema = null ) {
		$this->schema = $schema instanceof Form_Response_Schema ? $schema : new Form_Response_Schema();
	}

	/**
	 * Inserts a form response row.
	 *
	 * @param array $data {
	 *     Response data.
	 *
	 *     @type int    $form_id        Form post ID (0 for legacy inline forms).
	 *     @type string $form_name      Form name.
	 *     @type string $action         The form action, e.g. 'sendToEmail'.
	 *     @type string $status         Response status, e.g. one of self::STATUSES.
	 *     @type string $email          Submitter email address.
	 *     @type string $from_name      Submitter name.
	 *     @type array  $fields         Sanitized label/value field payload.
	 *     @type string $source_url     URL of the page the form was submitted from.
	 *     @type int    $source_post_id Post ID of the page the form was submitted from.
	 *     @type int    $user_id        Logged-in user ID.
	 *     @type string $user_agent     Submitter user agent.
	 * }
	 * @return int|false The row ID on success, false on failure.
	 */
	public function insert( array $data ) {
		$this->schema->maybe_upgrade_table();

		$row_id = $this->insert_row( $data );
		if ( false !== $row_id ) {
			return $row_id;
		}

		// The version option can say the schema is current while the table is
		// missing or broken (for example after a database sync). Repair once,
		// then try once more.
		$this->schema->maybe_upgrade_table( true );

		return $this->insert_row( $data );
	}

	/**
	 * Runs one INSERT for a response row.
	 *
	 * @param array $data Response data. See insert().
	 * @return int|false The row ID on success, false on failure.
	 */
	private function insert_row( array $data ) {
		global $wpdb;

		$result = $wpdb->insert(
			$this->schema->get_table_name(),
			array(
				'created_at'     => current_time( 'mysql', true ),
				'form_id'        => ! empty( $data['form_id'] ) ? absint( $data['form_id'] ) : null,
				'form_name'      => $data['form_name'] ?? null,
				'action'         => $data['action'] ?? '',
				'status'         => $data['status'] ?? 'received',
				'email'          => ! empty( $data['email'] ) ? $data['email'] : null,
				'from_name'      => ! empty( $data['from_name'] ) ? $data['from_name'] : null,
				'fields'         => ! empty( $data['fields'] ) ? wp_json_encode( $data['fields'] ) : null,
				'source_url'     => ! empty( $data['source_url'] ) ? $data['source_url'] : null,
				'source_post_id' => ! empty( $data['source_post_id'] ) ? absint( $data['source_post_id'] ) : null,
				'user_id'        => ! empty( $data['user_id'] ) ? absint( $data['user_id'] ) : null,
				'user_agent'     => ! empty( $data['user_agent'] ) ? $data['user_agent'] : null,
				'is_spam'        => empty( $data['is_spam'] ) ? 0 : 1,
				// New responses are unread by default unless explicitly marked read.
				'is_unread'      => array_key_exists( 'is_unread', $data ) ? ( empty( $data['is_unread'] ) ? 0 : 1 ) : 1,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Retrieves a single response by row ID.
	 *
	 * @param int $id The row ID.
	 * @return array|null The response row or null when not found.
	 */
	public function get( $id ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $id )
			),
			ARRAY_A
		);

		return $row ? $this->format_row( $row ) : null;
	}

	/**
	 * Retrieves responses with filtering and pagination.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type int    $form_id   Filter by form post ID.
	 *     @type string $form_name Filter by form name (legacy inline forms).
	 *     @type string $status    Filter by status (comma separated for multiple).
	 *     @type string $search    LIKE search across form name, email, from name, and fields.
	 *     @type int         $is_spam         Folder: 0 for inbox (default), 1 for spam.
	 *     @type int|null    $is_unread       Optional: 1 unread only, 0 read only, null = no filter.
	 *     @type int         $source_post_id  Optional source post filter.
	 *     @type string      $before          Optional ISO8601 upper bound on created_at (exclusive).
	 *     @type string      $after           Optional ISO8601 lower bound on created_at (exclusive).
	 *     @type int         $page            Page number.
	 *     @type int         $per_page        Rows per page (max 100).
	 *     @type string      $order           'ASC' or 'DESC' by created_at.
	 * }
	 * @return array{items: array, total: int, pages: int}
	 */
	public function query( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'form_id'        => 0,
				'form_name'      => '',
				'status'         => '',
				'search'         => '',
				'is_spam'        => 0,
				'is_unread'      => null,
				'source_post_id' => 0,
				'before'         => '',
				'after'          => '',
				'page'           => 1,
				'per_page'       => 25,
				'order'          => 'DESC',
			)
		);

		$table_name = $this->schema->get_table_name();
		$where      = array( 'is_spam = %d' );
		$values     = array( $args['is_spam'] ? 1 : 0 );

		if ( null !== $args['is_unread'] && '' !== $args['is_unread'] ) {
			$where[]  = 'is_unread = %d';
			$values[] = $args['is_unread'] ? 1 : 0;
		}

		if ( ! empty( $args['form_id'] ) ) {
			$where[]  = 'form_id = %d';
			$values[] = absint( $args['form_id'] );
		}

		if ( ! empty( $args['form_name'] ) ) {
			$where[]  = 'form_name = %s';
			$values[] = $args['form_name'];
		}

		if ( ! empty( $args['source_post_id'] ) ) {
			$where[]  = 'source_post_id = %d';
			$values[] = absint( $args['source_post_id'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$statuses = array_filter( array_map( 'trim', explode( ',', $args['status'] ) ) );
			if ( 1 === count( $statuses ) ) {
				$where[]  = 'status = %s';
				$values[] = $statuses[0];
			} elseif ( count( $statuses ) > 1 ) {
				$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
				$where[]      = "status IN ( {$placeholders} )";
				$values       = array_merge( $values, $statuses );
			}
		}

		if ( ! empty( $args['search'] ) ) {
			$search_like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]     = '(form_name LIKE %s OR email LIKE %s OR from_name LIKE %s OR fields LIKE %s)';
			$values[]    = $search_like;
			$values[]    = $search_like;
			$values[]    = $search_like;
			$values[]    = $search_like;
		}

		if ( ! empty( $args['before'] ) ) {
			$before = $this->normalize_datetime_bound( $args['before'] );
			if ( $before ) {
				$where[]  = 'created_at < %s';
				$values[] = $before;
			}
		}

		if ( ! empty( $args['after'] ) ) {
			$after = $this->normalize_datetime_bound( $args['after'] );
			if ( $after ) {
				$where[]  = 'created_at > %s';
				$values[] = $after;
			}
		}

		$where_clause = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! empty( $values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$per_page = max( 1, min( 100, (int) $args['per_page'] ) );
		$pages    = (int) ceil( $total / $per_page );
		$page     = max( 1, min( ( $pages ? $pages : 1 ), (int) $args['page'] ) );
		$order    = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$offset   = ( $page - 1 ) * $per_page;

		$sql      = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at {$order}, id {$order} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$values[] = $per_page;
		$values[] = $offset;

		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $values ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return array(
			'items' => array_map( array( $this, 'format_row' ), ( $rows ? $rows : array() ) ),
			'total' => $total,
			'pages' => max( 1, $pages ),
		);
	}

	/**
	 * Deletes responses by row ID.
	 *
	 * @param int[] $ids The row IDs to delete.
	 * @return int Number of rows deleted.
	 */
	public function delete( array $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$table_name   = $this->schema->get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE id IN ( {$placeholders} )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ids
			)
		);

		return $deleted ? (int) $deleted : 0;
	}

	/**
	 * Moves responses into or out of the spam folder.
	 *
	 * Restoring a row also resets a `spam` status to `received`, unless the
	 * caller passes $reset_status false. The `spam` status means "held back
	 * from email". Form_Response_Not_Spam keeps it: it reads the status to
	 * decide whether to email the row, and then sets the status itself.
	 *
	 * @param int[] $ids          The row IDs to update.
	 * @param bool  $is_spam      True to mark as spam, false to restore to the inbox.
	 * @param bool  $reset_status Whether a restore turns a `spam` status into `received`. Form_Response_Not_Spam passes false: it reads the status first.
	 * @return int Number of rows updated.
	 */
	public function set_spam( array $ids, $is_spam, $reset_status = true ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$table_name   = $this->schema->get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		$status_reset = ( $is_spam || ! $reset_status ) ? '' : ", status = CASE WHEN status = 'spam' THEN 'received' ELSE status END";

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name} SET is_spam = %d{$status_reset} WHERE id IN ( {$placeholders} )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $status_reset holds no user input.
				array_merge( array( $is_spam ? 1 : 0 ), $ids )
			)
		);

		return $updated ? (int) $updated : 0;
	}

	/**
	 * Sets the status of one response.
	 *
	 * @param int    $id     The row ID.
	 * @param string $status The new status. One of self::STATUSES.
	 * @return bool True when the row exists and holds the status. False for an
	 *              unknown status, a missing row, or a database error.
	 */
	public function set_status( $id, $status ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id || ! is_string( $status ) || ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}

		$table_name = $this->schema->get_table_name();
		$updated    = $wpdb->update(
			$table_name,
			array( 'status' => $status ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return false;
		}

		if ( 0 === (int) $updated ) {
			// MySQL reports 0 changed rows both for a missing row and for a row
			// that already holds the status. Only the second case is a success.
			return null !== $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table_name} WHERE id = %d AND status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$id,
					$status
				)
			);
		}

		return true;
	}

	/**
	 * Moves a response to a status only when it holds one of the given statuses.
	 *
	 * The check and the write are one statement, so a request that stalled
	 * after it read the row cannot overwrite what another request wrote since.
	 * `$to` must not be in `$from`. Then the row always changes, and the
	 * affected-row count tells a win from a loss.
	 *
	 * @param int      $id   The row ID.
	 * @param string   $to   The new status.
	 * @param string[] $from The statuses the row may hold now.
	 * @return int|false 1 when the row moved, 0 when it held another status or is gone,
	 *                   false for an unknown status or a database error.
	 */
	public function transition_status( $id, $to, array $from ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id || ! is_string( $to ) || ! in_array( $to, self::STATUSES, true ) || array() === $from || in_array( $to, $from, true ) ) {
			return false;
		}
		foreach ( $from as $status ) {
			if ( ! is_string( $status ) || ! in_array( $status, self::STATUSES, true ) ) {
				return false;
			}
		}

		$table_name   = $this->schema->get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $from ), '%s' ) );
		$changed      = $wpdb->query(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one placeholder per status, and the values are passed as one array.
				"UPDATE {$table_name} SET status = %s WHERE id = %d AND status IN ( {$placeholders} )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the table name and the placeholder list are built here.
				array_merge( array( $to, $id ), array_values( $from ) )
			)
		);

		return false === $changed ? false : (int) $changed;
	}

	/**
	 * Marks responses as read or unread.
	 *
	 * @param int[] $ids       The row IDs to update.
	 * @param bool  $is_unread True to mark unread, false to mark read.
	 * @return int Number of rows updated.
	 */
	public function set_unread( array $ids, $is_unread ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$table_name   = $this->schema->get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name} SET is_unread = %d WHERE id IN ( {$placeholders} )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $is_unread ? 1 : 0 ), $ids )
			)
		);

		return $updated ? (int) $updated : 0;
	}

	/**
	 * Gets inbox, spam, and unread folder totals.
	 *
	 * Unread counts only non-spam (inbox) rows.
	 *
	 * @param int    $form_id   Optional form post ID to scope counts.
	 * @param string $form_name Optional legacy form name to scope counts.
	 * @return array{inbox: int, spam: int, unread: int}
	 */
	public function get_folder_counts( int $form_id = 0, string $form_name = '' ) {
		global $wpdb;

		if ( ! $this->schema->table_exists() ) {
			return array(
				'inbox'  => 0,
				'spam'   => 0,
				'unread' => 0,
			);
		}

		$table_name = $this->schema->get_table_name();
		$where      = array();
		$values     = array();

		if ( $form_id > 0 ) {
			$where[]  = 'form_id = %d';
			$values[] = $form_id;
		} elseif ( '' !== $form_name ) {
			$where[]  = 'form_name = %s';
			$values[] = $form_name;
		}

		$where_sql = $where ? ' WHERE ' . implode( ' AND ', $where ) : '';

		$sql = "SELECT is_spam, COUNT(*) AS total FROM {$table_name}{$where_sql} GROUP BY is_spam";

		if ( $values ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $sql, ARRAY_A );
		}

		$counts = array(
			'inbox'  => 0,
			'spam'   => 0,
			'unread' => 0,
		);
		foreach ( ( $rows ? $rows : array() ) as $row ) {
			$counts[ empty( $row['is_spam'] ) ? 'inbox' : 'spam' ] = (int) $row['total'];
		}

		$unread_where   = array_merge( array( 'is_spam = 0', 'is_unread = 1' ), $where );
		$unread_values  = $values;
		$unread_sql     = 'SELECT COUNT(*) FROM ' . $table_name . ' WHERE ' . implode( ' AND ', $unread_where );
		if ( $unread_values ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$counts['unread'] = (int) $wpdb->get_var( $wpdb->prepare( $unread_sql, $unread_values ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$counts['unread'] = (int) $wpdb->get_var( $unread_sql );
		}

		return $counts;
	}

	/**
	 * Count responses grouped by form_id for published forms list enrichment.
	 *
	 * @param int[] $form_ids Form post IDs.
	 * @return array<int, int> Map of form_id => response count (inbox + spam).
	 */
	public function count_by_form_ids( array $form_ids ) {
		global $wpdb;

		$form_ids = array_values( array_filter( array_map( 'absint', $form_ids ) ) );
		if ( empty( $form_ids ) || ! $this->schema->table_exists() ) {
			return array();
		}

		$table_name   = $this->schema->get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $form_ids ), '%d' ) );
		$sql          = "SELECT form_id, COUNT(*) AS total FROM {$table_name} WHERE form_id IN ( {$placeholders} ) GROUP BY form_id";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $form_ids ), ARRAY_A );

		$counts = array();
		foreach ( ( $rows ? $rows : array() ) as $row ) {
			$counts[ (int) $row['form_id'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Count unread inbox responses grouped by form_id.
	 *
	 * Batched counterpart to `get_folder_counts()` so the forms list can show an
	 * unread column without one query per row.
	 *
	 * @param int[] $form_ids Form post IDs.
	 * @return array<int, int> Map of form_id => unread count.
	 */
	public function unread_count_by_form_ids( array $form_ids ) {
		global $wpdb;

		$form_ids = array_values( array_filter( array_map( 'absint', $form_ids ) ) );
		if ( empty( $form_ids ) || ! $this->schema->table_exists() ) {
			return array();
		}

		$table_name   = $this->schema->get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $form_ids ), '%d' ) );
		$sql          = "SELECT form_id, COUNT(*) AS total FROM {$table_name} WHERE is_spam = 0 AND is_unread = 1 AND form_id IN ( {$placeholders} ) GROUP BY form_id";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $form_ids ), ARRAY_A );

		$counts = array();
		foreach ( ( $rows ? $rows : array() ) as $row ) {
			$counts[ (int) $row['form_id'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Deletes responses older than the retention window, in batches.
	 *
	 * Batching keeps each DELETE small (VIP at-scale guidance) and makes the
	 * purge restart-safe — an interrupted run simply resumes on the next one.
	 * The cutoff compares against UTC_TIMESTAMP() because `created_at` is
	 * stored in GMT.
	 *
	 * @param int  $retention_days Number of days to retain responses.
	 * @param bool $spam_only      When true, only delete responses in the spam folder.
	 * @return int Number of responses deleted.
	 */
	public function cleanup_by_retention( int $retention_days, bool $spam_only = false ): int {
		// Retention <= 0 means responses are kept indefinitely.
		if ( $retention_days <= 0 ) {
			return 0;
		}

		global $wpdb;

		if ( ! $this->schema->table_exists() ) {
			return 0;
		}

		$table_name    = $this->schema->get_table_name();
		$spam_clause   = $spam_only ? 'is_spam = 1 AND ' : '';
		$total_deleted = 0;

		do {
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_name} WHERE {$spam_clause}created_at < DATE_SUB( UTC_TIMESTAMP(), INTERVAL %d DAY ) LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$retention_days,
					self::DELETE_BATCH_SIZE
				)
			);

			$batch_deleted  = ( $deleted ? (int) $deleted : 0 );
			$total_deleted += $batch_deleted;

			if ( $batch_deleted < self::DELETE_BATCH_SIZE ) {
				continue;
			}

			usleep( 100000 );
		} while ( $batch_deleted >= self::DELETE_BATCH_SIZE );

		return $total_deleted;
	}

	/**
	 * Gets distinct statuses and legacy (inline, non-CPT) form names for filter dropdowns.
	 *
	 * @return array{statuses: string[], legacy_form_names: string[]}
	 */
	public function get_filter_options() {
		global $wpdb;

		if ( ! $this->schema->table_exists() ) {
			return array(
				'statuses'          => array(),
				'legacy_form_names' => array(),
			);
		}

		$table_name = $this->schema->get_table_name();

		$statuses = $wpdb->get_col( "SELECT DISTINCT status FROM {$table_name} ORDER BY status ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$legacy_form_names = $wpdb->get_col( "SELECT DISTINCT form_name FROM {$table_name} WHERE (form_id IS NULL OR form_id = 0) AND form_name IS NOT NULL AND form_name != '' ORDER BY form_name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'statuses'          => ( $statuses ? $statuses : array() ),
			'legacy_form_names' => ( $legacy_form_names ? $legacy_form_names : array() ),
		);
	}

	/**
	 * Formats a raw database row into a structured response entry.
	 *
	 * @param array $row Raw database row.
	 * @return array Formatted response entry.
	 */
	private function format_row( array $row ) {
		$fields = array();
		if ( ! empty( $row['fields'] ) ) {
			$decoded = json_decode( $row['fields'], true );
			if ( is_array( $decoded ) ) {
				$fields = $decoded;
			}
		}

		return array(
			'id'             => (int) $row['id'],
			'created_at'     => $row['created_at'],
			'form_id'        => $row['form_id'] ? (int) $row['form_id'] : 0,
			'form_name'      => $row['form_name'],
			'action'         => $row['action'],
			'status'         => $row['status'],
			'email'          => $row['email'],
			'from_name'      => $row['from_name'],
			'fields'         => $fields,
			'source_url'     => $row['source_url'],
			'source_post_id' => $row['source_post_id'] ? (int) $row['source_post_id'] : 0,
			'user_id'        => $row['user_id'] ? (int) $row['user_id'] : 0,
			'user_agent'     => $row['user_agent'],
			'is_spam'        => ! empty( $row['is_spam'] ),
			'is_unread'      => ! array_key_exists( 'is_unread', $row ) || ! empty( $row['is_unread'] ),
		);
	}

	/**
	 * Normalize an ISO8601-ish datetime string to MySQL UTC datetime.
	 *
	 * @param string $value Raw datetime bound.
	 * @return string|null MySQL datetime or null when invalid.
	 */
	private function normalize_datetime_bound( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}
