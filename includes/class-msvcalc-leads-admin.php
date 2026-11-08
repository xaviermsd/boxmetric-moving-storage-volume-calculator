<?php
/**
 * Leads screen: dashboard, list, lead details, notes, follow-ups and
 * exports, inside the plugin's own admin menu.
 *
 * @package MSVCalc
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Leads management in wp-admin.
 */
final class MSVCalc_Leads_Admin {

	/**
	 * Most leads in one PDF report. Use the CSV export for more.
	 */
	const REPORT_LIMIT = 200;

	/**
	 * Hook suffix of the Leads screen.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'mail_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'menu_badge' ) );
		add_action( 'admin_post_msvcalc_lead', array( __CLASS__, 'handle_lead' ) );
		add_action( 'admin_post_msvcalc_lead_report', array( __CLASS__, 'report' ) );
		add_action( 'load-edit.php', array( __CLASS__, 'redirect_old_screens' ) );
		add_action( 'load-post.php', array( __CLASS__, 'redirect_old_screens' ) );
	}

	/**
	 * Adds the Leads submenu. Called from MSVCalc_Admin::menu() so it sits
	 * right under Overview.
	 */
	public static function add_page(): void {
		if ( ! current_user_can( MSVCalc_Leads::CAP ) ) {
			return;
		}
		$new   = self::count_new();
		$label = __( 'Leads', 'moving-storage-volume-calculator' );
		if ( $new ) {
			$label .= ' <span class="awaiting-mod count-' . absint( $new ) . '"><span class="pending-count">' . esc_html( number_format_i18n( $new ) ) . '</span></span>';
		}
		// Always inside the plugin menu. Staff who can manage leads but not
		// the plugin see Volume Calculator with only Leads under it.
		self::$hook = (string) add_submenu_page(
			MSVCalc_Admin::MENU_SLUG,
			__( 'Leads', 'moving-storage-volume-calculator' ),
			$label,
			MSVCalc_Leads::CAP,
			MSVCalc_Admin::LEADS_SLUG,
			array( __CLASS__, 'page' )
		);
		if ( self::$hook ) {
			add_action( 'load-' . self::$hook, array( __CLASS__, 'load' ) );
		}
	}

	/**
	 * Brand colours for the new-leads count in the admin menu, on every
	 * screen. WordPress paints the current item's count in the menu colour,
	 * which hides it on the Leads screen itself.
	 */
	public static function menu_badge(): void {
		if ( ! current_user_can( MSVCalc_Leads::CAP ) ) {
			return;
		}
		wp_add_inline_style(
			'admin-menu',
			'#adminmenu #toplevel_page_' . MSVCalc_Admin::MENU_SLUG . ' .awaiting-mod{background:linear-gradient(135deg,#0fb5ba,#84cc16)!important;color:#fff!important;font-weight:700;box-shadow:0 0 0 1.5px rgba(255,255,255,.18)}'
		);
	}

	/**
	 * Number of new leads, for the menu badge.
	 *
	 * @return int
	 */
	private static function count_new(): int {
		return self::count_meta( MSVCalc_Leads::STATUS_META, 'new' );
	}

