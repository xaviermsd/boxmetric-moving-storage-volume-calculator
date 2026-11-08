<?php
/**
 * Privacy tools: suggested policy text and the personal data exporter and
 * eraser for stored quote requests.
 *
 * @package MSVCalc
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Connects quote requests to Tools > Export / Erase Personal Data.
 */
final class MSVCalc_Privacy {

	/**
	 * Leads handled per exporter or eraser page.
	 */
	const PER_PAGE = 50;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'policy_text' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * Suggested text for the site's privacy policy.
	 */
	public static function policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content  = '<p class="privacy-policy-tutorial">' . esc_html__( 'Moving & Storage Volume Calculator does not track visitors and makes no requests to outside services. It only handles personal data when a visitor sends a quote request.', 'moving-storage-volume-calculator' ) . '</p>';
		$content .= '<strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'moving-storage-volume-calculator' ) . '</strong> ';
		$content .= '<p>' . esc_html__( 'When you ask for a quote with our volume calculator, we collect the details you enter (such as your name, email address, phone number, moving addresses and date) and your list of items. We use them only to prepare and send your quote. They are emailed to us and kept in our website database until we delete them, or for the period we set for quote requests. You can ask us for a copy of this data or ask us to erase it.', 'moving-storage-volume-calculator' ) . '</p>';
		$content .= '<p>' . esc_html__( 'With your request we also record when you agreed to our consent statement and its wording, the page you sent it from and the website that referred you (if any). Our team may add internal notes and reminders about your enquiry. All of this is included when you ask for a copy of your data and removed when you ask us to erase it.', 'moving-storage-volume-calculator' ) . '</p>';
		$content .= '<p>' . esc_html__( 'If you use the calculator without sending a request, your list is kept only in your own browser (local storage) so you can come back to it. It is not sent to us.', 'moving-storage-volume-calculator' ) . '</p>';

