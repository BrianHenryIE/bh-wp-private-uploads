


WP_CLI_TEST_DBTYPE=sqlite WP_CLI_PHP=$(which php) behat

XDEBUG_MODE=coverage WP_CLI_TEST_DBTYPE=sqlite vendor/bin/behat --profile=coverage


BASEURL=http://localhost:8888 npx playwright test --ui &;                


Deleting an attachment post via the REST API _does_ delete its file from the uploads directory. 
```bash
USER_LOGIN="admin";
USER_ID=$(wp user get $USER_LOGIN --field=ID);
echo "$USER_LOGIN user_id: USER_ID";
APPLICATION_PASSWORD=$(wp user application-password create $USER_ID private-uploads-tests --porcelain);
echo "$USER_LOGIN application password: $APPLICATION_PASSWORD";

ATTACHMENT_ID="182";
curl --location --request DELETE 'localhost:8888/wp-json/wp/v2/media/$ATTACHMENT_ID?force=true' \
  --header 'Authorization: Basic $APPLICATION_PASSWORD'
```
