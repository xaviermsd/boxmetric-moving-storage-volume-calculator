<?php
/**
 * Quote requests ("leads"), stored as a private custom post type.
 *
 * @package MSVCalc
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Stores, lists, exports and expires quote requests.
 */
final class MSVCalc_Leads {

	/**
	 * Post type name.
	 */
	const POST_TYPE = 'msvcalc_lead';

	/**
	 * Post meta key holding the request details.
	 */
	const META = '_msvcalc_lead';

	/**
	 * Post meta key holding the follow-up status.
	 */
	const STATUS_META = '_msvcalc_status';

	/**
	 * Post meta key holding the customer email, for privacy lookups.
	 */
	const EMAIL_META = '_msvcalc_email';

	/**
	 * Total volume in m³, for sorting.
	 */
	const M3_META = '_msvcalc_m3';

	/**
	 * Lower-case name, email, phone and addresses, for searching.
	 */
	const SEARCH_META = '_msvcalc_search';

	/**
	 * Follow-up date (Y-m-d), or empty.
	 */
	const FOLLOWUP_META = '_msvcalc_followup';

	/**
	 * Follow-up note.
	 */
	const FOLLOWUP_NOTE_META = '_msvcalc_followup_note';

	/**
	 * Activity timeline: a list of entries with time, user, type and text.
	 */
	const HISTORY_META = '_msvcalc_history';

	/**
	 * Where the lead came from: page, referrer and campaign tags.
	 */
	const SOURCE_META = '_msvcalc_source';

	/**
	 * Fingerprint of a submission, to catch repeats.
	 */
	const HASH_META = '_msvcalc_hash';

	/**
	 * Meta key for the consent record: when it was given and the exact text shown.
	 */
	const CONSENT_META = '_msvcalc_consent';

	/**
	 * Meta key for the notification email result: sent or failed.
	 */
	const MAIL_META = '_msvcalc_mail';

	/**
	 * Version of the stored lead data, for one-off upgrades.
	 */
	const DB_OPTION = 'msvcalc_leads_db';

	/**
	 * Current lead data version.
	 */
	const DB_VERSION = 3;

	/**
	 * Capability for working with leads. Administrators always have it;
	 * Editors get it when "Who can manage leads" allows them.
	 */
	const CAP = 'msvcalc_manage_leads';

	/**
	 * Leads processed per upgrade batch.
	 */
	const UPGRADE_BATCH = 200;

	/**
	 * Repeat submissions inside this window are merged into the first lead.
	 */
	const DUPLICATE_WINDOW = 900;

