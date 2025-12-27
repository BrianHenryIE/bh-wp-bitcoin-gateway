<?php
/**
 * Admin UI hooks to stop the WooCommerce setup wizard.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Admin;

use Automattic\WooCommerce\Internal\Admin\Onboarding\OnboardingProfile;

/**
 * Add simple filters.
 */
class WooCommerce {

	/**
	 * Add filters.
	 */
	public function register_hooks(): void {

		/**
		 * @see \Automattic\WooCommerce\Internal\Admin\Onboarding\OnboardingSetupWizard::do_admin_redirects()
		 */
		add_filter( 'woocommerce_prevent_automatic_wizard_redirect', '__return_true' );
		add_filter( 'woocommerce_enable_setup_wizard', '__return_false' );

		/**
		 * @see OnboardingProfile::DATA_OPTION
		 * @see OnboardingProfile::needs_completion()
		 */
		add_filter( 'pre_option_woocommerce_onboarding_profile', fn() => array( 'completed' => true ) );
	}
}
