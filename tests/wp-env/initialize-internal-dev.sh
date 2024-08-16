#!/bin/bash

#PLUGIN_SLUG="bh-wc-checkout-rate-limiter";
PLUGIN_SLUG=$1;

# Print the script name.
echo "Running " $(basename "$0") " for " $PLUGIN_SLUG;

#// Nothing to do.