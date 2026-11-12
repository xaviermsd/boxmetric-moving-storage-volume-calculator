<?php
/**
 * Rooms, items, home-size presets and the vehicle, container and storage tables.
 *
 * Defaults live in data/catalogue.php and data/tables.php (translatable). When
 * the site owner edits them, the edited copy is stored in a non-autoloaded
 * option and used instead.
 *
 * @package MSVCalc
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Loads, validates and stores the item catalogue and the recommendation tables.
 */
final class MSVCalc_Catalogue {

	/**
	 * Option holding an edited catalogue (rooms, items, presets).
	 */
	const OPTION = 'msvcalc_catalogue';

	/**
	 * Option holding edited recommendation tables.
	 */
	const TABLES_OPTION = 'msvcalc_tables';

	/**
	 * Upper bounds that keep a stored catalogue a sane size.
	 */
	const MAX_ROOMS   = 40;
	const MAX_ITEMS   = 1500;
	const MAX_PRESETS = 20;
	const MAX_ROWS    = 40;

	/**
	 * Largest volume accepted for one item, in cubic metres.
	 */
	const MAX_ITEM_M3 = 100.0;

	/**
	 * Group for each shipped room, used when a stored room has no group yet.
	 */
	const ROOM_GROUPS = array(
		'garage'      => 'outdoor',
		'garden'      => 'outdoor',
		'leisure'     => 'hobbies',
		'specialty'   => 'hobbies',
		'boxes'       => 'boxes',
		'business'    => 'business',
		'retail'      => 'business',
		'hospitality' => 'business',
		'clinic'      => 'business',
		'school'      => 'business',
		'warehouse'   => 'business',
	);

	/**
	 * Request cache.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $catalogue = null;

	/**
	 * The room groups visitors pick from, in display order.
	 *
	 * @return array<int, array{id: string, name: string, icon: string}>
	 */
	public static function groups(): array {
		return array(
			array(
				'id'   => 'home',
				'name' => __( 'Home', 'boxmetric-moving-storage-volume-calculator' ),
				'icon' => 'living',
			),
			array(
				'id'   => 'outdoor',
				'name' => __( 'Garage & garden', 'boxmetric-moving-storage-volume-calculator' ),
				'icon' => 'garden',
			),
			array(
				'id'   => 'hobbies',
				'name' => __( 'Specialty items', 'boxmetric-moving-storage-volume-calculator' ),
				'icon' => 'piano',
			),
			array(
				'id'   => 'boxes',
				'name' => __( 'Boxes & packing', 'boxmetric-moving-storage-volume-calculator' ),
				'icon' => 'boxes',
			),
			array(
				'id'   => 'business',
				'name' => __( 'Business', 'boxmetric-moving-storage-volume-calculator' ),
				'icon' => 'office',
			),
		);
	}

