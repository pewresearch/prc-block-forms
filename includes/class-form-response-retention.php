<?php
/**
 * Data retention for the form responses log.
 *
 * @package PRC\Platform\Block_Forms
 */

namespace PRC\Platform\Block_Forms;

/**
 * Schedules a recurring Action Scheduler job that purges form responses
 * older than the retention window (365 days by default) every morning.
 *
 * @package PRC\Platform\Block_Forms
 */
class Form_Response_Retention {
	/**
	 * Action Scheduler hook fired by the recurring purge job.
	 *
	 * @var string
	 */
	const PURGE_HOOK = 'prc_form_responses_retention_purge';

	/**
	 * Action Scheduler group for form response actions.
	 *
	 * @var string
	 */
	const ACTION_GROUP = 'prc-block-forms';

	/**
	 * Action Scheduler group the purge job used before the forms system was
	 * extracted from prc-block-library. Retained so legacy recurring actions
	 * can be cleaned up on upgrade.
	 *
	 * @var string
	 */
	const LEGACY_ACTION_GROUP = 'prc-block-library';

	/**
	 * Default number of days a form response is retained.
	 *
	 * @var int
	 */
	const DEFAULT_RETENTION_DAYS = 365;

	/**
	 * Default number of days a spam-foldered response is retained.
	 *
	 * @var int
	 */
	const DEFAULT_SPAM_RETENTION_DAYS = 30;

	/**
	 * Local (site timezone) hour of day the purge job runs at.
	 *
	 * @var int
	 */
	const RUN_HOUR = 4;

	/**
	 * Constructor.
	 *
	 * @param \PRC\Platform\Block_Forms\Loader $loader The loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'init', $this, 'maybe_schedule' );
		$loader->add_action( self::PURGE_HOOK, $this, 'purge' );
	}

	/**
	 * The number of days a form response is retained before being purged.
	 *
	 * @return int
	 */
	public static function get_retention_days() {
		/**
		 * Filter the number of days form responses are retained.
		 *
		 * Return 0 to disable retention and keep responses indefinitely
		 * (this also unschedules the recurring purge job).
		 *
		 * @param int $retention_days Number of days to retain responses. Default 365.
		 */
		return max( 0, (int) apply_filters( 'prc_form_responses_retention_days', self::DEFAULT_RETENTION_DAYS ) );
	}

	/**
	 * The number of days a spam-foldered response is retained before being purged.
	 *
	 * @return int
	 */
	public static function get_spam_retention_days() {
		/**
		 * Filter the number of days spam-foldered responses are retained.
		 *
		 * Return 0 to keep spam on the general retention window instead.
		 * Note the purge job only runs while general retention is enabled.
		 *
		 * @param int $retention_days Number of days to retain spam. Default 30.
		 */
		return max( 0, (int) apply_filters( 'prc_form_responses_spam_retention_days', self::DEFAULT_SPAM_RETENTION_DAYS ) );
	}

	/**
	 * Ensure the recurring purge action matches the retention setting:
	 * scheduled when retention is enabled, unscheduled when disabled.
	 *
	 * Runs on each site's `init`, so on multisite every site gets its own
	 * action purging its own responses table.
	 *
	 * @hook init
	 */
	public function maybe_schedule() {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		// The purge job previously ran under the prc-block-library group. Clean
		// up any recurring action left there before the forms extraction so it
		// doesn't keep running alongside (and duplicating) the new one.
		if ( as_has_scheduled_action( self::PURGE_HOOK, array(), self::LEGACY_ACTION_GROUP ) ) {
			as_unschedule_all_actions( self::PURGE_HOOK, array(), self::LEGACY_ACTION_GROUP );
		}

		$retention_days = self::get_retention_days();
		$is_scheduled   = as_has_scheduled_action( self::PURGE_HOOK, array(), self::ACTION_GROUP );

		if ( $retention_days > 0 && ! $is_scheduled ) {
			as_schedule_recurring_action(
				self::get_first_run_timestamp(),
				DAY_IN_SECONDS,
				self::PURGE_HOOK,
				array(),
				self::ACTION_GROUP
			);
		} elseif ( 0 === $retention_days && $is_scheduled ) {
			as_unschedule_all_actions( self::PURGE_HOOK, array(), self::ACTION_GROUP );
		}
	}

	/**
	 * Purge responses older than the retention window.
	 *
	 * @hook prc_form_responses_retention_purge
	 */
	public function purge() {
		$retention_days = self::get_retention_days();
		if ( $retention_days <= 0 ) {
			return;
		}

		$repository = new Form_Response_Repository();
		$deleted    = $repository->cleanup_by_retention( $retention_days );

		// Spam gets a shorter window (30 days by default) — it exists only
		// so false positives can be recovered, not for long-term reference.
		$spam_retention_days = self::get_spam_retention_days();
		$spam_deleted        = 0;
		if ( $spam_retention_days > 0 && $spam_retention_days < $retention_days ) {
			$spam_deleted = $repository->cleanup_by_retention( $spam_retention_days, true );
		}

		/**
		 * Fires after the form responses retention purge runs.
		 *
		 * @param int $deleted             Number of responses deleted by the general pass.
		 * @param int $retention_days      The general retention window, in days.
		 * @param int $spam_deleted        Number of spam responses deleted by the spam pass.
		 * @param int $spam_retention_days The spam retention window, in days.
		 */
		do_action( 'prc_form_responses_purged', $deleted, $retention_days, $spam_deleted, $spam_retention_days );

		if ( $deleted > 0 || $spam_deleted > 0 ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[prc-block-forms] Form responses retention purge deleted %d response(s) older than %d days and %d spam response(s) older than %d days.',
					$deleted,
					$retention_days,
					$spam_deleted,
					$spam_retention_days
				)
			);
		}
	}

	/**
	 * The next occurrence of the run hour (4:00 AM) in the site timezone,
	 * as a Unix timestamp.
	 *
	 * The recurrence itself is a fixed 24-hour interval, so the run time
	 * drifts by an hour across DST transitions — acceptable for a purge job.
	 *
	 * @return int
	 */
	private static function get_first_run_timestamp() {
		$now       = new \DateTimeImmutable( 'now', wp_timezone() );
		$first_run = $now->setTime( self::RUN_HOUR, 0, 0 );

		if ( $first_run <= $now ) {
			$first_run = $first_run->modify( '+1 day' );
		}

		return $first_run->getTimestamp();
	}
}
