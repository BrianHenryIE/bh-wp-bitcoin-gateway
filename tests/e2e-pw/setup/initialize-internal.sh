#!/bin/bash

# Print the script name.
echo $(basename "$0")

# Does this need to be done first?
wp plugin activate woocommerce;

# trying to avoid "'tests-wordpress.wp_lhr_log' doesn't exist", maybe if it's activated individually...
wp plugin activate log-http-requests;

echo "wp plugin activate --all"
wp plugin activate --all

wp plugin deactivate give-next-gen --skip-plugins;
wp plugin deactivate give --skip-plugins;
wp plugin deactivate givewp-example-gateway --skip-plugins;

wp plugin deactivate decentralized-bitcoin-cryptodec-payment-gateway-for-woocommerce --skip-plugins;

wp plugin deactivate woo-nimiq-gateway --skip-plugins;


echo "Set up pretty permalinks for REST API."
wp rewrite structure /%year%/%monthnum%/%postname%/ --hard;

# Prevent WooCommerce setup wizard from running.
# It checks for the existence of the option 'woocommerce_version'.
# WC_Install::is_new_install()
wp option set woocommerce_version "10.0.4"
wp transient delete _wc_activation_redirect

# TODO: Pick a block theme that supports WooCommerce.
# wp theme install storefront --activate;



echo "Creating WooCommerce Customer Account"
wp user create customer customer@woocommercecoree2etestsuite.com \
	--user_pass=password \
	--role=subscriber \
	--first_name='Jane' \
	--last_name='Smith' \
	--path=/var/www/html || true

echo "Adding basic WooCommerce settings..."
wp option set woocommerce_store_address "Example Address Line 1"
wp option set woocommerce_store_address_2 "Example Address Line 2"
wp option set woocommerce_store_city "Example City"
wp option set woocommerce_default_country "US:CA"
wp option set woocommerce_store_postcode "94110"
wp option set woocommerce_currency "USD"
wp option set woocommerce_product_type "both"
wp option set woocommerce_allow_tracking "no"
wp rewrite structure /%postname%/

echo "Installing WooCommerce shop pages..."
wp wc tool run install_pages

echo "Installing and activating the WordPress Importer plugin..."
wp plugin install wordpress-importer --activate

echo "Importing WooCommerce sample products..."
wp option get sample_products_installed
if [ $? -ne 0 ]; then
    echo "Importing sample products..."
    wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip
    wp option add sample_products_installed 1
else
    echo "Sample products already imported."
fi

#echo "Installing basic-auth to interact with the API..."
#wp plugin install https://github.com/WP-API/basic-auth/archive/master.zip --activate --force


# initialize pretty permalinks
wp rewrite structure /%postname%/

# Not all products were being displayed, making it impossible to certainly choose one by name.
wp option update woocommerce_catalog_rows 10

# Have more than just Bitcoin active so it has to be manually selected at checkout.
wp wc payment_gateway update cheque --enabled=1

# --porcelain
#wp post create --post_type=page --post_title="Blocks Checkout" --post_status=publish ./wp-content/plugins/bh-wp-bitcoin-gateway/tests/e2e/docker/blocks-checkout-post-content.txt

wp option set woocommerce_coming_soon no

# Not doing well with PHP 8.4.
wp plugin deactivate crypto-payments-woo
wp plugin deactivate bh-wc-set-gateway-by-url