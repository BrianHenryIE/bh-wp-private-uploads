[![WordPress tested 6.9](https://img.shields.io/badge/WordPress-v6.9%20tested-0073aa.svg)](#) [![PHPCS WPCS](https://img.shields.io/badge/PHPCS-WordPress%20Coding%20Standards%20❌-8892BF.svg)](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards) [![PHPUnit ](.github/coverage.svg)](https://brianhenryie.github.io/bh-wp-private-uploads/) [![PHPStan ](https://img.shields.io/badge/PHPStan-Level%208%20❌-2a5ea7.svg)](https://github.com/szepeviktor/phpstan-wordpress)


# BH WP Private Uploads

A library to easily create a WordPress uploads subdirectory whose contents cannot be publicly downloaded. Based on [Chris Dennis](https://github.com/StarsoftAnalysis) 's brilliant [Private Uploads](wordpress.org/plugins/private-uploads/) plugin. Adds convenience functions for uploading files to the protected directory, CLI and REST API commands, and displays an admin notice if the directory is public.

### Intro

I've needed this in various plugins and libraries:

e.g.
* [BH WP Logger](https://github.com/BrianHenryIE/bh-wp-logger) needs the "logs" directory to be private
* [BH WP Mailboxes](https://github.com/BrianHenryIE/bh-wp-mailboxes) needs the "attachments" directory to be private
* BH WC Auto Print Shipping Labels & Receipts needs its PDF directory to be private

The main feature is that it regularly runs a HTTP request to confirm the directory is protected. If it's not, it displays an admin notice.

Then, it allows admins to download all files from that directory and has a filter to allow other users to access the files.

It duplicates the Media/attachment UI, and metaboxes can be added to custom post types for uploading files.

It's far from polished, but there's a lot going on that's not mentioned in this README.

NB: Expect breaking changes with every release until v1.0.0.

If you decide to use this, I'm happy to jump on a call to talk about the direction of the library and how it can be improved.

The main feature in-progress is to allow files to be tied to a specific user, and to allow broader permissions based on the parent post. Specifically to enable GDPR-compliant auto-deletion.

### Install

`composer require brianhenryie/bh-wp-private-uploads`

The logger is only a dev requirement for the test plugin.

The following code expects you're prefixing your library namespaces with a tool such as [brianhenryie/strauss](https://github.com/BrianHenryIE/strauss/).

### Instantiate

The following code will create a folder, `wp-content/uploads/my-plugin`, with a `.htaccess` protecting it (via WordPress rewrite rules), and creates a cron job to verify the URL is protected, otherwise it displays an admin notice warning the site admin.

```php
$settings = new class() implements \BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface {
	use \BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Trait;

	public function get_plugin_slug(): string {
		return 'my-plugin';
	}
};
$private_uploads = \BrianHenryIE\WP_Private_Uploads\Private_Uploads::instance( $settings );
```

The trait provides some sensible defaults based off the plugin slug, which can be easily overridden. It also allows forward-compatability, i.e. methods can be added to the settings interface and defaults provided by the trait.

### Use

That `$private_uploads` instance can be passed around, or the singleton can be accessed anywhere in the code without requiring the settings again.

```php
$private_uploads = \BrianHenryIE\WP_Private_Uploads\Private_Uploads::instance();
```

The `\BrianHenryIE\WP_Private_Uploads\API\API` class (which `Private_Uploads` extends) contains convenience functions for downloading and moving files to the private uploads folder. Their signatures resemble WordPress's internal functions, since behind the scenes they use `wp_handle_upload` and have the same return signature.

```php
// Download `https://example.org/doc.pdf` to `wp-content/uploads/my-plugin/2022/02/target-filename.pdf`.
$private_uploads->download_remote_file_to_private_uploads( 'https://example.org/doc.pdf', 'target-filename.pdf' );

// Move `'/local/path/to/doc.pdf` to `wp-content/uploads/my-plugin/2022/02/target-filename.pdf`.
$private_uploads->move_file_to_private_uploads( '/local/path/to/doc.pdf', 'target-filename.pdf' );
```

By default, administrators can access the files via their URL. This can be widened to more users with the filter:

```php
/**
 * Allow filtering for other users.
 *
 * @param bool $should_serve_file
 * @param string $file
 */
$should_serve_file = apply_filters( "bh_wp_private_uploads_{$plugin_slug}_allow", $should_serve_file, $file );
```

e.g. WooCommerce plugins probably always want shop-managers to be able to access files:

```php
add_filter( "bh_wp_private_uploads_{$plugin_slug}_allow", 'add_shop_manager_to_allow' );
function add_shop_manager_to_allow( bool $should_serve_file ): bool {
	return $should_serve_file || current_user_can( 'manage_woocommerce' );
}
```


### Advanced

#### Folder Name

The folder name can easily be changed, e.g. `wp-content/uploads/email-attachments`:

```php
$settings = new class() implements \BrianHenryIE\WP_Private_Uploads\API\Private_Uploads_Settings_Interface {
	use \BrianHenryIE\WP_Private_Uploads\API\Private_Uploads_Settings_Trait;

	public function get_plugin_slug(): string {
		return 'my-plugin';
	}
	
    /**
	 * Defaults to the plugin slug when using Private_Uploads_Settings_Trait.
	 */
	public function get_uploads_subdirectory_name(): string {
		return 'email-attachments';
	}

};
$private_uploads = \BrianHenryIE\WP_Private_Uploads\Private_Uploads::instance( $settings );
```

#### CLI Command

A CLI command is easily added during configuration:

```php
$settings = new class() implements \BrianHenryIE\WP_Private_Uploads\API\Private_Uploads_Settings_Interface {
	use \BrianHenryIE\WP_Private_Uploads\API\Private_Uploads_Settings_Trait;

	public function get_plugin_slug(): string {
		return 'my-plugin';
	}
	
	/**
	 * Defaults to no CLI commands when using Private_Uploads_Settings_Trait.
	 */
	public function get_cli_base(): ?string {
		return 'my-plugin';
	}

};
$private_uploads = \BrianHenryIE\WP_Private_Uploads\Private_Uploads::instance( $settings );
```

```
wp my-plugin download https://example.org/doc.pdf
```

#### !Singleton

There's no need to use the singleton.

```php
$private_uploads = new Private_Uploads( $private_uploads_settings, $logger );
// Add the hooks:
new BH_WP_Private_Uploads( $private_uploads, $private_uploads_settings, $logger );
```

#### Quick Test

To quickly test the URL is private with cURL:

```
curl -o /dev/null --silent --head --write-out '%{http_code}\n' http://localhost:8080/bh-wp-private-uploads/wp-content/uploads/private/private.txt
```


### Status

TODO:

1. Test API and serve private files classes
2. Focus settings on the post type, not the plugin slug (maybe rename the settings interface to reflect this)
3. Instantiate the hooks with API class as the parameter, not the Settings (i.e. avoid situation where wires could be crossed)
4. Add documentation for the media upload UI
5. Update this documentation to include post type object filter.
5. Verify all test steps in this README
6. Test with bh-wp-logger

Some amount of PHPUnit, WPCS, PhpStan done, but lots to do.

* User level permissions per file. (custom post type with filepath/url as GUID)
* Acceptance tests: https://github.com/gamajo/codeception-redirects
* Unit test REST endpoint
* Does the rewrite rule work when WordPress is installed in a subdir?
* Add Nginx instructions
* Detect the user's hosting provider
* GDPR deletion
* REST API file upload -> webhook.

#### Permissions: 

We already have registered a post type for registering the REST endpoint.
For files that need to be tied to a specific user, make them the author of the post
For broader permissions, use the parent of the post--- e.g. set the parent post to be the WooCommerce order and anyone who is allowed to view the order can view the file.
Also ties into privacy (GDPR deletion etc.)

