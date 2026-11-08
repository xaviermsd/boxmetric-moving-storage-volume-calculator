<?php
/**
 * Print-ready lead report, saved as a PDF from the browser's print dialog.
 * Included by MSVCalc_Leads_Admin::report().
 *
 * Available: $ids (lead IDs), $lead (single lead ID or 0), $total (leads matching the filters).
 *
 * @package MSVCalc
 */

defined( 'ABSPATH' ) || exit;

$msvcalc_company  = MSVCalc_Settings::text( 'company_name' );
$msvcalc_statuses = MSVCalc_Leads::statuses();
$msvcalc_datetime = (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' );
$msvcalc_date     = (string) get_option( 'date_format' );
$msvcalc_title    = $lead
	/* translators: %s: lead reference. */
	? sprintf( __( 'Lead %s', 'moving-storage-volume-calculator' ), MSVCalc_Leads::reference( $lead ) )
	: __( 'Leads report', 'moving-storage-volume-calculator' );
$msvcalc_accent   = (string) MSVCalc_Settings::get( 'accent' );
$msvcalc_accent_2 = (string) MSVCalc_Settings::get( 'accent_2' );
$msvcalc_total_m3 = 0.0;
foreach ( $ids as $msvcalc_id ) {
	$msvcalc_total_m3 += (float) ( MSVCalc_Leads::data( $msvcalc_id )['result']['m3'] ?? 0 );
}

$msvcalc_head = static function ( string $title, string $sub ) use ( $msvcalc_company ): void {
	?>
	<header class="msvcalc-r-head">
		<span class="msvcalc-r-mark"><?php MSVCalc_Icons::render( 'cube', 26 ); ?></span>
		<div>
			<p class="msvcalc-r-eyebrow"><?php echo esc_html( $msvcalc_company ); ?></p>
			<h1><?php echo esc_html( $title ); ?></h1>
		</div>
		<p class="msvcalc-r-date"><?php echo esc_html( $sub ); ?></p>
	</header>
	<?php
};
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $msvcalc_title . ' – ' . $msvcalc_company ); ?></title>
<?php wp_print_styles( 'msvcalc-admin' ); ?>
</head>
<body class="msvcalc-report" style="--msvcalc-r-a:<?php echo esc_attr( $msvcalc_accent ); ?>;--msvcalc-r-b:<?php echo esc_attr( $msvcalc_accent_2 ); ?>;">
<div class="msvcalc-r-toolbar">
	<span><?php echo esc_html( $msvcalc_title ); ?></span>
	<button type="button" class="msvcalc-r-print" data-msvcalc-print><?php MSVCalc_Icons::render( 'print', 16 ); ?><?php esc_html_e( 'Save as PDF', 'moving-storage-volume-calculator' ); ?></button>
</div>

<?php if ( ! $ids ) : ?>
	<main class="msvcalc-r-page"><p><?php esc_html_e( 'No leads match these filters.', 'moving-storage-volume-calculator' ); ?></p></main>
<?php endif; ?>

