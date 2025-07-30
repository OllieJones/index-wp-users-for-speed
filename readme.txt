=== Index WP Users For Speed ===
Contributors: OllieJones
Tags: users, database, index, performance, largesite
Requires at least: 5.2
Tested up to: 6.8.2
Requires PHP: 5.6
Stable tag: 1.1.11
Network: true
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author URI: https://github.com/OllieJones
Plugin URI: https://plumislandmedia.net/index-wp-users-for-speed/
GitHub Plugin URI: https://github.com/OllieJones/index-wp-users-for-speed
Primary Branch: main
Text Domain: index-wp-users-for-speed
Domain Path: /languages
Donate link: https://github.com/sponsors/OllieJones

Do you have thousands of users on your WordPress site? Look them up fast. Find authors more easily. Speed up your laggy dashboard.

== Description ==

This plugin speeds up the handling of your WordPress registered users, especially when your site has many thousands of them. (Congratulations! Building a successful site with thousands of users is an accomplishment.)   With optimized MySQL / MariaDB database techniques, it finds and displays your users more quickly. Your All Users panel on your dashboard displays faster and searches faster. Your All Posts and All Pages panels no longer lag when displaying. And, you can edit your posts to change authorship more efficiently.

Without this plugin WordPress sites with many users slow down drastically on Dashboard pages. It can take many seconds each time you display your Users dashboard panel. It takes just about the same large amount of time to display your Posts or Pages panels. While those slow displays are loading, WordPress is hammering on your site's MySQL or MariaDB database server. That means your site serves your visitors slowly too, not just your dashboard users.

