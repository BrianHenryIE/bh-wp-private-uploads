[![WordPress tested 5.9](https://img.shields.io/badge/WordPress-v5.9%20tested-0073aa.svg)](https://wordpress.org/plugins/plugin_slug)

# BH WP Private Uploads

A library to easily create a WordPress uploads subdirectory whose contents cannot be publicly downloaded. Based on [Chris Dennis](https://github.com/StarsoftAnalysis) 's brilliant [Private Uploads](wordpress.org/plugins/private-uploads/) plugin. Adds convenience functions for uploading files to the protected directory, CLI and REST API commands, and displays an admin notice if the directory is public.

### Status

Some amount of PHPUnit, WPCS, PhpStan done, but lots to do.

### Intro

I've needed this in various plugins and libraries:

e.g.
* [BH WP Logger](https://github.com/BrianHenryIE/bh-wp-logger) needs the "logs" directory to be private
* [BH WP Mailboxes](https://github.com/BrianHenryIE/bh-wp-mailboxes) needs the "attachments" directory to be private
* BH WC Auto Print Shipping Labels & Receipts needs its PDF directory to be private

### Install

This library is not on Packagist yet, so first add this repo:

`composer config repositories.brianhenryie/bh-wp-private-uploads git https://github.com/brianhenryie/bh-wp-private-uploads`

The reason being it is using a fork of [wptrt/admin-notices](https://github.com/WPTT/admin-notices) because of [a race condition in Firefox](https://github.com/WPTT/admin-notices/issues/14). So also add that repo:

`composer config repositories.wptrt/admin-notices git https://github.com/brianhenryie/admin-notices`

Then require as normal:

`composer require brianhenryie/bh-wp-private-uploads`

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

### TODO

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

