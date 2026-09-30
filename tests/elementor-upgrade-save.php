<?php
/**
 * Exercise migration save failures in a disposable native WordPress/Elementor site.
 * Run: wp --user=<admin> eval-file /path/to/tests/elementor-upgrade-save.php
 * The injected document simulates rejected, partial and ineffective Elementor saves.
 */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }
if ( ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
	WP_CLI::error( 'These acceptance tests require a disposable local or development site.' );
}
if ( ! current_user_can( 'edit_theme_options' ) ) { WP_CLI::error( 'Run as an administrator using --user=<id>.' ); }
$args = array();
require_once __DIR__ . '/../tools/elementor-editability-upgrade.php';

final class Chidemoon_Upgrade_Save_Failure_Document {
	public int $calls = 0;
	public function __construct( private int $id, private string $mode ) {}
	public function save( array $input ): mixed {
		++$this->calls;
		if ( 'success' === $this->mode ) {
			update_post_meta( $this->id, '_elementor_data', wp_slash( wp_json_encode( $input['elements'] ) ) );
			if ( isset( $input['settings'] ) ) { update_post_meta( $this->id, '_elementor_page_settings', wp_slash( $input['settings'] ) ); }
			return null; // Native Loop::save() is void; successful persistence is authoritative.
		}
		wp_update_post( array( 'ID' => $this->id, 'post_content' => 'Partial save content', 'post_excerpt' => 'Partial excerpt', 'post_title' => 'Partial title', 'post_status' => 'publish' ) );
		update_post_meta( $this->id, '_elementor_version', 'partial-version' );
		delete_post_meta( $this->id, '_elementor_css' );
		delete_post_meta( $this->id, '_elementor_present_empty_setting' );
		update_post_meta( $this->id, '_elementor_new_partial_cache', 'discard me' );
		update_post_meta( $this->id, '_elementor_existing_plugin_state', 'partial plugin state' );
		update_post_meta( $this->id, '_elementor_page_settings', array( 'source' => 'wrong-source' ) );
		update_post_meta( $this->id, '_wp_page_template', 'wrong-template' );
		update_post_meta( $this->id, '_chidemoon_native_editability', 'wrong-marker' );
		if ( 'ineffective' !== $this->mode ) {
			$stored = $input['elements'];
			if ( 'partial' === $this->mode ) { unset( $stored[0]['elements'][0]['settings']['title'] ); }
			if ( 'dropped-child' === $this->mode ) { $stored[0]['elements'] = array(); }
			if ( 'dropped-repeater' === $this->mode ) { array_pop( $stored[0]['elements'][0]['settings']['editor_items'] ); }
			update_post_meta( $this->id, '_elementor_data', wp_slash( wp_json_encode( $stored ) ) );
		}
		if ( 'exception' === $this->mode ) { throw new RuntimeException( 'Injected native save failure.' ); }
		return 'false' === $this->mode ? false : true;
	}
}

