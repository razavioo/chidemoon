<?php
declare(strict_types=1);
define( 'ABSPATH', __DIR__ );
class WP_Error { public function __construct( public string $code, public string $message ) {} }
function is_wp_error( $v ): bool { return $v instanceof WP_Error; }
function wp_salt( $v ): string { return 'fixture-encryption-key'; }
$options = array();
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function update_option( $key, $value, $autoload ) { $GLOBALS['options'][$key] = $value; }
function current_user_can( $cap ): bool { return $GLOBALS['authorized'] ?? true; }
function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }
class Chidemoon_AI_Capabilities { const MANAGE = 'manage_options'; }
class Chidemoon_AI_Provider_Factory { public static function is_safe_provider_url( $url ): bool { return str_starts_with( $url, 'https://public.example/' ); } }
interface Chidemoon_AI_Provider_Interface {}
require __DIR__ . '/../plugins/chidemoon-ai/includes/class-chidemoon-ai-connections.php';
function check( bool $ok, string $message ): void { if ( ! $ok ) { throw new RuntimeException( $message ); } }
$profile = array( 'enabled' => '1', 'base_url' => 'https://public.example/v1', 'model' => 'text-model', 'api_key' => 'fixture-secret' );
check( true === Chidemoon_AI_Connections::save( array( 'text' => $profile ) ), 'Valid connection rejected.' );
check( ! str_contains( json_encode( $options ), 'fixture-secret' ), 'Plain credential persisted.' );
check( 'fixture-secret' === Chidemoon_AI_Connections::resolve( 'text' )['api_key'], 'Credential did not decrypt.' );
check( 'text-model' === Chidemoon_AI_Connections::resolve( 'vision' )['model'], 'Vision fallback failed.' );
$profile['api_key'] = '';
check( true === Chidemoon_AI_Connections::save( array( 'text' => $profile ) ), 'Blank key must preserve secret.' );
check( 'fixture-secret' === Chidemoon_AI_Connections::resolve( 'text' )['api_key'], 'Blank key deleted secret.' );
$prior = $options;
$profile['base_url'] = 'http://localhost/v1';
check( is_wp_error( Chidemoon_AI_Connections::save( array( 'text' => $profile ) ) ), 'Unsafe connection accepted.' );
check( $prior === $options, 'Invalid save partially changed settings.' );
$profile['base_url'] = 'https://public.example/v1'; $profile['clear_key'] = 1;
check( is_wp_error( Chidemoon_AI_Connections::save( array( 'text' => $profile ) ) ), 'Enabled connection accepted missing key.' );
$authorized = false;
check( is_wp_error( Chidemoon_AI_Connections::save( array() ) ), 'Unauthorized save accepted.' );
check( '' === Chidemoon_AI_Connections::decrypt( 'corrupt' ), 'Corrupt credential accepted.' );
echo "AI connection storage, fallback, validation and access checks passed.\n";
class Chidemoon_AI_Settings {
 public static function get_int( $key ): int { return 30; }
 public static function get_string( $key ): string { return 'medium'; }
}
class Chidemoon_AI_OpenAI_Compatible_Provider implements Chidemoon_AI_Provider_Interface {
 public function __construct( private string $base, private string $key, private string $model, ...$rest ) {}
 public function generate_text( $job, $evidence ): array { return array( $this->base, $this->key, $this->model ); }
 public function generate_image( $job ): array { return array( $this->base, $this->key, $this->model ); }
}
$authorized = true;
$profile['clear_key'] = 0; $profile['api_key'] = 'text-secret';
$image = $profile; $image['model'] = 'image-model'; $image['api_key'] = 'image-secret'; $image['base_url'] = 'https://public.example/images/v1';
check( true === Chidemoon_AI_Connections::save( array( 'text' => $profile, 'image' => $image ) ), 'Independent profiles rejected.' );
$router = new Chidemoon_AI_Routed_Provider();
check( array( 'https://public.example/v1', 'text-secret', 'text-model' ) === $router->generate_text( array(), array() ), 'Text routed to wrong credentials.' );
check( array( 'https://public.example/images/v1', 'image-secret', 'image-model' ) === $router->generate_image( array() ), 'Image routed to wrong credentials.' );
echo "Independent provider routing checks passed.\n";

$edit = $image; $edit['model'] = 'edit-model';
check( true === Chidemoon_AI_Connections::save( array( 'text' => $profile, 'image' => $image, 'image_edit' => $edit ) ), 'Edit profile rejected.' );
check( 'edit-model' === $router->generate_image( array( 'request_payload' => array( 'mode' => 'edit', 'source_attachment_ids' => array( 1 ) ) ) )[2], 'Edit model routing failed.' );
check( 'edit-model' === $router->image_model(), 'Edit provenance model incorrect.' );

check( 'edit-model' === $router->generate_image( array( 'request_payload' => array( 'mode' => 'look_scene', 'product_ids' => array( 42 ) ) ) )[2], 'Product-based Look must use reference-aware edit model.' );
