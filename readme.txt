=== Index WP MySQL For Speed ===
Contributors: Ollie Jones
Tags: users, database, index, performance
Requires at least: 5.2
Tested up to: 5.9.2
Requires PHP: 5.6
Stable tag: 1.0.0
Network: true
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author URI: https://github.com/OllieJones
Plugin URI: https://plumislandmedia.net/index-wp-users-for-speed/
Github Plugin URI: https://github.com/OllieJones/index-wp-users-for-speed
Primary Branch: main
Text Domain: index-wp-users-for-speed
Domain Path: /languages

Do you have many users on your WordPress site? Look them up fast by indexing them in your metadata.

== Description ==

WordPress sites with many users slow down drastically, especially on Dashboard pages. It can take many seconds each time you display your Users dashboard panel. It takes just about the same amount of time to display your Posts or Pages panels. While those slow displays are loading, WordPress is hammering on your site's MySQL or MariaDB database server. That means your site serves your visitors slowly too, not just your dashboard users.

This plugin helps speed up the handling of those large numbers of users. It does so by indexing your users by adding metadata that's easily optimized by MySQL or MariaDB. For example, when your site must ask the database for your post-author users, the database no longer needs to examine every user on your system. (In database jargon, it no longer needs to do a notoriously slow full table scan.)

When slow queries are required to make sure the metadata indexes are up to date, this plugin does them in the background so nobody has to wait for them to complete. You can set the plugin to do this background work at a particular time each day. Many people prefer to do them overnight or at some other off-peak time.

This is a companion plugin to [Index WP MySQL for Speed](https://wordpress.org/plugins/index-wp-mysql-for-speed/). But they are in no way dependent on one another; you may use either, both, or of course neither.

== Frequently Asked Questions ==

= Should I back up my site before using this? =

**Yes.** Backups are good practice. Still, this plugin makes no extensive changes to your site or database.

= My WordPress host offers MariaDB, not MySQL. Can I use this plugin?

**Yes.**

= I have a multi-site WordPress installation. Can I use this plugin?

**Yes.**

= How does it work? (Geeky!) =

Standard WordPress puts a `wp_capabilities` row in the `wp_usermeta` table for each user. Its `meta_value` contains a small data structure. For example, an author has this data structure.

`array("author")`

In order to find all the authors WordPress must issue a database query containing a filter like this one, that starts and ends with the SQL wildcard character `%`.

`meta_key = 'wp_capabilities' AND meta_value LIKE '%"author"%'`

Filters like that are notoriously slow: they cannot exploit any database keys, and so MySQL or MariaDB must examine that `wp_usermeta` row for every user in your site.

This plugin adds rows to `wp_usermeta` describing each user in a way that's easier to search.  To find authors, the plugin uses this much faster filter instead.

`meta_key = 'wp_index_wp_users_for_speed_role_author'`

It takes a while to insert these extra indexing rows into the database; that happens in the background.

Once the indexing rows are in place, you can add, delete, or change user roles without regenerating those rows: the plugin maintains them.

= What is the background for this plugin? =

WordPress's trac (defect-tracking) system has [this ticket # 38741](https://core.trac.wordpress.org/ticket/38741).

== Installation ==

Install and activate this plugin in the usual way via the Plugins panel in your site's dashboard.

Configure it via the Index for Speed menu item under Users.

= Credits =
* "Crowd", a photo by James Cridland, in the banner and icon. [CC BY 2.0](https://creativecommons.org/licenses/by/2.0/)
* Japreet Sethi for advice, and for testing on his large installation.
* Rick James for everything.

== Screenshots ==

1. Access to this plugin's configuration panel.
2. This plugin's configuration panel.

== Changelog ==

= 1.0.1 =

* Add notice bar showing progress. Use heartbeat to keep progress going.
* Fix defect when changing user role.
* Integrate correctly with https://core.trac.wordpress.org/ticket/38741 for large site handling.

= 1.0.0 =

First release

== Upgrade Notice ==

Now we show a notice bar showing progress building or rebuilding the user index metadata.

= 1.0.1 =

Now allows changing user roles. Supports WordPress 6.0
