<?php
/**
 * A small Excel (.xlsx) writer for the branded leads export.
 *
 * CSV is plain text, so it cannot carry colours, column widths or cell
 * types. This writes a real spreadsheet instead: a branded title band, a
 * frozen header row with filters, sized columns, real dates and numbers,
 * and phone numbers kept as text. Pure PHP, no extensions beyond zlib
 * (and it still works without zlib, just uncompressed).
 *
 * @package MSVCalc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds .xlsx files.
 */
class MSVCalc_Xlsx {

	const INK    = 'FF0F1D29';
	const TEAL   = 'FF0FB5BA';
	const LIME   = 'FF84CC16';
	const HEADER = 'FFE3F6F6';
	const ZEBRA  = 'FFF5F9FA';
	const LINE   = 'FFE1E7EC';

	/**
	 * Status fills and text colours, matching the admin status pills.
	 *
	 * @var array<string, array<int, string>>
	 */
	const STATUS = array(
		'new'         => array( 'FFDDF8F0', 'FF0B4D50' ),
		'contacted'   => array( 'FFFCF0D6', 'FF7A5300' ),
		'in_progress' => array( 'FFE5F0FB', 'FF135E96' ),
		'converted'   => array( 'FFE7F7EA', 'FF00650F' ),
		'lost'        => array( 'FFFBEAEA', 'FF8A2424' ),
	);

	/**
	 * Cell kinds and their number format ids. Each kind gets an odd and an
	 * even (zebra) style.
	 *
	 * @var array<string, int>
	 */
	const KINDS = array(
		'text'     => 0,
		'wrap'     => 0,
		'date'     => 164,
		'datetime' => 165,
		'num2'     => 2,
		'num1'     => 166,
		'int'      => 3,
	);

	/**
	 * Builds a workbook.
	 *
	 * Each sheet: name, title, subtitle, columns (label, type, max width,
	 * optional status map of label => status key) and rows (arrays of
	 * scalar cells, in column order).
	 *
	 * @param array<int, array<string, mixed>> $sheets Sheets.
	 * @param string                           $title  Document title.
	 * @return string The .xlsx file contents.
	 */
	public static function build( array $sheets, string $title ): string {
		$files = array(
			'[Content_Types].xml'        => self::content_types( count( $sheets ) ),
			'_rels/.rels'                => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
			'docProps/core.xml'          => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' . self::esc( $title ) . '</dc:title><dc:creator>BoxMetric</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate( 'Y-m-d\TH:i:s\Z' ) . '</dcterms:created></cp:coreProperties>',
			'docProps/app.xml'           => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>BoxMetric</Application></Properties>',
			'xl/_rels/workbook.xml.rels' => self::workbook_rels( count( $sheets ) ),
			'xl/workbook.xml'            => self::workbook( $sheets ),
			'xl/styles.xml'              => self::styles(),
		);
		foreach ( array_values( $sheets ) as $i => $sheet ) {
			$files[ 'xl/worksheets/sheet' . ( $i + 1 ) . '.xml' ] = self::sheet( $sheet );
		}
		return self::zip( $files );
	}

	/**
	 * Style index for a cell kind on an odd or even row.
	 *
	 * @param string $kind Kind.
	 * @param bool   $even Even row.
	 * @return int
	 */
	private static function style( string $kind, bool $even ): int {
		$index = array_search( $kind, array_keys( self::KINDS ), true );
		return 4 + 2 * (int) ( false === $index ? 0 : $index ) + ( $even ? 1 : 0 );
	}

	/**
	 * Style index for a status pill.
	 *
	 * @param string $status Status key.
	 * @return int
	 */
	private static function status_style( string $status ): int {
		$index = array_search( $status, array_keys( self::STATUS ), true );
		return 4 + 2 * count( self::KINDS ) + (int) ( false === $index ? 0 : $index );
	}

