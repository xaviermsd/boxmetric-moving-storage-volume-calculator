<?php
/**
 * Quote request email. Included by MSVCalc_Mailer::body().
 *
 * Built with tables and inline styles so it looks the same in Gmail,
 * Outlook, Apple Mail and on phones.
 *
 * Available: $contact, $result, $audience, $admin_link, $company, $units, $accent, $accent_2, $services.
 *
 * @package MSVCalc
 */

defined( 'ABSPATH' ) || exit;

$msvcalc_is_business = 'business' === $audience;
$msvcalc_volume      = MSVCalc_Calculator::format_volume( (float) $result['m3'], $units );
$msvcalc_details     = MSVCalc_Leads::detail_rows( $contact );
$msvcalc_first_name  = (string) strtok( (string) ( $contact['name'] ?? '' ), ' ' );
$msvcalc_font        = '-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif';
$msvcalc_ink         = '#0f1d29';
$msvcalc_muted       = '#5b6b78';
$msvcalc_line        = '#e6edf1';
$msvcalc_soft        = '#f2f7f8';
$msvcalc_weight      = 'ft3' === $units
	/* translators: %s: weight in pounds. */
	? sprintf( __( '%s lb', 'boxmetric-moving-storage-volume-calculator' ), number_format_i18n( (int) $result['lb'] ) )
	/* translators: %s: weight in kilograms. */
	: sprintf( __( '%s kg', 'boxmetric-moving-storage-volume-calculator' ), number_format_i18n( (int) $result['kg'] ) );

$msvcalc_stats = array(
	array( __( 'Items', 'boxmetric-moving-storage-volume-calculator' ), number_format_i18n( (int) $result['count'] ) ),
	array( __( 'Boxes', 'boxmetric-moving-storage-volume-calculator' ), number_format_i18n( (int) ( $result['boxes'] ?? 0 ) ) ),
	array( __( 'Est. weight', 'boxmetric-moving-storage-volume-calculator' ), $msvcalc_weight ),
);

$msvcalc_fits = array();
foreach (
	array(
		'vehicle'   => __( 'Recommended vehicle', 'boxmetric-moving-storage-volume-calculator' ),
		'container' => __( 'Shipping container', 'boxmetric-moving-storage-volume-calculator' ),
		'storage'   => __( 'Storage unit', 'boxmetric-moving-storage-volume-calculator' ),
	) as $msvcalc_key => $msvcalc_label
) {
	if ( ! empty( $result[ $msvcalc_key ]['label'] ) ) {
		$msvcalc_fits[] = array( $msvcalc_label, (string) $result[ $msvcalc_key ]['label'], max( 0, min( 100, (int) ( $result[ $msvcalc_key ]['fill'] ?? 0 ) ) ) );
	}
}

// Group the inventory by room, with a subtotal for each.
$msvcalc_rooms = array();
foreach ( (array) $result['lines'] as $msvcalc_row ) {
	$msvcalc_room = (string) $msvcalc_row['room'];
	if ( ! isset( $msvcalc_rooms[ $msvcalc_room ] ) ) {
		$msvcalc_rooms[ $msvcalc_room ] = array(
			'm3'    => 0.0,
			'lines' => array(),
		);
	}
	$msvcalc_rooms[ $msvcalc_room ]['m3']     += (float) $msvcalc_row['m3'];
	$msvcalc_rooms[ $msvcalc_room ]['lines'][] = $msvcalc_row;
}

