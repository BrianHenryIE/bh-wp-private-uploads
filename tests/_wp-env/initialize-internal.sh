#!/bin/bash

PLUGIN_SLUG=$1;
# Print the script name.
echo "Running " $(basename "$0") " for " $PLUGIN_SLUG;


#rm /var/www/html/wp-content/plugins/test-plugin/vendor/brianhenryie/bh-wp-plugin-updater;
#ln -s /var/www/html/wp-content/bh-wp-plugin-updater/ /var/www/html/wp-content/plugins/test-plugin/vendor/brianhenryie/bh-wp-plugin-updater;

mkdir /var/www/html/wp-content/uploads || true;
chmod a+w /var/www/html/wp-content/uploads;

echo "wp plugin activate --all"
wp plugin activate --all


# Use the Composer installed WP-CLI.
#sudo rm /usr/local/bin/wp;
#sudo ln -s /var/www/html/wp-content/plugins/example-plugin/vendor/bin/wp /usr/local/bin/wp;

# Install jq to manipulate json (optional output of WP CLI commands)
if command -v jq &> /dev/null; then
	echo "jq is already installed."
else
	echo "jq not found, installing..."
	sudo apk add jq
fi

echo "Set up pretty permalinks for REST API."
wp rewrite structure /%year%/%monthnum%/%postname%/ --hard;