	/**
	 * The stylesheet: fonts, fills, borders and cell styles.
	 *
	 * @return string
	 */
	private static function styles(): string {
		$fonts = array(
			'<font><sz val="11"/><color rgb="' . self::INK . '"/><name val="Calibri"/><family val="2"/></font>',
			'<font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>',
			'<font><sz val="10"/><color rgb="FFC6FF3D"/><name val="Calibri"/><family val="2"/></font>',
			'<font><b/><sz val="11"/><color rgb="' . self::INK . '"/><name val="Calibri"/><family val="2"/></font>',
		);
		$fills = array(
			'<fill><patternFill patternType="none"/></fill>',
			'<fill><patternFill patternType="gray125"/></fill>',
			self::solid( self::INK ),
			'<fill><gradientFill degree="0"><stop position="0"><color rgb="' . self::TEAL . '"/></stop><stop position="1"><color rgb="' . self::LIME . '"/></stop></gradientFill></fill>',
			self::solid( self::HEADER ),
			self::solid( self::ZEBRA ),
		);
		foreach ( self::STATUS as $pair ) {
			$fills[] = self::solid( $pair[0] );
			$fonts[] = '<font><b/><sz val="11"/><color rgb="' . $pair[1] . '"/><name val="Calibri"/><family val="2"/></font>';
		}
		$borders = array(
			'<border><left/><right/><top/><bottom/><diagonal/></border>',
			'<border><left/><right/><top/><bottom style="thin"><color rgb="' . self::LINE . '"/></bottom><diagonal/></border>',
			'<border><left/><right/><top/><bottom style="medium"><color rgb="' . self::TEAL . '"/></bottom><diagonal/></border>',
		);

		$top = '<alignment vertical="top"/>';
		$xfs = array(
			'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>',
			'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>',
			'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>',
			'<xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1"/>',
		);
		// 4 + 2n: data cells by kind, odd then even.
		foreach ( self::KINDS as $kind => $format ) {
			$align = 'wrap' === $kind ? '<alignment vertical="top" wrapText="1"/>' : $top;
			foreach ( array( 0, 5 ) as $fill ) {
				$xfs[] = '<xf numFmtId="' . $format . '" fontId="0" fillId="' . $fill . '" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>';
			}
		}
		// Status pills.
		$i = 0;
		foreach ( array_keys( self::STATUS ) as $unused ) {
			$xfs[] = '<xf numFmtId="0" fontId="' . ( 4 + $i ) . '" fillId="' . ( 6 + $i ) . '" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" horizontal="center"/></xf>';
			++$i;
		}
		// Header row, last so the indexes above stay simple.
		$xfs[] = '<xf numFmtId="0" fontId="3" fillId="4" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>';

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<numFmts count="3"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/><numFmt numFmtId="165" formatCode="yyyy-mm-dd hh:mm"/><numFmt numFmtId="166" formatCode="#,##0.0"/></numFmts>'
			. '<fonts count="' . count( $fonts ) . '">' . implode( '', $fonts ) . '</fonts>'
			. '<fills count="' . count( $fills ) . '">' . implode( '', $fills ) . '</fills>'
			. '<borders count="' . count( $borders ) . '">' . implode( '', $borders ) . '</borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="' . count( $xfs ) . '">' . implode( '', $xfs ) . '</cellXfs>'
			. '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
			. '</styleSheet>';
	}

	/**
	 * Header style index (the last cell style).
	 *
	 * @return int
	 */
	private static function header_style(): int {
		return 4 + 2 * count( self::KINDS ) + count( self::STATUS );
	}

	/**
	 * A solid fill.
	 *
	 * @param string $argb Colour.
	 * @return string
	 */
	private static function solid( string $argb ): string {
		return '<fill><patternFill patternType="solid"><fgColor rgb="' . $argb . '"/><bgColor indexed="64"/></patternFill></fill>';
	}

