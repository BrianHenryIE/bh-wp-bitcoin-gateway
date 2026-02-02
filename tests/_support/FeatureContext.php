<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway;

use WP_CLI;

class FeatureContext extends \WP_CLI\Tests\Context\FeatureContext {

	/**
	 * @Given /^a plugin located at ([^\s]+)$/
	 *
	 * @see \AmpProject\AmpWP\Tests\Behat\FeatureContext::given_a_wp_installation_with_the_amp_plugin()
	 * @see https://github.com/ampproject/amp-wp/blob/d4200c4b26446541282aef3c3cc2acd3b93674d7/tests/php/src/Behat/FeatureContext.php#L79-L93
	 */
	public function given_a_plugin_located_at( $path ) {

		$project_dir = realpath( self::get_vendor_dir() . '/../' );

		// path could be relative, the directory, or the plugin file.
		switch ( true ) {
			case is_dir( $path ):
				$source_dir = realpath( $path );
				break;
			case is_file( $path ):
				$source_dir = realpath( dirname( $path ) );
				break;
			case is_dir( $project_dir . '/' . $path ):
				$source_dir = $project_dir . '/' . $path;
				break;
			case is_file( $project_dir . '/' . $path ):
				$source_dir = $project_dir . '/' . dirname( $path );
				break;
			default:
				WP_CLI::error( "Path not found: {$path}" );
		}

		$plugin_slug = basename( $source_dir );

		// Symlink the current project folder into the WP folder as a plugin.
		$wp_plugins_dir = $this->variables['RUN_DIR'] . '/wp-content/plugins';
		$this->proc( "ln -s {$source_dir} {$wp_plugins_dir}/{$plugin_slug}" )->run_check();

		// Activate the plugin.
		$this->proc( "wp plugin activate {$plugin_slug}" )->run_check();
	}
}