final class Chidemoon_Upgrade_Save_Acceptance {
	private array $posts = array();
	private int $checks = 0;
	private const BACKUP = '_chidemoon_pre_editability_20260930';
	private function check( bool $value, string $message ): void { ++$this->checks; if ( ! $value ) { throw new RuntimeException( $message ); } }
	private function invoke( object $upgrade, string $method, array $arguments ): mixed { return ( new ReflectionMethod( $upgrade, $method ) )->invokeArgs( $upgrade, $arguments ); }
	private function elements( string $title ): array {
		return array( array( 'id' => 'savebox1', 'elType' => 'container', 'settings' => array( 'flex_direction' => 'column' ), 'elements' => array( array( 'id' => 'savehead', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => $title, 'header_size' => 'h2', 'editor_items' => array( array( '_id' => 'editor1', 'title' => 'اول' ), array( '_id' => 'editor2', 'title' => 'دوم' ) ) ), 'elements' => array() ) ) ) );
	}
	private function fixture( bool $with_native_data ): int {
		$id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'عنوان سردبیر', 'post_excerpt' => 'خلاصهٔ سردبیر: C:\\draft', 'post_content' => '<p>متن سردبیر: C:\\editor\\draft و «نقل قول».</p>' ) ), true );
		if ( is_wp_error( $id ) || ! $id ) { throw new RuntimeException( 'Could not create a disposable fixture.' ); }
		$this->posts[] = (int) $id;
		update_post_meta( $id, '_elementor_css', wp_slash( array( 'time' => 123, 'css' => '/* C:\\editor */' ) ) );
		update_post_meta( $id, '_elementor_existing_plugin_state', wp_slash( array( 'path' => 'C:\\editor\\choice', 'title' => 'نوشتهٔ فعلی' ) ) );
		update_post_meta( $id, '_elementor_present_empty_setting', '' );
		update_post_meta( $id, '_elementor_page_settings', array( 'hide_title' => 'yes', 'background_color' => '#edf7ed' ) );
		update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
		if ( $with_native_data ) {
			update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $this->elements( 'عنوان فعلی C:\\editor' ) ) ) );
			update_post_meta( $id, '_elementor_edit_mode', 'builder' );
			update_post_meta( $id, '_chidemoon_native_editability', 'earlier-marker' );
		}
		return (int) $id;
	}
	private function expect_failure( object $upgrade, int $id, array $elements, string $mode, array $settings = array() ): void {
		$before = $this->invoke( $upgrade, 'snapshot', array( $id ) );
		$document = new Chidemoon_Upgrade_Save_Failure_Document( $id, $mode );
		$failure = null;
		try { $this->invoke( $upgrade, 'save', array( $id, $elements, array( 'injected failure' ), $settings, $document, true ) ); }
		catch ( Throwable $error ) { $failure = $error; }
		$this->check( null !== $failure && 1 === $document->calls, 'Failure was not detected for ' . $mode );
		$after = $this->invoke( $upgrade, 'snapshot', array( $id ) );
		if ( $before !== $after ) { WP_CLI::log( 'Rollback error: ' . ( $failure?->getMessage() ?? 'No failure detected.' ) ); }
		$this->check( $before === $after, 'Rollback did not restore exact immediate pre-save fields/meta for ' . $mode );
		$this->check( ! metadata_exists( 'post', $id, '_elementor_new_partial_cache' ), 'New partial metadata survived rollback for ' . $mode );
		$this->check( metadata_exists( 'post', $id, self::BACKUP ), 'Recovery backup missing after ' . $mode );
	}
	public function run(): void {
		$upgrade = new Chidemoon_Elementor_Editability_Upgrade( true );
		$planned = $this->elements( 'عنوان برنامه‌ریزی‌شده C:\\planned' );
		foreach ( array( 'false', 'exception', 'ineffective', 'partial', 'dropped-child', 'dropped-repeater', 'wrong-settings' ) as $mode ) {
			$this->expect_failure( $upgrade, $this->fixture( true ), $planned, $mode, 'wrong-settings' === $mode ? array( 'source' => 'post' ) : array() );
		}
		$this->expect_failure( $upgrade, $this->fixture( false ), $planned, 'exception' );
		foreach ( array( 'elementor_canvas', 'editor-plugin-dynamic-layout', '' ) as $template ) {
			$id = $this->fixture( true );
			update_post_meta( $id, '_wp_page_template', $template );
			$this->expect_failure( $upgrade, $id, $planned, 'partial' );
			$this->check( $template === get_post_meta( $id, '_wp_page_template', true ) && metadata_exists( 'post', $id, '_elementor_present_empty_setting' ), 'Original dynamic/obsolete/empty page template or present empty Elementor metadata was lost.' );
		}

		// A later save must roll back to the editor's latest state, while keeping the first backup.
		$id = $this->fixture( true );
		$first_snapshot = $this->invoke( $upgrade, 'snapshot', array( $id ) );
		$this->invoke( $upgrade, 'save', array( $id, $planned, array( 'first safe save' ), array(), new Chidemoon_Upgrade_Save_Failure_Document( $id, 'success' ), true ) );
		$this->check( $first_snapshot === get_post_meta( $id, self::BACKUP, true ), 'Successful void save lost the first exact recovery snapshot.' );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $this->elements( 'ویرایش تازهٔ سردبیر' ) ) ) );
		update_post_meta( $id, '_elementor_page_settings', array( 'background_color' => '#fff7ed' ) );
		wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => '<p>ویرایش تازه: C:\\new\\editor</p>' ) ) );
		$this->expect_failure( $upgrade, $id, $planned, 'partial' );
		$this->check( $first_snapshot === get_post_meta( $id, self::BACKUP, true ) && 1 === count( get_post_meta( $id, self::BACKUP, false ) ), 'Later failed saves changed or duplicated the first recovery backup.' );

		// A concurrent editor change must reject the plan before creating backups or invoking save.
		$id = $this->fixture( true );
		$before = get_post_meta( $id );
		$document = new Chidemoon_Upgrade_Save_Failure_Document( $id, 'success' );
		$failed = false;
		try { $this->invoke( $upgrade, 'save', array( $id, $planned, array( 'stale plan' ), array(), $document, true, 'stale-native-json' ) ); }
		catch ( Throwable $error ) { $failed = str_contains( $error->getMessage(), 'changed after planning' ); }
		$this->check( $failed && 0 === $document->calls && $before === get_post_meta( $id ), 'Stale plans must fail before all writes and native saves.' );
		$failed = false;
		try { $this->invoke( $upgrade, 'save', array( $id, array(), array( 'empty plan' ), array(), $document, true ) ); }
		catch ( Throwable $error ) { $failed = str_contains( $error->getMessage(), 'empty canvas' ); }
		$this->check( $failed && 0 === $document->calls && $before === get_post_meta( $id ), 'Empty plans must fail before all writes and native saves.' );

		// A field update may throw before WordPress writes; metadata recovery must
		// still complete, and the error must identify the remaining field damage.
		$id = $this->fixture( true );
		$snapshot = $this->invoke( $upgrade, 'snapshot', array( $id ) );
		wp_update_post( array( 'ID' => $id, 'post_title' => 'Unrestored partial title', 'post_content' => 'Unrestored partial content' ) );
		delete_post_meta( $id, '_elementor_css' );
		update_post_meta( $id, '_elementor_new_partial_cache', 'discard me' );
		update_post_meta( $id, '_elementor_data', 'broken-native-json' );
		$reject_restore = static function ( array $data, array $postarr ) use ( $id ): array {
			if ( $id === (int) ( $postarr['ID'] ?? 0 ) ) { throw new RuntimeException( 'Injected WordPress field-update failure.' ); }
			return $data;
		};
		add_filter( 'wp_insert_post_data', $reject_restore, 10, 2 );
		$failure = null;
		try { $this->invoke( $upgrade, 'restore_snapshot', array( $id, $snapshot ) ); }
		catch ( Throwable $error ) { $failure = $error; }
		finally { remove_filter( 'wp_insert_post_data', $reject_restore, 10 ); }
		$after = $this->invoke( $upgrade, 'snapshot', array( $id ) );
		$this->check( $snapshot['meta'] === $after['meta'], 'Field-update failure prevented exact Elementor metadata recovery.' );
		$this->check( $failure && str_contains( $failure->getMessage(), 'Injected WordPress field-update failure.' ) && str_contains( $failure->getMessage(), 'Post field post_content' ) && str_contains( $failure->getMessage(), 'Post field post_title' ), 'Rollback must accumulate its concrete field-update error and each remaining field mismatch.' );
		WP_CLI::success( 'Elementor save rollback acceptance: ' . $this->checks . ' checks passed.' );
	}
	public function cleanup(): void { foreach ( $this->posts as $id ) { wp_delete_post( $id, true ); } }
}

$acceptance = new Chidemoon_Upgrade_Save_Acceptance();
$failure = null;
try { $acceptance->run(); } catch ( Throwable $error ) { $failure = $error; } finally { $acceptance->cleanup(); }
if ( $failure ) { WP_CLI::error( $failure->getMessage() ); }
