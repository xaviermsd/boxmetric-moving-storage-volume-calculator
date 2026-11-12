<?php
/**
 * One lead: customer, quote, move details, inventory, status, follow-up,
 * notes and activity. Included by MSVCalc_Leads_Admin::render_lead().
 *
 * Available: $lead_id.
 *
 * @package MSVCalc
 */

defined( 'ABSPATH' ) || exit;

$msvcalc_data     = MSVCalc_Leads::data( $lead_id );
$msvcalc_contact  = (array) ( $msvcalc_data['contact'] ?? array() );
$msvcalc_result   = (array) ( $msvcalc_data['result'] ?? array() );
$msvcalc_units    = (string) ( $msvcalc_data['units'] ?? 'm3' );
$msvcalc_status   = MSVCalc_Leads::status( $lead_id );
$msvcalc_statuses = MSVCalc_Leads::statuses();
$msvcalc_history  = array_reverse( MSVCalc_Leads::history( $lead_id ) );
$msvcalc_source   = (array) get_post_meta( $lead_id, MSVCalc_Leads::SOURCE_META, true );
$msvcalc_followup = (string) get_post_meta( $lead_id, MSVCalc_Leads::FOLLOWUP_META, true );
$msvcalc_fu_note  = (string) get_post_meta( $lead_id, MSVCalc_Leads::FOLLOWUP_NOTE_META, true );
$msvcalc_received = (int) get_post_time( 'U', true, $lead_id );
$msvcalc_name     = (string) ( $msvcalc_contact['name'] ?? '' );
$msvcalc_email    = (string) ( $msvcalc_contact['email'] ?? '' );
$msvcalc_phone    = (string) ( $msvcalc_contact['phone'] ?? '' );
$msvcalc_digits   = preg_replace( '/[^\d]/', '', $msvcalc_phone );
$msvcalc_company  = MSVCalc_Settings::text( 'company_name' );
$msvcalc_datetime = (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' );
$msvcalc_list_url = admin_url( 'admin.php?page=' . MSVCalc_Admin::LEADS_SLUG );
$msvcalc_pdf_url  = wp_nonce_url( admin_url( 'admin-post.php?action=msvcalc_lead_report&lead=' . $lead_id ), 'msvcalc_lead_report' );
$msvcalc_csv_url  = wp_nonce_url( admin_url( 'admin-post.php?action=msvcalc_export_leads&lead=' . $lead_id ), 'msvcalc_export_leads' );
$msvcalc_xlsx_url = wp_nonce_url( admin_url( 'admin-post.php?action=msvcalc_export_leads&format=xlsx&lead=' . $lead_id ), 'msvcalc_export_leads' );
$msvcalc_today    = wp_date( 'Y-m-d' );
$msvcalc_is_open  = in_array( $msvcalc_status, MSVCalc_Leads::open_statuses(), true );
$msvcalc_mail     = (string) get_post_meta( $lead_id, MSVCalc_Leads::MAIL_META, true );
$msvcalc_consent  = (array) get_post_meta( $lead_id, MSVCalc_Leads::CONSENT_META, true );

/* translators: %s: business name. */
$msvcalc_subject = sprintf( __( 'Your moving quote from %s', 'boxmetric-moving-storage-volume-calculator' ), $msvcalc_company );

$msvcalc_weight = 'ft3' === $msvcalc_units
	/* translators: %s: weight in pounds. */
	? sprintf( __( '%s lb', 'boxmetric-moving-storage-volume-calculator' ), number_format_i18n( (int) ( $msvcalc_result['lb'] ?? 0 ) ) )
	/* translators: %s: weight in kilograms. */
	: sprintf( __( '%s kg', 'boxmetric-moving-storage-volume-calculator' ), number_format_i18n( (int) ( $msvcalc_result['kg'] ?? 0 ) ) );

$msvcalc_stats = array(
	array( 'cube', __( 'Total volume', 'boxmetric-moving-storage-volume-calculator' ), MSVCalc_Calculator::format_volume( (float) ( $msvcalc_result['m3'] ?? 0 ), $msvcalc_units ) ),
	array( 'list', __( 'Items', 'boxmetric-moving-storage-volume-calculator' ), number_format_i18n( (int) ( $msvcalc_result['count'] ?? 0 ) ) ),
	array( 'box', __( 'Boxes', 'boxmetric-moving-storage-volume-calculator' ), number_format_i18n( (int) ( $msvcalc_result['boxes'] ?? 0 ) ) ),
	array( 'weight', __( 'Est. weight', 'boxmetric-moving-storage-volume-calculator' ), $msvcalc_weight ),
);

$msvcalc_fits = array();
foreach (
	array(
		'vehicle'   => array( 'truck', __( 'Recommended vehicle', 'boxmetric-moving-storage-volume-calculator' ) ),
		'container' => array( 'container', __( 'Shipping container', 'boxmetric-moving-storage-volume-calculator' ) ),
		'storage'   => array( 'storage', __( 'Storage unit', 'boxmetric-moving-storage-volume-calculator' ) ),
	) as $msvcalc_key => $msvcalc_fit
) {
	if ( ! empty( $msvcalc_result[ $msvcalc_key ]['label'] ) ) {
		$msvcalc_fits[] = array( $msvcalc_fit[0], $msvcalc_fit[1], (string) $msvcalc_result[ $msvcalc_key ]['label'], max( 0, min( 100, (int) ( $msvcalc_result[ $msvcalc_key ]['fill'] ?? 0 ) ) ) );
	}
}

$msvcalc_rooms = array();
foreach ( (array) ( $msvcalc_result['lines'] ?? array() ) as $msvcalc_line ) {
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

$msvcalc_form = static function ( string $action ) use ( $lead_id ): void {
	echo '<input type="hidden" name="action" value="msvcalc_lead">';
	echo '<input type="hidden" name="lead" value="' . esc_attr( (string) $lead_id ) . '">';
	echo '<input type="hidden" name="do" value="' . esc_attr( $action ) . '">';
	wp_nonce_field( 'msvcalc_lead_' . $lead_id );
};
?>
<p class="msvcalc-back"><a href="<?php echo esc_url( $msvcalc_list_url ); ?>"><?php MSVCalc_Icons::render( 'arrow', 16 ); ?><?php esc_html_e( 'All leads', 'boxmetric-moving-storage-volume-calculator' ); ?></a></p>

<section class="msvcalc-lead-hero">
	<span class="msvcalc-avatar msvcalc-avatar--lg" aria-hidden="true"><?php echo esc_html( MSVCalc_Leads_Table::initials( $msvcalc_name ) ); ?></span>
	<div class="msvcalc-lead-hero__main">
		<p class="msvcalc-lead-hero__eyebrow">
			<span class="msvcalc-ref"><?php echo esc_html( MSVCalc_Leads::reference( $lead_id ) ); ?></span>
			<span class="msvcalc-status msvcalc-status--<?php echo esc_attr( $msvcalc_status ); ?>"><?php echo esc_html( $msvcalc_statuses[ $msvcalc_status ] ); ?></span>
			<?php if ( $msvcalc_followup && $msvcalc_is_open ) : ?>
				<span class="msvcalc-due<?php echo esc_attr( $msvcalc_followup < $msvcalc_today ? ' is-overdue' : ( $msvcalc_followup === $msvcalc_today ? ' is-today' : '' ) ); ?>">
					<?php MSVCalc_Icons::render( 'calendar', 14 ); ?>
					<?php
					/* translators: %s: date. */
					echo esc_html( sprintf( __( 'Follow up %s', 'boxmetric-moving-storage-volume-calculator' ), mysql2date( (string) get_option( 'date_format' ), $msvcalc_followup ) ) );
					?>
				</span>
			<?php endif; ?>
		</p>
		<h2 class="msvcalc-lead-hero__name"><?php echo esc_html( $msvcalc_name ); ?></h2>
		<p class="msvcalc-lead-hero__meta">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: date and time, 2: time span, 3: lead source. */
					__( 'Received %1$s (%2$s ago) · %3$s', 'boxmetric-moving-storage-volume-calculator' ),
					wp_date( $msvcalc_datetime, $msvcalc_received ),
					human_time_diff( $msvcalc_received ),
					MSVCalc_Leads::source_text( $lead_id )
				)
			);
			?>
		</p>
	</div>
	<div class="msvcalc-lead-hero__volume">
		<span><?php esc_html_e( 'Total volume', 'boxmetric-moving-storage-volume-calculator' ); ?></span>
		<strong><?php echo esc_html( MSVCalc_Calculator::format_volume( (float) ( $msvcalc_result['m3'] ?? 0 ), $msvcalc_units ) ); ?></strong>
	</div>
