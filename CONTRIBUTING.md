# Contributing

Requires Composer, NPM, Docker.

## Quickstart

Install dependencies and run tests:
```bash
composer install
codecept run unit
npm install
chmod +x tests/_wp-env/initialize-external.sh
chmod +x tests/_wp-env/initialize-internal.sh
chmod +x tests/_wp-env/initialize-internal-tests.sh
npx wp-env start
codecept run wpunit
npx playwright install
npx playwright test
```

## Automated Tests

Open the Playwright UI for better visibility when running E2E tests:
```bash
BASEURL=http://localhost:8888 npx playwright test --ui &;
```

Run WP CLI tests:
```bash
WP_CLI_TEST_DBTYPE=sqlite WP_CLI_PHP=$(which php) behat

XDEBUG_MODE=coverage WP_CLI_TEST_DBTYPE=sqlite vendor/bin/behat --profile=coverage
```

## Helpful commands

### wp-env

Start with xdebug:
```bash
npx wp-env start --xdebug
```

Start a bash session in the wp-env container:
```bash
npx wp-env run cli bash
```

Show the location of the container's files:
```bash
# Previously: `npx wp-env install-path`.
npx wp-env status --json | jq -r .installPath
```

Remove the wp-env Docker container and its files:
```bash
npx wp-env destroy
```

Start wp-env Docker container with verbose output:
```bash
npx wp-env start --debug
```


## Experiment

Deleting an `attachment` post via the REST API _does_ delete its file from the `wp-content/uploads` directory. 
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

## Troubleshooting

### Mappings Symlinks appear as Empty Directories

* Restart Docker
* Restart your computer
* In Docker / Settings / Virtual Machine Options, set "file sharing implementation" to _osxfs (Legacy)_
