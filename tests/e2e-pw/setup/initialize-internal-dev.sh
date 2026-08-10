#!/bin/bash

PLUGIN_SLUG=$1;
MODE=$2;

# Print the script name.
echo $(basename "$0")

SCRIPT_DIR="$(dirname "$0")"

# In CI the plugin is not mounted from the working directory; install the zip
# initialize-external.sh copied into the mapped setup directory. The filename carries the version,
# so echoing it records exactly which build the tests ran against.
if [ "$MODE" = "ci" ]; then
  PLUGIN_ZIP=$(ls -t "$SCRIPT_DIR/$PLUGIN_SLUG".*.zip 2>/dev/null | head -n 1)
  if [ -z "$PLUGIN_ZIP" ]; then
    echo "No $PLUGIN_SLUG zip found in $SCRIPT_DIR" >&2
    exit 1
  fi
  echo "Installing $PLUGIN_SLUG from $(basename "$PLUGIN_ZIP")"
  wp plugin install "$PLUGIN_ZIP" --force --activate
fi