<?php if ( count( $ids ) > 1 ) : ?>
	<main class="msvcalc-r-page">
		<?php
		/* translators: %s: date. */
		$msvcalc_head( __( 'Leads report', 'moving-storage-volume-calculator' ), sprintf( __( 'Prepared on %s', 'moving-storage-volume-calculator' ), wp_date( $msvcalc_date ) ) );
		?>
		<?php if ( $total > count( $ids ) ) : ?>
			<p class="msvcalc-r-note">
				<?php
				/* translators: 1: leads in this report, 2: leads matching the filters. */
				echo esc_html( sprintf( __( 'This report shows the newest %1$s of %2$s matching leads. Narrow the filters, or use Export CSV for all of them.', 'moving-storage-volume-calculator' ), number_format_i18n( count( $ids ) ), number_format_i18n( $total ) ) );
				?>
			</p>
		<?php endif; ?>
		<div class="msvcalc-r-stats">
			<div class="is-main"><span><?php esc_html_e( 'Leads', 'moving-storage-volume-calculator' ); ?></span><strong><?php echo esc_html( number_format_i18n( count( $ids ) ) ); ?></strong></div>
			<div><span><?php esc_html_e( 'Total volume', 'moving-storage-volume-calculator' ); ?></span><strong><?php echo esc_html( MSVCalc_Calculator::format_volume( $msvcalc_total_m3, (string) MSVCalc_Settings::get( 'units' ) ) ); ?></strong></div>
			<?php
			$msvcalc_counts = array_fill_keys( array_keys( $msvcalc_statuses ), 0 );
			foreach ( $ids as $msvcalc_id ) {
				++$msvcalc_counts[ MSVCalc_Leads::status( $msvcalc_id ) ];
			}
			?>
			<div><span><?php esc_html_e( 'New', 'moving-storage-volume-calculator' ); ?></span><strong><?php echo esc_html( number_format_i18n( $msvcalc_counts['new'] ) ); ?></strong></div>
			<div><span><?php esc_html_e( 'Converted', 'moving-storage-volume-calculator' ); ?></span><strong><?php echo esc_html( number_format_i18n( $msvcalc_counts['converted'] ) ); ?></strong></div>
		</div>
		<table class="msvcalc-r-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Ref.', 'moving-storage-volume-calculator' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'moving-storage-volume-calculator' ); ?></th>
					<th><?php esc_html_e( 'Move', 'moving-storage-volume-calculator' ); ?></th>
					<th><?php esc_html_e( 'Status', 'moving-storage-volume-calculator' ); ?></th>
					<th class="num"><?php esc_html_e( 'Volume', 'moving-storage-volume-calculator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $ids as $msvcalc_id ) : ?>
					<?php
					$msvcalc_d = MSVCalc_Leads::data( $msvcalc_id );
					$msvcalc_c = (array) ( $msvcalc_d['contact'] ?? array() );
					$msvcalc_s = MSVCalc_Leads::status( $msvcalc_id );
					?>
					<tr>
						<td><?php echo esc_html( MSVCalc_Leads::reference( $msvcalc_id ) ); ?><br><span class="msvcalc-r-muted"><?php echo esc_html( (string) get_the_date( $msvcalc_date, $msvcalc_id ) ); ?></span></td>
						<td><strong><?php echo esc_html( (string) ( $msvcalc_c['name'] ?? '' ) ); ?></strong><br><span class="msvcalc-r-muted"><?php echo esc_html( trim( (string) ( $msvcalc_c['email'] ?? '' ) . ' · ' . (string) ( $msvcalc_c['phone'] ?? '' ), ' ·' ) ); ?></span></td>
						<td><?php echo esc_html( implode( ' → ', array_filter( array( (string) ( $msvcalc_c['from'] ?? '' ), (string) ( $msvcalc_c['to'] ?? '' ) ) ) ) ); ?><br><span class="msvcalc-r-muted"><?php echo esc_html( (string) ( MSVCalc_Leads::services()[ $msvcalc_c['service'] ?? '' ] ?? '' ) ); ?></span></td>
						<td><span class="msvcalc-status msvcalc-status--<?php echo esc_attr( $msvcalc_s ); ?>"><?php echo esc_html( $msvcalc_statuses[ $msvcalc_s ] ); ?></span></td>
						<td class="num"><strong><?php echo esc_html( MSVCalc_Calculator::format_volume( (float) ( $msvcalc_d['result']['m3'] ?? 0 ), (string) ( $msvcalc_d['units'] ?? 'm3' ) ) ); ?></strong></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</main>
<?php endif; ?>

<?php foreach ( $ids as $msvcalc_id ) : ?>
	<?php
	$msvcalc_d      = MSVCalc_Leads::data( $msvcalc_id );
	$msvcalc_c      = (array) ( $msvcalc_d['contact'] ?? array() );
	$msvcalc_r      = (array) ( $msvcalc_d['result'] ?? array() );
	$msvcalc_u      = (string) ( $msvcalc_d['units'] ?? 'm3' );
	$msvcalc_s      = MSVCalc_Leads::status( $msvcalc_id );
	$msvcalc_fu     = (string) get_post_meta( $msvcalc_id, MSVCalc_Leads::FOLLOWUP_META, true );
	$msvcalc_weight = 'ft3' === $msvcalc_u
		/* translators: %s: weight in pounds. */
		? sprintf( __( '%s lb', 'moving-storage-volume-calculator' ), number_format_i18n( (int) ( $msvcalc_r['lb'] ?? 0 ) ) )
		/* translators: %s: weight in kilograms. */
		: sprintf( __( '%s kg', 'moving-storage-volume-calculator' ), number_format_i18n( (int) ( $msvcalc_r['kg'] ?? 0 ) ) );
	$msvcalc_rooms = array();
	foreach ( (array) ( $msvcalc_r['lines'] ?? array() ) as $msvcalc_line ) {
		$msvcalc_room = (string) ( $msvcalc_line['room'] ?? '' );
		if ( ! isset( $msvcalc_rooms[ $msvcalc_room ] ) ) {
			$msvcalc_rooms[ $msvcalc_room ] = array(
				'm3'    => 0.0,
				'lines' => array(),
			);
		}
		$msvcalc_rooms[ $msvcalc_room ]['m3']     += (float) ( $msvcalc_line['m3'] ?? 0 );
		$msvcalc_rooms[ $msvcalc_room ]['lines'][] = $msvcalc_line;
	}
	$msvcalc_notes = array_filter(
		MSVCalc_Leads::history( $msvcalc_id ),
		static function ( $entry ) {
			return 'note' === ( $entry['type'] ?? '' );
		}
	);
	?>
	<main class="msvcalc-r-page">
		<?php
		/* translators: 1: lead reference, 2: date and time. */
		$msvcalc_head( (string) ( $msvcalc_c['name'] ?? '' ), sprintf( __( '%1$s · received %2$s', 'moving-storage-volume-calculator' ), MSVCalc_Leads::reference( $msvcalc_id ), wp_date( $msvcalc_datetime, (int) get_post_time( 'U', true, $msvcalc_id ) ) ) );
		?>
		<p class="msvcalc-r-tags">
			<span class="msvcalc-status msvcalc-status--<?php echo esc_attr( $msvcalc_s ); ?>"><?php echo esc_html( $msvcalc_statuses[ $msvcalc_s ] ); ?></span>
			<span><?php echo esc_html( MSVCalc_Leads::source_text( $msvcalc_id ) ); ?></span>
			<?php if ( $msvcalc_fu ) : ?>
				<span>
					<?php
					/* translators: %s: date. */
					echo esc_html( sprintf( __( 'Follow up %s', 'moving-storage-volume-calculator' ), mysql2date( $msvcalc_date, $msvcalc_fu ) ) );
					?>
				</span>
			<?php endif; ?>
		</p>

		<div class="msvcalc-r-stats">
			<div class="is-main"><span><?php esc_html_e( 'Total volume', 'moving-storage-volume-calculator' ); ?></span><strong><?php echo esc_html( MSVCalc_Calculator::format_volume( (float) ( $msvcalc_r['m3'] ?? 0 ), $msvcalc_u ) ); ?></strong></div>
			<div><span><?php esc_html_e( 'Items', 'moving-storage-volume-calculator' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) ( $msvcalc_r['count'] ?? 0 ) ) ); ?></strong></div>
			<div><span><?php esc_html_e( 'Boxes', 'moving-storage-volume-calculator' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) ( $msvcalc_r['boxes'] ?? 0 ) ) ); ?></strong></div>
			<div><span><?php esc_html_e( 'Est. weight', 'moving-storage-volume-calculator' ); ?></span><strong><?php echo esc_html( $msvcalc_weight ); ?></strong></div>
		</div>

		<div class="msvcalc-r-fits">
			<?php
			foreach (
				array(
					'vehicle'   => __( 'Recommended vehicle', 'moving-storage-volume-calculator' ),
					'container' => __( 'Shipping container', 'moving-storage-volume-calculator' ),
					'storage'   => __( 'Storage unit', 'moving-storage-volume-calculator' ),
				) as $msvcalc_key => $msvcalc_label
			) :
				if ( empty( $msvcalc_r[ $msvcalc_key ]['label'] ) ) {
					continue;
				}
				$msvcalc_fill = max( 0, min( 100, (int) ( $msvcalc_r[ $msvcalc_key ]['fill'] ?? 0 ) ) );
				?>
				<div>
					<span class="msvcalc-r-label"><?php echo esc_html( $msvcalc_label ); ?></span>
					<strong><?php echo esc_html( (string) $msvcalc_r[ $msvcalc_key ]['label'] ); ?></strong>
					<span class="msvcalc-meter"><span style="width:<?php echo esc_attr( (string) $msvcalc_fill ); ?>%"></span></span>
					<span class="msvcalc-r-muted">
						<?php
						/* translators: %d: how full, in percent. */
						echo esc_html( sprintf( __( '%d%% full', 'moving-storage-volume-calculator' ), $msvcalc_fill ) );
						?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>

		<h2><?php esc_html_e( 'Customer and move', 'moving-storage-volume-calculator' ); ?></h2>
		<dl class="msvcalc-r-facts">
			<?php foreach ( MSVCalc_Leads::detail_rows( $msvcalc_c ) as $msvcalc_label => $msvcalc_value ) : ?>
				<?php
				if ( '' === (string) $msvcalc_value ) {
					continue;
				}
				?>
				<div><dt><?php echo esc_html( $msvcalc_label ); ?></dt><dd><?php echo esc_html( (string) $msvcalc_value ); ?></dd></div>
			<?php endforeach; ?>
		</dl>
		<?php if ( ! empty( $msvcalc_c['notes'] ) ) : ?>
			<h3><?php esc_html_e( 'Message from the customer', 'moving-storage-volume-calculator' ); ?></h3>
			<blockquote class="msvcalc-quote"><?php echo esc_html( (string) $msvcalc_c['notes'] ); ?></blockquote>
		<?php endif; ?>

		<?php if ( $msvcalc_notes ) : ?>
			<h3><?php esc_html_e( 'Internal notes', 'moving-storage-volume-calculator' ); ?></h3>
			<ul class="msvcalc-r-notes">
				<?php foreach ( $msvcalc_notes as $msvcalc_note ) : ?>
					<li><span class="msvcalc-r-muted"><?php echo esc_html( wp_date( $msvcalc_date, (int) $msvcalc_note['time'] ) . ' · ' . MSVCalc_Leads_Admin::who( (int) $msvcalc_note['user'] ) ); ?></span><?php echo esc_html( (string) $msvcalc_note['text'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Inventory', 'moving-storage-volume-calculator' ); ?></h2>
		<table class="msvcalc-inventory">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Qty', 'moving-storage-volume-calculator' ); ?></th>
					<th><?php esc_html_e( 'Item', 'moving-storage-volume-calculator' ); ?></th>
					<th class="num"><?php esc_html_e( 'Volume', 'moving-storage-volume-calculator' ); ?></th>
				</tr>
			</thead>
			<?php foreach ( $msvcalc_rooms as $msvcalc_room => $msvcalc_group ) : ?>
				<tbody>
					<tr class="msvcalc-inventory__room">
						<th colspan="2"><?php echo esc_html( $msvcalc_room ); ?></th>
						<th class="num"><?php echo esc_html( MSVCalc_Calculator::format_volume( $msvcalc_group['m3'], $msvcalc_u ) ); ?></th>
					</tr>
					<?php foreach ( $msvcalc_group['lines'] as $msvcalc_line ) : ?>
						<tr>
							<td class="msvcalc-inventory__qty"><?php echo esc_html( number_format_i18n( (int) ( $msvcalc_line['qty'] ?? 0 ) ) ); ?> ×</td>
							<td><?php echo esc_html( (string) ( $msvcalc_line['name'] ?? '' ) ); ?></td>
							<td class="num"><?php echo esc_html( MSVCalc_Calculator::format_volume( (float) ( $msvcalc_line['m3'] ?? 0 ), $msvcalc_u ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			<?php endforeach; ?>
			<tfoot>
				<?php if ( ! empty( $msvcalc_r['margin'] ) ) : ?>
					<tr>
						<td colspan="2">
							<?php
							/* translators: %d: packing margin percentage. */
							echo esc_html( sprintf( __( 'Packing and gaps (%d%%)', 'moving-storage-volume-calculator' ), (int) $msvcalc_r['margin'] ) );
							?>
						</td>
						<td class="num"><?php echo esc_html( MSVCalc_Calculator::format_volume( max( 0, (float) ( $msvcalc_r['m3'] ?? 0 ) - (float) ( $msvcalc_r['net_m3'] ?? 0 ) ), $msvcalc_u ) ); ?></td>
					</tr>
				<?php endif; ?>
				<tr class="msvcalc-inventory__total">
					<td colspan="2"><?php esc_html_e( 'Total volume', 'moving-storage-volume-calculator' ); ?></td>
					<td class="num"><?php echo esc_html( MSVCalc_Calculator::format_volume( (float) ( $msvcalc_r['m3'] ?? 0 ), $msvcalc_u ) ); ?></td>
				</tr>
			</tfoot>
		</table>
		<footer class="msvcalc-r-foot">
			<?php
			/* translators: %s: business name. */
			echo esc_html( sprintf( __( '%s · Volumes and weights are estimates based on typical item sizes.', 'moving-storage-volume-calculator' ), $msvcalc_company ) );
			?>
			<span><?php esc_html_e( 'Moving & Storage Volume Calculator for WordPress', 'moving-storage-volume-calculator' ); ?></span>
		</footer>
	</main>
<?php endforeach; ?>
<?php wp_print_scripts( 'msvcalc-report' ); ?>
</body>
</html>
