<?php

namespace Kalahamoon\Tests\Syntax;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs `php -l` on every PHP file in the plugin.
 *
 * A syntax error anywhere is a hard failure — no WordPress needed.
 */
class SyntaxTest extends TestCase {

	/** @return array<string, array{0: string}> */
	public static function phpFileProvider(): array {
		$plugin_dir = dirname( __DIR__, 2 );
		$iterator   = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $plugin_dir, \FilesystemIterator::SKIP_DOTS )
		);

		$cases = array();
		/** @var \SplFileInfo $file */
		foreach ( $iterator as $file ) {
			if ( $file->getExtension() !== 'php' ) {
				continue;
			}
			$real = $file->getRealPath();
			// Skip vendor and the test files themselves
			if (
				strpos( $real, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR ) !== false ||
				strpos( $real, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR ) !== false
			) {
				continue;
			}
			$relative        = str_replace( $plugin_dir . DIRECTORY_SEPARATOR, '', $real );
			$cases[ $relative ] = array( $real );
		}
		ksort( $cases );
		return $cases;
	}

	#[DataProvider('phpFileProvider')]
	public function test_php_file_has_no_syntax_errors( string $path ): void {
		$php = PHP_BINARY;
		$cmd = escapeshellarg( $php ) . ' -l ' . escapeshellarg( $path ) . ' 2>&1';

		exec( $cmd, $output, $code );

		$this->assertSame(
			0,
			$code,
			"PHP syntax error in $path:\n" . implode( "\n", $output )
		);
	}
}
