<?php
/** Evidence-backed technical details shared by product pages and comparisons. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Detailed_Specs {
 public const META = '_chidemoon_detailed_specs_v1';

 /** Keep provenance in the evidence record, outside customer-facing values. */
 public static function display_label( string $label ): string {
  return str_replace( array( 'رنگ نمونه تصویر', 'قابلیت های اعلام شده', 'قابلیت‌های اعلام‌شده', ' (مشخصات منبع)' ), array( 'رنگ', 'قابلیت‌ها', 'قابلیت‌ها', '' ), $label );
 }

 public static function display_value( string $value ): string {
  $value = preg_replace( '/\s*[؛،]?\s*(?:بر اساس تصویر|مطابق توضیح فروشگاه|مطابق توضیحات فروشگاه|مطابق عنوان محصول|مطابق متن فروشگاه)/u', '', $value );
  $value = preg_replace( '/\s*[؛،]?\s*رنگ تصویر ممکن است با گزینه سفارش فرق کند/u', '', $value );
  $value = str_replace( array( 'نمونه تصویر: ', '(پیشنهاد بصری چیدمون)', '(اندازه های اعلام شده)', '(اندازه‌های اعلام‌شده)' ), '', $value );
  return trim( preg_replace( '/^[؛،\s]+|[؛،\s]+$/u', '', $value ) );
 }

 public static function canonical_label( string $label ): string {
  $label = trim( preg_replace( '/\s+/u', ' ', str_replace( array( 'ي', 'ك', '‌', ' (مشخصات منبع)', ' اعلام‌شده فروشنده' ), array( 'ی', 'ک', ' ', '', '' ), $label ) ) );
  $aliases = array(
   'توان'=>'توان مصرفی', 'قدرت موتور'=>'توان مصرفی', 'توان دستگاه'=>'توان مصرفی',
   'فشار پمپ'=>'فشار بخار', 'فشار'=>'فشار بخار',
   'حجم مخزن'=>'ظرفیت مخزن', 'ظرفیت مخزن آب'=>'ظرفیت مخزن',
   'منبع انرژی'=>'منبع تغذیه', 'کشور تولید کننده'=>'کشور سازنده',
   'تعداد پارچه'=>'تعداد قطعات', 'تعداد تکه'=>'تعداد قطعات',
   'تعداد شانه'=>'شانه', 'رج:'=>'رج', 'وزن تقریبی'=>'وزن',
   'نحوه شست وشو'=>'نحوه شستشو', 'قابلیت شست وشو'=>'قابلیت شستشو',
   'نحوه نصب'=>'روش نصب', 'طریقه نصب'=>'روش نصب',
   'جنس نخ بافته شده'=>'جنس نخ', 'طول (cm)'=>'طول', 'عرض (cm)'=>'عرض', 'ارتفاع (cm)'=>'ارتفاع', 'عمق (cm)'=>'عمق',
  );
  return $aliases[ $label ] ?? $label;
 }

 /** Merge equivalent rows instead of creating a sparse union of spelling variants. */
 public static function comparison_facts( array $facts ): array {
  $result = array();
  foreach ( $facts as $label => $value ) {
   $key = self::canonical_label( $label );
   if ( preg_match( '/\(cm\)$/', $label ) ) { $value .= ' سانتی‌متر'; }
   if ( in_array( $key, array( 'طول', 'عرض', 'ارتفاع', 'عمق', 'قطر' ), true ) ) {
    $value = $key . ': ' . $value;
    $key = 'ابعاد';
   }
   if ( 'جنس صفحه و پایه' === $key ) { $value = 'صفحه و پایه: ' . $value; $key = 'جنس'; }
   if ( 'رنگ نمونه تصویر' === $key ) { $value = 'نمونه تصویر: ' . $value; $key = 'رنگ'; }
   if ( ! isset( $result[ $key ] ) ) { $result[ $key ] = $value; continue; }
   $normalize = static fn( $s ) => trim( preg_replace( '/\s+/u', ' ', str_replace( '‌', ' ', $s ) ) );
   if ( $normalize( $result[ $key ] ) !== $normalize( $value ) ) { $result[ $key ] .= '؛ ' . $value; }
  }
  return $result;
 }

 /** No network requests on public product/comparison pages. */
 public static function facts( WC_Product $product ): array {
  $record = $product->get_meta( self::META, true );
  $facts = array();
  foreach ( is_array( $record ) ? ( $record['specs'] ?? array() ) : array() as $label => $value ) {
   if ( is_string( $label ) && is_scalar( $value ) && '' !== trim( (string) $value ) ) {
    $facts[ self::display_label( sanitize_text_field( $label ) ) ] = self::display_value( sanitize_text_field( (string) $value ) );
   }
  }
  // Preserve manually maintained technical attributes, including future products.
  foreach ( $product->get_attributes() as $attribute ) {
   if ( $attribute->is_taxonomy() || ! $attribute->get_visible() ) { continue; }
   $label = sanitize_text_field( $attribute->get_name() );
   if ( ! isset( $facts[ $label ] ) ) {
    $facts[ $label ] = implode( '، ', array_map( 'sanitize_text_field', $attribute->get_options() ) );
   }
  }
  return array_filter( $facts, static fn( $value ) => '' !== $value );
 }

 public static function display_attributes( array $attributes, WC_Product $product ): array {
  $existing = array_column( $attributes, 'label' );
  foreach ( self::facts( $product ) as $label => $value ) {
   if ( in_array( $label, $existing, true ) ) { continue; }
   $attributes[ 'chidemoon_detail_' . md5( $label ) ] = array( 'label' => $label, 'value' => esc_html( $value ) );
  }
  return $attributes;
 }
}
