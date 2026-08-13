#!/bin/bash

# Script which runs outside Docker (on the host machine).
# Called by wp-env's afterStart lifecycle hook.
#
# Pass "ci" as the first argument (see .wp-env.ci.json) to test the built zip rather than the
# working directory. .wp-env.json passes nothing and mounts the working directory as usual.

MODE=$1

# Print the script name.
echo $(basename "$0")

# This presumes the current working directory is the project root and the directory name matches the plugin slug.
PLUGIN_SLUG=$(basename $PWD)

# tests/e2e-pw/setup is mapped to /var/www/setup inside the container, one level above the webroot,
# so copying the zip here is how it is handed to initialize-internal.sh.
SETUP_DIR="$(dirname "$0")"
PROJECT_DIR="$(cd "$SETUP_DIR/../../.." && pwd)"

# `wp-env run` defaults to .wp-env.json, so the CI environment has to be named explicitly or the
# command would target a different (non-running) environment than the one that invoked this script.
WP_ENV_CONFIG_ARGS=()

# Build the plugin's translation template.
echo "wp i18n make-pot includes languages/$PLUGIN_SLUG.pot --domain=$PLUGIN_SLUG"
vendor/bin/wp i18n make-pot includes languages/$PLUGIN_SLUG.pot --domain=$PLUGIN_SLUG

if [ "$MODE" = "ci" ]; then
  WP_ENV_CONFIG_ARGS=(--config "$PROJECT_DIR/.wp-env.ci.json")

  ZIP=$(ls -Art "$PROJECT_DIR"/dist-archive/*.zip 2>/dev/null | tail -n 1)
  if [ -z "$ZIP" ]; then
      echo "wp dist-archive . $SETUP_DIR --plugin-dirname=$PLUGIN_SLUG --force"
      vendor/bin/wp dist-archive $PROJECT_DIR "$PROJECT_DIR"/dist-archive --plugin-dirname=$PLUGIN_SLUG --force --create-target-dir
      ZIP=$(ls -Art "$PROJECT_DIR"/dist-archive/*.zip 2>/dev/null | tail -n 1)
  fi

  # Keep the versioned filename so the CI logs name the exact build being tested, and clear out
  # any earlier version first so initialize-internal.sh finds exactly one candidate.
  rm -f "$SETUP_DIR/$PLUGIN_SLUG".*.zip
  echo "Copying $ZIP to $SETUP_DIR/$(basename "$ZIP")"
  cp "$ZIP" "$SETUP_DIR/$(basename "$ZIP")"
fi

if [ "$MODE" = "ci" ]; then
  echo "run npx wp-env run cli ../setup/initialize-internal-ci.sh $PLUGIN_SLUG $MODE"
  npx wp-env run "${WP_ENV_CONFIG_ARGS[@]}" cli ../setup/initialize-internal-ci.sh $PLUGIN_SLUG $MODE;
fi

# Run the internal script which configures the environment inside Docker.
# `--config` must precede the container name: `run <container> [command...]` absorbs every
# trailing argument into the command run inside the container.
echo "run npx wp-env run ${WP_ENV_CONFIG_ARGS[*]} cli ../setup/initialize-internal.sh $PLUGIN_SLUG $MODE;"
npx wp-env run "${WP_ENV_CONFIG_ARGS[@]}" cli ../setup/initialize-internal.sh $PLUGIN_SLUG $MODE;
