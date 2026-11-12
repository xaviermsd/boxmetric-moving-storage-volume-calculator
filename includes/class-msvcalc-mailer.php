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
	 * Most customer copies the site sends in one hour.
	 */
	const COPY_HOURLY_CAP = 20;

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
			__( 'New quote request: %1$s (%2$s)', 'boxmetric-moving-storage-volume-calculator' ),
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

		if ( $copy && MSVCalc_Settings::get( 'send_copy' ) && self::copy_allowed( (string) $contact['email'] ) ) {
			$copy_subject = sprintf(
				/* translators: %s: company name. */
				__( 'Your moving inventory for %s', 'boxmetric-moving-storage-volume-calculator' ),
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
	 * Whether a customer copy may go to this address now. Each address gets
	 * at most one copy a day, and the whole site at most a set number an
	 * hour, so the public form cannot be used to send mail to strangers.
	 *
	 * @param string $email Customer email address.
	 * @return bool
	 */
	private static function copy_allowed( string $email ): bool {
		if ( ! is_email( $email ) ) {
			return false;
		}
		$address = 'msvcalc_copy_' . substr( wp_hash( strtolower( $email ) ), 0, 20 );
		$hour    = 'msvcalc_copies_' . gmdate( 'YmdH' );
		/**
		 * Filters how many customer copies the site may send in one hour.
		 *
		 * @param int $cap Copies per hour.
		 */
		$cap   = (int) apply_filters( 'msvcalc_copy_hourly_cap', self::COPY_HOURLY_CAP );
		$count = (int) get_transient( $hour );
		if ( get_transient( $address ) || $count >= $cap ) {
			return false;
		}
		set_transient( $address, 1, DAY_IN_SECONDS );
		set_transient( $hour, $count + 1, HOUR_IN_SECONDS );
		return true;
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