And, versions of WordPress since 6.0.1 have dealt with [this performance problem](https://make.wordpress.org/core/2022/05/02/performance-increase-for-sites-with-large-user-counts-now-also-available-on-single-site/) by preventing changes to the authors of posts and pages in the Gutenberg editor, the classic editor, and the Quick Edit feature. Those recent versions also suppress the user counts shown at the top of the Users panel. This plugin restores those functions.

This plugin helps speed up the handling of those large numbers of users. It does so by indexing your users by adding metadata that's easily optimized by MySQL or MariaDB. For example, when your site must ask the database for your post-author users, the database no longer needs to examine every user on your system. (In database jargon, it no longer needs to do notoriously slow full table scans to find users.)

When slow queries are required to make sure the metadata indexes are up to date, this plugin does them in the background so nobody has to wait for them to complete. You can set the plugin to do this background work at a particular time each day. Many people prefer to do them overnight or at some other off-peak time.

<h4>How can I learn more about making my WordPress site more efficient?</h4>

This is a companion plugin to [Index WP MySQL for Speed](https://wordpress.org/plugins/index-wp-mysql-for-speed/). If that plugin is in use, this plugin will perform better. But they are in no way dependent on one another; you may use either, both, or of course neither.

I offer several plugins to help with your site's database efficiency. You can [read about them here](https://www.plumislandmedia.net/wordpress/performance/optimizing-wordpress-database-servers/).

== Frequently Asked Questions ==

= Should I back up my site before using this? =

**Yes.** Backups are good practice. Still, this plugin makes no changes to your site or database layout. It adds a few non-autoloaded options, and adds rows to wp_usermeta.

= My WordPress host offers MariaDB, not MySQL. Can I use this plugin?

**Yes.**

= I have a multi-site WordPress installation. Can I use this plugin?

**Yes.**

= I see high CPU usage (load average) on my MariaDB / MySQL database server during user index building or refresh. Is that normal?

**Yes.** Indexing your registered users requires us to insert a row in your wp_usermeta tab;e for each of them. We do this work in batches of 5000 users to avoid locking up your MariaDB / MySQL server. Each batch takes server time. Once all index building or refresh batches are complete, your CPU usage will return to normal.

= Can I use this if I have disabled WP_Cron and use an operating system cronjob instead?

**Yes**

= What if I assign multiple roles to some users? =

Plugins like Vladimir Garagulya's [User Role Editor](https://wordpress.org/plugins/user-role-editor/) let you assign multiple roles to users. This plugin handles those users correctly.

= How does it work? (Geeky!) =

Standard WordPress puts a `wp_capabilities` row in the `wp_usermeta` table for each user. Its `meta_value` contains a small data structure. For example, an author has this data structure.

`array("author")`

In order to find all the authors WordPress must issue a database query containing a filter like this one, that starts and ends with the SQL wildcard character `%`.

`meta_key = 'wp_capabilities' AND meta_value LIKE '%"author"%'`

Filters like that are notoriously slow: they cannot exploit any database keys, and so MySQL or MariaDB must examine that `wp_usermeta` row for every user in your site.

This plugin adds rows to `wp_usermeta` describing each user's role (or roles) in a way that's easier to search.  To find authors, the plugin uses this much faster filter instead.

`meta_key = 'wp_index_wp_users_for_speed_role_author'`

It takes a while to insert these extra indexing rows into the database; that happens in the background.

Once the indexing rows are in place, you can add, delete, or change user roles without regenerating those rows: the plugin maintains them.

= What is the background for this plugin? =

WordPress's trac (defect-tracking) system has [this ticket # 38741](https://core.trac.wordpress.org/ticket/38741).

= Why use this plugin? =

Three reasons (maybe four):

1. to save carbon footprint.
2. to save carbon footprint.
3. to save carbon footprint.
4. to save people time.

Seriously, the microwatt hours of electricity saved by faster web site technologies add up fast, especially at WordPress's global scale.

== Installation ==

Install and activate this plugin in the usual way via the Plugins panel in your site's dashboard. Once you have activated it, configure it via the Index for Speed menu item under Users.

= WP-CLI =

`wp plugin install index-wp-users-for-speed
wp plugin activate index-wp-users-for-speed
`
= Composer =

If you configure your WordPress installation using composer, you may install this plugin into your WordPress top level configuration with this command.

`composer require "wpackagist-plugin/index-wp-users-for-speed":"^1.1"`


= Credits =
* "Crowd", a photo by James Cridland, in the banner and icon. [CC BY 2.0](https://creativecommons.org/licenses/by/2.0/)
* Japreet Sethi for advice, and for testing on his large installation.
* Rick James for everything.

== Screenshots ==

1. Access to this plugin's configuration panel.
2. This plugin's configuration panel.
3. The bulk editor for All Posts showing the selection box with autocompletion of author name.

== Changelog ==

= 1.1.11 =

Fix incompatibility with https://wordpress.org/plugins/co-authors-plus/ .

= 1.1.10 =

Remove rendundant l11n loading.

= 1.1.9 =

Fix typo in cron-disabled code path.

= 1.1.8 =

Use transactions to hopefully avoid deadlocks. Use options instead of transients.

= 1.1.7 =

Display both user display name and login name in dropdowns.

= 1.1.6 =

Handles WP_User_Query operations with metadata search correctly.

= 1.1.5 =

Repair problem handing user queries with role__not_in and role__in search terms.

= 1.1.4 =

* Fix compatibility with WordPress pre 5.9.
* Display more reliable user count on dashboard panel.

= 1.1.3 =

* Correct query-optimization problem when rendering autocompletion fields.
* Test and optimize with MariaDB 10.9.

= 1.1.2 =

* Correct query-optimization error.
* Update the usermeta table's query-planning statistics after adding user metadata.

= 1.1.1 =

* Replace the author dropdown menus in Quick Edit and Bulk Edit with autocompletion fields, to
allow more flexible changes of post and page authors.
* Improve the performance of user lookups.
* Allow multiple roles per user as provided in plugins like User Role Editor.

= 1.0.4 =

* Fix bug preventing wp-cli deactivation. Props to [João Faria](https://github.com/jffaria).

== Upgrade Notice ==

Version 1.1.6 supports metadata user queries.

Thanks to my loyal users who have reported problems.
