<?php
/**
 * REST endpoints for the public quote form.
 *
 * POST msvcalc/v1/quote is public on purpose: visitors who are not logged in
 * send quote requests. It is protected by a nonce, a honeypot field, a
 * per-visitor rate limit and strict validation, and the totals are always
 * recalculated on the server.
 *
 * @package MSVCalc
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles the REST routes.
 */
final class MSVCalc_REST {

	/**
	 * Route namespace.
	 */
	const ROUTE_NS = 'msvcalc/v1';

	/**
	 * Nonce action for the quote form.
	 */
	const NONCE_ACTION = 'msvcalc_quote';

	/**
	 * Requests allowed per visitor in one window.
	 */
	const RATE_LIMIT = 5;

	/**
	 * Rate limit window in seconds.
	 */
	const RATE_WINDOW = 600;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Registers the routes.
	 */
	public static function routes(): void {
		register_rest_route(
			self::ROUTE_NS,
			'/quote',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'quote' ),
				'permission_callback' => array( __CLASS__, 'quote_permission' ),
			)
		);

		register_rest_route(
			self::ROUTE_NS,
			'/nonce',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'nonce' ),
				'permission_callback' => array( __CLASS__, 'quote_permission' ),
			)
		);
	}

	/**
	 * The quote form is open to everyone while it is enabled in the settings.
	 *
	 * @return bool|WP_Error
	 */
	public static function quote_permission() {
		if ( ! MSVCalc_Settings::get( 'quote_enabled' ) ) {
			return new WP_Error( 'msvcalc_disabled', __( 'Quote requests are turned off.', 'moving-storage-volume-calculator' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * A fresh form nonce, for pages served from a full-page cache that is
	 * older than the nonce lifetime.
	 *
	 * @return WP_REST_Response
	 */
	public static function nonce(): WP_REST_Response {
		$response = new WP_REST_Response( array( 'nonce' => wp_create_nonce( self::NONCE_ACTION ) ) );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		return $response;
	}

	/**
	 * Handles a quote request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function quote( WP_REST_Request $request ) {
		$nonce = $request->get_param( 'nonce' );
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( $nonce ), self::NONCE_ACTION ) ) {
			return new WP_Error( 'msvcalc_expired', __( 'Your session has expired. Please try again.', 'moving-storage-volume-calculator' ), array( 'status' => 403 ) );
		}

		// Honeypot: real visitors never see or fill this field.
		$trap = $request->get_param( 'website' );
		if ( is_string( $trap ) && '' !== trim( $trap ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => true,
					'message' => MSVCalc_Settings::text( 'success_message' ),
				)
			);
		}

		if ( self::rate_limited() ) {
			return new WP_Error( 'msvcalc_rate_limited', __( 'Too many requests. Please wait a few minutes and try again.', 'moving-storage-volume-calculator' ), array( 'status' => 429 ) );
		}

		$contact = self::validate_contact( $request );
		if ( is_wp_error( $contact ) ) {
			return $contact;
		}

		$quantities = $request->get_param( 'items' );
		$custom     = $request->get_param( 'custom' );
		$quantities = is_array( $quantities ) ? array_slice( $quantities, 0, MSVCalc_Catalogue::MAX_ITEMS, true ) : array();
		$custom     = is_array( $custom ) ? $custom : array();

		$result = MSVCalc_Calculator::calculate( $quantities, $custom );
		if ( $result['count'] < 1 ) {
			return new WP_Error(
				'msvcalc_empty',
				__( 'Please add at least one item before asking for a quote.', 'moving-storage-volume-calculator' ),
				array( 'status' => 400 )
			);
		}

		// The same person sending the same list again within a few minutes
		// (a double tap, a reload): note it on the first lead, send nothing new.
		$repeat = MSVCalc_Settings::get( 'store_leads' ) ? MSVCalc_Leads::find_duplicate( MSVCalc_Leads::fingerprint( $contact, $result ) ) : 0;
		if ( $repeat ) {
			MSVCalc_Leads::log( $repeat, 'repeat' );
			return new WP_REST_Response(
				array(
					'ok'       => true,
					'message'  => MSVCalc_Settings::text( 'success_message' ),
					'redirect' => '',
				)
			);
		}

		self::count_request();
		$lead_id = MSVCalc_Settings::get( 'store_leads' ) ? MSVCalc_Leads::create( $contact, $result, self::source( $request ) ) : 0;
		$sent    = MSVCalc_Mailer::send( $contact, $result, $lead_id );
		if ( $lead_id ) {
			MSVCalc_Leads::mail_result( $lead_id, $sent );
		}

		if ( ! $sent && ! $lead_id ) {
			return new WP_Error( 'msvcalc_not_sent', __( 'Sorry, we could not send your request. Please try again or contact us directly.', 'moving-storage-volume-calculator' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after a quote request was stored and emailed.
		 *
		 * @param int                  $lead_id Lead post ID, or 0 when leads are not stored.
		 * @param array<string, mixed> $contact Sanitized contact details.
		 * @param array<string, mixed> $result  Server-side calculation.
		 */
		do_action( 'msvcalc_quote_received', $lead_id, $contact, $result );

		$page = (int) MSVCalc_Settings::get( 'thank_you_page' );

		return new WP_REST_Response(
			array(
				'ok'       => true,
				'message'  => MSVCalc_Settings::text( 'success_message' ),
				'redirect' => $page ? (string) get_permalink( $page ) : '',
			)
		);
	}

	/**
	 * Where the visitor sent the request from: the page, the site that
	 * linked to it and any campaign tags in the page address.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, string>
	 */
	public static function source( WP_REST_Request $request ): array {
		$url    = static function ( $value ): string {
			return is_string( $value ) ? substr( esc_url_raw( $value, array( 'http', 'https' ) ), 0, 500 ) : '';
		};
		$source = array(
			'page'     => $url( $request->get_param( 'source' ) ),
			'referrer' => $url( $request->get_param( 'referrer' ) ),
		);
		$query  = (string) wp_parse_url( $source['page'], PHP_URL_QUERY );
		$params = array();
		wp_parse_str( $query, $params );
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign' ) as $key ) {
			if ( isset( $params[ $key ] ) && is_string( $params[ $key ] ) ) {
				$source[ $key ] = substr( sanitize_text_field( $params[ $key ] ), 0, 80 );
			}
		}
		return $source;
	}

	/**
	 * Sanitizes and validates the contact fields.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, string>|WP_Error
	 */
	public static function validate_contact( WP_REST_Request $request ) {
		$errors = array();
		$text   = static function ( $value, int $max ): string {
			$value = is_string( $value ) ? sanitize_text_field( $value ) : '';
			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
		};

		$contact          = array(
			'name'    => $text( $request->get_param( 'name' ), 80 ),
			'email'   => is_string( $request->get_param( 'email' ) ) ? sanitize_email( $request->get_param( 'email' ) ) : '',
			'phone'   => $text( $request->get_param( 'phone' ), 30 ),
			'from'    => $text( $request->get_param( 'from' ), 200 ),
			'to'      => $text( $request->get_param( 'to' ), 200 ),
			'date'    => $text( $request->get_param( 'date' ), 10 ),
			'service' => is_string( $request->get_param( 'service' ) ) ? sanitize_key( $request->get_param( 'service' ) ) : '',
			'notes'   => is_string( $request->get_param( 'notes' ) ) ? sanitize_textarea_field( $request->get_param( 'notes' ) ) : '',
		);
		$contact['notes'] = function_exists( 'mb_substr' ) ? mb_substr( $contact['notes'], 0, 2000 ) : substr( $contact['notes'], 0, 2000 );

		if ( strlen( $contact['name'] ) < 2 ) {
			$errors['name'] = __( 'Please enter your name.', 'moving-storage-volume-calculator' );
		}
		if ( ! is_email( $contact['email'] ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'moving-storage-volume-calculator' );
		}

		$phone_rule = (string) MSVCalc_Settings::get( 'field_phone' );
		if ( 'hidden' === $phone_rule ) {
			$contact['phone'] = '';
		} elseif ( '' !== $contact['phone'] || 'required' === $phone_rule ) {
			$digits = preg_replace( '/\D+/', '', $contact['phone'] );
			if ( ! preg_match( '/^[0-9+().\-\s]{6,30}$/', $contact['phone'] ) || strlen( (string) $digits ) < 6 ) {
				$errors['phone'] = __( 'Please enter a valid phone number.', 'moving-storage-volume-calculator' );
			}
		}

		$address_rule = (string) MSVCalc_Settings::get( 'field_addresses' );
		if ( 'hidden' === $address_rule ) {
			$contact['from'] = '';
			$contact['to']   = '';
		} elseif ( 'required' === $address_rule ) {
			if ( '' === $contact['from'] ) {
				$errors['from'] = __( 'Please tell us where you are moving from.', 'moving-storage-volume-calculator' );
			}
			if ( '' === $contact['to'] ) {
				$errors['to'] = __( 'Please tell us where you are moving to.', 'moving-storage-volume-calculator' );
			}
		}

		$date_rule = (string) MSVCalc_Settings::get( 'field_date' );
		if ( 'hidden' === $date_rule ) {
			$contact['date'] = '';
		} elseif ( '' !== $contact['date'] || 'required' === $date_rule ) {
			$date = DateTime::createFromFormat( '!Y-m-d', $contact['date'], wp_timezone() );
			$min  = new DateTime( 'today', wp_timezone() );
			$max  = ( new DateTime( 'today', wp_timezone() ) )->modify( '+3 years' );
			if ( ! $date || $date->format( 'Y-m-d' ) !== $contact['date'] || $date < $min || $date > $max ) {
				$errors['date'] = __( 'Please pick a moving date from today onwards.', 'moving-storage-volume-calculator' );
			}
		}

		$service_rule = (string) MSVCalc_Settings::get( 'field_service' );
		if ( 'hidden' === $service_rule ) {
			$contact['service'] = '';
		} elseif ( '' !== $contact['service'] || 'required' === $service_rule ) {
			if ( ! isset( MSVCalc_Leads::services()[ $contact['service'] ] ) ) {
				$errors['service'] = __( 'Please choose the service you need.', 'moving-storage-volume-calculator' );
			}
		}

		$access_rule = (string) MSVCalc_Settings::get( 'field_access' );
		foreach ( array( 'from', 'to' ) as $end ) {
			$floor = $request->get_param( $end . '_floor' );
			$floor = is_string( $floor ) ? sanitize_key( $floor ) : '';

			$contact[ $end . '_floor' ] = 'hidden' !== $access_rule && isset( MSVCalc_Leads::floors()[ $floor ] ) ? $floor : '';
			$contact[ $end . '_lift' ]  = 'hidden' !== $access_rule && true === rest_sanitize_boolean( $request->get_param( $end . '_lift' ) ) ? '1' : '';
			if ( 'required' === $access_rule && '' === $contact[ $end . '_floor' ] && ( 'from' === $end || '' !== $contact['to'] ) ) {
				$errors[ $end . '_floor' ] = __( 'Please choose the floor.', 'moving-storage-volume-calculator' );
			}
		}
		$parking            = $request->get_param( 'parking' );
		$parking            = is_string( $parking ) ? sanitize_key( $parking ) : '';
		$contact['parking'] = 'hidden' !== $access_rule && isset( MSVCalc_Leads::parking()[ $parking ] ) ? $parking : '';

		$extras            = $request->get_param( 'extras' );
		$contact['extras'] = array();
		if ( 'hidden' !== MSVCalc_Settings::get( 'field_extras' ) && is_array( $extras ) ) {
			foreach ( array_slice( $extras, 0, 20 ) as $extra ) {
				$extra = is_string( $extra ) ? sanitize_key( $extra ) : '';
				if ( isset( MSVCalc_Leads::extras()[ $extra ] ) && ! in_array( $extra, $contact['extras'], true ) ) {
					$contact['extras'][] = $extra;
				}
			}
		}

		if ( true !== rest_sanitize_boolean( $request->get_param( 'consent' ) ) ) {
			$errors['consent'] = __( 'Please agree so we can prepare your quote.', 'moving-storage-volume-calculator' );
		}

		if ( $errors ) {
			return new WP_Error(
				'msvcalc_invalid',
				__( 'Please check the highlighted fields.', 'moving-storage-volume-calculator' ),
				array(
					'status' => 400,
					'fields' => $errors,
				)
			);
		}

		return $contact;
	}

	/**
	 * Counts requests per visitor. The IP address is only used as a salted
	 * hash in a short-lived transient and is never stored with the lead.
	 *
	 * @return bool True when the visitor is over the limit.
	 */
	private static function rate_limited(): bool {
		/**
		 * Filters how many quote requests one visitor may send in ten minutes.
		 *
		 * @param int $limit Requests per window.
		 */
		$limit = (int) apply_filters( 'msvcalc_rate_limit', self::RATE_LIMIT );
		return (int) get_transient( self::rate_key() ) >= $limit;
	}

	/**
	 * Counts a valid request towards the visitor's limit. Requests that fail
	 * validation (a typo in the email, a missing field) are not counted, so a
	 * real customer is never locked out for fixing their form.
	 */
	private static function count_request(): void {
		$key = self::rate_key();
		set_transient( $key, (int) get_transient( $key ) + 1, self::RATE_WINDOW );
	}

	/**
	 * Transient key for the visitor's IP address (hashed, never stored as is).
	 *
	 * @return string
	 */
	private static function rate_key(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = 'unknown';
		}
		return 'msvcalc_rl_' . substr( wp_hash( $ip ), 0, 20 );
	}
}