</section>

<?php if ( 'failed' === $msvcalc_mail ) : ?>
	<div class="msvcalc-alert">
		<?php MSVCalc_Icons::render( 'mail', 18 ); ?>
		<p><strong><?php esc_html_e( 'The notification email for this request could not be sent.', 'boxmetric-moving-storage-volume-calculator' ); ?></strong> <?php esc_html_e( 'Nothing is lost: every detail is below. Reply to the customer directly, or try the email again once your site can send mail.', 'boxmetric-moving-storage-volume-calculator' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php $msvcalc_form( 'resend' ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Send email again', 'boxmetric-moving-storage-volume-calculator' ); ?></button>
		</form>
	</div>
<?php endif; ?>

<div class="msvcalc-quick">
	<?php if ( $msvcalc_email ) : ?>
		<a class="button button-primary" href="<?php echo esc_url( 'mailto:' . $msvcalc_email . '?subject=' . rawurlencode( $msvcalc_subject ) ); ?>"><?php MSVCalc_Icons::render( 'mail', 16 ); ?><?php esc_html_e( 'Email', 'boxmetric-moving-storage-volume-calculator' ); ?></a>
	<?php endif; ?>
	<?php if ( $msvcalc_digits ) : ?>
		<a class="button" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $msvcalc_phone ) ); ?>"><?php MSVCalc_Icons::render( 'phone', 16 ); ?><?php esc_html_e( 'Call', 'boxmetric-moving-storage-volume-calculator' ); ?></a>
		<a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( 'https://wa.me/' . $msvcalc_digits ); ?>"><?php MSVCalc_Icons::render( 'chat', 16 ); ?><?php esc_html_e( 'WhatsApp', 'boxmetric-moving-storage-volume-calculator' ); ?></a>
	<?php endif; ?>
	<?php if ( $msvcalc_email ) : ?>
		<span id="msvcalc-lead-email" class="screen-reader-text"><?php echo esc_html( $msvcalc_email ); ?></span>
		<button type="button" class="button" data-msvcalc-copy="#msvcalc-lead-email"><?php MSVCalc_Icons::render( 'link', 16 ); ?><?php esc_html_e( 'Copy email', 'boxmetric-moving-storage-volume-calculator' ); ?></button>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="msvcalc-quick__form">
		<?php $msvcalc_form( 'contacted' ); ?>
		<label class="screen-reader-text" for="msvcalc-contact-how"><?php esc_html_e( 'How did you contact them?', 'boxmetric-moving-storage-volume-calculator' ); ?></label>
		<select id="msvcalc-contact-how" name="how">
			<option value="email"><?php esc_html_e( 'by email', 'boxmetric-moving-storage-volume-calculator' ); ?></option>
			<option value="phone"><?php esc_html_e( 'by phone', 'boxmetric-moving-storage-volume-calculator' ); ?></option>
			<option value="whatsapp"><?php esc_html_e( 'on WhatsApp', 'boxmetric-moving-storage-volume-calculator' ); ?></option>
		</select>
		<button type="submit" class="button"><?php MSVCalc_Icons::render( 'check', 16 ); ?><?php esc_html_e( 'Log contact', 'boxmetric-moving-storage-volume-calculator' ); ?></button>
	</form>
	<span class="msvcalc-quick__spacer"></span>
	<a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( $msvcalc_pdf_url ); ?>"><?php MSVCalc_Icons::render( 'print', 16 ); ?><?php esc_html_e( 'PDF', 'boxmetric-moving-storage-volume-calculator' ); ?></a>
	<a class="button" href="<?php echo esc_url( $msvcalc_xlsx_url ); ?>"><?php MSVCalc_Icons::render( 'list', 16 ); ?><?php esc_html_e( 'Excel', 'boxmetric-moving-storage-volume-calculator' ); ?></a>
	<a class="button" href="<?php echo esc_url( $msvcalc_csv_url ); ?>"><?php MSVCalc_Icons::render( 'list', 16 ); ?><?php esc_html_e( 'CSV', 'boxmetric-moving-storage-volume-calculator' ); ?></a>
