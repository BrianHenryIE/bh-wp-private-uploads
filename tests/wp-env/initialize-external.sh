#!/bin/bash

# Script which runs outside Docker

# Print the script name.
echo $(basename "$0")

# This presumes the current working directory is the project root and the directory name matches the plugin slug.
PLUGIN_SLUG=$(basename $PWD)
echo "Building $PLUGIN_SLUG"

# Detect the operating system
OS_TYPE=$(uname)
echo "Detected OS: $OS_TYPE"

# Function to build the plugin for Unix-based systems (Linux and macOS)
build_plugin_unix() {
  # Uncomment the following lines if you need to use them
  # vendor/bin/wp i18n make-pot src languages/$PLUGIN_SLUG.pot --domain=$PLUGIN_SLUG
  # vendor/bin/wp dist-archive . ./tests/e2e-pw/setup --plugin-dirname=$PLUGIN_SLUG --filename-format="{name}.latest"
  
  # Run the internal scripts which configure the environments:
  # First the script that is common to both environments:
  echo "run npx wp-env run cli ./setup/initialize-internal.sh $PLUGIN_SLUG;"
  npx wp-env run cli ./setup/initialize-internal.sh $PLUGIN_SLUG;
  echo "run npx wp-env run tests-cli ./setup/initialize-internal.sh $PLUGIN_SLUG;"
  npx wp-env run tests-cli ./setup/initialize-internal.sh $PLUGIN_SLUG;

  # The scripts individual to each environment:
  echo "run npx wp-env run cli ./setup/initialize-internal-dev.sh $PLUGIN_SLUG;"
  npx wp-env run cli ./setup/initialize-internal-dev.sh $PLUGIN_SLUG;

  echo "run npx wp-env run tests-cli ./setup/initialize-internal-tests.sh $PLUGIN_SLUG;"
  npx wp-env run tests-cli ./setup/initialize-internal-tests.sh $PLUGIN_SLUG;
}

# Function to build the plugin for Windows
build_plugin_windows() {
  # Run the internal scripts which configure the environments:
  # First the script that is common to both environments:
  echo "run npx wp-env run cli setup/initialize-internal.sh $PLUGIN_SLUG;"
  npx wp-env run cli setup/initialize-internal.sh $PLUGIN_SLUG;
  echo "run npx wp-env run tests-cli setup/initialize-internal.sh $PLUGIN_SLUG;"
  npx wp-env run tests-cli setup/initialize-internal.sh $PLUGIN_SLUG;

  # The scripts individual to each environment:
  echo "run npx wp-env run cli setup/initialize-internal-dev.sh $PLUGIN_SLUG;"
  npx wp-env run cli setup/initialize-internal-dev.sh $PLUGIN_SLUG;

  echo "run npx wp-env run tests-cli setup/initialize-internal-tests.sh $PLUGIN_SLUG;"
  npx wp-env run tests-cli setup/initialize-internal-tests.sh $PLUGIN_SLUG;
}

# OS-specific actions
if [[ "$OS_TYPE" == "Linux" ]]; then
  echo "Running on Linux"
  build_plugin_unix
elif [[ "$OS_TYPE" == "Darwin" ]]; then
  echo "Running on macOS"
  build_plugin_unix
elif [[ "$OS_TYPE" == "MINGW"* || "$OS_TYPE" == "CYGWIN"* ]]; then
  echo "Running on Windows (Git Bash or Cygwin)"
  build_plugin_windows
else
  echo "Unsupported OS: $OS_TYPE"
  exit 1
fi