	/**
	 * Whether a room group id exists.
	 *
	 * @param string $id Group id.
	 * @return bool
	 */
	public static function valid_group( string $id ): bool {
		foreach ( self::groups() as $group ) {
			if ( $group['id'] === $id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Request cache.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $tables = null;

	/**
	 * The catalogue in use.
	 *
	 * @return array{rooms: array<int, array<string, mixed>>, items: array<int, array<string, mixed>>, presets: array<int, array<string, mixed>>}
	 */
	public static function get(): array {
		if ( null === self::$catalogue ) {
			$stored          = get_option( self::OPTION, null );
			self::$catalogue = is_array( $stored ) && ! empty( $stored['items'] ) ? self::sanitize( $stored ) : self::defaults();
		}
		return self::$catalogue;
	}

	/**
	 * Whether the site uses an edited catalogue.
	 *
	 * @return bool
	 */
	public static function is_customised(): bool {
		$stored = get_option( self::OPTION, null );
		return is_array( $stored ) && ! empty( $stored['items'] );
	}

	/**
	 * The shipped catalogue.
	 *
	 * @return array{rooms: array<int, array<string, mixed>>, items: array<int, array<string, mixed>>, presets: array<int, array<string, mixed>>}
	 */
	public static function defaults(): array {
		$data = require MSVCALC_DIR . 'data/catalogue.php';
		return self::sanitize( is_array( $data ) ? $data : array() );
	}

	/**
	 * Saves an edited catalogue. Returns the sanitized copy that was stored.
	 *
	 * @param mixed $catalogue Raw catalogue.
	 * @return array<string, mixed>
	 */
	public static function save( $catalogue ): array {
		$clean = self::sanitize( $catalogue );
		update_option( self::OPTION, $clean, false );
		self::$catalogue = null;
		return $clean;
	}

	/**
	 * Restores the shipped catalogue.
	 */
	public static function reset(): void {
		delete_option( self::OPTION );
		self::$catalogue = null;
	}

	/**
	 * Items keyed by ID.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function items_by_id(): array {
		$map = array();
		foreach ( self::get()['items'] as $item ) {
			$map[ $item['id'] ] = $item;
		}
		return $map;
	}

	/**
	 * Rooms keyed by ID.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function rooms_by_id(): array {
		$map = array();
		foreach ( self::get()['rooms'] as $room ) {
			$map[ $room['id'] ] = $room;
		}
		return $map;
	}

	/**
	 * Sanitizes a catalogue array from any source (defaults, the option, an
	 * admin form or an imported file). Invalid rows are dropped, never trusted.
	 *
	 * @param mixed $data Raw catalogue.
	 * @return array{rooms: array<int, array<string, mixed>>, items: array<int, array<string, mixed>>, presets: array<int, array<string, mixed>>}
	 */
	public static function sanitize( $data ): array {
		$data  = is_array( $data ) ? $data : array();
		$rooms = array();
		$ids   = array();

		foreach ( array_slice( (array) ( $data['rooms'] ?? array() ), 0, self::MAX_ROOMS ) as $room ) {
			if ( ! is_array( $room ) ) {
				continue;
			}
			$id   = self::id( $room['id'] ?? '' );
			$name = self::label( $room['name'] ?? '' );
			if ( '' === $id || '' === $name || isset( $ids[ $id ] ) ) {
				continue;
			}
			$ids[ $id ] = true;
			$group      = self::id( $room['group'] ?? '' );
			if ( ! self::valid_group( $group ) ) {
				$group = self::ROOM_GROUPS[ $id ] ?? 'home';
			}
			$rooms[] = array(
				'id'    => $id,
				'name'  => $name,
				'icon'  => MSVCalc_Icons::valid( (string) ( $room['icon'] ?? '' ) ) ? (string) $room['icon'] : 'box',
				'group' => $group,
			);
		}

		$items    = array();
		$item_ids = array();
		foreach ( array_slice( (array) ( $data['items'] ?? array() ), 0, self::MAX_ITEMS ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id   = self::id( $item['id'] ?? '' );
			$room = self::id( $item['room'] ?? '' );
			$name = self::label( $item['name'] ?? '' );
			$m3   = self::volume( $item['m3'] ?? 0 );
			if ( '' === $id || '' === $name || ! isset( $ids[ $room ] ) || $m3 <= 0 || isset( $item_ids[ $id ] ) ) {
				continue;
			}
			$item_ids[ $id ] = true;

			$aliases = array();
			foreach ( array_slice( (array) ( $item['aliases'] ?? array() ), 0, 12 ) as $alias ) {
				$alias = self::label( $alias );
				if ( '' !== $alias ) {
					$aliases[] = $alias;
				}
			}

			$kg = isset( $item['kg'] ) && is_numeric( $item['kg'] ) ? round( max( 0.0, min( 5000.0, (float) $item['kg'] ) ), 1 ) : null;

			$items[] = array(
				'id'      => $id,
				'room'    => $room,
				'name'    => $name,
				'aliases' => $aliases,
				'm3'      => $m3,
				'kg'      => $kg,
				'icon'    => MSVCalc_Icons::valid( (string) ( $item['icon'] ?? '' ) ) ? (string) $item['icon'] : 'box',
				'popular' => ! empty( $item['popular'] ),
				'box'     => ! empty( $item['box'] ),
			);
		}

		$presets    = array();
		$preset_ids = array();
		foreach ( array_slice( (array) ( $data['presets'] ?? array() ), 0, self::MAX_PRESETS ) as $preset ) {
			if ( ! is_array( $preset ) ) {
				continue;
			}
			$id    = self::id( $preset['id'] ?? '' );
			$label = self::label( $preset['label'] ?? '' );
			if ( '' === $id || '' === $label || isset( $preset_ids[ $id ] ) ) {
				continue;
			}
			$preset_ids[ $id ] = true;

			$quantities = array();
			foreach ( (array) ( $preset['items'] ?? array() ) as $item_id => $qty ) {
				$item_id = self::id( $item_id );
				$qty     = absint( $qty );
				if ( isset( $item_ids[ $item_id ] ) && $qty > 0 ) {
					$quantities[ $item_id ] = min( 999, $qty );
				}
			}
			if ( $quantities ) {
				$icon      = (string) ( $preset['icon'] ?? '' );
				$presets[] = array(
					'id'    => $id,
					'label' => $label,
					'icon'  => MSVCalc_Icons::valid( $icon ) ? $icon : 'home',
					'items' => $quantities,
				);
			}
		}

		return array(
			'rooms'   => $rooms,
			'items'   => $items,
			'presets' => $presets,
		);
	}

	/**
	 * The recommendation tables in use, for every region.
	 *
	 * @return array<string, mixed>
	 */
	public static function tables(): array {
		if ( null === self::$tables ) {
			$stored       = get_option( self::TABLES_OPTION, null );
			self::$tables = is_array( $stored ) && ! empty( $stored['vehicles'] ) ? self::sanitize_tables( $stored ) : self::default_tables();
		}
		return self::$tables;
	}

	/**
	 * The shipped recommendation tables.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_tables(): array {
		$data = require MSVCALC_DIR . 'data/tables.php';
		return self::sanitize_tables( is_array( $data ) ? $data : array() );
	}

	/**
	 * Saves edited recommendation tables.
	 *
	 * @param mixed $tables Raw tables.
	 * @return array<string, mixed>
	 */
	public static function save_tables( $tables ): array {
		$clean = self::sanitize_tables( $tables );
		update_option( self::TABLES_OPTION, $clean, false );
		self::$tables = null;
		return $clean;
	}

	/**
	 * Restores the shipped recommendation tables.
	 */
	public static function reset_tables(): void {
		delete_option( self::TABLES_OPTION );
		self::$tables = null;
	}

	/**
	 * The tables for one region: vehicles, containers, storage units, and the
	 * shared box and density figures.
	 *
	 * @param string $region Region key.
	 * @return array<string, mixed>
	 */
	public static function region_tables( string $region ): array {
		$tables = self::tables();
		$region = isset( $tables['vehicles'][ $region ] ) ? $region : 'uk';

		return array(
			'vehicles'   => $tables['vehicles'][ $region ] ?? array(),
			'storage'    => $tables['storage'][ $region ] ?? array(),
			'containers' => $tables['containers'],
			'density'    => $tables['density'],
		);
	}

	/**
	 * Sanitizes the recommendation tables.
	 *
	 * @param mixed $data Raw tables.
	 * @return array<string, mixed>
	 */
	public static function sanitize_tables( $data ): array {
		$data    = is_array( $data ) ? $data : array();
		$regions = array( 'uk', 'eu', 'us', 'au' );
		$clean   = array(
			'vehicles'   => array(),
			'storage'    => array(),
			'containers' => self::rows( $data['containers'] ?? array() ),
			'density'    => isset( $data['density'] ) && is_numeric( $data['density'] ) ? max( 20, min( 600, (int) $data['density'] ) ) : 160,
		);

		foreach ( $regions as $region ) {
			$clean['vehicles'][ $region ] = self::rows( $data['vehicles'][ $region ] ?? array() );
			$clean['storage'][ $region ]  = self::rows( $data['storage'][ $region ] ?? array() );
		}

		return $clean;
	}

	/**
	 * Sanitizes one capacity table: rows of id, name, size note and usable m3,
	 * sorted by capacity.
	 *
	 * @param mixed $rows Raw rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function rows( $rows ): array {
		$clean = array();
		$seen  = array();
		foreach ( array_slice( (array) $rows, 0, self::MAX_ROWS ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id   = self::id( $row['id'] ?? '' );
			$name = self::label( $row['name'] ?? '' );
			$m3   = isset( $row['m3'] ) && is_numeric( $row['m3'] ) ? round( (float) $row['m3'], 2 ) : 0.0;
			if ( '' === $id || '' === $name || $m3 <= 0 || $m3 > 1000 || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$clean[]     = array(
				'id'   => $id,
				'name' => $name,
				'note' => self::label( $row['note'] ?? '', 120 ),
				'm3'   => $m3,
				'icon' => in_array( $row['icon'] ?? '', array( 'van', 'luton', 'truck', 'lorry', 'container', 'storage' ), true ) ? $row['icon'] : 'truck',
			);
		}
		usort(
			$clean,
			static function ( array $a, array $b ): int {
				return $a['m3'] <=> $b['m3'];
			}
		);
		return $clean;
	}

	/**
	 * A safe identifier: lowercase letters, digits, dashes and underscores.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function id( $value ): string {
		return is_scalar( $value ) ? substr( sanitize_key( (string) $value ), 0, 64 ) : '';
	}

	/**
	 * A plain-text label.
	 *
	 * @param mixed $value  Raw value.
	 * @param int   $length Maximum length.
	 * @return string
	 */
	private static function label( $value, int $length = 80 ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = sanitize_text_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	/**
	 * An item volume in cubic metres, 3 decimal places.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	private static function volume( $value ): float {
		if ( ! is_numeric( $value ) ) {
			return 0.0;
		}
		return round( max( 0.0, min( self::MAX_ITEM_M3, (float) $value ) ), 3 );
	}
}
