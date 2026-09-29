<?php
/** Persian calendar for display; storage, feeds and API timestamps stay ISO/Gregorian. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Dates {
	public static function register(): void {
		add_filter( 'wp_date', array( __CLASS__, 'display_date' ), 20, 4 );
		foreach ( array( 'get_the_date', 'get_the_modified_date' ) as $hook ) {
			add_filter( $hook, array( __CLASS__, 'post_date' ), 20, 3 );
		}
		add_filter( 'get_comment_date', array( __CLASS__, 'comment_date' ), 20, 3 );
	}

	private static function machine_format( string $format ): bool {
		return in_array( $format, array( 'c', 'r', 'U', 'Y-m-d', 'Y-m-d H:i:s', 'Y-m-d\\TH:i:s', 'Y-m-d\\TH:i:sP', DATE_ATOM, DATE_RFC3339_EXTENDED, DATE_RFC2822, DATE_RSS, DATE_W3C ), true )
			|| str_contains( $format, '\\T' ) || str_contains( $format, '\\Z' );
	}

	public static function display_date( string $date, string $format, int $timestamp, DateTimeZone $timezone ): string {
		if ( self::machine_format( $format ) ) {
			return $date;
		}
		return self::format( $format, $timestamp, $timezone ) ?: $date;
	}

	public static function post_date( string $date, string $format, WP_Post $post ): string {
		if ( '' === $format ) {
			$format = get_option( 'date_format' );
		}
		if ( self::machine_format( $format ) ) {
			return $date;
		}
		$datetime = get_post_datetime( $post, 'get_the_modified_date' === current_filter() ? 'modified' : 'date' );
		return $datetime ? ( self::format( $format, $datetime->getTimestamp(), wp_timezone() ) ?: $date ) : $date;
	}

	public static function comment_date( string $date, string $format, WP_Comment $comment ): string {
		$format = $format ?: get_option( 'date_format' );
		if ( self::machine_format( $format ) ) {
			return $date;
		}
		$datetime = new DateTimeImmutable( $comment->comment_date, wp_timezone() );
		return self::format( $format, $datetime->getTimestamp(), wp_timezone() ) ?: $date;
	}

	public static function format( string $format, int $timestamp, DateTimeZone $timezone ): string {
		if ( ! class_exists( IntlDateFormatter::class ) ) {
			return '';
		}
		$tokens = array( 'Y' => 'yyyy', 'y' => 'yy', 'm' => 'MM', 'n' => 'M', 'd' => 'dd', 'j' => 'd', 'F' => 'MMMM', 'M' => 'MMM', 'l' => 'EEEE', 'D' => 'EEE', 'H' => 'HH', 'G' => 'H', 'h' => 'hh', 'g' => 'h', 'i' => 'mm', 's' => 'ss', 'a' => 'a', 'A' => 'a', 'W' => 'ww' );
		$zone = $timezone->getName();
		if ( preg_match( '/^[+-]/', $zone ) ) {
			$zone = 'GMT' . $zone;
		}
		$formatter = new IntlDateFormatter( 'fa_IR@calendar=persian', IntlDateFormatter::NONE, IntlDateFormatter::NONE, $zone, IntlDateFormatter::TRADITIONAL );
		$calendar = IntlCalendar::createInstance( $zone, 'fa_IR@calendar=persian' );
		$calendar->setTime( $timestamp * 1000 );
		$datetime = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone );
		$result = '';
		$escaped = false;
		foreach ( str_split( $format ) as $token ) {
			if ( $escaped ) {
				$result .= $token;
				$escaped = false;
			} elseif ( '\\' === $token ) {
				$escaped = true;
			} elseif ( in_array( $token, array( 't', 'L', 'z', 'S' ), true ) ) {
				$values = array( 't' => $calendar->getActualMaximum( IntlCalendar::FIELD_DAY_OF_MONTH ), 'L' => 366 === $calendar->getActualMaximum( IntlCalendar::FIELD_DAY_OF_YEAR ) ? 1 : 0, 'z' => $calendar->get( IntlCalendar::FIELD_DAY_OF_YEAR ) - 1, 'S' => '' );
				$result .= strtr( (string) $values[ $token ], array_combine( range( 0, 9 ), preg_split( '//u', '۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY ) ) );
			} elseif ( isset( $tokens[ $token ] ) ) {
				$formatter->setPattern( $tokens[ $token ] );
				$result .= $formatter->format( $timestamp );
			} elseif ( preg_match( '/[a-zA-Z]/', $token ) ) {
				$result .= $datetime->format( $token );
			} else {
				$result .= $token;
			}
		}
		return $result;
	}
}