$msvcalc_gradient = 'background:' . $accent . ';background-image:linear-gradient(90deg,' . $accent . ',' . $accent_2 . ');';
$msvcalc_cell     = 'border-bottom:1px solid #f0f4f6;padding:6px 0;';
?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?php echo esc_html( $company ); ?></title>
<style>body,table,td,th,p,a,h1,h2,h3,strong,span{font-family:<?php echo esc_html( $msvcalc_font ); ?>;}</style>
</head>
<body style="margin:0;padding:0;background:#eef3f6;font-family:<?php echo esc_attr( $msvcalc_font ); ?>;color:<?php echo esc_attr( $msvcalc_ink ); ?>;">
<div style="display:none;max-height:0;overflow:hidden;">
	<?php
	if ( $msvcalc_is_business ) {
		/* translators: 1: customer name, 2: volume. */
		echo esc_html( sprintf( __( '%1$s sent an inventory of %2$s.', 'boxmetric-moving-storage-volume-calculator' ), (string) ( $contact['name'] ?? '' ), $msvcalc_volume ) );
	} else {
		/* translators: %s: volume. */
		echo esc_html( sprintf( __( 'Your inventory of %s is with us.', 'boxmetric-moving-storage-volume-calculator' ), $msvcalc_volume ) );
	}
	?>
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3f6;font-family:<?php echo esc_attr( $msvcalc_font ); ?>;color:<?php echo esc_attr( $msvcalc_ink ); ?>;">
<tr><td align="center" style="padding:28px 12px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:18px;overflow:hidden;font-family:<?php echo esc_attr( $msvcalc_font ); ?>;border:1px solid <?php echo esc_attr( $msvcalc_line ); ?>;">
	<tr><td height="6" style="height:6px;line-height:6px;font-size:0;<?php echo esc_attr( $msvcalc_gradient ); ?>">&nbsp;</td></tr>

	<tr>
		<td style="padding:28px 32px 8px;">
			<table role="presentation" cellpadding="0" cellspacing="0">
				<tr>
					<td width="44" height="44" align="center" valign="middle" style="width:44px;height:44px;border-radius:12px;color:#ffffff;font-size:15px;font-weight:700;<?php echo esc_attr( $msvcalc_gradient ); ?>"><?php echo esc_html( 'ft3' === $units ? 'ft³' : 'm³' ); ?></td>
					<td style="padding-left:14px;">
						<p style="margin:0;font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:<?php echo esc_attr( $accent ); ?>;font-weight:700;">
							<?php echo esc_html( $msvcalc_is_business ? __( 'New quote request', 'boxmetric-moving-storage-volume-calculator' ) : __( 'Your moving inventory', 'boxmetric-moving-storage-volume-calculator' ) ); ?>
						</p>
						<p style="margin:2px 0 0;font-size:15px;font-weight:600;color:<?php echo esc_attr( $msvcalc_ink ); ?>;"><?php echo esc_html( $company ); ?></p>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td style="padding:16px 32px 0;">
			<p style="margin:0;font-size:13px;color:<?php echo esc_attr( $msvcalc_muted ); ?>;"><?php esc_html_e( 'Total volume', 'boxmetric-moving-storage-volume-calculator' ); ?></p>
			<p style="margin:2px 0 0;font-size:40px;line-height:1.1;font-weight:800;letter-spacing:-.02em;color:<?php echo esc_attr( $msvcalc_ink ); ?>;"><?php echo esc_html( $msvcalc_volume ); ?></p>
			<p style="margin:8px 0 0;font-size:15px;line-height:1.5;color:<?php echo esc_attr( $msvcalc_muted ); ?>;">
				<?php
				if ( $msvcalc_is_business ) {
					/* translators: %s: customer name. */
					echo esc_html( sprintf( __( '%s has sent their inventory and is waiting for your quote.', 'boxmetric-moving-storage-volume-calculator' ), (string) ( $contact['name'] ?? '' ) ) );
				} else {
					/* translators: %s: customer first name. */
					echo esc_html( sprintf( __( 'Thanks, %s. Here is a copy of the inventory you sent us. We will be in touch with your quote.', 'boxmetric-moving-storage-volume-calculator' ), $msvcalc_first_name ) );
				}
				?>
			</p>
		</td>
	</tr>

	<tr>
		<td style="padding:20px 26px 4px;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<?php foreach ( $msvcalc_stats as $msvcalc_stat ) : ?>
						<td width="33%" style="padding:0 6px;" valign="top">
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:<?php echo esc_attr( $msvcalc_soft ); ?>;border-radius:12px;">
								<tr><td style="padding:12px 14px;">
									<p style="margin:0;font-size:12px;color:<?php echo esc_attr( $msvcalc_muted ); ?>;"><?php echo esc_html( $msvcalc_stat[0] ); ?></p>
									<p style="margin:2px 0 0;font-size:18px;font-weight:700;color:<?php echo esc_attr( $msvcalc_ink ); ?>;"><?php echo esc_html( $msvcalc_stat[1] ); ?></p>
								</td></tr>
							</table>
						</td>
					<?php endforeach; ?>
				</tr>
			</table>
		</td>
	</tr>

	<?php if ( $msvcalc_fits ) : ?>
	<tr>
		<td style="padding:20px 32px 0;">
			<p style="margin:0 0 6px;font-size:16px;font-weight:700;"><?php esc_html_e( 'What it fits in', 'boxmetric-moving-storage-volume-calculator' ); ?></p>
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
				<?php foreach ( $msvcalc_fits as $msvcalc_fit ) : ?>
					<tr>
						<td style="padding:10px 0 4px;color:<?php echo esc_attr( $msvcalc_muted ); ?>;"><?php echo esc_html( $msvcalc_fit[0] ); ?></td>
						<td style="padding:10px 0 4px;text-align:right;font-weight:600;"><?php echo esc_html( $msvcalc_fit[1] ); ?></td>
					</tr>
					<tr>
						<td colspan="2" style="padding:0 0 10px;border-bottom:1px solid <?php echo esc_attr( $msvcalc_line ); ?>;">
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
								<tr>
									<td valign="middle" style="background:<?php echo esc_attr( $msvcalc_soft ); ?>;border-radius:6px;height:8px;line-height:8px;font-size:0;">
										<table role="presentation" width="<?php echo esc_attr( (string) max( 1, $msvcalc_fit[2] ) ); ?>%" cellpadding="0" cellspacing="0"><tr><td height="8" style="height:8px;line-height:8px;font-size:0;border-radius:6px;<?php echo esc_attr( $msvcalc_gradient ); ?>">&nbsp;</td></tr></table>
									</td>
									<td width="72" valign="middle" style="width:72px;text-align:right;font-size:12px;color:<?php echo esc_attr( $msvcalc_muted ); ?>;">
										<?php
										/* translators: %d: how full, in percent. */
										echo esc_html( sprintf( __( '%d%% full', 'boxmetric-moving-storage-volume-calculator' ), $msvcalc_fit[2] ) );
										?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr>
	<?php endif; ?>

	<tr>
		<td style="padding:24px 32px 0;">
			<p style="margin:0 0 6px;font-size:16px;font-weight:700;"><?php echo esc_html( $msvcalc_is_business ? __( 'Customer and move', 'boxmetric-moving-storage-volume-calculator' ) : __( 'Your details', 'boxmetric-moving-storage-volume-calculator' ) ); ?></p>
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
				<?php foreach ( $msvcalc_details as $msvcalc_label => $msvcalc_value ) : ?>
					<?php
					if ( '' === (string) $msvcalc_value ) {
						continue;
					}
					?>
					<tr>
						<td valign="top" style="padding:8px 12px 8px 0;border-bottom:1px solid <?php echo esc_attr( $msvcalc_line ); ?>;color:<?php echo esc_attr( $msvcalc_muted ); ?>;width:38%;"><?php echo esc_html( $msvcalc_label ); ?></td>
						<td valign="top" style="padding:8px 0;border-bottom:1px solid <?php echo esc_attr( $msvcalc_line ); ?>;font-weight:600;"><?php echo esc_html( (string) $msvcalc_value ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php if ( ! empty( $contact['notes'] ) ) : ?>
				<p style="margin:14px 0 0;padding:12px 14px;background:<?php echo esc_attr( $msvcalc_soft ); ?>;border-left:3px solid <?php echo esc_attr( $accent ); ?>;border-radius:8px;font-size:14px;line-height:1.5;white-space:pre-line;"><?php echo esc_html( (string) $contact['notes'] ); ?></p>
			<?php endif; ?>
		</td>
	</tr>

	<?php if ( $msvcalc_is_business ) : ?>
	<tr>
		<td style="padding:22px 32px 0;">
			<?php if ( $admin_link ) : ?>
				<a href="<?php echo esc_url( $admin_link ); ?>" style="display:inline-block;margin:0 8px 8px 0;padding:12px 22px;border-radius:999px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;<?php echo esc_attr( $msvcalc_gradient ); ?>"><?php esc_html_e( 'Open in WordPress', 'boxmetric-moving-storage-volume-calculator' ); ?></a>
			<?php endif; ?>
			<?php if ( is_email( (string) ( $contact['email'] ?? '' ) ) ) : ?>
				<a href="<?php echo esc_url( 'mailto:' . sanitize_email( (string) $contact['email'] ) ); ?>" style="display:inline-block;margin:0 0 8px;padding:11px 20px;border-radius:999px;border:1px solid <?php echo esc_attr( $accent ); ?>;color:<?php echo esc_attr( $msvcalc_ink ); ?>;text-decoration:none;font-weight:600;font-size:14px;">
					<?php
					/* translators: %s: customer first name. */
					echo esc_html( sprintf( __( 'Reply to %s', 'boxmetric-moving-storage-volume-calculator' ), $msvcalc_first_name ) );
					?>
				</a>
			<?php endif; ?>
		</td>
	</tr>
	<?php else : ?>
	<tr>
		<td style="padding:24px 32px 0;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:<?php echo esc_attr( $msvcalc_soft ); ?>;border-radius:14px;">
				<tr><td style="padding:16px 18px;">
					<p style="margin:0 0 4px;font-size:15px;font-weight:700;"><?php esc_html_e( 'What happens next', 'boxmetric-moving-storage-volume-calculator' ); ?></p>
					<?php
					$msvcalc_steps = array(
						__( 'We check your inventory and access details.', 'boxmetric-moving-storage-volume-calculator' ),
						__( 'We send you a quote, or call if anything needs a quick chat.', 'boxmetric-moving-storage-volume-calculator' ),
						__( 'Reply to this email any time to add or change items.', 'boxmetric-moving-storage-volume-calculator' ),
					);
					foreach ( $msvcalc_steps as $msvcalc_i => $msvcalc_step ) :
						?>
						<p style="margin:8px 0 0;font-size:14px;line-height:1.5;color:<?php echo esc_attr( $msvcalc_ink ); ?>;"><span style="display:inline-block;width:20px;height:20px;line-height:20px;text-align:center;border-radius:50%;color:#ffffff;font-size:11px;font-weight:700;margin-right:8px;<?php echo esc_attr( $msvcalc_gradient ); ?>"><?php echo esc_html( number_format_i18n( $msvcalc_i + 1 ) ); ?></span><?php echo esc_html( $msvcalc_step ); ?></p>
					<?php endforeach; ?>
				</td></tr>
			</table>
		</td>
	</tr>
	<?php endif; ?>

	<tr>
		<td style="padding:26px 32px 8px;">
			<p style="margin:0 0 4px;font-size:16px;font-weight:700;"><?php esc_html_e( 'Inventory', 'boxmetric-moving-storage-volume-calculator' ); ?></p>
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
				<?php foreach ( $msvcalc_rooms as $msvcalc_room => $msvcalc_group ) : ?>
					<tr>
						<td colspan="2" style="padding:16px 0 6px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:<?php echo esc_attr( $accent ); ?>;font-weight:700;border-bottom:2px solid <?php echo esc_attr( $msvcalc_soft ); ?>;"><?php echo esc_html( (string) $msvcalc_room ); ?></td>
						<td style="padding:16px 0 6px;font-size:12px;text-align:right;color:<?php echo esc_attr( $msvcalc_muted ); ?>;font-weight:700;border-bottom:2px solid <?php echo esc_attr( $msvcalc_soft ); ?>;white-space:nowrap;"><?php echo esc_html( MSVCalc_Calculator::format_volume( (float) $msvcalc_group['m3'], $units ) ); ?></td>
					</tr>
					<?php foreach ( $msvcalc_group['lines'] as $msvcalc_row ) : ?>
						<tr>
							<td style="<?php echo esc_attr( $msvcalc_cell ); ?>width:44px;font-weight:700;"><?php echo esc_html( number_format_i18n( (int) $msvcalc_row['qty'] ) ); ?> ×</td>
							<td style="<?php echo esc_attr( $msvcalc_cell ); ?>"><?php echo esc_html( (string) $msvcalc_row['name'] ); ?></td>
							<td style="<?php echo esc_attr( $msvcalc_cell ); ?>text-align:right;color:<?php echo esc_attr( $msvcalc_muted ); ?>;white-space:nowrap;"><?php echo esc_html( MSVCalc_Calculator::format_volume( (float) $msvcalc_row['m3'], $units ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
				<?php if ( ! empty( $result['margin'] ) ) : ?>
					<tr>
						<td colspan="2" style="padding:12px 0 4px;color:<?php echo esc_attr( $msvcalc_muted ); ?>;">
							<?php
							/* translators: %d: packing margin percentage. */
							echo esc_html( sprintf( __( 'Packing and gaps (%d%%)', 'boxmetric-moving-storage-volume-calculator' ), (int) $result['margin'] ) );
							?>
						</td>
						<td style="padding:12px 0 4px;text-align:right;color:<?php echo esc_attr( $msvcalc_muted ); ?>;white-space:nowrap;"><?php echo esc_html( MSVCalc_Calculator::format_volume( max( 0, (float) $result['m3'] - (float) ( $result['net_m3'] ?? $result['m3'] ) ), $units ) ); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<td colspan="2" style="padding:10px 0 0;border-top:2px solid <?php echo esc_attr( $msvcalc_ink ); ?>;font-weight:800;font-size:15px;"><?php esc_html_e( 'Total volume', 'boxmetric-moving-storage-volume-calculator' ); ?></td>
					<td style="padding:10px 0 0;border-top:2px solid <?php echo esc_attr( $msvcalc_ink ); ?>;text-align:right;font-weight:800;font-size:15px;white-space:nowrap;"><?php echo esc_html( $msvcalc_volume ); ?></td>
				</tr>
			</table>
		</td>
	</tr>

	<tr>
		<td style="padding:24px 32px 26px;">
			<p style="margin:0;padding-top:18px;border-top:1px solid <?php echo esc_attr( $msvcalc_line ); ?>;font-size:12px;line-height:1.6;color:<?php echo esc_attr( $msvcalc_muted ); ?>;">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: site name. */
						__( 'Sent from the volume calculator on %s. Volumes and weights are estimates based on typical item sizes.', 'boxmetric-moving-storage-volume-calculator' ),
						$company
					)
				);
				?>
			</p>
		</td>
	</tr>
</table>
</td></tr>
</table>
</body>
</html>
