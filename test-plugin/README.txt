=== Private Uploads ===
Contributors: ChrisDennis
Donate link: http://fbcs.co.uk/pucd-donation/
Tags: private,server,privacy,documents,web server,nginx
Requires at least: 4.3.0
Tested up to: 5.1
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protects sensitive uploaded files so that only logged-in users can access them.

The plugin depends on corresponding web server (e.g. Nginx, Apache) configuration to work.

== Description ==

'Private' uploaded files (PDFs, images, etc.) will normally be only included in private posts and pages.  But the files themselves can still be accessed by anyone if they know the corresponding URLs.

For example, a PDF file's URL might be

	http://example.com/wp-content/uploads/minutes-20160924.pdf

and anyone could download that file because WordPress does not get a chance to check their authorisation.

The solution that the Private Uploads plugin uses involves moving any private files to a separate folder, and then configuring the web server to ask WordPress to authenticate access to files in that folder.

So the file's URL might now be

	http://example.com/wp-content/uploads/private/minutes-20160924.pdf

and an HTTP server rewrite rule will convert this to

	http://example.com/?pucd-folder=private&pucd-file=minutes-20160924.pdf

The Private Uploads plugin will intercept that URL and reject it with a 403 status code.

This plugin is more efficient than some similar ones because it only has to run when serving files in the private folder(s): the web server handles other uploaded files (ones not in the private folders) directly.

== Requirements ==

* Sufficient access to the web server to allow the required configuration.

== Acknowledgements ==

* This plugin was inspired by a discussion on [StackExchange](https://wordpress.stackexchange.com/questions/37144/how-to-protect-uploads-if-user-is-not-logged-in).

== Future Plans ==

* Currently, access to private files just depends on the `is_user_logged_in()` function.  This plugin could be developed to give more fine-grained control, such as having a folder for each user.

== Installation ==

Install the plugin in the usual way and activate it.

Move your private uploads (PDFs, images, or whatever) into a separate sub-folder within the WordPress uploads folder (usually /wp-content/uploads).  One way of creating such a folder and moving the private files is by means of the [Media Organiser](https://wordpress.org/plugins/media-organiser/) plugin.

Then configure your web server as follows:

= Nginx =

Include a line like this in the server section of the Nginx configuration:

    rewrite ^/wp-content/uploads/(private)/(.*)$ /?pucd-folder=$1&pucd-file=$2 break;

The folder name 'private' can be anything you like -- it just has to match the name of the folder where your private files are kept, and be enclosed in parentheses in the rewrite statement.

More than one private folder can be configured by adding more lines of the same form, for example:

    rewrite ^/wp-content/uploads/(2017/secure)/(.*)$ /?pucd-folder=$1&pucd-file=$2 break;

= Apache =

[Enchiridion](https://wordpress.org/support/users/enchiridion/) has supplied the following configuration for Apache.  Thank you.

Here's an equivalent rule for Apache to add to your existing rules:

    RewriteRule ^wp-content/uploads/(private)/(.*)$ /?pucd-folder=$1&pucd-file=$2 [L]

Or you can copy/paste this entire block into your `.htaccess` file. Add before the `# BEGIN WordPress` block:

    <IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    # Block unauthenticated user access to the /private/ uploads folder
    RewriteRule ^wp-content/uploads/(private)/(.*)$ /?pucd-folder=$1&pucd-file=$2 [L]
    </IfModule>

= Other web servers =

are left as an exercise for the reader.

== Frequently Asked Questions ==


== Screenshots ==


== Changelog ==

= 0.1.1 =

Tested with WordPress 5.  Documentation tidied up.

= 0.1.0 =

* First public release.