	/**
	 * Daily clean-up event.
	 */
	const CRON_HOOK = 'msvcalc_purge_leads';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'purge' ) );

		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'upgrade' ) );
			add_action( 'admin_post_msvcalc_export_leads', array( __CLASS__, 'export_csv' ) );
		}
	}

	/**
	 * Registers the post type. Leads are only visible in wp-admin, to users
	 * who can manage options.
	 */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Quote requests', 'moving-storage-volume-calculator' ),
					'singular_name'      => __( 'Quote request', 'moving-storage-volume-calculator' ),
					'menu_name'          => __( 'Quote requests', 'moving-storage-volume-calculator' ),
					'all_items'          => __( 'Quote requests', 'moving-storage-volume-calculator' ),
					'edit_item'          => __( 'Quote request', 'moving-storage-volume-calculator' ),
					'view_item'          => __( 'View quote request', 'moving-storage-volume-calculator' ),
					'search_items'       => __( 'Search quote requests', 'moving-storage-volume-calculator' ),
					'not_found'          => __( 'No quote requests yet. They appear here when visitors send their inventory.', 'moving-storage-volume-calculator' ),
					'not_found_in_trash' => __( 'No quote requests in the trash.', 'moving-storage-volume-calculator' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'query_var'           => false,
				'rewrite'             => false,
				'has_archive'         => false,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'capabilities'        => array(
					'edit_post'          => self::CAP,
					'read_post'          => self::CAP,
					'delete_post'        => self::CAP,
					'edit_posts'         => self::CAP,
					'edit_others_posts'  => self::CAP,
					'delete_posts'       => self::CAP,
					'publish_posts'      => self::CAP,
					'read_private_posts' => self::CAP,
					'create_posts'       => 'do_not_allow',
				),
				'map_meta_cap'        => false,
			)
		);
	}

	/**
	 * Follow-up statuses.
	 *
	 * @return array<string, string>
	 */
	public static function statuses(): array {
		return array(
			'new'         => __( 'New', 'moving-storage-volume-calculator' ),
			'contacted'   => __( 'Contacted', 'moving-storage-volume-calculator' ),
			'in_progress' => __( 'In progress', 'moving-storage-volume-calculator' ),
			'converted'   => __( 'Converted', 'moving-storage-volume-calculator' ),
			'lost'        => __( 'Lost', 'moving-storage-volume-calculator' ),
		);
	}

	/**
	 * Statuses that still need work.
	 *
	 * @return array<int, string>
	 */
	public static function open_statuses(): array {
		return array( 'new', 'contacted', 'in_progress' );
	}

	/**
	 * A lead's status, with values from earlier versions mapped across.
	 *
	 * @param int $post_id Lead ID.
	 * @return string
	 */
	public static function status( int $post_id ): string {
		$status = (string) get_post_meta( $post_id, self::STATUS_META, true );
		$legacy = array(
			'quoted' => 'in_progress',
			'won'    => 'converted',
		);
		$status = $legacy[ $status ] ?? $status;
		return isset( self::statuses()[ $status ] ) ? $status : 'new';
	}

	/**
	 * Link to a lead in the Leads screen.
	 *
	 * @param int $post_id Lead ID.
	 * @return string
	 */
	public static function admin_link( int $post_id ): string {
		return admin_url( 'admin.php?page=' . MSVCalc_Admin::LEADS_SLUG . '&lead=' . $post_id );
	}

	/**
	 * Short reference shown to the team, e.g. "Q-0012".
	 *
	 * @param int $post_id Lead ID.
	 * @return string
	 */
	public static function reference( int $post_id ): string {
		return 'Q-' . str_pad( (string) $post_id, 4, '0', STR_PAD_LEFT );
	}

	/**
	 * Adds an entry to a lead's activity timeline.
	 *
	 * @param int    $post_id Lead ID.
	 * @param string $type    created, status, note, followup, followup_done, contact, repeat, email_failed or email_sent.
	 * @param string $text    Plain text.
	 */
	public static function log( int $post_id, string $type, string $text = '' ): void {
		$history   = self::history( $post_id );
		$history[] = array(
			'time' => time(),
			'user' => get_current_user_id(),
			'type' => sanitize_key( $type ),
			'text' => sanitize_textarea_field( $text ),
		);
		// Keep the timeline to a sensible size.
		$history = array_slice( $history, -200 );
		update_post_meta( $post_id, self::HISTORY_META, $history );
	}

	/**
	 * A lead's activity timeline, oldest first.
	 *
	 * @param int $post_id Lead ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function history( int $post_id ): array {
		$history = get_post_meta( $post_id, self::HISTORY_META, true );
		return is_array( $history ) ? array_values( array_filter( $history, 'is_array' ) ) : array();
	}

	/**
	 * Where a lead came from, in words: a campaign, another site or the
	 * website itself.
	 *
	 * @param int $post_id Lead ID.
	 * @return string
	 */
	public static function source_text( int $post_id ): string {
		$source = (array) get_post_meta( $post_id, self::SOURCE_META, true );
		if ( ! empty( $source['utm_source'] ) ) {
			return trim( (string) $source['utm_source'] . ( empty( $source['utm_medium'] ) ? '' : ' / ' . (string) $source['utm_medium'] ) );
		}
		$referrer = isset( $source['referrer'] ) ? (string) wp_parse_url( (string) $source['referrer'], PHP_URL_HOST ) : '';
		$own      = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( '' !== $referrer && $referrer !== $own ) {
			return preg_replace( '/^www\./', '', $referrer );
		}
		return __( 'Website calculator', 'moving-storage-volume-calculator' );
	}

	/**
	 * Fingerprint of a submission: the same person sending the same list.
	 *
	 * @param array<string, mixed> $contact Contact details.
	 * @param array<string, mixed> $result  Calculation result.
	 * @return string
	 */
	public static function fingerprint( array $contact, array $result ): string {
		$lines = array();
		foreach ( (array) ( $result['lines'] ?? array() ) as $line ) {
			$lines[] = (string) ( $line['name'] ?? '' ) . ':' . (int) ( $line['qty'] ?? 0 );
		}
		return md5( strtolower( (string) ( $contact['email'] ?? '' ) ) . '|' . implode( ',', $lines ) );
	}

	/**
	 * A lead with the same fingerprint received in the last few minutes.
	 *
	 * @param string $hash Fingerprint.
	 * @return int Lead ID, or 0.
	 */
	public static function find_duplicate( string $hash ): int {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => self::HASH_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Single lookup per quote request.
				'meta_value'     => $hash, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Single lookup per quote request.
				'date_query'     => array(
					array(
						'column' => 'post_date_gmt',
						'after'  => gmdate( 'Y-m-d H:i:s', time() - self::DUPLICATE_WINDOW ),
					),
				),
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Stores the sortable and searchable copies of a lead's details.
	 *
	 * @param int $post_id Lead ID.
	 */
	public static function index( int $post_id ): void {
		$data    = self::data( $post_id );
		$contact = (array) ( $data['contact'] ?? array() );
		$result  = (array) ( $data['result'] ?? array() );
		update_post_meta( $post_id, self::M3_META, round( (float) ( $result['m3'] ?? 0 ), 2 ) );
		$search = array( $contact['name'] ?? '', $contact['email'] ?? '', $contact['phone'] ?? '', $contact['from'] ?? '', $contact['to'] ?? '', self::reference( $post_id ) );
		update_post_meta( $post_id, self::SEARCH_META, strtolower( implode( ' ', array_map( 'strval', $search ) ) ) );
	}

	/**
	 * One-off upgrade of leads stored by earlier versions: new statuses,
	 * sortable volume and search text.
	 */
	public static function upgrade(): void {
		if ( (int) get_option( self::DB_OPTION ) >= self::DB_VERSION ) {
			return;
		}
		self::sync_roles();
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		// Works through every lead in batches. Nothing is deleted or rewritten:
		// it only adds the status, search index and first timeline entry that
		// older versions did not store. If a request runs out of time, the
		// next admin page load carries on from the saved page.
		$progress = self::DB_OPTION . '_page';
		$page     = max( 1, (int) get_option( $progress, 1 ) );
		$started  = time();
		do {
			$ids = get_posts(
				array(
					'post_type'              => self::POST_TYPE,
					'post_status'            => 'any',
					'posts_per_page'         => self::UPGRADE_BATCH,
					'paged'                  => $page,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
				)
			);
			foreach ( $ids as $id ) {
				self::upgrade_lead( (int) $id );
			}
			++$page;
			update_option( $progress, $page, false );
			$more = self::UPGRADE_BATCH === count( $ids );
		} while ( $more && time() - $started < 20 );

		if ( $more ) {
			return; // More to do on the next load.
		}
		delete_option( $progress );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Brings one lead up to the current data version. Safe to run twice.
	 *
	 * @param int $id Lead ID.
	 */
	private static function upgrade_lead( int $id ): void {
		update_post_meta( $id, self::STATUS_META, self::status( $id ) );
		self::index( $id );
		if ( ! self::history( $id ) ) {
			update_post_meta(
				$id,
				self::HISTORY_META,
				array(
					array(
						'time' => (int) get_post_time( 'U', true, $id ),
						'user' => 0,
						'type' => 'created',
						'text' => '',
					),
				)
			);
		}
	}

	/**
	 * Gives the leads capability to Administrators, and to Editors when the
	 * "Who can manage leads" setting allows it.
	 */
	public static function sync_roles(): void {
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( self::CAP ) ) {
			$admin->add_cap( self::CAP );
		}
		$editor = get_role( 'editor' );
		if ( ! $editor ) {
			return;
		}
		$allow = 'editors' === MSVCalc_Settings::get( 'leads_access' );
		if ( $allow && ! $editor->has_cap( self::CAP ) ) {
			$editor->add_cap( self::CAP );
		} elseif ( ! $allow && $editor->has_cap( self::CAP ) ) {
			$editor->remove_cap( self::CAP );
		}
	}

	/**
	 * Lead counts for the dashboard.
	 *
	 * @return array<string, int>
	 */
	public static function stats(): array {
		$count  = static function ( array $args ): int {
			$query = new WP_Query(
				array_merge(
					array(
						'post_type'      => self::POST_TYPE,
						'post_status'    => 'private',
						'posts_per_page' => 1,
						'fields'         => 'ids',
					),
					$args
				)
			);
			return (int) $query->found_posts;
		};
		$status = static function ( $value ): array {
			return array(
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin dashboard counts.
					array(
						'key'     => self::STATUS_META,
						'value'   => (array) $value,
						'compare' => 'IN',
					),
				),
			);
		};

		$stats = array(
			'total'     => $count( array() ),
			'month'     => $count(
				array(
					'date_query' => array(
						array(
							'after'     => wp_date( 'Y-m-01 00:00:00' ),
							'inclusive' => true,
						),
					),
				)
			),
			'followups' => $count( self::followup_query( true ) ),
			'overdue'   => $count( self::followup_query( false ) ),
			'failed'    => $count(
				array(
					'meta_key'   => self::MAIL_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small admin count.
					'meta_value' => 'failed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Small admin count.
				)
			),
		);
		foreach ( array_keys( self::statuses() ) as $key ) {
			$stats[ $key ] = $count( $status( $key ) );
		}
		$closed        = $stats['converted'] + $stats['lost'];
		$stats['rate'] = $closed ? (int) round( $stats['converted'] / $closed * 100 ) : 0;
		return $stats;
	}

	/**
	 * Query arguments for open leads with a follow-up due today or earlier
	 * (or, with $include_today false, overdue ones only).
	 *
	 * @param bool $include_today Count today as due.
	 * @return array<string, mixed>
	 */
	public static function followup_query( bool $include_today ): array {
		return array(
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin follow-up list.
				'relation' => 'AND',
				array(
					'key'     => self::FOLLOWUP_META,
					'value'   => wp_date( 'Y-m-d' ),
					'compare' => $include_today ? '<=' : '<',
					'type'    => 'DATE',
				),
				array(
					'key'     => self::FOLLOWUP_META,
					'value'   => '',
					'compare' => '!=',
				),
				array(
					'key'     => self::STATUS_META,
					'value'   => self::open_statuses(),
					'compare' => 'IN',
				),
			),
		);
	}

	/**
	 * Reads and sanitizes the list filters from the request.
	 *
	 * @return array<string, string>
	 */
	public static function filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
		$get     = static function ( string $key ): string {
			return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		};
		$filters = array(
			'status'  => sanitize_key( $get( 'status' ) ),
			'service' => sanitize_key( $get( 'service' ) ),
			'from'    => $get( 'date_from' ),
			'to'      => $get( 'date_to' ),
			's'       => $get( 's' ),
			'orderby' => sanitize_key( $get( 'orderby' ) ),
			'order'   => 'asc' === strtolower( $get( 'order' ) ) ? 'ASC' : 'DESC',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		foreach ( array( 'from', 'to' ) as $key ) {
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters[ $key ] ) ) {
				$filters[ $key ] = '';
			}
		}
		if ( ! isset( self::statuses()[ $filters['status'] ] ) && ! in_array( $filters['status'], array( 'followup', 'mail_failed' ), true ) ) {
			$filters['status'] = '';
		}
		if ( ! isset( self::services()[ $filters['service'] ] ) ) {
			$filters['service'] = '';
		}
		return $filters;
	}

	/**
	 * WP_Query arguments for a filtered lead list.
	 *
	 * @param array<string, string> $filters From filters().
	 * @return array<string, mixed>
	 */
	public static function query_args( array $filters ): array {
		$args = array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'private',
			'orderby'     => 'date',
			'order'       => $filters['order'] ?? 'DESC',
		);
		$meta = array();

		if ( 'followup' === ( $filters['status'] ?? '' ) ) {
			$meta = self::followup_query( true )['meta_query'];
		} elseif ( 'mail_failed' === ( $filters['status'] ?? '' ) ) {
			$meta[] = array(
				'key'   => self::MAIL_META,
				'value' => 'failed',
			);
		} elseif ( ! empty( $filters['status'] ) ) {
			$meta[] = array(
				'key'   => self::STATUS_META,
				'value' => $filters['status'],
			);
		}
		if ( ! empty( $filters['s'] ) ) {
			$meta[] = array(
				'key'     => self::SEARCH_META,
				'value'   => strtolower( $filters['s'] ),
				'compare' => 'LIKE',
			);
		}
		if ( ! empty( $filters['service'] ) ) {
			$meta[] = array(
				'key'     => self::META,
				'value'   => '"service";s:' . strlen( $filters['service'] ) . ':"' . $filters['service'] . '"',
				'compare' => 'LIKE',
			);
		}
		if ( $meta ) {
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only lead filters.
		}

		$dates = array( 'inclusive' => true );
		if ( ! empty( $filters['from'] ) ) {
			$dates['after'] = $filters['from'] . ' 00:00:00';
		}
		if ( ! empty( $filters['to'] ) ) {
			$dates['before'] = $filters['to'] . ' 23:59:59';
		}
		if ( count( $dates ) > 1 ) {
			$args['date_query'] = array( $dates );
		}

		switch ( $filters['orderby'] ?? '' ) {
			case 'customer':
				$args['orderby'] = 'title';
				break;
			case 'volume':
				$args['meta_key'] = self::M3_META; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin-only sort.
				$args['orderby']  = 'meta_value_num';
				break;
			case 'followup':
				$args['meta_key'] = self::FOLLOWUP_META; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin-only sort.
				$args['orderby']  = 'meta_value';
				break;
		}
		return $args;
	}

	/**
	 * Floor choices for each address.
	 *
	 * @return array<string, string>
	 */
	public static function floors(): array {
		return array(
			'ground' => __( 'Ground floor', 'moving-storage-volume-calculator' ),
			'house'  => __( 'House with upstairs', 'moving-storage-volume-calculator' ),
			'1'      => __( '1st floor', 'moving-storage-volume-calculator' ),
			'2'      => __( '2nd floor', 'moving-storage-volume-calculator' ),
			'3'      => __( '3rd floor', 'moving-storage-volume-calculator' ),
			'4'      => __( '4th floor', 'moving-storage-volume-calculator' ),
			'5'      => __( '5th floor or higher', 'moving-storage-volume-calculator' ),
		);
	}

	/**
	 * Parking choices.
	 *
	 * @return array<string, string>
	 */
	public static function parking(): array {
		return array(
			'door' => __( 'Parking right outside', 'moving-storage-volume-calculator' ),
			'near' => __( 'Parking within 20 m (65 ft)', 'moving-storage-volume-calculator' ),
			'far'  => __( 'Long carry or parking permit needed', 'moving-storage-volume-calculator' ),
		);
	}

	/**
	 * Extra services a visitor can tick.
	 *
	 * @return array<string, string>
	 */
	public static function extras(): array {
		return array(
			'packing'   => __( 'Packing service', 'moving-storage-volume-calculator' ),
			'materials' => __( 'Boxes and packing materials', 'moving-storage-volume-calculator' ),
			'dismantle' => __( 'Furniture dismantling and reassembly', 'moving-storage-volume-calculator' ),
			'storage'   => __( 'Storage', 'moving-storage-volume-calculator' ),
			'disposal'  => __( 'Disposal of unwanted items', 'moving-storage-volume-calculator' ),
			'cleaning'  => __( 'Cleaning', 'moving-storage-volume-calculator' ),
		);
	}

	/**
	 * Readable access details for one end of the move, e.g. "2nd floor, lift".
	 *
	 * @param array<string, mixed> $contact Contact details.
	 * @param string               $end     "from" or "to".
	 * @return string
	 */
	public static function access_text( array $contact, string $end ): string {
		$parts = array();
		$floor = (string) ( $contact[ $end . '_floor' ] ?? '' );
		if ( isset( self::floors()[ $floor ] ) ) {
			$parts[] = self::floors()[ $floor ];
		}
		if ( ! empty( $contact[ $end . '_lift' ] ) ) {
			$parts[] = __( 'lift available', 'moving-storage-volume-calculator' );
		} elseif ( $floor && ! in_array( $floor, array( 'ground', 'house' ), true ) ) {
			$parts[] = __( 'no lift', 'moving-storage-volume-calculator' );
		}
		return implode( ', ', $parts );
	}

	/**
	 * Readable list of the ticked extra services.
	 *
	 * @param array<string, mixed> $contact Contact details.
	 * @return string
	 */
	public static function extras_text( array $contact ): string {
		$labels = array();
		foreach ( (array) ( $contact['extras'] ?? array() ) as $key ) {
			if ( is_string( $key ) && isset( self::extras()[ $key ] ) ) {
				$labels[] = self::extras()[ $key ];
			}
		}
		return implode( ', ', $labels );
	}

	/**
	 * Every detail row shown in emails and the lead screen, label => value.
	 * Empty values are kept so callers can skip them.
	 *
	 * @param array<string, mixed> $contact Contact details.
	 * @return array<string, string>
	 */
	public static function detail_rows( array $contact ): array {
		return array(
			__( 'Name', 'moving-storage-volume-calculator' )           => (string) ( $contact['name'] ?? '' ),
			__( 'Email', 'moving-storage-volume-calculator' )          => (string) ( $contact['email'] ?? '' ),
			__( 'Phone', 'moving-storage-volume-calculator' )          => (string) ( $contact['phone'] ?? '' ),
			__( 'Service', 'moving-storage-volume-calculator' )        => (string) ( self::services()[ $contact['service'] ?? '' ] ?? '' ),
			__( 'Moving from', 'moving-storage-volume-calculator' )    => (string) ( $contact['from'] ?? '' ),
			__( 'Access (from)', 'moving-storage-volume-calculator' )  => self::access_text( $contact, 'from' ),
			__( 'Moving to', 'moving-storage-volume-calculator' )      => (string) ( $contact['to'] ?? '' ),
			__( 'Access (to)', 'moving-storage-volume-calculator' )    => self::access_text( $contact, 'to' ),
			__( 'Parking', 'moving-storage-volume-calculator' )        => (string) ( self::parking()[ $contact['parking'] ?? '' ] ?? '' ),
			__( 'Move date', 'moving-storage-volume-calculator' )      => ! empty( $contact['date'] ) ? (string) mysql2date( (string) get_option( 'date_format' ), (string) $contact['date'] ) : '',
			__( 'Extra services', 'moving-storage-volume-calculator' ) => self::extras_text( $contact ),
		);
	}

	/**
	 * Moving services a visitor can pick.
	 *
	 * @return array<string, string>
	 */
	public static function services(): array {
		return array(
			'removal'       => __( 'House or flat removal', 'moving-storage-volume-calculator' ),
			'storage'       => __( 'Storage', 'moving-storage-volume-calculator' ),
			'removal_store' => __( 'Removal and storage', 'moving-storage-volume-calculator' ),
			'office'        => __( 'Office move', 'moving-storage-volume-calculator' ),
			'international' => __( 'International move', 'moving-storage-volume-calculator' ),
			'man_van'       => __( 'Man and van', 'moving-storage-volume-calculator' ),
		);
	}

	/**
	 * Stores a lead. Every value must already be sanitized by the caller.
	 *
	 * @param array<string, mixed>  $contact Contact details.
	 * @param array<string, mixed>  $result  Calculation result.
	 * @param array<string, string> $source  Page, referrer and campaign tags.
	 * @return int Post ID, or 0 on failure.
	 */
	public static function create( array $contact, array $result, array $source = array() ): int {
		$title = sprintf(
			/* translators: 1: customer name, 2: volume. */
			__( '%1$s, %2$s', 'moving-storage-volume-calculator' ),
			$contact['name'],
			MSVCalc_Calculator::format_volume( (float) $result['m3'], (string) MSVCalc_Settings::get( 'units' ) )
		);

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		update_post_meta(
			$post_id,
			self::META,
			array(
				'contact' => $contact,
				'result'  => $result,
				'units'   => (string) MSVCalc_Settings::get( 'units' ),
				'version' => MSVCALC_VERSION,
			)
		);
		update_post_meta( $post_id, self::STATUS_META, 'new' );
		update_post_meta( $post_id, self::EMAIL_META, strtolower( (string) $contact['email'] ) );
		update_post_meta( $post_id, self::HASH_META, self::fingerprint( $contact, $result ) );
		update_post_meta( $post_id, self::SOURCE_META, $source );
		update_post_meta(
			$post_id,
			self::CONSENT_META,
			array(
				'time'    => time(),
				'text'    => MSVCalc_Settings::text( 'consent_text' ),
				'privacy' => (string) get_privacy_policy_url(),
			)
		);
		self::index( $post_id );
		self::log( $post_id, 'created' );

		return (int) $post_id;
	}

	/**
	 * Records whether the notification email for a lead went out. A failed
	 * email never loses the enquiry: the lead stays in Leads, is flagged, and
	 * can be sent again from its page.
	 *
	 * @param int  $post_id Lead ID.
	 * @param bool $sent    Whether wp_mail() accepted the message.
	 * @param bool $resend  Whether this was a manual resend.
	 */
	public static function mail_result( int $post_id, bool $sent, bool $resend = false ): void {
		update_post_meta( $post_id, self::MAIL_META, $sent ? 'sent' : 'failed' );
		if ( ! $sent ) {
			self::log( $post_id, 'email_failed' );
		} elseif ( $resend ) {
			self::log( $post_id, 'email_sent' );
		}
	}

	/**
	 * The stored details of a lead.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function data( int $post_id ): array {
		$data = get_post_meta( $post_id, self::META, true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Streams leads as a branded Excel workbook (format=xlsx) or a plain
	 * CSV file for importing into a CRM.
	 */
	public static function export_csv(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to export quote requests.', 'moving-storage-volume-calculator' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'msvcalc_export_leads' );

		$lead   = isset( $_GET['lead'] ) ? absint( wp_unslash( $_GET['lead'] ) ) : 0;
		$format = isset( $_GET['format'] ) && 'xlsx' === sanitize_key( wp_unslash( $_GET['format'] ) ) ? 'xlsx' : 'csv';
		if ( $lead ) {
			$ids = self::POST_TYPE === get_post_type( $lead ) ? array( $lead ) : array();
		} else {
			$ids = get_posts(
				array_merge(
					self::query_args( self::filters() ),
					array(
						'posts_per_page' => 5000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin-only CSV export of IDs.
						'fields'         => 'ids',
						'no_found_rows'  => true,
					)
				)
			);
		}

		$status_map     = array_flip( self::statuses() );
		$columns        = array(
			array(
				'label' => __( 'Reference', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 16,
			),
			array(
				'label' => __( 'Received', 'moving-storage-volume-calculator' ),
				'type'  => 'datetime',
				'width' => 18,
			),
			array(
				'label'  => __( 'Status', 'moving-storage-volume-calculator' ),
				'type'   => 'status',
				'width'  => 14,
				'status' => $status_map,
			),
			array(
				'label' => __( 'Follow-up', 'moving-storage-volume-calculator' ),
				'type'  => 'date',
				'width' => 12,
			),
			array(
				'label' => __( 'Name', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 28,
			),
			array(
				'label' => __( 'Email', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 34,
			),
			array(
				'label' => __( 'Phone', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 18,
			),
			array(
				'label' => __( 'Service', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 22,
			),
			array(
				'label' => __( 'Moving from', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 34,
			),
			array(
				'label' => __( 'Access (from)', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 26,
			),
			array(
				'label' => __( 'Moving to', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 34,
			),
			array(
				'label' => __( 'Access (to)', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 26,
			),
			array(
				'label' => __( 'Parking', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 26,
			),
			array(
				'label' => __( 'Move date', 'moving-storage-volume-calculator' ),
				'type'  => 'date',
				'width' => 12,
			),
			array(
				'label' => __( 'Extra services', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 40,
			),
			array(
				'label' => __( 'Volume (m³)', 'moving-storage-volume-calculator' ),
				'type'  => 'num2',
				'width' => 12,
			),
			array(
				'label' => __( 'Volume (ft³)', 'moving-storage-volume-calculator' ),
				'type'  => 'num1',
				'width' => 12,
			),
			array(
				'label' => __( 'Weight (kg)', 'moving-storage-volume-calculator' ),
				'type'  => 'int',
				'width' => 12,
			),
			array(
				'label' => __( 'Items', 'moving-storage-volume-calculator' ),
				'type'  => 'int',
				'width' => 9,
			),
			array(
				'label' => __( 'Boxes', 'moving-storage-volume-calculator' ),
				'type'  => 'int',
				'width' => 9,
			),
			array(
				'label' => __( 'Vehicle', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 24,
			),
			array(
				'label' => __( 'Shipping container', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 22,
			),
			array(
				'label' => __( 'Storage unit', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 22,
			),
			array(
				'label' => __( 'Customer message', 'moving-storage-volume-calculator' ),
				'type'  => 'wrap',
				'width' => 48,
			),
			array(
				'label' => __( 'Lead source', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 30,
			),
			array(
				'label' => __( 'Page', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 40,
			),
			array(
				'label' => __( 'Consent given', 'moving-storage-volume-calculator' ),
				'type'  => 'datetime',
				'width' => 18,
			),
			array(
				'label' => __( 'Consent text', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 40,
			),
			array(
				'label' => __( 'Internal notes', 'moving-storage-volume-calculator' ),
				'type'  => 'wrap',
				'width' => 48,
			),
			array(
				'label' => __( 'Inventory', 'moving-storage-volume-calculator' ),
				'type'  => 'text',
				'width' => 60,
			),
		);
		$rows           = array();
		$inventory_rows = array();
		foreach ( $ids as $id ) {
			$data      = self::data( (int) $id );
			$contact   = (array) ( $data['contact'] ?? array() );
			$result    = (array) ( $data['result'] ?? array() );
			$inventory = array();
			foreach ( (array) ( $result['lines'] ?? array() ) as $line ) {
				$inventory[]      = sprintf( '%d x %s', (int) ( $line['qty'] ?? 0 ), (string) ( $line['name'] ?? '' ) );
				$inventory_rows[] = array(
					self::reference( (int) $id ),
					(string) ( $contact['name'] ?? '' ),
					(string) ( $line['room'] ?? '' ),
					(string) ( $line['name'] ?? '' ),
					(string) (int) ( $line['qty'] ?? 0 ),
					(string) ( $line['m3'] ?? '' ),
				);
			}
			$internal = array();
			foreach ( self::history( (int) $id ) as $entry ) {
				if ( 'note' === ( $entry['type'] ?? '' ) ) {
					$internal[] = wp_date( 'Y-m-d', (int) $entry['time'] ) . ': ' . (string) $entry['text'];
				}
			}
			$source  = (array) get_post_meta( (int) $id, self::SOURCE_META, true );
			$consent = (array) get_post_meta( (int) $id, self::CONSENT_META, true );
			$rows[]  = array(
				self::reference( (int) $id ),
				(string) get_the_date( 'Y-m-d H:i', (int) $id ),
				self::statuses()[ self::status( (int) $id ) ],
				(string) get_post_meta( (int) $id, self::FOLLOWUP_META, true ),
				(string) ( $contact['name'] ?? '' ),
				(string) ( $contact['email'] ?? '' ),
				(string) ( $contact['phone'] ?? '' ),
				(string) ( self::services()[ $contact['service'] ?? '' ] ?? '' ),
				(string) ( $contact['from'] ?? '' ),
				self::access_text( $contact, 'from' ),
				(string) ( $contact['to'] ?? '' ),
				self::access_text( $contact, 'to' ),
				(string) ( self::parking()[ $contact['parking'] ?? '' ] ?? '' ),
				(string) ( $contact['date'] ?? '' ),
				self::extras_text( $contact ),
				(string) ( $result['m3'] ?? '' ),
				(string) ( $result['ft3'] ?? '' ),
				(string) ( $result['kg'] ?? '' ),
				(string) ( $result['count'] ?? '' ),
				(string) ( $result['boxes'] ?? '' ),
				(string) ( $result['vehicle']['label'] ?? '' ),
				(string) ( $result['container']['label'] ?? '' ),
				(string) ( $result['storage']['label'] ?? '' ),
				(string) ( $contact['notes'] ?? '' ),
				self::source_text( (int) $id ),
				(string) ( $source['page'] ?? '' ),
				empty( $consent['time'] ) ? '' : wp_date( 'Y-m-d H:i', (int) $consent['time'] ),
				(string) ( $consent['text'] ?? '' ),
				implode( ' | ', $internal ),
				implode( '; ', $inventory ),
			);
		}

		$file = ( $lead ? 'lead-' . self::reference( $lead ) : 'leads' ) . '-' . gmdate( 'Y-m-d' );
		nocache_headers();

		if ( 'xlsx' === $format ) {
			$company = MSVCalc_Settings::text( 'company_name' );
			$company = '' === $company ? wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) : $company;
			$stamp   = wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) );
			$title   = $lead
				/* translators: %s: lead reference. */
				? sprintf( __( 'Quote request %s', 'moving-storage-volume-calculator' ), self::reference( $lead ) )
				/* translators: %s: business name. */
				: sprintf( __( '%s · Quote requests', 'moving-storage-volume-calculator' ), $company );
			/* translators: 1: export date and time, 2: number of quote requests. */
			$subtitle = sprintf( _n( 'Exported %1$s · %2$s quote request · Moving & Storage Volume Calculator', 'Exported %1$s · %2$s quote requests · Moving & Storage Volume Calculator', count( $rows ), 'moving-storage-volume-calculator' ), $stamp, number_format_i18n( count( $rows ) ) );
			$sheets   = array(
				array(
					'name'     => __( 'Quote requests', 'moving-storage-volume-calculator' ),
					'title'    => $title,
					'subtitle' => $subtitle,
					'columns'  => $columns,
					'rows'     => $rows,
				),
				array(
					'name'     => __( 'Inventory', 'moving-storage-volume-calculator' ),
					'title'    => __( 'Inventory, item by item', 'moving-storage-volume-calculator' ),
					'subtitle' => $subtitle,
					'columns'  => array(
						array(
							'label' => __( 'Reference', 'moving-storage-volume-calculator' ),
							'type'  => 'text',
							'width' => 16,
						),
						array(
							'label' => __( 'Name', 'moving-storage-volume-calculator' ),
							'type'  => 'text',
							'width' => 28,
						),
						array(
							'label' => __( 'Room', 'moving-storage-volume-calculator' ),
							'type'  => 'text',
							'width' => 24,
						),
						array(
							'label' => __( 'Item', 'moving-storage-volume-calculator' ),
							'type'  => 'text',
							'width' => 40,
						),
						array(
							'label' => __( 'Quantity', 'moving-storage-volume-calculator' ),
							'type'  => 'int',
							'width' => 11,
						),
						array(
							'label' => __( 'Volume (m³)', 'moving-storage-volume-calculator' ),
							'type'  => 'num2',
							'width' => 13,
						),
					),
					'rows'     => $inventory_rows,
				),
			);
			header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
			header( 'Content-Disposition: attachment; filename=' . $file . '.xlsx' );
			echo MSVCalc_Xlsx::build( $sheets, $title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary .xlsx download; every cell is XML-escaped in MSVCalc_Xlsx.
			exit;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $file . '.csv' );

		echo "\xEF\xBB\xBF"; // UTF-8 byte order mark, so spreadsheet apps read accents correctly.
		echo self::csv_line( wp_list_pluck( $columns, 'label' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV file download, every cell is quoted and formula-neutralised in csv_line().
		foreach ( $rows as $row ) {
			echo self::csv_line( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV file download, every cell is quoted and formula-neutralised in csv_line().
		}
		exit;
	}

	/**
	 * One CSV line. Cells that a spreadsheet would read as a formula get a
	 * leading apostrophe (CSV injection protection).
	 *
	 * @param array<int, string> $cells Cells.
	 * @return string
	 */
	private static function csv_line( array $cells ): string {
		$out = array();
		foreach ( $cells as $cell ) {
			$cell = str_replace( array( "\r\n", "\r" ), "\n", $cell );
			if ( '' !== $cell && in_array( $cell[0], array( '=', '+', '-', '@', "\t" ), true ) && ! is_numeric( $cell ) ) {
				$cell = "'" . $cell;
			}
			$out[] = '"' . str_replace( '"', '""', $cell ) . '"';
		}
		return implode( ',', $out ) . "\r\n";
	}

	/**
	 * Deletes leads older than the retention period. Runs daily.
	 */
	public static function purge(): void {
		$days = (int) MSVCalc_Settings::get( 'retention_days' );
		if ( $days <= 0 ) {
			return;
		}
		// Up to 1,000 per run; anything left is removed the next day.
		for ( $batch = 0; $batch < 10; $batch++ ) {
			$ids = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 100,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'date_query'     => array(
						array(
							'column' => 'post_date_gmt',
							'before' => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
						),
					),
				)
			);
			foreach ( $ids as $id ) {
				wp_delete_post( (int) $id, true );
			}
			if ( count( $ids ) < 100 ) {
				break;
			}
		}
	}

	/**
	 * Schedules the daily clean-up.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Removes the daily clean-up.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
