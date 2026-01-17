#!/bin/bash

#  "lifecycleScripts": {
#    "afterStart": "./tests/e2e-pw/setup/initialize-external.sh"
#  },

# Script which runs outside Docker

# Print the script name.
echo $(basename "$0")

# This presumes the current working directory is the project root and the directory name matches the plugin slug.
PLUGIN_SLUG=$(basename $PWD)
echo "Building $PLUGIN_SLUG"

# Build the plugin
echo "wp i18n make-pot includes languages/$PLUGIN_SLUG.pot --domain=$PLUGIN_SLUG"
vendor/bin/wp i18n make-pot includes languages/$PLUGIN_SLUG.pot --domain=$PLUGIN_SLUG

# TODO: delete the existing plugin – it seems to add to the .zip file rather than totally recreating it.
echo "wp dist-archive . ./tests/e2e-pw/setup --plugin-dirname=$PLUGIN_SLUG --filename-format="{name}.latest" --force"
vendor/bin/wp dist-archive . ./tests/e2e-pw/setup --plugin-dirname=$PLUGIN_SLUG --filename-format="{name}.latest" --force

# Run the internal scripts which configure the environments:
# First the script that is common to both environments:
echo "run npx wp-env run cli ../setup/initialize-internal.sh;"
npx wp-env run cli ../setup/initialize-internal.sh;
echo "run npx wp-env run tests-cli ../setup/initialize-internal.sh;"
npx wp-env run tests-cli ../setup/initialize-internal.sh;

# The the scripts individual to each:
echo "run npx wp-env run cli ../setup/initialize-internal-dev.sh;"
npx wp-env run cli /var/www/setup/initialize-internal-dev.sh;

echo "run npx wp-env run tests-cli ../setup/initialize-internal-tests.sh;"
npx wp-env run tests-cli ../setup/initialize-internal-tests.sh;
