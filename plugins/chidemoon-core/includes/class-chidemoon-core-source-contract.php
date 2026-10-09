<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Source_Contract {
 public static function identity( array $identity ): array {
  if ( array_diff( array_keys( $identity ), array( 'provider', 'store', 'product', 'variant' ) ) || ! preg_match( '/^[a-z][a-z0-9_-]{0,63}$/D', (string) ( $identity['provider'] ?? '' ) ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
  foreach ( array( 'store', 'product' ) as $field ) {
   if ( ! isset( $identity[ $field ] ) || ! is_string( $identity[ $field ] ) || '' === trim( $identity[ $field ] ) || mb_strlen( $identity[ $field ] ) > 256 ) { throw new InvalidArgumentException( 'invalid_record' ); }
  }
  if ( ! array_key_exists( 'variant', $identity ) || ( null !== $identity['variant'] && ( ! is_string( $identity['variant'] ) || '' === trim( $identity['variant'] ) || mb_strlen( $identity['variant'] ) > 256 ) ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
  $store = strtolower( trim( $identity['store'] ) );
  if ( preg_match( '~^https?://~', $store ) ) {
   $parts = parse_url( $store );
   if ( ! $parts || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || ! in_array( $parts['path'] ?? '', array( '', '/' ), true ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
   $default_port = 'https' === $parts['scheme'] ? 443 : 80;
   $store = $parts['host'] . ( isset( $parts['port'] ) && $parts['port'] !== $default_port ? ':' . $parts['port'] : '' );
  }
  $store = preg_replace( '/^www\./', '', $store );
  return array( 'provider' => $identity['provider'], 'store' => $store, 'product' => trim( $identity['product'] ), 'variant' => null === $identity['variant'] ? null : trim( $identity['variant'] ) );
 }

 public static function source_key( array $identity ): string {
  $identity = self::identity( $identity );
  return 'src:v2:' . hash( 'sha256', json_encode( array( $identity['provider'], $identity['store'], $identity['product'], $identity['variant'] ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR ) );
 }

 public static function validate_record( array $record ): array {
  if ( array_diff( array_keys( $record ), array( 'sourceKey', 'identity', 'aliases', 'revision', 'contentRevision', 'content', 'observations', 'removals' ) ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
  $identity = self::identity( $record['identity'] ?? array() );
  if ( ! hash_equals( self::source_key( $identity ), (string) ( $record['sourceKey'] ?? '' ) ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
  foreach ( array( 'revision', 'contentRevision' ) as $field ) {
   if ( ! is_int( $record[ $field ] ?? null ) || $record[ $field ] < 1 || $record[ $field ] > 2147483647 ) { throw new InvalidArgumentException( 'invalid_record' ); }
  }
  if ( ! is_array( $record['aliases'] ?? null ) || count( $record['aliases'] ) > 16 || ! is_array( $record['removals'] ?? null ) || count( $record['removals'] ) > 7 ) { throw new InvalidArgumentException( 'invalid_record' ); }
  foreach ( $record['aliases'] as $alias ) { if ( ! is_string( $alias ) || ! preg_match( '/^[A-Za-z0-9._:-]{1,190}$/D', $alias ) ) { throw new InvalidArgumentException( 'invalid_record' ); } }
  if ( array_diff( $record['removals'], array( 'description', 'images', 'specifications', 'brand', 'seller', 'price', 'rating' ) ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
  $content = $record['content'] ?? null;
  if ( ! is_array( $content ) || array_diff( array_keys( $content ), array( 'title', 'description', 'sourceUrl', 'affiliateUrl', 'images', 'specifications', 'brand', 'seller' ) ) || ! is_string( $content['title'] ?? null ) || '' === trim( $content['title'] ) || mb_strlen( $content['title'] ) > 500 ) { throw new InvalidArgumentException( 'invalid_record' ); }
  foreach ( array( 'sourceUrl', 'affiliateUrl' ) as $field ) { if ( ! self::https_url( $content[ $field ] ?? null ) ) { throw new InvalidArgumentException( 'invalid_record' ); } }
  foreach ( array( 'description' => 100000, 'brand' => 200, 'seller' => 200 ) as $field => $limit ) {
   if ( array_key_exists( $field, $content ) && ( ! is_string( $content[ $field ] ) || mb_strlen( $content[ $field ] ) > $limit ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
  }
  if ( array_key_exists( 'images', $content ) ) {
   if ( ! is_array( $content['images'] ) || count( $content['images'] ) > 24 ) { throw new InvalidArgumentException( 'invalid_record' ); }
   foreach ( $content['images'] as $url ) { if ( ! self::https_url( $url ) ) { throw new InvalidArgumentException( 'invalid_record' ); } }
  }
  if ( array_key_exists( 'specifications', $content ) ) {
   if ( ! is_array( $content['specifications'] ) || count( $content['specifications'] ) > 200 ) { throw new InvalidArgumentException( 'invalid_record' ); }
   foreach ( $content['specifications'] as $spec ) {
    if ( ! is_array( $spec ) || array_diff( array_keys( $spec ), array( 'label', 'value' ) ) || ! is_string( $spec['label'] ?? null ) || '' === $spec['label'] || mb_strlen( $spec['label'] ) > 200 || ! is_string( $spec['value'] ?? null ) || mb_strlen( $spec['value'] ) > 2000 ) { throw new InvalidArgumentException( 'invalid_record' ); }
   }
  }
  $observations = $record['observations'] ?? null;
  if ( ! is_array( $observations ) || array_diff( array_keys( $observations ), array( 'price', 'availability', 'rating' ) ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
  foreach ( $observations as $field => $observation ) {
   if ( ! is_array( $observation ) || ! self::timestamp( $observation['observedAt'] ?? null ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
   if ( 'price' === $field ) {
    if ( array_diff( array_keys( $observation ), array( 'current', 'previous', 'currency', 'observedAt' ) ) || ! self::decimal( $observation['current'] ?? null ) || (float) $observation['current'] <= 0 || ! preg_match( '/^[A-Z]{3}$/D', (string) ( $observation['currency'] ?? '' ) ) || ! array_key_exists( 'previous', $observation ) || ( null !== $observation['previous'] && ( ! self::decimal( $observation['previous'] ) || self::compare_decimals( $observation['previous'], $observation['current'] ) <= 0 ) ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
   } elseif ( 'availability' === $field ) {
    if ( array_diff( array_keys( $observation ), array( 'status', 'observedAt' ) ) || ! in_array( $observation['status'] ?? '', array( 'in_stock', 'out_of_stock', 'unknown' ), true ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
   } elseif ( array_diff( array_keys( $observation ), array( 'value', 'scale', 'count', 'provider', 'sourceUrl', 'observedAt' ) ) || ! self::decimal( $observation['value'] ?? null ) || ! self::decimal( $observation['scale'] ?? null ) || (float) $observation['scale'] <= 0 || (float) $observation['value'] > (float) $observation['scale'] || ! is_int( $observation['count'] ?? null ) || ( $observation['count'] < 0 || $observation['count'] > 9007199254740991 ) || ! is_string( $observation['provider'] ?? null ) || ( '' === $observation['provider'] || mb_strlen( $observation['provider'] ) > 200 ) || ! self::https_url( $observation['sourceUrl'] ?? null ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
  }
  $record['identity'] = $identity;
  return $record;
 }

 public static function validate_snapshot( array $snapshot, string $organization_slug ): array {
  if ( array_diff( array_keys( $snapshot ), array( 'version', 'organization', 'snapshot', 'items', 'withdrawals', 'checksum' ) ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
  foreach ( array( 'organization' => array( 'id', 'slug' ), 'snapshot' => array( 'id', 'revision', 'generatedAt' ) ) as $key => $fields ) {
   if ( ! is_array( $snapshot[ $key ] ?? null ) || array_diff( array_keys( $snapshot[ $key ] ), $fields ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
   foreach ( array( 'id', 'organization' === $key ? 'slug' : 'id' ) as $field ) { if ( ! is_string( $snapshot[ $key ][ $field ] ?? null ) || '' === trim( $snapshot[ $key ][ $field ] ) || mb_strlen( $snapshot[ $key ][ $field ] ) > 256 ) { throw new InvalidArgumentException( 'invalid_record' ); } }
  }
  if ( 'v2' !== ( $snapshot['version'] ?? '' ) || $organization_slug !== ( $snapshot['organization']['slug'] ?? '' ) || ! is_array( $snapshot['items'] ?? null ) || ! is_array( $snapshot['withdrawals'] ?? null ) || count( $snapshot['items'] ) > 50000 || count( $snapshot['withdrawals'] ) > 50000 || ! self::timestamp( $snapshot['snapshot']['generatedAt'] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/D', (string) ( $snapshot['snapshot']['revision'] ?? '' ) ) || empty( $snapshot['snapshot']['id'] ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
  $seen = array();
  foreach ( $snapshot['items'] as &$item ) {
   if ( ! is_array( $item ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
   $item = self::validate_record( $item );
   if ( isset( $seen[ $item['sourceKey'] ] ) ) { throw new InvalidArgumentException( 'identity_collision' ); }
   $seen[ $item['sourceKey'] ] = true;
  }
  unset( $item );
  foreach ( $snapshot['withdrawals'] as $withdrawal ) {
   if ( ! is_array( $withdrawal ) || array_diff( array_keys( $withdrawal ), array( 'sourceKey', 'revision' ) ) || ( $withdrawal['revision'] ?? 0 ) > 2147483647 ) { throw new InvalidArgumentException( 'invalid_record' ); }
   if ( ! is_array( $withdrawal ) || ! preg_match( '/^src:v2:[a-f0-9]{64}$/D', (string) ( $withdrawal['sourceKey'] ?? '' ) ) || ! is_int( $withdrawal['revision'] ?? null ) || $withdrawal['revision'] < 1 || isset( $seen[ $withdrawal['sourceKey'] ] ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
   $seen[ $withdrawal['sourceKey'] ] = true;
  }
  return $snapshot;
 }

 public static function decimal( $value ): bool { return is_string( $value ) && 1 === preg_match( '/^(?:0|[1-9]\d{0,15})(?:\.\d{1,6})?$/D', $value ); }
 public static function compare_decimals( string $left, string $right ): int {
  $a = explode( '.', $left, 2 ); $b = explode( '.', $right, 2 );
  if ( strlen( $a[0] ) !== strlen( $b[0] ) ) { return strlen( $a[0] ) <=> strlen( $b[0] ); }
  return strcmp( $a[0] . str_pad( $a[1] ?? '', 6, '0' ), $b[0] . str_pad( $b[1] ?? '', 6, '0' ) );
 }
 public static function timestamp( $value ): bool { return is_string( $value ) && 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})T([01]\d|2[0-3]):([0-5]\d):([0-5]\d)(?:\.\d+)?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D', $value, $parts ) && checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) && false !== strtotime( $value ); }
 public static function https_url( $value ): bool { return is_string( $value ) && strlen( $value ) <= 2048 && 'https' === parse_url( $value, PHP_URL_SCHEME ) && Chidemoon_Core_Affiliate::is_allowed_affiliate_url( $value ) && ! parse_url( $value, PHP_URL_USER ) && ! parse_url( $value, PHP_URL_PASS ); }
}
