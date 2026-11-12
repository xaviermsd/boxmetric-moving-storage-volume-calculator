<?php
/**
 * The lead list on the Leads screen.
 *
 * @package MSVCalc
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Searchable, sortable, filterable list of leads.
 */
final class MSVCalc_Leads_Table extends WP_List_Table {

	/**
	 * Leads per page.
	 */
	const PER_PAGE = 20;

	/**
	 * Current filters.
	 *
	 * @var array<string, string>
	 */
	private $filters = array();

	/**
	 * Sets up the table.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'lead',
				'plural'   => 'leads',
				'ajax'     => false,
			)
		);
		$this->filters = MSVCalc_Leads::filters();
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox">',
			'customer' => __( 'Customer', 'boxmetric-moving-storage-volume-calculator' ),
			'volume'   => __( 'Quote', 'boxmetric-moving-storage-volume-calculator' ),
			'move'     => __( 'Move', 'boxmetric-moving-storage-volume-calculator' ),
			'status'   => __( 'Status', 'boxmetric-moving-storage-volume-calculator' ),
			'followup' => __( 'Follow-up', 'boxmetric-moving-storage-volume-calculator' ),
			'received' => __( 'Received', 'boxmetric-moving-storage-volume-calculator' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array<int, string|bool>>
	 */
	protected function get_sortable_columns() {
		return array(
			'customer' => array( 'customer', false ),
			'volume'   => array( 'volume', true ),
			'followup' => array( 'followup', false ),
			'received' => array( 'date', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		$actions = array();
		foreach ( MSVCalc_Leads::statuses() as $key => $label ) {
			/* translators: %s: status name. */
			$actions[ 'status_' . $key ] = sprintf( __( 'Mark as %s', 'boxmetric-moving-storage-volume-calculator' ), $label );
		}
		$actions['delete'] = __( 'Delete permanently', 'boxmetric-moving-storage-volume-calculator' );
		return $actions;
	}

	/**
	 * Status links with counts.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$stats   = MSVCalc_Leads::stats();
		$base    = admin_url( 'admin.php?page=' . MSVCalc_Admin::LEADS_SLUG );
		$current = $this->filters['status'];
		$link    = static function ( string $key, string $label, int $count ) use ( $base, $current ): string {
			$url = '' === $key ? $base : add_query_arg( 'status', $key, $base );
			return sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( $url ),
				$current === $key ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $count ) )
			);
		};
		$views   = array( 'all' => $link( '', __( 'All', 'boxmetric-moving-storage-volume-calculator' ), $stats['total'] ) );
		foreach ( MSVCalc_Leads::statuses() as $key => $label ) {
			$views[ $key ] = $link( $key, $label, $stats[ $key ] );
		}
		$views['followup'] = $link( 'followup', __( 'Follow-ups due', 'boxmetric-moving-storage-volume-calculator' ), $stats['followups'] );
		if ( $stats['failed'] || 'mail_failed' === $current ) {
			$views['mail_failed'] = $link( 'mail_failed', __( 'Email failed', 'boxmetric-moving-storage-volume-calculator' ), $stats['failed'] );
		}
		return $views;
	}

	/**
	 * Filters above the table.
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions msvcalc-leads__filters">
			<label class="screen-reader-text" for="msvcalc-filter-service"><?php esc_html_e( 'Service', 'boxmetric-moving-storage-volume-calculator' ); ?></label>
			<select id="msvcalc-filter-service" name="service">
				<option value=""><?php esc_html_e( 'All services', 'boxmetric-moving-storage-volume-calculator' ); ?></option>
				<?php foreach ( MSVCalc_Leads::services() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $this->filters['service'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label class="msvcalc-leads__date">
				<span><?php esc_html_e( 'From', 'boxmetric-moving-storage-volume-calculator' ); ?></span>
				<input type="date" name="date_from" value="<?php echo esc_attr( $this->filters['from'] ); ?>">
			</label>
			<label class="msvcalc-leads__date">
				<span><?php esc_html_e( 'To', 'boxmetric-moving-storage-volume-calculator' ); ?></span>
				<input type="date" name="date_to" value="<?php echo esc_attr( $this->filters['to'] ); ?>">
			</label>
			<?php submit_button( __( 'Filter', 'boxmetric-moving-storage-volume-calculator' ), '', 'filter_action', false ); ?>
			<?php if ( $this->filters['service'] || $this->filters['from'] || $this->filters['to'] || $this->filters['s'] ) : ?>
				<a class="button-link" href="<?php echo esc_url( add_query_arg( array_filter( array( 'status' => $this->filters['status'] ) ), admin_url( 'admin.php?page=' . MSVCalc_Admin::LEADS_SLUG ) ) ); ?>"><?php esc_html_e( 'Clear filters', 'boxmetric-moving-storage-volume-calculator' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Loads the current page of leads.
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'customer' );
		$query                 = new WP_Query(
			array_merge(
				MSVCalc_Leads::query_args( $this->filters ),
				array(
					'posts_per_page' => self::PER_PAGE,
					'paged'          => $this->get_pagenum(),
				)
			)
		);
		$this->items           = $query->posts;
		$this->set_pagination_args(
			array(
				'total_items' => (int) $query->found_posts,
				'per_page'    => self::PER_PAGE,
				'total_pages' => (int) $query->max_num_pages,
			)
		);
	}

	/**
	 * Shown when nothing matches.
	 */
	public function no_items() {
		if ( array_filter( array( $this->filters['status'], $this->filters['service'], $this->filters['from'], $this->filters['to'], $this->filters['s'] ) ) ) {
			esc_html_e( 'No leads match these filters.', 'boxmetric-moving-storage-volume-calculator' );
			return;
		}
		esc_html_e( 'No leads yet. Every quote request sent from the calculator appears here.', 'boxmetric-moving-storage-volume-calculator' );
	}

	/**
	 * Checkbox.
	 *
	 * @param WP_Post $item Lead.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<label class="screen-reader-text" for="msvcalc-lead-%1$d">%2$s</label><input type="checkbox" id="msvcalc-lead-%1$d" name="lead_ids[]" value="%1$d">',
			(int) $item->ID,
			esc_html__( 'Select lead', 'boxmetric-moving-storage-volume-calculator' )
		);
	}

	/**
	 * Customer: name, contact and row actions.
	 *
	 * @param WP_Post $item Lead.
	 * @return string
	 */
	protected function column_customer( $item ) {
		$data    = MSVCalc_Leads::data( (int) $item->ID );
		$contact = (array) ( $data['contact'] ?? array() );
		$name    = (string) ( $contact['name'] ?? '' );
		$link    = MSVCalc_Leads::admin_link( (int) $item->ID );
		$out     = '<div class="msvcalc-person">';
		$out    .= '<span class="msvcalc-avatar" aria-hidden="true">' . esc_html( self::initials( $name ) ) . '</span>';
		$out    .= '<div><a class="row-title" href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>';
		$out    .= '<span class="msvcalc-ref">' . esc_html( MSVCalc_Leads::reference( (int) $item->ID ) ) . '</span>';
		if ( ! empty( $contact['email'] ) ) {
			$out .= '<br><a class="msvcalc-muted" href="' . esc_url( 'mailto:' . $contact['email'] ) . '">' . esc_html( (string) $contact['email'] ) . '</a>';
		}
		if ( ! empty( $contact['phone'] ) ) {
			$out .= '<br><a class="msvcalc-muted" href="' . esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', (string) $contact['phone'] ) ) . '">' . esc_html( (string) $contact['phone'] ) . '</a>';
		}
		$out .= '<span class="msvcalc-row-meta">' . $this->column_status( $item ) . '<strong class="msvcalc-volume">' . esc_html( MSVCalc_Calculator::format_volume( (float) ( $data['result']['m3'] ?? 0 ), (string) ( $data['units'] ?? 'm3' ) ) ) . '</strong></span>';
		$out .= '</div></div>';

		$actions = array(
			'view' => '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Open', 'boxmetric-moving-storage-volume-calculator' ) . '</a>',
		);
		if ( ! empty( $contact['email'] ) ) {
			$actions['email'] = '<a href="' . esc_url( 'mailto:' . $contact['email'] ) . '">' . esc_html__( 'Email', 'boxmetric-moving-storage-volume-calculator' ) . '</a>';
		}
		$actions['pdf'] = '<a target="_blank" rel="noopener" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=msvcalc_lead_report&lead=' . (int) $item->ID ), 'msvcalc_lead_report' ) ) . '">' . esc_html__( 'PDF', 'boxmetric-moving-storage-volume-calculator' ) . '</a>';

		return $out . $this->row_actions( $actions );
	}

	/**
	 * Volume, items and vehicle.
	 *
	 * @param WP_Post $item Lead.
	 * @return string
	 */
	protected function column_volume( $item ) {
		$data   = MSVCalc_Leads::data( (int) $item->ID );
		$result = (array) ( $data['result'] ?? array() );
		$units  = (string) ( $data['units'] ?? 'm3' );
		$out    = '<strong class="msvcalc-volume">' . esc_html( MSVCalc_Calculator::format_volume( (float) ( $result['m3'] ?? 0 ), $units ) ) . '</strong>';
		/* translators: %s: number of items. */
		$out .= '<br><span class="msvcalc-muted">' . esc_html( sprintf( _n( '%s item', '%s items', (int) ( $result['count'] ?? 0 ), 'boxmetric-moving-storage-volume-calculator' ), number_format_i18n( (int) ( $result['count'] ?? 0 ) ) ) );
		if ( ! empty( $result['vehicle']['label'] ) ) {
			$out .= ' · ' . esc_html( (string) $result['vehicle']['label'] );
		}
		return $out . '</span>';
	}

	/**
	 * Addresses, date and service.
	 *
	 * @param WP_Post $item Lead.
	 * @return string
	 */
	protected function column_move( $item ) {
		$data    = MSVCalc_Leads::data( (int) $item->ID );
		$contact = (array) ( $data['contact'] ?? array() );
		$parts   = array_filter( array( (string) ( $contact['from'] ?? '' ), (string) ( $contact['to'] ?? '' ) ) );
		$out     = $parts ? esc_html( implode( ' → ', $parts ) ) : '<span class="msvcalc-muted">-</span>';
		$meta    = array_filter(
			array(
				(string) ( MSVCalc_Leads::services()[ $contact['service'] ?? '' ] ?? '' ),
				! empty( $contact['date'] ) ? (string) mysql2date( (string) get_option( 'date_format' ), (string) $contact['date'] ) : '',
			)
		);
		if ( $meta ) {
			$out .= '<br><span class="msvcalc-muted">' . esc_html( implode( ' · ', $meta ) ) . '</span>';
		}
		return $out;
	}

	/**
	 * Status pill.
	 *
	 * @param WP_Post $item Lead.
	 * @return string
	 */
	protected function column_status( $item ) {
		$status = MSVCalc_Leads::status( (int) $item->ID );
		return sprintf( '<span class="msvcalc-status msvcalc-status--%1$s">%2$s</span>', esc_attr( $status ), esc_html( MSVCalc_Leads::statuses()[ $status ] ) );
	}

	/**
	 * Follow-up date, flagged when due or overdue.
	 *
	 * @param WP_Post $item Lead.
	 * @return string
	 */
	protected function column_followup( $item ) {
		$date = (string) get_post_meta( (int) $item->ID, MSVCalc_Leads::FOLLOWUP_META, true );
		if ( '' === $date ) {
			return '<span class="msvcalc-muted">-</span>';
		}
		$today = wp_date( 'Y-m-d' );
		$open  = in_array( MSVCalc_Leads::status( (int) $item->ID ), MSVCalc_Leads::open_statuses(), true );
		$class = 'msvcalc-due';
		if ( $open && $date < $today ) {
			$class .= ' is-overdue';
		} elseif ( $open && $date === $today ) {
			$class .= ' is-today';
		}
		return '<span class="' . esc_attr( $class ) . '">' . esc_html( (string) mysql2date( (string) get_option( 'date_format' ), $date ) ) . '</span>';
	}

	/**
	 * When it arrived.
	 *
	 * @param WP_Post $item Lead.
	 * @return string
	 */
	protected function column_received( $item ) {
		$time = (int) get_post_time( 'U', true, $item );
		/* translators: %s: time span, e.g. "2 hours". */
		$ago = sprintf( __( '%s ago', 'boxmetric-moving-storage-volume-calculator' ), human_time_diff( $time ) );
		return '<span title="' . esc_attr( wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $time ) ) . '">' . esc_html( $ago ) . '</span><br><span class="msvcalc-muted">' . esc_html( MSVCalc_Leads::source_text( (int) $item->ID ) ) . '</span>';
	}

	/**
	 * Two letters for the avatar.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function initials( string $name ): string {
		$letters = '';
		foreach ( array_slice( preg_split( '/\s+/', trim( $name ) ), 0, 2 ) as $word ) {
			$letters .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
		}
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $letters ) : strtoupper( $letters );
	}
}
