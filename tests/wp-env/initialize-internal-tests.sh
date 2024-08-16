#!/bin/bash

#PLUGIN_SLUG="bh-wc-checkout-rate-limiter";
PLUGIN_SLUG=$1;

# Print the script name.
echo "Running " $(basename "$0") " for " $PLUGIN_SLUG;

#echo "Installing latest build of $PLUGIN_SLUG"
#wp plugin install ./setup/$PLUGIN_SLUG.latest.zip --activate --force