</div>

<div class="msvcalc-lead-grid">
	<div class="msvcalc-lead-main">
		<section class="msvcalc-card msvcalc-panel">
			<h2><?php esc_html_e( 'Quote summary', 'boxmetric-moving-storage-volume-calculator' ); ?></h2>
			<div class="msvcalc-tiles">
				<?php foreach ( $msvcalc_stats as $msvcalc_i => $msvcalc_stat ) : ?>
					<div class="msvcalc-tile<?php echo 0 === $msvcalc_i ? ' is-main' : ''; ?>">
						<?php MSVCalc_Icons::render( $msvcalc_stat[0], 18 ); ?>
						<span><?php echo esc_html( $msvcalc_stat[1] ); ?></span>
						<strong><?php echo esc_html( $msvcalc_stat[2] ); ?></strong>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( $msvcalc_fits ) : ?>
				<div class="msvcalc-fits">
					<?php foreach ( $msvcalc_fits as $msvcalc_fit ) : ?>
						<div class="msvcalc-fit">
							<span class="msvcalc-fit__icon"><?php MSVCalc_Icons::render( $msvcalc_fit[0], 20 ); ?></span>
							<div class="msvcalc-fit__body">
								<span class="msvcalc-fit__label"><?php echo esc_html( $msvcalc_fit[1] ); ?></span>
								<strong><?php echo esc_html( $msvcalc_fit[2] ); ?></strong>
								<span class="msvcalc-meter"><span style="width:<?php echo esc_attr( (string) $msvcalc_fit[3] ); ?>%"></span></span>
								<span class="msvcalc-fit__pct">
									<?php
									/* translators: %d: how full, in percent. */
									echo esc_html( sprintf( __( '%d%% full', 'boxmetric-moving-storage-volume-calculator' ), $msvcalc_fit[3] ) );
									?>
								</span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $msvcalc_result['margin'] ) ) : ?>
				<p class="msvcalc-muted">
					<?php
					/* translators: %d: packing margin percentage. */
					echo esc_html( sprintf( __( 'Includes a %d%% margin for packing and gaps.', 'boxmetric-moving-storage-volume-calculator' ), (int) $msvcalc_result['margin'] ) );
					?>
				</p>
			<?php endif; ?>
		</section>

		<section class="msvcalc-card msvcalc-panel">
			<h2><?php esc_html_e( 'Customer and move', 'boxmetric-moving-storage-volume-calculator' ); ?></h2>
			<dl class="msvcalc-facts">
				<?php foreach ( MSVCalc_Leads::detail_rows( $msvcalc_contact ) as $msvcalc_label => $msvcalc_value ) : ?>
					<div>
						<dt><?php echo esc_html( $msvcalc_label ); ?></dt>
						<dd><?php echo '' !== (string) $msvcalc_value ? esc_html( (string) $msvcalc_value ) : '<span class="msvcalc-muted">-</span>'; ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
			<?php if ( ! empty( $msvcalc_contact['notes'] ) ) : ?>
				<h3><?php esc_html_e( 'Message from the customer', 'boxmetric-moving-storage-volume-calculator' ); ?></h3>
				<blockquote class="msvcalc-quote"><?php echo esc_html( (string) $msvcalc_contact['notes'] ); ?></blockquote>
			<?php endif; ?>
		</section>

		<section class="msvcalc-card msvcalc-panel msvcalc-panel--inventory">
			<h2>
				<?php esc_html_e( 'Inventory', 'boxmetric-moving-storage-volume-calculator' ); ?>
				<span class="msvcalc-muted">
					<?php
					/* translators: %s: number of rooms. */
					echo esc_html( sprintf( _n( '%s room', '%s rooms', count( $msvcalc_rooms ), 'boxmetric-moving-storage-volume-calculator' ), number_format_i18n( count( $msvcalc_rooms ) ) ) );
					?>
				</span>
			</h2>
			<table class="msvcalc-inventory">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Qty', 'boxmetric-moving-storage-volume-calculator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Item', 'boxmetric-moving-storage-volume-calculator' ); ?></th>
						<th scope="col" class="num"><?php esc_html_e( 'Volume', 'boxmetric-moving-storage-volume-calculator' ); ?></th>
					</tr>
				</thead>
				<?php foreach ( $msvcalc_rooms as $msvcalc_room => $msvcalc_group ) : ?>
					<tbody>
						<tr class="msvcalc-inventory__room">
							<th scope="rowgroup" colspan="2"><?php echo esc_html( $msvcalc_room ); ?></th>
							<th class="num"><?php echo esc_html( MSVCalc_Calculator::format_volume( $msvcalc_group['m3'], $msvcalc_units ) ); ?></th>
						</tr>
						<?php foreach ( $msvcalc_group['lines'] as $msvcalc_line ) : ?>
							<tr>
								<td class="msvcalc-inventory__qty"><?php echo esc_html( number_format_i18n( (int) ( $msvcalc_line['qty'] ?? 0 ) ) ); ?> ×</td>
								<td><?php echo esc_html( (string) ( $msvcalc_line['name'] ?? '' ) ); ?></td>
								<td class="num"><?php echo esc_html( MSVCalc_Calculator::format_volume( (float) ( $msvcalc_line['m3'] ?? 0 ), $msvcalc_units ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				<?php endforeach; ?>
				<tfoot>
					<?php if ( ! empty( $msvcalc_result['margin'] ) ) : ?>
						<tr>
							<td colspan="2"><?php esc_html_e( 'Items', 'boxmetric-moving-storage-volume-calculator' ); ?></td>
							<td class="num"><?php echo esc_html( MSVCalc_Calculator::format_volume( (float) ( $msvcalc_result['net_m3'] ?? 0 ), $msvcalc_units ) ); ?></td>
						</tr>
						<tr>
							<td colspan="2">
								<?php
								/* translators: %d: packing margin percentage. */
								echo esc_html( sprintf( __( 'Packing and gaps (%d%%)', 'boxmetric-moving-storage-volume-calculator' ), (int) $msvcalc_result['margin'] ) );
								?>
							</td>
							<td class="num"><?php echo esc_html( MSVCalc_Calculator::format_volume( max( 0, (float) ( $msvcalc_result['m3'] ?? 0 ) - (float) ( $msvcalc_result['net_m3'] ?? 0 ) ), $msvcalc_units ) ); ?></td>
						</tr>
					<?php endif; ?>
					<tr class="msvcalc-inventory__total">
						<td colspan="2"><?php esc_html_e( 'Total volume', 'boxmetric-moving-storage-volume-calculator' ); ?></td>
						<td class="num"><?php echo esc_html( MSVCalc_Calculator::format_volume( (float) ( $msvcalc_result['m3'] ?? 0 ), $msvcalc_units ) ); ?></td>
					</tr>
				</tfoot>
			</table>
		</section>
	</div>

	<aside class="msvcalc-lead-side">
		<section class="msvcalc-card msvcalc-panel">
			<h2><?php esc_html_e( 'Status', 'boxmetric-moving-storage-volume-calculator' ); ?></h2>
			<ol class="msvcalc-steps">
				<?php
				$msvcalc_order = array_keys( $msvcalc_statuses );
				$msvcalc_at    = (int) array_search( $msvcalc_status, $msvcalc_order, true );
				foreach ( array( 'new', 'contacted', 'in_progress', 'converted' ) as $msvcalc_i => $msvcalc_key ) :
					$msvcalc_class = 'lost' === $msvcalc_status ? '' : ( $msvcalc_i < $msvcalc_at ? 'is-done' : ( $msvcalc_i === $msvcalc_at ? 'is-current' : '' ) );
					?>
					<li class="<?php echo esc_attr( $msvcalc_class ); ?>"><span></span><?php echo esc_html( $msvcalc_statuses[ $msvcalc_key ] ); ?></li>
				<?php endforeach; ?>
			</ol>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="msvcalc-inline-form">
				<?php $msvcalc_form( 'status' ); ?>
				<label class="screen-reader-text" for="msvcalc-status"><?php esc_html_e( 'Status', 'boxmetric-moving-storage-volume-calculator' ); ?></label>
				<select id="msvcalc-status" name="status">
					<?php foreach ( $msvcalc_statuses as $msvcalc_key => $msvcalc_label ) : ?>
						<option value="<?php echo esc_attr( $msvcalc_key ); ?>" <?php selected( $msvcalc_status, $msvcalc_key ); ?>><?php echo esc_html( $msvcalc_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Update', 'boxmetric-moving-storage-volume-calculator' ); ?></button>
			</form>
		</section>

		<section class="msvcalc-card msvcalc-panel">
			<h2><?php esc_html_e( 'Follow-up', 'boxmetric-moving-storage-volume-calculator' ); ?></h2>
			<?php if ( $msvcalc_followup ) : ?>
				<div class="msvcalc-followup<?php echo esc_attr( $msvcalc_is_open && $msvcalc_followup < $msvcalc_today ? ' is-overdue' : '' ); ?>">
					<?php MSVCalc_Icons::render( 'calendar', 18 ); ?>
					<div>
						<strong><?php echo esc_html( (string) mysql2date( (string) get_option( 'date_format' ), $msvcalc_followup ) ); ?></strong>
						<?php if ( $msvcalc_fu_note ) : ?>
							<span><?php echo esc_html( $msvcalc_fu_note ); ?></span>
						<?php endif; ?>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php $msvcalc_form( 'followup_done' ); ?>
						<button type="submit" class="button button-small"><?php esc_html_e( 'Done', 'boxmetric-moving-storage-volume-calculator' ); ?></button>
					</form>
				</div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="msvcalc-stack-form">
				<?php $msvcalc_form( 'followup' ); ?>
				<label for="msvcalc-followup"><?php echo esc_html( $msvcalc_followup ? __( 'Change the date', 'boxmetric-moving-storage-volume-calculator' ) : __( 'Remind me on', 'boxmetric-moving-storage-volume-calculator' ) ); ?></label>
				<input type="date" id="msvcalc-followup" name="followup" min="<?php echo esc_attr( $msvcalc_today ); ?>" value="<?php echo esc_attr( $msvcalc_followup ); ?>" required>
				<label for="msvcalc-followup-note"><?php esc_html_e( 'What to do', 'boxmetric-moving-storage-volume-calculator' ); ?> <span class="msvcalc-muted"><?php esc_html_e( '(optional)', 'boxmetric-moving-storage-volume-calculator' ); ?></span></label>
				<input type="text" id="msvcalc-followup-note" name="followup_note" maxlength="200" placeholder="<?php esc_attr_e( 'e.g. Call about the piano', 'boxmetric-moving-storage-volume-calculator' ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Save follow-up', 'boxmetric-moving-storage-volume-calculator' ); ?></button>
			</form>
		</section>

		<section class="msvcalc-card msvcalc-panel">
			<h2><?php esc_html_e( 'Notes and activity', 'boxmetric-moving-storage-volume-calculator' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="msvcalc-stack-form">
				<?php $msvcalc_form( 'note' ); ?>
				<label class="screen-reader-text" for="msvcalc-note"><?php esc_html_e( 'Internal note', 'boxmetric-moving-storage-volume-calculator' ); ?></label>
				<textarea id="msvcalc-note" name="note" rows="3" maxlength="2000" required placeholder="<?php esc_attr_e( 'Add an internal note. Customers never see these.', 'boxmetric-moving-storage-volume-calculator' ); ?>"></textarea>
				<button type="submit" class="button"><?php esc_html_e( 'Add note', 'boxmetric-moving-storage-volume-calculator' ); ?></button>
			</form>
			<ol class="msvcalc-timeline">
				<?php foreach ( $msvcalc_history as $msvcalc_entry ) : ?>
					<li class="msvcalc-timeline__item msvcalc-timeline__item--<?php echo esc_attr( (string) ( $msvcalc_entry['type'] ?? '' ) ); ?>">
						<span class="msvcalc-timeline__dot" aria-hidden="true"></span>
						<p class="msvcalc-timeline__title"><?php echo esc_html( MSVCalc_Leads_Admin::describe( $msvcalc_entry ) ); ?></p>
						<?php if ( in_array( (string) ( $msvcalc_entry['type'] ?? '' ), array( 'note', 'followup' ), true ) && '' !== (string) ( $msvcalc_entry['text'] ?? '' ) ) : ?>
							<p class="msvcalc-timeline__text"><?php echo esc_html( 'followup' === $msvcalc_entry['type'] ? (string) ( explode( ' · ', (string) $msvcalc_entry['text'], 2 )[1] ?? '' ) : (string) $msvcalc_entry['text'] ); ?></p>
						<?php endif; ?>
						<p class="msvcalc-timeline__meta">
							<?php echo esc_html( MSVCalc_Leads_Admin::who( (int) ( $msvcalc_entry['user'] ?? 0 ) ) . ' · ' . wp_date( $msvcalc_datetime, (int) ( $msvcalc_entry['time'] ?? 0 ) ) ); ?>
						</p>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>

		<section class="msvcalc-card msvcalc-panel msvcalc-panel--info">
			<h2><?php esc_html_e( 'Lead information', 'boxmetric-moving-storage-volume-calculator' ); ?></h2>
			<dl class="msvcalc-facts msvcalc-facts--compact">
				<div><dt><?php esc_html_e( 'Reference', 'boxmetric-moving-storage-volume-calculator' ); ?></dt><dd><?php echo esc_html( MSVCalc_Leads::reference( $lead_id ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Received', 'boxmetric-moving-storage-volume-calculator' ); ?></dt><dd><?php echo esc_html( wp_date( $msvcalc_datetime, $msvcalc_received ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Lead source', 'boxmetric-moving-storage-volume-calculator' ); ?></dt><dd><?php echo esc_html( MSVCalc_Leads::source_text( $lead_id ) ); ?></dd></div>
				<?php if ( ! empty( $msvcalc_source['page'] ) ) : ?>
					<div><dt><?php esc_html_e( 'Page', 'boxmetric-moving-storage-volume-calculator' ); ?></dt><dd><a href="<?php echo esc_url( (string) $msvcalc_source['page'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) wp_parse_url( (string) $msvcalc_source['page'], PHP_URL_PATH ) ); ?></a></dd></div>
				<?php endif; ?>
				<?php if ( ! empty( $msvcalc_source['referrer'] ) ) : ?>
					<div><dt><?php esc_html_e( 'Referrer', 'boxmetric-moving-storage-volume-calculator' ); ?></dt><dd><?php echo esc_html( (string) $msvcalc_source['referrer'] ); ?></dd></div>
				<?php endif; ?>
				<?php if ( ! empty( $msvcalc_source['utm_campaign'] ) ) : ?>
					<div><dt><?php esc_html_e( 'Campaign', 'boxmetric-moving-storage-volume-calculator' ); ?></dt><dd><?php echo esc_html( (string) $msvcalc_source['utm_campaign'] ); ?></dd></div>
				<?php endif; ?>
				<?php if ( ! empty( $msvcalc_consent['time'] ) ) : ?>
					<div><dt><?php esc_html_e( 'Consent', 'boxmetric-moving-storage-volume-calculator' ); ?></dt><dd>
						<?php
						/* translators: %s: date and time. */
						echo esc_html( sprintf( __( 'Given %s', 'boxmetric-moving-storage-volume-calculator' ), wp_date( $msvcalc_datetime, (int) $msvcalc_consent['time'] ) ) );
						?>
						<?php if ( ! empty( $msvcalc_consent['text'] ) ) : ?>
							<span class="msvcalc-muted msvcalc-consent-text">“<?php echo esc_html( (string) $msvcalc_consent['text'] ); ?>”</span>
						<?php endif; ?>
						<?php if ( ! empty( $msvcalc_consent['privacy'] ) ) : ?>
							<span class="msvcalc-muted msvcalc-consent-text"><a href="<?php echo esc_url( (string) $msvcalc_consent['privacy'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy policy shown', 'boxmetric-moving-storage-volume-calculator' ); ?></a></span>
						<?php endif; ?>
					</dd></div>
				<?php endif; ?>
				<?php if ( '' !== $msvcalc_mail ) : ?>
					<div><dt><?php esc_html_e( 'Notification', 'boxmetric-moving-storage-volume-calculator' ); ?></dt><dd><?php echo esc_html( 'failed' === $msvcalc_mail ? __( 'Email failed', 'boxmetric-moving-storage-volume-calculator' ) : __( 'Email sent', 'boxmetric-moving-storage-volume-calculator' ) ); ?></dd></div>
				<?php endif; ?>
				<div><dt><?php esc_html_e( 'Units', 'boxmetric-moving-storage-volume-calculator' ); ?></dt><dd><?php echo esc_html( 'ft3' === $msvcalc_units ? 'ft³' : 'm³' ); ?></dd></div>
			</dl>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-msvcalc-confirm="<?php esc_attr_e( 'Delete this lead for good? This cannot be undone.', 'boxmetric-moving-storage-volume-calculator' ); ?>">
				<?php $msvcalc_form( 'delete' ); ?>
				<button type="submit" class="button-link button-link-delete"><?php esc_html_e( 'Delete lead', 'boxmetric-moving-storage-volume-calculator' ); ?></button>
			</form>
		</section>
	</aside>
</div>
