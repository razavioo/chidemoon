<?php
/** Per-purpose connections, with authenticated encryption for saved credentials. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Chidemoon_AI_Connections {
 const OPTION = 'chidemoon_ai_connections';
 const PURPOSES = array( 'text' => 'ساخت متن', 'image' => 'ساخت تصویر', 'image_edit' => 'ویرایش تصویر', 'vision' => 'تحلیل تصویر', 'moderation' => 'بررسی محتوا' );
 public static function saved(): array { $v = get_option( self::OPTION, array() ); return is_array( $v ) ? $v : array(); }
 private static function key(): string { return hash( 'sha256', wp_salt( 'auth' ), true ); }
 public static function encrypt( string $value ): string {
  $iv = random_bytes( 12 ); $tag = '';
  $encrypted = openssl_encrypt( $value, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
  if ( false === $encrypted ) { throw new RuntimeException( 'Credential encryption failed.' ); }
  return base64_encode( $iv . $tag . $encrypted );
 }
 public static function decrypt( string $value ): string {
  $raw = base64_decode( $value, true );
  if ( false === $raw || strlen( $raw ) < 29 ) { return ''; }
  $plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
  return false === $plain ? '' : $plain;
 }
 public static function resolve( string $purpose ): array|WP_Error {
  if ( ! isset( self::PURPOSES[ $purpose ] ) ) { return new WP_Error( 'invalid_purpose', 'نوع اتصال معتبر نیست.' ); }
  $saved = self::saved(); $row = $saved[ $purpose ] ?? array();
  if ( ! empty( $row['enabled'] ) ) {
   $config = array( 'base_url' => $row['base_url'] ?? '', 'api_key' => self::decrypt( $row['credential'] ?? '' ), 'model' => $row['model'] ?? '' );
  } elseif ( 'image_edit' === $purpose ) {
   return self::resolve( 'image' );
  } elseif ( 'vision' === $purpose && ! empty( $saved['text']['enabled'] ) ) {
   return self::resolve( 'text' );
  } else {
   $model = 'moderation' === $purpose ? trim( (string) getenv( 'CHIDEMOON_AI_MODERATION_MODEL' ) ) : Chidemoon_AI_Settings::get_string( $purpose . '_model' );
   if ( 'vision' === $purpose && '' === $model ) { $model = Chidemoon_AI_Settings::effective_vision_model(); }
   $config = array( 'base_url' => trim( (string) getenv( 'CHIDEMOON_AI_PROVIDER_BASE_URL' ) ), 'api_key' => trim( (string) getenv( 'CHIDEMOON_AI_API_KEY' ) ), 'model' => $model );
  }
  if ( '' === $config['api_key'] || '' === $config['model'] || ! Chidemoon_AI_Provider_Factory::is_safe_provider_url( $config['base_url'] ) ) { return new WP_Error( 'connection_incomplete', 'نشانی، مدل یا کلید اتصال کامل و معتبر نیست. تنظیمات اتصال را بررسی کنید.' ); }
  $config['base_url'] = rtrim( $config['base_url'], '/' );
  return $config;
 }
 public static function save( array $input ): true|WP_Error {
  if ( ! current_user_can( Chidemoon_AI_Capabilities::MANAGE ) ) { return new WP_Error( 'forbidden', 'اجازه تغییر اتصال‌ها را ندارید.' ); }
  $saved = self::saved(); $next = array();
  foreach ( self::PURPOSES as $purpose => $label ) {
   $row = is_array( $input[ $purpose ] ?? null ) ? $input[ $purpose ] : array();
   $old = $saved[ $purpose ] ?? array();
   $enabled = ! empty( $row['enabled'] );
   $base = is_string( $row['base_url'] ?? null ) ? trim( $row['base_url'] ) : '';
   $model = is_string( $row['model'] ?? null ) ? trim( $row['model'] ) : '';
   $key = is_string( $row['api_key'] ?? null ) ? trim( $row['api_key'] ) : '';
   $credential = ! empty( $row['clear_key'] ) ? '' : ( $old['credential'] ?? '' );
   if ( '' !== $key ) {
    if ( strlen( $key ) > 4096 || preg_match( '/[\x00-\x1f\x7f]/', $key ) ) { return new WP_Error( 'invalid_key', $label . ': کلید معتبر نیست.' ); }
    $credential = self::encrypt( $key );
   }
   if ( $enabled && ( strlen( $base ) > 2048 || ! Chidemoon_AI_Provider_Factory::is_safe_provider_url( $base ) || wp_parse_url( $base, PHP_URL_QUERY ) || wp_parse_url( $base, PHP_URL_FRAGMENT ) || ! preg_match( '/^[a-zA-Z0-9][a-zA-Z0-9._:\/-]{0,127}$/D', $model ) || '' === self::decrypt( $credential ) ) ) { return new WP_Error( 'invalid_connection', $label . ': نشانی HTTPS عمومی، مدل و کلید معتبر را وارد کنید.' ); }
   $next[ $purpose ] = array( 'enabled' => $enabled, 'base_url' => $base, 'model' => $model, 'credential' => $credential );
  }
  update_option( self::OPTION, $next, false );
  return true;
 }
 public static function test( string $purpose ): true|WP_Error {
  $config = self::resolve( $purpose );
  if ( is_wp_error( $config ) ) { return $config; }
  $gemini = str_starts_with( $config['model'], 'gemini-' );
  $base = $gemini ? preg_replace( '~/v1(?:beta)?$~', '', $config['base_url'] ) . '/v1beta' : $config['base_url'];
  $response = wp_safe_remote_get( $base . '/models/' . rawurlencode( $config['model'] ), array( 'timeout' => 15, 'redirection' => 0, 'limit_response_size' => 65536, 'headers' => array( 'Authorization' => 'Bearer ' . $config['api_key'] ) ) );
  if ( is_wp_error( $response ) ) { return new WP_Error( 'connection_failed', 'اتصال برقرار نشد؛ نشانی سرویس و دسترسی شبکه را بررسی کنید.' ); }
  $status = (int) wp_remote_retrieve_response_code( $response );
  if ( $status < 200 || $status >= 300 ) { return new WP_Error( 'connection_rejected', 'سرویس بررسی مدل را نپذیرفت (HTTP ' . $status . '). کلید، مدل و Base URL را بررسی کنید. بعضی واسطه‌ها این مسیر را پشتیبانی نمی‌کنند.' ); }
  $data = json_decode( wp_remote_retrieve_body( $response ), true );
  if ( ! is_array( $data ) || ( empty( $data['id'] ) && empty( $data['name'] ) ) ) { return new WP_Error( 'connection_invalid', 'پاسخ سرویس، اطلاعات معتبر مدل نبود.' ); }
  return true;
 }
 public static function render(): void {
  $notice = null;
  if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['chidemoon_connections_action'] ) ) {
   check_admin_referer( 'chidemoon_connections' );
   $input = isset( $_POST['connections'] ) && is_array( $_POST['connections'] ) ? wp_unslash( $_POST['connections'] ) : array();
   if ( isset( $_POST['test_purpose'] ) && is_string( $_POST['test_purpose'] ) ) { $notice = self::test( sanitize_key( $_POST['test_purpose'] ) ); }
   else { $notice = self::save( $input ); }
  }
  if ( null !== $notice ) { echo '<div role="status" class="notice ' . ( is_wp_error( $notice ) ? 'notice-error' : 'notice-success' ) . '"><p>' . esc_html( is_wp_error( $notice ) ? $notice->get_error_message() : ( isset( $_POST['test_purpose'] ) ? 'اتصال و دسترسی به مدل تأیید شد. تولید واقعی باید جداگانه در استودیو آزمایش شود.' : 'اتصال‌ها ذخیره شدند.' ) ) . '</p></div>'; }
  echo '<h2>اتصال سرویس‌های هوش مصنوعی</h2><p>برای هر کاربرد، سرویس سازگار با OpenAI یا Gemini انتخاب کنید. اتصال شخصی فعال بر تنظیمات سرور اولویت دارد. کلیدها با کلیدهای امنیتی همین نصب رمزگذاری می‌شوند؛ پس از تغییر salt وردپرس باید کلیدها دوباره وارد شوند. با غیرفعال کردن آن به تنظیمات سرور برمی‌گردید. تحلیل تصویر بدون اتصال شخصی، از اتصال شخصی متن استفاده می‌کند. ویرایش بدون اتصال شخصی از اتصال ساخت تصویر استفاده می‌کند. برای GPT Image 2.6، Flare برای ساخت سریع و Sunburst برای ویرایش دقیق پیشنهاد می‌شود.</p>';
  echo '<form method="post">'; wp_nonce_field( 'chidemoon_connections' );
  echo '<input type="hidden" name="chidemoon_connections_action" value="save">';
  $saved = self::saved();
  foreach ( self::PURPOSES as $purpose => $label ) {
   $row = $saved[ $purpose ] ?? array(); $prefix = 'connections[' . $purpose . ']';
   $effective = self::resolve( $purpose );
   echo '<fieldset><legend><h3>' . esc_html( $label ) . '</h3></legend>';
   echo '<p>' . esc_html( is_wp_error( $effective ) ? 'اتصال آماده نیست: ' . $effective->get_error_message() : 'تنظیم‌شده: ' . wp_parse_url( $effective['base_url'], PHP_URL_HOST ) . ' / ' . $effective['model'] ) . '</p>';
   echo '<p><label><input type="checkbox" name="' . esc_attr( $prefix . '[enabled]' ) . '" value="1" ' . checked( ! empty( $row['enabled'] ), true, false ) . '> استفاده از اتصال شخصی</label></p>';
   foreach ( array( 'base_url' => 'Base URL', 'model' => 'نام مدل', 'api_key' => 'کلید API' ) as $field => $name ) {
    $id = 'connection-' . $purpose . '-' . $field;
    echo '<p><label for="' . esc_attr( $id ) . '">' . esc_html( $name ) . '</label><br><input class="regular-text" dir="ltr" id="' . esc_attr( $id ) . '" name="' . esc_attr( $prefix . '[' . $field . ']' ) . '" type="' . ( 'api_key' === $field ? 'password' : ( 'base_url' === $field ? 'url' : 'text' ) ) . '" autocomplete="off" value="' . esc_attr( 'api_key' === $field ? '' : ( $row[ $field ] ?? '' ) ) . '"></p>';
   }
   echo '<p class="description">کلید ذخیره‌شده نمایش داده نمی‌شود؛ خالی گذاشتن فیلد آن را حفظ می‌کند. ' . ( ! empty( $row['credential'] ) ? 'کلید شخصی ثبت شده است.' : 'کلید شخصی ثبت نشده است.' ) . '</p>';
   echo '<p><button class="button" type="submit" name="test_purpose" value="' . esc_attr( $purpose ) . '">تست اتصال ذخیره‌شده</button></p>';
   echo '<p><label><input type="checkbox" name="' . esc_attr( $prefix . '[clear_key]' ) . '" value="1"> حذف کلید شخصی</label></p></fieldset>';
  }
  echo '<p class="description">تست اتصال، تنظیمات ذخیره‌شده را بررسی می‌کند؛ تغییرها را ابتدا ذخیره کنید. این تست درخواست تولید نمی‌فرستد. Base URL را با پیشوند API، مانند https://provider.example/v1 وارد کنید. پشتیبانی واقعی مدل از متن ساختاریافته، تصویر و تحلیل تصویر به سرویس انتخابی بستگی دارد.</p>';
  submit_button( 'ذخیره اتصال‌ها' ); echo '</form>';
 }
}

/** Routes each operation to the selected purpose without sharing credentials. */
final class Chidemoon_AI_Routed_Provider implements Chidemoon_AI_Provider_Interface {
 private string $image_purpose = 'image';
 private function provider( string $purpose ): Chidemoon_AI_Provider_Interface|WP_Error {
  $config = Chidemoon_AI_Connections::resolve( $purpose );
  if ( is_wp_error( $config ) ) { return $config; }
  return new Chidemoon_AI_OpenAI_Compatible_Provider( $config['base_url'], $config['api_key'], $config['model'], $config['model'], Chidemoon_AI_Settings::get_int( 'provider_timeout' ), array( 'vision_model' => $config['model'], 'image_size' => Chidemoon_AI_Settings::get_string( 'image_size' ), 'image_quality' => Chidemoon_AI_Settings::get_string( 'image_quality' ), 'search_mode' => Chidemoon_AI_Settings::get_string( 'search_mode' ) ) );
 }
 private function model( string $purpose ): string { $c = Chidemoon_AI_Connections::resolve( $purpose ); return is_wp_error( $c ) ? '' : $c['model']; }
 public function name(): string { return 'purpose-connections'; }
 public function text_model(): string { return $this->model( 'text' ); }
 public function image_model(): string { return $this->model( $this->image_purpose ); }
 public function vision_model(): string { return $this->model( 'vision' ); }
 public function supports_vision(): bool { return '' !== $this->vision_model(); }
 public function generate_text( array $job, array $evidence ): array|WP_Error { $p = $this->provider( 'text' ); return is_wp_error( $p ) ? $p : $p->generate_text( $job, $evidence ); }
 public function generate_enrichment( array $job, array $evidence ): array|WP_Error { $p = $this->provider( 'text' ); return is_wp_error( $p ) ? $p : $p->generate_enrichment( $job, $evidence ); }
 public function generate_image( array $job ): array|WP_Error { $payload = $job['request_payload'] ?? array(); $edit = ! empty( $payload['source_attachment_ids'] ) || in_array( $payload['mode'] ?? '', array( 'edit', 'variation' ), true ); $this->image_purpose = $edit ? 'image_edit' : 'image'; $p = $this->provider( $this->image_purpose ); return is_wp_error( $p ) ? $p : $p->generate_image( $job ); }
 public function analyze_image( array $attachment_ids, array $products, string $prompt ): array|WP_Error { $p = $this->provider( 'vision' ); return is_wp_error( $p ) ? $p : $p->analyze_image( $attachment_ids, $products, $prompt ); }
}
