<?php
/**
 * Emails a form response when a person marks it "Not spam".
 *
 * @package PRC\Platform\Block_Forms
 */

namespace PRC\Platform\Block_Forms;

/**
 * Moves responses out of the Spam folder and sends the form's notification
 * email for the ones that were never emailed.
 *
 * One email per entry, never two:
 *
 * - run_once() keys the work on `form_{formId}_notspam_{entryId}`. A second
 *   request for the same entry waits out the lease and gets BUSY. A success
 *   is stored, so a later request replays it and sends nothing.
 * - Inside the lease the stored status decides. Only `spam` (held back at
 *   submit) and `send_failed` (the mailer refused it) are sent. `sent`,
 *   `pending` (a request that died mid-send, so the outcome is unknown),
 *   and every other status are never sent.
 * - The status is written first. It is `pending` while wp_mail() runs, then
 *   `sent` only after the mailer accepted the message, `send_failed`, or
 *   `send_unknown`. The write to `pending` is conditional: it succeeds only
 *   while the row still holds `spam` or `send_failed`, and it needs exactly
 *   one changed row. A request that stalled and lost its lease finds another
 *   status, or no lease, and stops without a send.
 * - A request that loses its lease after the `pending` write still sends.
 *   The row is `pending`, so no other request can send it, and stopping
 *   would leave the entry unsent.
 * - `send_unknown` means Mandrill did not answer in time, so it may have
 *   accepted the message. The native-mailer fallback does not run (see
 *   Form_Send_Email::send_notification_outcome()). The claim is final, so the
 *   entry is never sent again by this path.
 *
 * A failed send is not stored as final. The status shows `send_failed` and
 * the lease is released. A person can mark the response as spam and then as
 * not spam again to send once more. That cannot send twice: `send_failed`
 * means the mailer did not accept the message, and the next success writes
 * both `sent` and the final claim.
 *
 * @package PRC\Platform\Block_Forms
 */
class Form_Response_Not_Spam {
	/**
	 * Statuses that may be emailed: held back as spam, or refused by the mailer.
	 *
	 * @var string[]
	 */
	const SENDABLE_STATUSES = array( 'spam', 'send_failed' );

	/**
	 * What a request is told when another request holds the response.
	 *
	 * @var string
	 */
	const BUSY_MESSAGE = 'Another request is already handling this response. Try again in a moment.';

	/**
	 * The responses repository.
	 *
	 * @var Form_Response_Repository
	 */
	private Form_Response_Repository $repository;

	/**
	 * Constructor.
	 *
	 * @param Form_Response_Repository|null $repository The repository. Defaults to a new one.
	 */
	public function __construct( ?Form_Response_Repository $repository = null ) {
		$this->repository = $repository ?? new Form_Response_Repository();
	}

	/**
	 * Move responses to the inbox and email the ones that were never emailed.
	 *
	 * @param int[] $ids The response IDs.
	 * @return array{updated: int, emailed: int[], email_failed: array<int, array{id: int, message: string}>} Rows moved, rows emailed, and rows that could not be emailed.
	 */
	public function mark_not_spam( array $ids ): array {
		$result = array(
			'updated'      => 0,
			'emailed'      => array(),
			'email_failed' => array(),
		);

		foreach ( array_unique( array_filter( array_map( 'absint', $ids ) ) ) as $id ) {
			$one = $this->handle( $id );

			$result['updated'] += $one['moved'] ? 1 : 0;
			if ( 'emailed' === $one['outcome'] ) {
				$result['emailed'][] = $id;
			} elseif ( in_array( $one['outcome'], array( 'failed', 'unknown' ), true ) ) {
				$result['email_failed'][] = array(
					'id'      => $id,
					'message' => $one['message'],
				);
			}
		}

		return $result;
	}