	/**
	 * One worksheet.
	 *
	 * @param array<string, mixed> $sheet Sheet.
	 * @return string
	 */
	private static function sheet( array $sheet ): string {
		$columns = array_values( (array) $sheet['columns'] );
		$rows    = array_values( (array) $sheet['rows'] );
		$count   = max( 1, count( $columns ) );
		$last    = self::col( $count - 1 );
		$head    = 4;
		$end     = $head + max( 1, count( $rows ) );

		// Column widths: fit the longest line, within each column's limit.
		$widths = array();
		foreach ( $columns as $c => $column ) {
			$max  = (int) ( $column['width'] ?? 40 );
			$best = self::length( (string) $column['label'] ) + 6;
			foreach ( $rows as $row ) {
				$best = max( $best, min( $max, self::length( (string) ( $row[ $c ] ?? '' ) ) + 2 ) );
			}
			if ( in_array( $column['type'] ?? 'text', array( 'date' ), true ) ) {
				$best = max( $best, 12 );
			} elseif ( 'datetime' === ( $column['type'] ?? 'text' ) ) {
				$best = max( $best, 17 );
			}
			$widths[] = max( 8, $best );
		}

		$xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		$xml .= '<sheetPr><tabColor rgb="' . self::TEAL . '"/><pageSetUpPr fitToPage="1"/></sheetPr>';
		$xml .= '<dimension ref="A1:' . $last . $end . '"/>';
		$xml .= '<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane xSplit="1" ySplit="' . $head . '" topLeftCell="B' . ( $head + 1 ) . '" activePane="bottomRight" state="frozen"/><selection pane="topRight"/><selection pane="bottomLeft"/><selection pane="bottomRight" activeCell="B' . ( $head + 1 ) . '" sqref="B' . ( $head + 1 ) . '"/></sheetView></sheetViews>';
		$xml .= '<sheetFormatPr defaultRowHeight="18" customHeight="1"/>';
		$xml .= '<cols>';
		foreach ( $widths as $c => $width ) {
			$xml .= '<col min="' . ( $c + 1 ) . '" max="' . ( $c + 1 ) . '" width="' . $width . '" customWidth="1"/>';
		}
		$xml .= '</cols><sheetData>';

		// Title band, subtitle and the brand gradient bar.
		$xml .= self::band( 1, 32, 1, (string) $sheet['title'], $count );
		$xml .= self::band( 2, 20, 2, (string) $sheet['subtitle'], $count );
		$xml .= self::band( 3, 5, 3, '', $count );

		$xml .= '<row r="' . $head . '" ht="30" customHeight="1">';
		foreach ( $columns as $c => $column ) {
			$xml .= self::text_cell( self::col( $c ) . $head, (string) $column['label'], self::header_style() );
		}
		$xml .= '</row>';

		foreach ( $rows as $r => $row ) {
			$n    = $head + 1 + $r;
			$even = 1 === $r % 2;
			$xml .= '<row r="' . $n . '">';
			foreach ( $columns as $c => $column ) {
				$xml .= self::cell( self::col( $c ) . $n, $row[ $c ] ?? '', $column, $even );
			}
			$xml .= '</row>';
		}
		$xml .= '</sheetData>';
		if ( $rows ) {
			$xml .= '<autoFilter ref="A' . $head . ':' . $last . $end . '"/>';
		}
		$xml .= '<mergeCells count="3"><mergeCell ref="A1:' . $last . '1"/><mergeCell ref="A2:' . $last . '2"/><mergeCell ref="A3:' . $last . '3"/></mergeCells>';
		$xml .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>';
		$xml .= '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>';
		$xml .= '</worksheet>';
		return $xml;
	}

	/**
	 * A merged band row.
	 *
	 * @param int    $n      Row number.
	 * @param int    $height Height in points.
	 * @param int    $style  Style index.
	 * @param string $text   Text in the first cell.
	 * @param int    $count  Number of columns.
	 * @return string
	 */
	private static function band( int $n, int $height, int $style, string $text, int $count ): string {
		$xml = '<row r="' . $n . '" ht="' . $height . '" customHeight="1">';
		for ( $c = 0; $c < $count; $c++ ) {
			$ref  = self::col( $c ) . $n;
			$xml .= 0 === $c && '' !== $text ? self::text_cell( $ref, $text, $style ) : '<c r="' . $ref . '" s="' . $style . '"/>';
		}
		return $xml . '</row>';
	}

	/**
	 * One data cell, typed by its column.
	 *
	 * @param string               $ref    Cell reference.
	 * @param mixed                $value  Value.
	 * @param array<string, mixed> $column Column.
	 * @param bool                 $even   Even row.
	 * @return string
	 */
	private static function cell( string $ref, $value, array $column, bool $even ): string {
		$type  = (string) ( $column['type'] ?? 'text' );
		$value = is_scalar( $value ) ? (string) $value : '';

		if ( 'status' === $type ) {
			$map    = (array) ( $column['status'] ?? array() );
			$status = isset( $map[ $value ] ) ? (string) $map[ $value ] : '';
			if ( isset( self::STATUS[ $status ] ) ) {
				return self::text_cell( $ref, $value, self::status_style( $status ) );
			}
			$type = 'text';
		}

		$style = self::style( isset( self::KINDS[ $type ] ) ? $type : 'text', $even );
		if ( '' === $value ) {
			return '<c r="' . $ref . '" s="' . $style . '"/>';
		}
		if ( 'date' === $type || 'datetime' === $type ) {
			$serial = self::serial( $value );
			if ( null === $serial ) {
				return self::text_cell( $ref, $value, self::style( 'text', $even ) );
			}
			return '<c r="' . $ref . '" s="' . $style . '"><v>' . $serial . '</v></c>';
		}
		if ( in_array( $type, array( 'num1', 'num2', 'int' ), true ) && is_numeric( $value ) ) {
			return '<c r="' . $ref . '" s="' . $style . '"><v>' . ( 0 + $value ) . '</v></c>';
		}
		return self::text_cell( $ref, $value, $style );
	}

