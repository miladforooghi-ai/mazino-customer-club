<?php
/**
 * CSV / Excel export of club members.
 *
 * @package Mazino_Customer_Club
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'MCC_Export' ) ) {
	return;
}

/**
 * Streams member rows as a downloadable file.
 */
class MCC_Export {

	/**
	 * Columns of the exported file.
	 *
	 * @return array<string,string>
	 */
	public static function columns() {
		return array(
			'index'      => 'ردیف',
			'name'       => 'نام و نام خانوادگی',
			'phone'      => 'شماره تلفن همراه',
			'birth_date' => 'تاریخ تولد',
			'age'        => 'سن',
			'created_at' => 'تاریخ عضویت',
			'source'     => 'صفحهٔ ثبت‌نام',
		);
	}

	/**
	 * Map a database row to export values.
	 *
	 * @param object $row  Row.
	 * @param int    $index Row number.
	 * @return array
	 */
	private static function row_values( $row, $index ) {
		$birth = MCC_Jalali::format( $row->birth_date, true );
		$age   = '';

		if ( '' !== $birth && preg_match( '#^(\d{4})/(\d{1,2})/(\d{1,2})$#', (string) $row->birth_date, $m ) ) {
			$age = MCC_Jalali::age( $m[1], $m[2], $m[3] );
		}

		return array(
			'index'      => $index,
			'name'       => (string) $row->name,
			'phone'      => (string) $row->phone,
			'birth_date' => $birth,
			'age'        => '' === $age ? '' : $age,
			'created_at' => MCC_Helpers::local_date( $row->created_at, 'Y/m/d H:i' ),
			'source'     => (string) $row->source,
		);
	}

	/**
	 * Get the prepared matrix of rows.
	 *
	 * @param array $rows Database rows.
	 * @return array
	 */
	private static function matrix( $rows ) {
		$matrix = array();
		$index  = 0;

		foreach ( $rows as $row ) {
			$index++;
			$matrix[] = self::row_values( $row, $index );
		}

		return $matrix;
	}

	/**
	 * Send the file to the browser and stop.
	 *
	 * @param array  $rows   Database rows.
	 * @param string $format csv|xlsx.
	 * @return void
	 */
	public static function output( $rows, $format = 'csv' ) {
		if ( headers_sent() ) {
			wp_die( 'خروجی گرفتن امکان‌پذیر نیست: پیش از ارسال فایل، چیزی در صفحه چاپ شده است.' );
		}

		$matrix = self::matrix( $rows );
		$stamp  = gmdate( 'Y-m-d' );

		nocache_headers();

		if ( 'xlsx' === $format && class_exists( 'ZipArchive' ) ) {
			$file = self::build_xlsx( $matrix );

			if ( $file ) {
				header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
				header( 'Content-Disposition: attachment; filename="mazino-club-members-' . $stamp . '.xlsx"' );
				header( 'Content-Length: ' . filesize( $file ) );

				readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
				wp_delete_file( $file );
				exit;
			}
		}

		self::send_csv( $matrix, $stamp );
	}

	/**
	 * Separator used in the CSV file.
	 *
	 * Persian Excel expects a semicolon, most other tools a comma.
	 *
	 * @return string
	 */
	private static function delimiter() {
		$delimiter = 0 === strpos( get_locale(), 'fa' ) ? ';' : ',';

		/**
		 * Filter the CSV delimiter.
		 *
		 * @param string $delimiter Delimiter.
		 */
		return (string) apply_filters( 'mcc_csv_delimiter', $delimiter );
	}

	/**
	 * Stream a CSV file.
	 *
	 * @param array  $matrix Prepared rows.
	 * @param string $stamp  Date stamp.
	 * @return void
	 */
	private static function send_csv( $matrix, $stamp ) {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mazino-club-members-' . $stamp . '.csv"' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false === $output ) {
			exit;
		}