		wp_add_privacy_policy_content( __( 'Moving & Storage Volume Calculator', 'moving-storage-volume-calculator' ), wp_kses_post( $content ) );
	}

	/**
	 * Registers the exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_exporter( array $exporters ): array {
		$exporters['moving-storage-volume-calculator'] = array(
			'exporter_friendly_name' => __( 'Volume calculator quote requests', 'moving-storage-volume-calculator' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Registers the eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers['moving-storage-volume-calculator'] = array(
			'eraser_friendly_name' => __( 'Volume calculator quote requests', 'moving-storage-volume-calculator' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Lead IDs for an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number, from 1.
	 * @return array<int, int>
	 */
	private static function lead_ids( string $email, int $page ): array {
		$email = strtolower( sanitize_email( $email ) );
		if ( '' === $email ) {
			return array();
		}
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => MSVCalc_Leads::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => self::PER_PAGE,
					'paged'          => max( 1, $page ),
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => MSVCalc_Leads::EMAIL_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only runs for privacy requests.
					'meta_value'     => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Only runs for privacy requests.
				)
			)
		);
	}

	/**
	 * Exports the quote requests sent with an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public static function export( string $email, int $page = 1 ): array {
		$ids  = self::lead_ids( $email, $page );
		$data = array();

		foreach ( $ids as $id ) {
			$lead    = MSVCalc_Leads::data( $id );
			$contact = (array) ( $lead['contact'] ?? array() );
			$result  = (array) ( $lead['result'] ?? array() );
			$items   = array();
			foreach ( (array) ( $result['lines'] ?? array() ) as $line ) {
				$items[] = sprintf( '%d × %s', (int) ( $line['qty'] ?? 0 ), (string) ( $line['name'] ?? '' ) );
			}

			$fields  = array(
				__( 'Sent', 'moving-storage-volume-calculator' )        => (string) get_the_date( 'Y-m-d H:i', $id ),
				__( 'Name', 'moving-storage-volume-calculator' )        => (string) ( $contact['name'] ?? '' ),
				__( 'Email', 'moving-storage-volume-calculator' )       => (string) ( $contact['email'] ?? '' ),
				__( 'Phone', 'moving-storage-volume-calculator' )       => (string) ( $contact['phone'] ?? '' ),
				__( 'Moving from', 'moving-storage-volume-calculator' ) => (string) ( $contact['from'] ?? '' ),
				__( 'Moving to', 'moving-storage-volume-calculator' )   => (string) ( $contact['to'] ?? '' ),
				__( 'Move date', 'moving-storage-volume-calculator' )   => (string) ( $contact['date'] ?? '' ),
				__( 'Notes', 'moving-storage-volume-calculator' )       => (string) ( $contact['notes'] ?? '' ),
				__( 'Volume (m³)', 'moving-storage-volume-calculator' ) => (string) ( $result['m3'] ?? '' ),
				__( 'Inventory', 'moving-storage-volume-calculator' )   => implode( ', ', $items ),
			);
			$details = MSVCalc_Leads::detail_rows( $contact );
			foreach ( $details as $label => $value ) {
				if ( ! isset( $fields[ $label ] ) ) {
					$fields[ $label ] = $value;
				}
			}
			$fields[ __( 'Status', 'moving-storage-volume-calculator' ) ] = (string) ( MSVCalc_Leads::statuses()[ MSVCalc_Leads::status( $id ) ] ?? '' );

			$consent = (array) get_post_meta( $id, MSVCalc_Leads::CONSENT_META, true );
			if ( ! empty( $consent['time'] ) ) {
				$fields[ __( 'Consent given', 'moving-storage-volume-calculator' ) ] = wp_date( 'Y-m-d H:i', (int) $consent['time'] ) . ( empty( $consent['text'] ) ? '' : ', ' . (string) $consent['text'] );
				if ( ! empty( $consent['privacy'] ) ) {
					$fields[ __( 'Privacy policy shown', 'moving-storage-volume-calculator' ) ] = (string) $consent['privacy'];
				}
			}
			$source = (array) get_post_meta( $id, MSVCalc_Leads::SOURCE_META, true );
			$fields[ __( 'Sent from page', 'moving-storage-volume-calculator' ) ] = (string) ( $source['page'] ?? '' );
			$fields[ __( 'Referring site', 'moving-storage-volume-calculator' ) ] = (string) ( $source['referrer'] ?? '' );

			$followup = (string) get_post_meta( $id, MSVCalc_Leads::FOLLOWUP_META, true );
			if ( '' !== $followup ) {
				$fields[ __( 'Follow-up', 'moving-storage-volume-calculator' ) ] = trim( $followup . ' ' . (string) get_post_meta( $id, MSVCalc_Leads::FOLLOWUP_NOTE_META, true ) );
			}
			$notes = array();
			foreach ( MSVCalc_Leads::history( $id ) as $entry ) {
				if ( 'note' === ( $entry['type'] ?? '' ) && '' !== (string) ( $entry['text'] ?? '' ) ) {
					$notes[] = wp_date( 'Y-m-d', (int) ( $entry['time'] ?? 0 ) ) . ': ' . (string) $entry['text'];
				}
			}
			$fields[ __( 'Notes by our team', 'moving-storage-volume-calculator' ) ] = implode( "\n", $notes );

			$rows = array();
			foreach ( $fields as $name => $value ) {
				if ( '' !== $value ) {
					$rows[] = array(
						'name'  => $name,
						'value' => $value,
					);
				}
			}

			$data[] = array(
				'group_id'    => 'msvcalc-quote-requests',
				'group_label' => __( 'Volume calculator quote requests', 'moving-storage-volume-calculator' ),
				'item_id'     => 'msvcalc-lead-' . $id,
				'data'        => $rows,
			);
		}

		return array(
			'data' => $data,
			'done' => count( $ids ) < self::PER_PAGE,
		);
	}

	/**
	 * Erases the quote requests sent with an email address.
	 *
	 * Always reads page 1, because erased leads drop out of the results.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number (unused, see above).
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public static function erase( string $email, int $page = 1 ): array {
		unset( $page );
		$ids     = self::lead_ids( $email, 1 );
		$removed = false;

		foreach ( $ids as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				$removed = true;
			}
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => count( $ids ) < self::PER_PAGE,
		);
	}
}
