<?php
define( 'ABSPATH', __DIR__ );
class WP_Post {}
class WP_Comment {}
require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-dates.php';
$zone = new DateTimeZone( 'Asia/Tehran' );
$cases = array(
	array( '2026-09-29 12:00:00', 'j F Y', '۷ مهر ۱۴۰۵' ),
	array( '2025-03-20 12:00:00', 'Y/m/d', '۱۴۰۳/۱۲/۳۰' ),
	array( '2025-03-21 12:00:00', 'Y/m/d', '۱۴۰۴/۰۱/۰۱' ),
	array( '2026-03-20 12:00:00', 'Y/m/d', '۱۴۰۴/۱۲/۲۹' ),
	array( '2026-03-21 12:00:00', 'Y/m/d', '۱۴۰۵/۰۱/۰۱' ),
	array( '2026-09-29 12:00:00', 'H:i', '۱۲:۰۰' ),
	array( '2025-03-20 12:00:00', 't L z jS', '۳۰ ۱ ۳۶۵ ۳۰' ),
);
foreach ( $cases as list( $source, $format, $expected ) ) {
	$stamp = ( new DateTimeImmutable( $source, $zone ) )->getTimestamp();
	$actual = Chidemoon_Core_Dates::format( $format, $stamp, $zone );
	if ( $expected !== $actual ) {
		throw new RuntimeException( "$source $format: $actual != $expected" );
	}
}
$stamp = ( new DateTimeImmutable( '2026-03-20T21:00:00Z' ) )->getTimestamp();
if ( '۱۴۰۵/۰۱/۰۱' !== Chidemoon_Core_Dates::format( 'Y/m/d', $stamp, $zone ) ) {
	throw new RuntimeException( 'Persian date must respect the site timezone.' );
}
foreach ( array( DATE_ATOM, 'Y-m-d H:i:s', 'Y-m-d', 'c', 'r', 'U' ) as $format ) {
	if ( 'machine-value' !== Chidemoon_Core_Dates::display_date( 'machine-value', $format, $stamp, $zone ) ) {
		throw new RuntimeException( 'Machine timestamp was changed.' );
	}
	if ( 'machine-value' !== Chidemoon_Core_Dates::post_date( 'machine-value', $format, new WP_Post() ) || 'machine-value' !== Chidemoon_Core_Dates::comment_date( 'machine-value', $format, new WP_Comment() ) ) {
		throw new RuntimeException( 'A post or comment machine timestamp was changed.' );
	}
}
if ( '۱۴۰۵/۰۱/۰۱' !== Chidemoon_Core_Dates::format( 'Y/m/d', $stamp, new DateTimeZone( '+03:30' ) ) ) {
	throw new RuntimeException( 'Fixed-offset WordPress timezones must also work.' );
}
echo "Jalali leap years, Nowruz, timezone and machine timestamps passed.\n";