		// BOM so Excel detects UTF-8 and renders Persian correctly.
		fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		fputcsv( $output, array_values( self::columns() ), self::delimiter() );

		foreach ( $matrix as $row ) {
			fputcsv( $output, array_values( $row ), self::delimiter() );
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Escape a value for XML.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function xml( $value ) {
		$value = (string) $value;
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value );

		return htmlspecialchars( $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/**
	 * Column letter for a zero based index.
	 *
	 * @param int $index Index.
	 * @return string
	 */
	private static function column_letter( $index ) {
		$letters = '';
		$index   = (int) $index + 1;

		while ( $index > 0 ) {
			$remainder = ( $index - 1 ) % 26;
			$letters   = chr( 65 + $remainder ) . $letters;
			$index     = (int) ( ( $index - $remainder ) / 26 );
		}

		return $letters;
	}

	/**
	 * Build a minimal but valid XLSX file.
	 *
	 * @param array $matrix Prepared rows.
	 * @return string|false Path of the temporary file.
	 */
	private static function build_xlsx( $matrix ) {
		$file = wp_tempnam( 'mcc-members.xlsx' );

		if ( ! $file ) {
			return false;
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $file );

			return false;
		}

		$headers = array_values( self::columns() );
		$count   = count( $headers );

		$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<sheetViews><sheetView rightToLeft="1" tabSelected="1" workbookViewId="0">'
			. '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
			. '</sheetView></sheetViews>'
			. '<sheetFormatPr defaultRowHeight="18"/>'
			. '<cols>';

		$widths = array( 7, 30, 18, 18, 7, 20, 40 );

		for ( $i = 0; $i < $count; $i++ ) {
			$width = isset( $widths[ $i ] ) ? $widths[ $i ] : 18;
			$sheet .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="' . $width . '" customWidth="1"/>';
		}

		$sheet .= '</cols><sheetData>';

		$sheet .= '<row r="1" ht="26" customHeight="1">';

		foreach ( $headers as $index => $header ) {
			$ref    = self::column_letter( $index ) . '1';
			$sheet .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t>' . self::xml( $header ) . '</t></is></c>';
		}

		$sheet .= '</row>';

		$row_number = 1;

		foreach ( $matrix as $row ) {
			$row_number++;
			$sheet .= '<row r="' . $row_number . '">';

			foreach ( array_values( $row ) as $index => $value ) {
				$ref    = self::column_letter( $index ) . $row_number;
				$sheet .= '<c r="' . $ref . '" t="inlineStr" s="0"><is><t>' . self::xml( $value ) . '</t></is></c>';
			}

			$sheet .= '</row>';
		}

		$sheet .= '</sheetData></worksheet>';

		$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="2">'
			. '<font><sz val="11"/><name val="Tahoma"/><family val="2"/></font>'
			. '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Tahoma"/><family val="2"/></font>'
			. '</fonts>'
			. '<fills count="3">'
			. '<fill><patternFill patternType="none"/></fill>'
			. '<fill><patternFill patternType="gray125"/></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FF6D5EFC"/><bgColor indexed="64"/></patternFill></fill>'
			. '</fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="2">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1">'
			. '<alignment horizontal="right" vertical="center" wrapText="1"/></xf>'
			. '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1">'
			. '<alignment horizontal="center" vertical="center"/></xf>'
			. '</cellXfs>'
			. '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
			. '</styleSheet>';

		$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
			. 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="اعضای باشگاه" sheetId="1" r:id="rId1"/></sheets></workbook>';

		$content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>';

		$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';

		$workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>';

		$zip->addFromString( '[Content_Types].xml', $content_types );
		$zip->addFromString( '_rels/.rels', $rels );
		$zip->addFromString( 'xl/workbook.xml', $workbook );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $workbook_rels );
		$zip->addFromString( 'xl/styles.xml', $styles );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet );

		$zip->close();

		return $file;
	}
}