	/**
	 * A text cell. Inline strings are never read as formulas.
	 *
	 * @param string $ref   Cell reference.
	 * @param string $text  Text.
	 * @param int    $style Style index.
	 * @return string
	 */
	private static function text_cell( string $ref, string $text, int $style ): string {
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 32000 ) : substr( $text, 0, 32000 );
		return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . self::esc( $text ) . '</t></is></c>';
	}

	/**
	 * Excel date serial for "Y-m-d" or "Y-m-d H:i", or null.
	 *
	 * @param string $value Date.
	 * @return float|null
	 */
	private static function serial( string $value ): ?float {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return null;
		}
		$days = gmmktime( 0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1] ) / 86400 + 25569;
		$time = isset( $m[4] ) ? ( (int) $m[4] * 60 + (int) $m[5] ) / 1440 : 0;
		return round( $days + $time, 6 );
	}

	/**
	 * Column letters for a zero-based index.
	 *
	 * @param int $index Index.
	 * @return string
	 */
	private static function col( int $index ): string {
		$name = '';
		for ( $n = $index + 1; $n > 0; $n = intdiv( $n - 1, 26 ) ) {
			$name = chr( 65 + ( $n - 1 ) % 26 ) . $name;
		}
		return $name;
	}

	/**
	 * Longest line length of a cell.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function length( string $text ): int {
		$best = 0;
		foreach ( explode( "\n", $text ) as $line ) {
			$best = max( $best, function_exists( 'mb_strlen' ) ? mb_strlen( $line ) : strlen( $line ) );
		}
		return $best;
	}

	/**
	 * XML-escapes text and drops characters XML cannot hold.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function esc( string $text ): string {
		$text = (string) preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text );
		return htmlspecialchars( $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/**
	 * Content types.
	 *
	 * @param int $count Number of sheets.
	 * @return string
	 */
	private static function content_types( int $count ): string {
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';
		for ( $i = 1; $i <= $count; $i++ ) {
			$xml .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		}
		return $xml . '</Types>';
	}

	/**
	 * Workbook relationships.
	 *
	 * @param int $count Number of sheets.
	 * @return string
	 */
	private static function workbook_rels( int $count ): string {
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
		for ( $i = 1; $i <= $count; $i++ ) {
			$xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
		}
		$xml .= '<Relationship Id="rId' . ( $count + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
		return $xml . '</Relationships>';
	}

	/**
	 * The workbook: sheet list, filter ranges and print titles.
	 *
	 * @param array<int, array<string, mixed>> $sheets Sheets.
	 * @return string
	 */
	private static function workbook( array $sheets ): string {
		$list  = '';
		$names = '';
		foreach ( array_values( $sheets ) as $i => $sheet ) {
			$name  = self::sheet_name( (string) $sheet['name'] );
			$quote = "'" . str_replace( "'", "''", $name ) . "'";
			$last  = self::col( max( 1, count( (array) $sheet['columns'] ) ) - 1 );
			$end   = 4 + max( 1, count( (array) $sheet['rows'] ) );
			$list .= '<sheet name="' . self::esc( $name ) . '" sheetId="' . ( $i + 1 ) . '" r:id="rId' . ( $i + 1 ) . '"/>';
			if ( $sheet['rows'] ) {
				$names .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">' . self::esc( $quote . '!$A$4:$' . $last . '$' . $end ) . '</definedName>';
			}
			$names .= '<definedName name="_xlnm.Print_Titles" localSheetId="' . $i . '">' . self::esc( $quote . '!$4:$4' ) . '</definedName>';
		}
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>' . $list . '</sheets><definedNames>' . $names . '</definedNames></workbook>';
	}

	/**
	 * A valid sheet name (31 characters, no []:*?/\).
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function sheet_name( string $name ): string {
		$name = trim( str_replace( array( '[', ']', ':', '*', '?', '/', '\\' ), ' ', $name ) );
		$name = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 31 ) : substr( $name, 0, 31 );
		return '' === $name ? 'Sheet' : $name;
	}

	/**
	 * Packs files into a ZIP archive in memory.
	 *
	 * @param array<string, string> $files Path => contents.
	 * @return string
	 */
	private static function zip( array $files ): string {
		$time    = 0;
		$date    = ( ( (int) gmdate( 'Y' ) - 1980 ) << 9 ) | ( (int) gmdate( 'n' ) << 5 ) | (int) gmdate( 'j' );
		$deflate = function_exists( 'gzdeflate' );
		$body    = '';
		$central = '';
		foreach ( $files as $name => $data ) {
			$crc      = crc32( $data );
			$packed   = $deflate ? (string) gzdeflate( $data, 6 ) : $data;
			$method   = $deflate ? 8 : 0;
			$offset   = strlen( $body );
			$body    .= pack( 'VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $method, $time, $date, $crc, strlen( $packed ), strlen( $data ), strlen( $name ), 0 ) . $name . $packed;
			$central .= pack( 'VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, $method, $time, $date, $crc, strlen( $packed ), strlen( $data ), strlen( $name ), 0, 0, 0, 0, 0, $offset ) . $name;
		}
		return $body . $central . pack( 'VvvvvVVv', 0x06054b50, 0, 0, count( $files ), count( $files ), strlen( $central ), strlen( $body ), 0 );
	}
}