	/**
	 * Number of leads with one meta value, in a single query.
	 *
	 * @param string $key   Meta key.
	 * @param string $value Meta value.
	 * @return int
	 */
	private static function count_meta( string $key, string $value ): int {
		$query = new WP_Query(
			array(
				'post_type'              => MSVCalc_Leads::POST_TYPE,
				'post_status'            => 'private',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small admin-only count.
				'meta_value'             => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Small admin-only count.
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Old "Quote requests" screens now live under Leads.
	 */
	public static function redirect_old_screens(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only redirect.
		$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		$post = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( $post && MSVCalc_Leads::POST_TYPE === get_post_type( $post ) ) {
			wp_safe_redirect( MSVCalc_Leads::admin_link( $post ) );
			exit;
		}
		if ( MSVCalc_Leads::POST_TYPE === $type ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . MSVCalc_Admin::LEADS_SLUG ) );
			exit;
		}
	}

	/**
	 * Runs before the screen is drawn: bulk actions and screen options.
	 */
	public static function load(): void {
		if ( ! current_user_can( MSVCalc_Leads::CAP ) ) {
			return;
		}
		require_once MSVCALC_DIR . 'includes/class-msvcalc-leads-table.php';

		$table  = new MSVCalc_Leads_Table();
		$action = $table->current_action();
		if ( $action ) {
			check_admin_referer( 'bulk-leads' );
			$ids  = isset( $_REQUEST['lead_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['lead_ids'] ) ) : array();
			$done = 0;
			foreach ( array_filter( $ids ) as $id ) {
				if ( MSVCalc_Leads::POST_TYPE !== get_post_type( $id ) ) {
					continue;
				}
				if ( 'delete' === $action ) {
					wp_delete_post( $id, true );
					++$done;
				} elseif ( 0 === strpos( $action, 'status_' ) ) {
					$status = substr( $action, 7 );
					if ( isset( MSVCalc_Leads::statuses()[ $status ] ) && MSVCalc_Leads::status( $id ) !== $status ) {
						update_post_meta( $id, MSVCalc_Leads::STATUS_META, $status );
						MSVCalc_Leads::log( $id, 'status', $status );
						++$done;
					}
				}
			}
			$back = remove_query_arg( array( 'action', 'action2', '_wpnonce', '_wp_http_referer', 'lead_ids', 'msvcalc_notice', 'msvcalc_count' ), wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=' . MSVCalc_Admin::LEADS_SLUG ) );
			wp_safe_redirect(
				add_query_arg(
					array(
						'msvcalc_notice' => 'delete' === $action ? 'leads_deleted' : 'leads_updated',
						'msvcalc_count'  => $done,
					),
					$back
				)
			);
			exit;
		}
	}

	/**
	 * Warns staff when notification emails failed, so no enquiry goes
	 * unnoticed. Shown on the Dashboard and the plugin's screens only.
	 */
	public static function mail_notice(): void {
		if ( ! current_user_can( MSVCalc_Leads::CAP ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ( 'dashboard' !== $screen->id && false === strpos( (string) $screen->id, MSVCalc_Admin::MENU_SLUG ) ) ) {
			return;
		}
		$failed = self::count_meta( MSVCalc_Leads::MAIL_META, 'failed' );
		if ( ! $failed ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %s: number of leads. */
					_n( '%s quote request email could not be sent.', '%s quote request emails could not be sent.', $failed, 'moving-storage-volume-calculator' ),
					number_format_i18n( $failed )
				)
			),
			esc_html__( 'The enquiries are safe in Leads; open them to reply or send the email again.', 'moving-storage-volume-calculator' ),
			esc_url( admin_url( 'admin.php?page=' . MSVCalc_Admin::LEADS_SLUG . '&status=mail_failed' ) ),
			esc_html__( 'Show them', 'moving-storage-volume-calculator' )
		);
	}

	/**
	 * Handles the forms on a lead's page.
	 */
	public static function handle_lead(): void {
		$id = isset( $_POST['lead'] ) ? absint( wp_unslash( $_POST['lead'] ) ) : 0;
		if ( ! current_user_can( MSVCalc_Leads::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to manage leads.', 'moving-storage-volume-calculator' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'msvcalc_lead_' . $id );
		if ( ! $id || MSVCalc_Leads::POST_TYPE !== get_post_type( $id ) ) {
			wp_die( esc_html__( 'That lead no longer exists.', 'moving-storage-volume-calculator' ), '', array( 'response' => 404 ) );
		}

		$do     = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$notice = 'lead_saved';

		switch ( $do ) {
			case 'status':
				$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
				if ( isset( MSVCalc_Leads::statuses()[ $status ] ) && MSVCalc_Leads::status( $id ) !== $status ) {
					update_post_meta( $id, MSVCalc_Leads::STATUS_META, $status );
					MSVCalc_Leads::log( $id, 'status', $status );
				}
				break;

			case 'contacted':
				$how = isset( $_POST['how'] ) ? sanitize_key( wp_unslash( $_POST['how'] ) ) : '';
				MSVCalc_Leads::log( $id, 'contact', in_array( $how, array( 'email', 'phone', 'whatsapp' ), true ) ? $how : '' );
				if ( 'new' === MSVCalc_Leads::status( $id ) ) {
					update_post_meta( $id, MSVCalc_Leads::STATUS_META, 'contacted' );
					MSVCalc_Leads::log( $id, 'status', 'contacted' );
				}
				break;

			case 'followup':
				$date = isset( $_POST['followup'] ) ? sanitize_text_field( wp_unslash( $_POST['followup'] ) ) : '';
				$note = isset( $_POST['followup_note'] ) ? sanitize_text_field( wp_unslash( $_POST['followup_note'] ) ) : '';
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
					update_post_meta( $id, MSVCalc_Leads::FOLLOWUP_META, $date );
					update_post_meta( $id, MSVCalc_Leads::FOLLOWUP_NOTE_META, substr( $note, 0, 200 ) );
					MSVCalc_Leads::log( $id, 'followup', $date . ( '' !== $note ? ' · ' . $note : '' ) );
				} else {
					$notice = 'lead_bad_date';
				}
				break;

			case 'followup_done':
				delete_post_meta( $id, MSVCalc_Leads::FOLLOWUP_META );
				delete_post_meta( $id, MSVCalc_Leads::FOLLOWUP_NOTE_META );
				MSVCalc_Leads::log( $id, 'followup_done' );
				break;

			case 'note':
				$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
				$note = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, 2000 ) : substr( $note, 0, 2000 );
				if ( '' !== trim( $note ) ) {
					MSVCalc_Leads::log( $id, 'note', $note );
				}
				break;

			case 'resend':
				$data = MSVCalc_Leads::data( $id );
				$sent = ! empty( $data['contact'] ) && ! empty( $data['result'] ) && MSVCalc_Mailer::send( (array) $data['contact'], (array) $data['result'], $id, false );
				MSVCalc_Leads::mail_result( $id, $sent, true );
				$notice = $sent ? 'lead_resent' : 'lead_resend_failed';
				break;

			case 'delete':
				wp_delete_post( $id, true );
				wp_safe_redirect( admin_url( 'admin.php?page=' . MSVCalc_Admin::LEADS_SLUG . '&msvcalc_notice=lead_deleted' ) );
				exit;
		}

		wp_safe_redirect( add_query_arg( 'msvcalc_notice', $notice, MSVCalc_Leads::admin_link( $id ) ) );
		exit;
	}

	/**
	 * The Leads screen: the list, or one lead.
	 */
	public static function page(): void {
		if ( ! current_user_can( MSVCalc_Leads::CAP ) ) {
			return;
		}
		$lead = isset( $_GET['lead'] ) ? absint( wp_unslash( $_GET['lead'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view.
		echo '<div class="wrap msvcalc-wrap msvcalc-leads">';
		MSVCalc_Admin::page_header( __( 'Leads', 'moving-storage-volume-calculator' ) );
		self::notices();
		if ( $lead && MSVCalc_Leads::POST_TYPE === get_post_type( $lead ) ) {
			self::render_lead( $lead );
		} else {
			self::render_list();
		}
		echo '</div>';
	}

	/**
	 * Messages after an action.
	 */
	private static function notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display of a fixed message.
		$notice = isset( $_GET['msvcalc_notice'] ) ? sanitize_key( wp_unslash( $_GET['msvcalc_notice'] ) ) : '';
		$count  = isset( $_GET['msvcalc_count'] ) ? absint( wp_unslash( $_GET['msvcalc_count'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$map = array(
			'lead_saved'         => array( 'success', __( 'Lead updated.', 'moving-storage-volume-calculator' ) ),
			'lead_deleted'       => array( 'success', __( 'Lead deleted.', 'moving-storage-volume-calculator' ) ),
			'lead_bad_date'      => array( 'error', __( 'Please pick a follow-up date.', 'moving-storage-volume-calculator' ) ),
			'lead_resent'        => array( 'success', __( 'Notification email sent again.', 'moving-storage-volume-calculator' ) ),
			'lead_resend_failed' => array( 'error', __( 'The email could not be sent again. Check your site can send email (for example with an SMTP plugin). The lead is safe here.', 'moving-storage-volume-calculator' ) ),
			/* translators: %s: number of leads. */
			'leads_updated'      => array( 'success', sprintf( _n( '%s lead updated.', '%s leads updated.', $count, 'moving-storage-volume-calculator' ), number_format_i18n( $count ) ) ),
			/* translators: %s: number of leads. */
			'leads_deleted'      => array( 'success', sprintf( _n( '%s lead deleted.', '%s leads deleted.', $count, 'moving-storage-volume-calculator' ), number_format_i18n( $count ) ) ),
		);
		if ( isset( $map[ $notice ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $notice ][0] ), esc_html( $map[ $notice ][1] ) );
		}
	}

	/**
	 * Dashboard cards and the lead list.
	 */
	private static function render_list(): void {
		require_once MSVCALC_DIR . 'includes/class-msvcalc-leads-table.php';
		$stats   = MSVCalc_Leads::stats();
		$base    = admin_url( 'admin.php?page=' . MSVCalc_Admin::LEADS_SLUG );
		$filters = MSVCalc_Leads::filters();
		$cards   = array(
			array(
				'icon'  => 'mail',
				'label' => __( 'Total leads', 'moving-storage-volume-calculator' ),
				'value' => $stats['total'],
				/* translators: %s: number of leads. */
				'meta'  => sprintf( __( '%s this month', 'moving-storage-volume-calculator' ), number_format_i18n( $stats['month'] ) ),
				'url'   => $base,
				'tone'  => 'brand',
			),
			array(
				'icon'  => 'sparkle',
				'label' => __( 'New enquiries', 'moving-storage-volume-calculator' ),
				'value' => $stats['new'],
				'meta'  => __( 'Waiting for a first reply', 'moving-storage-volume-calculator' ),
				'url'   => add_query_arg( 'status', 'new', $base ),
				'tone'  => 'new',
			),
			array(
				'icon'  => 'calendar',
				'label' => __( 'Follow-ups due', 'moving-storage-volume-calculator' ),
				'value' => $stats['followups'],
				/* translators: %s: number of overdue follow-ups. */
				'meta'  => $stats['overdue'] ? sprintf( __( '%s overdue', 'moving-storage-volume-calculator' ), number_format_i18n( $stats['overdue'] ) ) : __( 'Nothing overdue', 'moving-storage-volume-calculator' ),
				'url'   => add_query_arg( 'status', 'followup', $base ),
				'tone'  => $stats['overdue'] ? 'warn' : 'calm',
			),
			array(
				'icon'  => 'check',
				'label' => __( 'Converted', 'moving-storage-volume-calculator' ),
				'value' => $stats['converted'],
				/* translators: %s: conversion rate in percent. */
				'meta'  => sprintf( __( '%s%% of closed leads', 'moving-storage-volume-calculator' ), number_format_i18n( $stats['rate'] ) ),
				'url'   => add_query_arg( 'status', 'converted', $base ),
				'tone'  => 'won',
			),
		);
		?>
		<div class="msvcalc-kpis">
			<?php foreach ( $cards as $card ) : ?>
				<a class="msvcalc-kpi msvcalc-kpi--<?php echo esc_attr( $card['tone'] ); ?>" href="<?php echo esc_url( $card['url'] ); ?>">
					<span class="msvcalc-kpi__icon"><?php MSVCalc_Icons::render( $card['icon'], 20 ); ?></span>
					<span class="msvcalc-kpi__label"><?php echo esc_html( $card['label'] ); ?></span>
					<strong class="msvcalc-kpi__value"><?php echo esc_html( number_format_i18n( (int) $card['value'] ) ); ?></strong>
					<span class="msvcalc-kpi__meta"><?php echo esc_html( $card['meta'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>

		<?php if ( $stats['total'] ) : ?>
			<div class="msvcalc-pipeline" role="img" aria-label="<?php esc_attr_e( 'Leads by status', 'moving-storage-volume-calculator' ); ?>">
				<?php foreach ( MSVCalc_Leads::statuses() as $key => $label ) : ?>
					<?php
					if ( ! $stats[ $key ] ) {
						continue;
					}
					?>
					<span class="msvcalc-pipeline__seg msvcalc-pipeline__seg--<?php echo esc_attr( $key ); ?>" style="flex-grow:<?php echo esc_attr( (string) (int) $stats[ $key ] ); ?>" title="<?php echo esc_attr( $label . ': ' . number_format_i18n( (int) $stats[ $key ] ) ); ?>"></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php
		$table = new MSVCalc_Leads_Table();
		$table->prepare_items();
		$export = array_filter(
			array(
				'status'    => $filters['status'],
				'service'   => $filters['service'],
				'date_from' => $filters['from'],
				'date_to'   => $filters['to'],
				's'         => $filters['s'],
			)
		);
		?>
		<div class="msvcalc-leads__bar">
			<?php $table->views(); ?>
			<div class="msvcalc-leads__exports">
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( $export, admin_url( 'admin-post.php?action=msvcalc_export_leads' ) ), 'msvcalc_export_leads' ) ); ?>"><?php MSVCalc_Icons::render( 'list', 16 ); ?><?php esc_html_e( 'Export CSV', 'moving-storage-volume-calculator' ); ?></a>
				<a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( wp_nonce_url( add_query_arg( $export, admin_url( 'admin-post.php?action=msvcalc_lead_report' ) ), 'msvcalc_lead_report' ) ); ?>"><?php MSVCalc_Icons::render( 'print', 16 ); ?><?php esc_html_e( 'Export PDF', 'moving-storage-volume-calculator' ); ?></a>
			</div>
		</div>
		<form method="get" class="msvcalc-leads__form">
			<input type="hidden" name="page" value="<?php echo esc_attr( MSVCalc_Admin::LEADS_SLUG ); ?>">
			<?php if ( $filters['status'] ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $filters['status'] ); ?>">
			<?php endif; ?>
			<?php $table->search_box( __( 'Search leads', 'moving-storage-volume-calculator' ), 'msvcalc-leads' ); ?>
			<?php $table->display(); ?>
		</form>
		<?php
	}

	/**
	 * One lead.
	 *
	 * @param int $lead_id Lead ID.
	 */
	private static function render_lead( int $lead_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Used by the included template.
		include MSVCALC_DIR . 'templates/admin-lead.php';
	}

	/**
	 * A print-ready report (one lead or the filtered list) that the browser
	 * saves as a PDF.
	 */
	public static function report(): void {
		if ( ! current_user_can( MSVCalc_Leads::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to export leads.', 'moving-storage-volume-calculator' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'msvcalc_lead_report' );

		$lead = isset( $_GET['lead'] ) ? absint( wp_unslash( $_GET['lead'] ) ) : 0;
		if ( $lead ) {
			$ids = MSVCalc_Leads::POST_TYPE === get_post_type( $lead ) ? array( $lead ) : array();
		} else {
			$ids = get_posts(
				array_merge(
					MSVCalc_Leads::query_args( MSVCalc_Leads::filters() ),
					array(
						'posts_per_page' => self::REPORT_LIMIT, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin-only report, capped and the cap is shown.
						'fields'         => 'ids',
						'no_found_rows'  => true,
					)
				)
			);
		}
		$ids = array_map( 'intval', $ids );
		// Tell the reader when the list was longer than one report holds.
		$total = $lead ? count( $ids ) : (int) ( new WP_Query(
			array_merge(
				MSVCalc_Leads::query_args( MSVCalc_Leads::filters() ),
				array(
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			)
		) )->found_posts;

		wp_register_style( 'msvcalc-admin', MSVCALC_URL . 'assets/css/admin.css', array(), MSVCALC_VERSION );
		wp_register_script( 'msvcalc-report', MSVCALC_URL . 'assets/js/report.js', array(), MSVCALC_VERSION, true );

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		include MSVCALC_DIR . 'templates/lead-report.php';
		exit;
	}

	/**
	 * Name of the person behind a timeline entry.
	 *
	 * @param int $user_id User ID, or 0 for the system.
	 * @return string
	 */
	public static function who( int $user_id ): string {
		$user = $user_id ? get_userdata( $user_id ) : false;
		return $user ? (string) $user->display_name : __( 'Website', 'moving-storage-volume-calculator' );
	}

	/**
	 * A timeline entry in words.
	 *
	 * @param array<string, mixed> $entry Entry.
	 * @return string
	 */
	public static function describe( array $entry ): string {
		$text     = (string) ( $entry['text'] ?? '' );
		$statuses = MSVCalc_Leads::statuses();
		switch ( (string) ( $entry['type'] ?? '' ) ) {
			case 'created':
				return __( 'Quote request received', 'moving-storage-volume-calculator' );
			case 'status':
				/* translators: %s: status name. */
				return sprintf( __( 'Status set to %s', 'moving-storage-volume-calculator' ), $statuses[ $text ] ?? $text );
			case 'note':
				return __( 'Note added', 'moving-storage-volume-calculator' );
			case 'followup':
				$parts = explode( ' · ', $text, 2 );
				/* translators: %s: date. */
				return sprintf( __( 'Follow-up set for %s', 'moving-storage-volume-calculator' ), mysql2date( (string) get_option( 'date_format' ), $parts[0] ) );
			case 'followup_done':
				return __( 'Follow-up done', 'moving-storage-volume-calculator' );
			case 'contact':
				$how = array(
					'email'    => __( 'by email', 'moving-storage-volume-calculator' ),
					'phone'    => __( 'by phone', 'moving-storage-volume-calculator' ),
					'whatsapp' => __( 'on WhatsApp', 'moving-storage-volume-calculator' ),
				);
				return trim( __( 'Customer contacted', 'moving-storage-volume-calculator' ) . ' ' . ( $how[ $text ] ?? '' ) );
			case 'repeat':
				return __( 'Customer sent the same list again (not duplicated)', 'moving-storage-volume-calculator' );
			case 'email_failed':
				return __( 'Notification email could not be sent', 'moving-storage-volume-calculator' );
			case 'email_sent':
				return __( 'Notification email sent again', 'moving-storage-volume-calculator' );
		}
		return $text;
	}
}
