<?php
/**
 * Volume maths and recommendations. The browser runs the same rules in
 * calculator.js; the server repeats them for quote requests so stored and
 * emailed totals never depend on numbers sent by the browser.
 *
 * @package MSVCalc
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Calculates totals and picks a vehicle, container and storage unit.
 */
final class MSVCalc_Calculator {

	/**
	 * Cubic feet in one cubic metre.
	 */
	const FT3_PER_M3 = 35.3147;

	/**
	 * Pounds in one kilogram.
	 */
	const LB_PER_KG = 2.20462;

	/**
	 * Most custom items a visitor can add.
	 */
	const MAX_CUSTOM = 30;

	/**
	 * Highest quantity of one item.
	 */
	const MAX_QTY = 999;

	/**
	 * Calculates an inventory.
	 *
	 * @param array<mixed> $quantities Item ID => quantity.
	 * @param array<mixed> $custom     Custom items: name, m3, qty, icon.
	 * @return array<string, mixed>
	 */
	public static function calculate( array $quantities, array $custom = array() ): array {
		$items   = MSVCalc_Catalogue::items_by_id();
		$rooms   = MSVCalc_Catalogue::rooms_by_id();
		$region  = (string) MSVCalc_Settings::get( 'region' );
		$tables  = MSVCalc_Catalogue::region_tables( $region );
		$density = (float) $tables['density'];

		$lines     = array();
		$total     = 0.0;
		$weight    = 0.0;
		$count     = 0;
		$box_count = 0;

		foreach ( $quantities as $id => $qty ) {
			$id  = sanitize_key( (string) $id );
			$qty = min( self::MAX_QTY, absint( $qty ) );
			if ( ! $qty || ! isset( $items[ $id ] ) ) {
				continue;
			}
			$item    = $items[ $id ];
			$volume  = $item['m3'] * $qty;
			$total  += $volume;
			$weight += ( null !== $item['kg'] ? (float) $item['kg'] : $item['m3'] * $density ) * $qty;
			$count  += $qty;
			if ( $item['box'] ) {
				$box_count += $qty;
			}
			$lines[] = array(
				'room' => isset( $rooms[ $item['room'] ] ) ? $rooms[ $item['room'] ]['name'] : '',
				'name' => $item['name'],
				'icon' => $item['icon'],
				'qty'  => $qty,
				'm3'   => round( $volume, 3 ),
			);
		}

		foreach ( array_slice( $custom, 0, self::MAX_CUSTOM ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
			$name = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 60 ) : substr( $name, 0, 60 );
			$m3   = is_numeric( $row['m3'] ?? null ) ? (float) $row['m3'] : 0.0;
			$qty  = min( self::MAX_QTY, absint( $row['qty'] ?? 0 ) );
			if ( '' === $name || $m3 <= 0 || $m3 > MSVCalc_Catalogue::MAX_ITEM_M3 || ! $qty ) {
				continue;
			}
			$icon    = sanitize_key( is_scalar( $row['icon'] ?? null ) ? (string) $row['icon'] : '' );
			$volume  = $m3 * $qty;
			$total  += $volume;
			$weight += $m3 * $density * $qty;
			$count  += $qty;
			$lines[] = array(
				'room' => __( 'Custom items', 'moving-storage-volume-calculator' ),
				'name' => $name,
				'icon' => MSVCalc_Icons::valid( $icon ) ? $icon : 'box',
				'qty'  => $qty,
				'm3'   => round( $volume, 3 ),
			);
		}

		$margin = (int) MSVCalc_Settings::get( 'margin' );
		$load   = $total * ( 1 + $margin / 100 );

		return array(
			'lines'     => $lines,
			'count'     => $count,
			'boxes'     => $box_count,
			'net_m3'    => round( $total, 2 ),
			'margin'    => $margin,
			'm3'        => round( $load, 2 ),
			'ft3'       => round( $load * self::FT3_PER_M3, 1 ),
			'kg'        => (int) round( $weight ),
			'lb'        => (int) round( $weight * self::LB_PER_KG ),
			'vehicle'   => self::fit( $load, $tables['vehicles'] ),
			'container' => self::fit( $load, $tables['containers'] ),
			'storage'   => self::fit( $load, $tables['storage'] ),
		);
	}

	/**
	 * Picks the smallest row that holds the load. When nothing is big enough,
	 * it uses as many of the largest row as needed, and the smallest row that
	 * holds what is left.
	 *
	 * @param float                            $load Load in cubic metres.
	 * @param array<int, array<string, mixed>> $rows Capacity rows sorted by m3.
	 * @return array<string, mixed>|null
	 */
	public static function fit( float $load, array $rows ): ?array {
		if ( $load <= 0 || ! $rows ) {
			return null;
		}

		foreach ( $rows as $row ) {
			if ( $row['m3'] >= $load ) {
				return array(
					'name'  => $row['name'],
					'label' => $row['name'],
					'count' => 1,
					'fill'  => (int) round( $load / $row['m3'] * 100 ),
				);
			}
		}

		$largest = end( $rows );
		$full    = (int) floor( $load / $largest['m3'] );
		$rest    = $load - $full * $largest['m3'];
		$parts   = array();

		if ( $full > 0 ) {
			/* translators: 1: number of vehicles or units, 2: their name. */
			$parts[] = sprintf( __( '%1$d × %2$s', 'moving-storage-volume-calculator' ), $full, $largest['name'] );
		}
		$extra = null;
		if ( $rest > 0.01 ) {
			foreach ( $rows as $row ) {
				if ( $row['m3'] >= $rest ) {
					$extra = $row;
					break;
				}
			}
			/* translators: 1: number of vehicles or units, 2: their name. */
			$parts[] = sprintf( __( '%1$d × %2$s', 'moving-storage-volume-calculator' ), 1, $extra ? $extra['name'] : $largest['name'] );
		}

		$capacity = $full * $largest['m3'] + ( $rest > 0.01 ? ( $extra ? $extra['m3'] : $largest['m3'] ) : 0 );

		return array(
			'name'  => $largest['name'],
			'label' => implode( ' + ', $parts ),
			'count' => $full + ( $rest > 0.01 ? 1 : 0 ),
			'fill'  => $capacity > 0 ? (int) round( $load / $capacity * 100 ) : 100,
		);
	}

	/**
	 * Formats a volume for people, in the site's unit.
	 *
	 * @param float  $m3   Volume in cubic metres.
	 * @param string $unit m3 or ft3.
	 * @return string
	 */
	public static function format_volume( float $m3, string $unit ): string {
		if ( 'ft3' === $unit ) {
			/* translators: %s: volume in cubic feet. */
			return sprintf( __( '%s ft³', 'moving-storage-volume-calculator' ), number_format_i18n( $m3 * self::FT3_PER_M3, 0 ) );
		}
		/* translators: %s: volume in cubic metres. */
		return sprintf( __( '%s m³', 'moving-storage-volume-calculator' ), number_format_i18n( $m3, 2 ) );
	}
}