	/**
	 * Handle one response.
	 *
	 * @param int $id The response ID.
	 * @return array{moved: bool, outcome: string, message: string} Outcome is emailed, skipped, failed, or unknown.
	 */
	private function handle( int $id ): array {
		$row = $this->repository->get( $id );
		if ( null === $row || ! $row['is_spam'] ) {
			return $this->done( false, 'skipped' );
		}

		if ( ! function_exists( '\\PRC\\Primitives\\Concurrency\\run_once' ) || ! class_exists( '\\PRC\\Primitives\\Concurrency\\Table_Claim_Store' ) ) {
			return $this->done( false, 'failed', 'The email could not be checked, so none was sent. Nothing changed.' );
		}

		$one    = $this->done( false, 'skipped' );
		$ran    = false;
		$result = \PRC\Primitives\Concurrency\run_once(
			'form_' . $row['form_id'] . '_notspam_' . $id,
			function ( $holds_lease = null ) use ( $id, &$one, &$ran ) {
				$ran = true;
				$one = $this->send_once( $id, $holds_lease instanceof \Closure ? $holds_lease : null );
				if ( 'emailed' === $one['outcome'] ) {
					return \PRC\Primitives\Concurrency\Run_Result::final( 'sent' );
				}
				// An unknown send is final too: Mandrill may have accepted the message, so nothing may send it again.
				return 'unknown' === $one['outcome']
					? \PRC\Primitives\Concurrency\Run_Result::final( 'send_unknown' )
					: \PRC\Primitives\Concurrency\Run_Result::retryable( $one['outcome'] );
			},
			new \PRC\Primitives\Concurrency\Table_Claim_Store(),
			Form_Send_Email::SUBMISSION_LEASE_TTL
		);

		if ( $ran ) {
			return $one;
		}

		if ( \PRC\Primitives\Concurrency\Run_Result::BUSY === $result->status ) {
			return $this->done( false, 'failed', self::BUSY_MESSAGE );
		}

		if ( \PRC\Primitives\Concurrency\Run_Result::FINAL === $result->status ) {
			// An earlier request already emailed this entry. Move it to the inbox and send nothing.
			return $this->done( $this->move( $id, true ), 'skipped' );
		}

		// The claim store failed. Failing closed: nothing is sent and the response stays in the Spam folder.
		error_log( sprintf( '[prc-block-forms] The not-spam claim could not be taken for response %d. Nothing was sent.', $id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- VIP routes this to the log stream.
		return $this->done( false, 'failed', 'The email could not be checked, so none was sent. The response is still in the Spam folder.' );
	}

	/**
	 * Run with the lease held: read the status again, record, send, record.
	 *
	 * @param int           $id          The response ID.
	 * @param \Closure|null $holds_lease Returns true while this run still holds the lease. Null means no check.
	 * @return array{moved: bool, outcome: string, message: string}
	 */
	private function send_once( int $id, ?\Closure $holds_lease = null ): array {
		$row = $this->repository->get( $id );
		if ( null === $row || ! $row['is_spam'] ) {
			return $this->done( false, 'skipped' );
		}

		if ( 'sendToEmail' !== $row['action'] || ! in_array( $row['status'], self::SENDABLE_STATUSES, true ) ) {
			return $this->done( $this->move( $id, true ), 'skipped' );
		}

		$target = Form_Send_Email::resolve_forward_to_for_response( $row );

		// Record first. A request that lost its lease while it stalled must not write or send: the
		// lease check stops most of them, and the conditional write stops the rest. Every status
		// write below this line is conditional, the no-recipient write too.
		if ( null !== $holds_lease && ! $holds_lease() ) {
			return $this->stop_for_lost_lease( $row, 'lease' );
		}

		if ( '' === $target ) {
			return $this->fail_without_recipient( $row );
		}

		$changed = $this->repository->transition_status( $id, 'pending', self::SENDABLE_STATUSES );
		if ( 0 === $changed ) {
			return $this->stop_for_lost_lease( $row, 'status_changed' );
		}
		if ( 1 !== $changed ) {
			error_log( sprintf( '[prc-block-forms] Form response %d could not be set to "pending". Nothing was sent.', $id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- VIP routes this to the log stream.
			return $this->done( false, 'failed', 'The status could not be saved, so no email was sent. The response is still in the Spam folder.' );
		}

		$moved = $this->move( $id, false );

		$report = array();
		try {
			$outcome = Form_Send_Email::send_notification_outcome( $target, $row['fields'], (string) $row['form_name'], (string) $row['email'], $report, (string) $row['created_at'] );
		} catch ( \Throwable $error ) {
			// The send may have gone out. The row stays pending and is never sent again.
			error_log( sprintf( '[prc-block-forms] Sending response %d threw "%s". It stays "pending".', $id, $error->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- VIP routes this to the log stream.
			return $this->done( $moved, 'failed', 'The email may or may not have been sent. Check the mail log before sending it again.' );
		}

		// Unconditional on purpose. Only this request can hold the row at pending: every other write is
		// conditional on spam or send_failed. The email is out, so the row must say so, or a later
		// Not spam would send it again.
		if ( ! $this->repository->set_status( $id, $outcome ) ) {
			error_log( sprintf( '[prc-block-forms] Form response %d could not be set to "%s". It stays "pending".', $id, $outcome ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- VIP routes this to the log stream.
		}

		if ( Form_Send_Email::SEND_UNKNOWN === $outcome ) {
			Form_Send_Email::log_send_unknown(
				array(
					'form_post_id' => (int) $row['form_id'],
					'response_id'  => $id,
					'message_ids'  => $report['message_ids'] ?? array(),
				)
			);
			return $this->done( $moved, 'unknown', 'The mail service did not answer in time. The email may or may not have been delivered, and it was not sent again. Check the mail log before you send it by hand.' );
		}

		return Form_Send_Email::SEND_SENT === $outcome
			? $this->done( $moved, 'emailed' )
			: $this->done( $moved, 'failed', 'The email could not be sent. The response is in the inbox with the status Send failed. Mark it as spam and then not spam to try again.' );
	}

	/**
	 * Stop a request that no longer owns the response. Nothing is written and nothing is sent.
	 *
	 * The log line holds IDs only, never a field value or an address.
	 *
	 * @param array  $row   The response row.
	 * @param string $stage Which check stopped it: lease or status_changed.
	 * @return array{moved: bool, outcome: string, message: string}
	 */
	private function stop_for_lost_lease( array $row, string $stage ): array {
		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- VIP routes this to the log stream.
			sprintf(
				'[prc-block-forms] lease_lost: stage=%s form_post_id=%d response_id=%d at=%s. This Not spam request lost its lease before it wrote a status, so it stopped. Nothing was sent by this request.',
				$stage,
				absint( $row['form_id'] ),
				absint( $row['id'] ),
				gmdate( 'c' )
			)
		);

		return $this->done( false, 'failed', self::BUSY_MESSAGE );
	}

	/**
	 * Record that the form has no recipient. The write is conditional, like the pending write: a row
	 * that is already send_failed needs no write, and a row that another request changed is left alone.
	 *
	 * @param array $row The response row read under the lease.
	 * @return array{moved: bool, outcome: string, message: string}
	 */
	private function fail_without_recipient( array $row ): array {
		$id = (int) $row['id'];
		if ( 'send_failed' !== $row['status'] ) {
			$changed = $this->repository->transition_status( $id, 'send_failed', array_values( array_diff( self::SENDABLE_STATUSES, array( 'send_failed' ) ) ) );
			if ( 0 === $changed ) {
				return $this->stop_for_lost_lease( $row, 'status_changed' );
			}
			if ( 1 !== $changed ) {
				error_log( sprintf( '[prc-block-forms] Form response %d could not be set to "send_failed". Nothing was sent.', $id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- VIP routes this to the log stream.
				return $this->done( false, 'failed', 'The status could not be saved, so no email was sent. The response is still in the Spam folder.' );
			}
		}
		return $this->done( $this->move( $id, false ), 'failed', 'No recipient is configured for this form, so no email was sent.' );
	}

	/**
	 * Move one response to the inbox.
	 *
	 * @param int  $id           The response ID.
	 * @param bool $reset_status Whether a `spam` status becomes `received`.
	 * @return bool Whether the row changed.
	 */
	private function move( int $id, bool $reset_status ): bool {
		return $this->repository->set_spam( array( $id ), false, $reset_status ) > 0;
	}

	/**
	 * Build one result.
	 *
	 * @param bool   $moved   Whether the row left the Spam folder.
	 * @param string $outcome emailed, skipped, failed, or unknown.
	 * @param string $message A message for the person, when the outcome is failed.
	 * @return array{moved: bool, outcome: string, message: string}
	 */
	private function done( bool $moved, string $outcome, string $message = '' ): array {
		return array(
			'moved'   => $moved,
			'outcome' => $outcome,
			'message' => $message,
		);
	}
}
