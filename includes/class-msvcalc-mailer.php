<?php
/**
 * Emails for quote requests: one to the business, an optional copy to the
 * customer.
 *
 * @package MSVCalc
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Builds and sends the quote request emails.
 */
final class MSVCalc_Mailer {

	/**
	 * Sends the notification to the business and, when enabled, a copy to the
	 * customer. Values must already be sanitized.
	 *
	 * @param array<string, mixed> $contact Contact details.
	 * @param array<string, mixed> $result  Calculation result.
	 * @param int                  $lead_id Stored lead ID, or 0.
	 * @param bool                 $copy    Whether to send the customer copy too (off for a resend).
	 * @return bool Whether the business notification was accepted for delivery.
	 */
	public static function send( array $contact, array $result, int $lead_id, bool $copy = true ): bool {
		$company = MSVCalc_Settings::text( 'company_name' );
		$to      = MSVCalc_Settings::text( 'notify_email' );
		$units   = (string) MSVCalc_Settings::get( 'units' );
		$volume  = MSVCalc_Calculator::format_volume( (float) $result['m3'], $units );

		$subject = sprintf(
			/* translators: 1: customer name, 2: volume. */
			__( 'New quote request: %1$s (%2$s)', 'moving-storage-volume-calculator' ),
			$contact['name'],
			$volume
		);

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'Reply-To: %s <%s>', self::header_text( (string) $contact['name'] ), $contact['email'] ),
		);

		$admin_link = $lead_id ? MSVCalc_Leads::admin_link( $lead_id ) : '';
		$body       = self::body( $contact, $result, 'business', $admin_link );
		$sent       = wp_mail( $to, self::header_text( $subject ), $body, $headers );

		if ( $copy && MSVCalc_Settings::get( 'send_copy' ) ) {
			$copy_subject = sprintf(
				/* translators: %s: company name. */
				__( 'Your moving inventory for %s', 'moving-storage-volume-calculator' ),
				$company
			);
			$copy_headers = array( 'Content-Type: text/html; charset=UTF-8' );
			if ( is_email( $to ) ) {
				$copy_headers[] = sprintf( 'Reply-To: %s <%s>', self::header_text( $company ), $to );
			}
			wp_mail( (string) $contact['email'], self::header_text( $copy_subject ), self::body( $contact, $result, 'customer', '' ), $copy_headers );
		}

		return (bool) $sent;
	}

	/**
	 * The HTML body of an email.
	 *
	 * @param array<string, mixed> $contact    Contact details.
	 * @param array<string, mixed> $result     Calculation result.
	 * @param string               $audience   "business" or "customer".
	 * @param string               $admin_link Link to the stored lead.
	 * @return string
	 */
	public static function body( array $contact, array $result, string $audience, string $admin_link ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Used by the included template.
		$company  = MSVCalc_Settings::text( 'company_name' );
		$units    = (string) MSVCalc_Settings::get( 'units' );
		$accent   = (string) MSVCalc_Settings::get( 'accent' );
		$accent_2 = (string) MSVCalc_Settings::get( 'accent_2' );
		$services = MSVCalc_Leads::services();

		ob_start();
		include MSVCALC_DIR . 'templates/email.php';
		return (string) ob_get_clean();
	}

	/**
	 * Strips line breaks and angle brackets from text used in a mail header.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function header_text( string $text ): string {
		return trim( (string) preg_replace( '/[\r\n<>"]+/', ' ', wp_strip_all_tags( $text ) ) );
	}
}
