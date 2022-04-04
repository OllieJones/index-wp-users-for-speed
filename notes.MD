wp_usermeta.meta_key = 'wp_capabilities'
---

| COUNT(*) | meta_value |
| ---: | --- |
| 167 | a:1:{s:10:"subscriber";b:1;} |
| 71 | a:1:{s:6:"editor";b:1;} |
| 65 | a:1:{s:6:"author";b:1;} |
| 36 | a:1:{s:11:"contributor";b:1;} |
| 34 | a:1:{s:15:"css_js_designer";b:1;} |
| 29 | a:1:{s:10:"translator";b:1;} |
| 1 | a:2:{s:13:"administrator";b:1;s:14:"backwpup_admin";b:1;} |

## Hooks....

```php

	/**
	 * Fires immediately before a user is deleted from the database.
	 *
	 * @since 2.0.0
	 * @since 5.5.0 Added the `$user` parameter.
	 *
	 * @param int      $id       ID of the user to delete.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 *                           Default null, for no reassignment.
	 * @param WP_User  $user     WP_User object of the user to delete.
	 */
	do_action( 'delete_user', $id, $reassign, $user );
	
	
		/**
	 * Fires immediately after a user is deleted from the database.
	 *
	 * @since 2.9.0
	 * @since 5.5.0 Added the `$user` parameter.
	 *
	 * @param int      $id       ID of the deleted user.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 *                           Default null, for no reassignment.
	 * @param WP_User  $user     WP_User object of the deleted user.
	 */
	do_action( 'deleted_user', $id, $reassign, $user );
	
		return apply_filters_ref_array( 'get_meta_sql', array( $sql, $this->queries, $type, $primary_table, $primary_id_column, $context ) );
		$search_columns = apply_filters( 'user_search_columns', $search_columns, $search, $this );

		/**
		 * Fires after the WP_User_Query has been parsed, and before
		 * the query is executed.
		 *
		 * The passed WP_User_Query object contains SQL parts formed
		 * from parsing the given query.
		 *
		 * @since 3.1.0
		 *
		 * @param WP_User_Query $query Current instance of WP_User_Query (passed by reference).
		 */
		do_action_ref_array( 'pre_user_query', array( &$this ) );


		/**
		 * Filters the users array before the query takes place.
		 *
		 * Return a non-null value to bypass WordPress' default user queries.
		 *
		 * Filtering functions that require pagination information are encouraged to set
		 * the `total_users` property of the WP_User_Query object, passed to the filter
		 * by reference. If WP_User_Query does not perform a database query, it will not
		 * have enough information to generate these values itself.
		 *
		 * @since 5.1.0
		 *
		 * @param array|null    $results Return an array of user data to short-circuit WP's user query
		 *                               or null to allow WP to run its normal queries.
		 * @param WP_User_Query $query   The WP_User_Query instance (passed by reference).
		 */
		$this->results = apply_filters_ref_array( 'users_pre_query', array( null, &$this ) );


				/**
				 * Filters SELECT FOUND_ROWS() query for the current WP_User_Query instance.
				 *
				 * @since 3.2.0
				 * @since 5.1.0 Added the `$this` parameter.
				 *
				 * @global wpdb $wpdb WordPress database abstraction object.
				 *
				 * @param string        $sql   The SELECT FOUND_ROWS() query for the current WP_User_Query.
				 * @param WP_User_Query $query The current WP_User_Query instance.
				 */
				$found_users_query = apply_filters( 'found_users_query', 'SELECT FOUND_ROWS()', $this );

	/**
	 * Short-circuits updating the metadata cache of a specific type.
	 *
	 * The dynamic portion of the hook name, `$meta_type`, refers to the meta object type
	 * (post, comment, term, user, or any other type with an associated meta table).
	 * Returning a non-null value will effectively short-circuit the function.
	 *
	 * Possible hook names include:
	 *
	 *  - `update_post_metadata_cache`
	 *  - `update_comment_metadata_cache`
	 *  - `update_term_metadata_cache`
	 *  - `update_user_metadata_cache`
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $check      Whether to allow updating the meta cache of the given type.
	 * @param int[] $object_ids Array of object IDs to update the meta cache for.
	 */

	/**
	 * Filters the query arguments for the list of users in the dropdown.
	 *
	 * @since 4.4.0
	 *
	 * @param array $query_args  The query arguments for get_users().
	 * @param array $parsed_args The arguments passed to wp_dropdown_users() combined with the defaults.
	 */
	$query_args = apply_filters( 'wp_dropdown_users_args', $query_args, $parsed_args );
	
						/**
						 * Filters the arguments used to generate the Quick Edit authors drop-down.
						 *
						 * @since 5.6.0
						 *
						 * @see wp_dropdown_users()
						 *
						 * @param array $users_opt An array of arguments passed to wp_dropdown_users().
						 * @param bool  $bulk      A flag to denote if it's a bulk action.
						 */
						$users_opt = apply_filters( 'quick_edit_dropdown_authors_args', $users_opt, $bulk );

		/**
		 * Filters WP_User_Query arguments when querying users via the REST API.
		 *
		 * @link https://developer.wordpress.org/reference/classes/wp_user_query/
		 *
		 * @since 4.7.0
		 *
		 * @param array           $prepared_args Array of arguments for WP_User_Query.
		 * @param WP_REST_Request $request       The REST API request.
		 */
		$prepared_args = apply_filters( 'rest_user_query', $prepared_args, $request );

		/**
		 * Fires before the WP_User_Query has been parsed.
		 *
		 * The passed WP_User_Query object contains the query variables,
		 * not yet passed into SQL.
		 *
		 * @since 4.0.0
		 *
		 * @param WP_User_Query $query Current instance of WP_User_Query (passed by reference).
		 */
		do_action_ref_array( 'pre_get_users', array( &$this ) );

		/**
		 * Fires after the WP_User_Query has been parsed, and before
		 * the query is executed.
		 *
		 * The passed WP_User_Query object contains SQL parts formed
		 * from parsing the given query.
		 *
		 * @since 3.1.0
		 *
		 * @param WP_User_Query $query Current instance of WP_User_Query (passed by reference).
		 */
		do_action_ref_array( 'pre_user_query', array( &$this ) );


		/**
		 * Filters the users array before the query takes place.
		 *
		 * Return a non-null value to bypass WordPress' default user queries.
		 *
		 * Filtering functions that require pagination information are encouraged to set
		 * the `total_users` property of the WP_User_Query object, passed to the filter
		 * by reference. If WP_User_Query does not perform a database query, it will not
		 * have enough information to generate these values itself.
		 *
		 * @since 5.1.0
		 *
		 * @param array|null    $results Return an array of user data to short-circuit WP's user query
		 *                               or null to allow WP to run its normal queries.
		 * @param WP_User_Query $query   The WP_User_Query instance (passed by reference).
		 */
		$this->results = apply_filters_ref_array( 'users_pre_query', array( null, &$this ) );

				/**
				 * Filters SELECT FOUND_ROWS() query for the current WP_User_Query instance.
                 *  BUT! only works if the query contained the SELECT_FOUND_ROWS modifier
                 *  so the performance hit is already taken. 
				 *
				 * @since 3.2.0
				 * @since 5.1.0 Added the `$this` parameter.
				 *
				 * @global wpdb $wpdb WordPress database abstraction object.
				 *
				 * @param string        $sql   The SELECT FOUND_ROWS() query for the current WP_User_Query.
				 * @param WP_User_Query $query The current WP_User_Query instance.
				 */
				$found_users_query = apply_filters( 'found_users_query', 'SELECT FOUND_ROWS()', $this );
		/**
		 * Fires immediately after a user is deleted via the REST API.
		 *
		 * @since 4.7.0
		 *
		 * @param WP_User          $user     The user data.
		 * @param WP_REST_Response $response The response returned from the API.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'rest_delete_user', $user, $response, $request );
		
		/**
		 * Fires immediately after a user is created or updated via the REST API.
		 *
		 * @since 4.7.0
		 *
		 * @param WP_User         $user     Inserted or updated user object.
		 * @param WP_REST_Request $request  Request object.
		 * @param bool            $creating True when creating a user, false when updating.
		 */
		do_action( 'rest_insert_user', $user, $request, true );
		
		/**
		 * Fires after a user is completely created or updated via the REST API.
		 *
		 * @since 5.0.0
		 *
		 * @param WP_User         $user     Inserted or updated user object.
		 * @param WP_REST_Request $request  Request object.
		 * @param bool            $creating True when creating a user, false when updating.
		 */
		do_action( 'rest_after_insert_user', $user, $request, true );



```

## Gets users for Gutenberg editor.

http://ubu2010.plumislandmedia.local/wp-json/wp/v2/users?context=view&who=authors&per_page=50&_fields=id,name&_locale=user

Here's a query to populate the easily readable caps meta

```sql
# noinspection SqlNoDataSourceInspectionForFile
# noinspection SqlResolve
-- INSERT INTO wp_usermeta (user_id, meta_key)
SELECT a.user_id, meta_key
  FROM (
           SELECT user_id, 'wp_caps_edit_posts' meta_key
             FROM wp_usermeta
            WHERE wp_usermeta.meta_value LIKE '%\"edit\\_posts\"%'
            UNION ALL
           SELECT user_id, 'wp_caps_administrator'
             FROM wp_usermeta
            WHERE wp_usermeta.meta_value LIKE '%\"administrator\"%'
            UNION ALL
           SELECT user_id, 'wp_caps_editor'
             FROM wp_usermeta
            WHERE wp_usermeta.meta_value LIKE '%\"editor\"%'
            UNION ALL
           SELECT user_id ID, 'wp_caps_author'
             FROM wp_usermeta
            WHERE wp_usermeta.meta_value LIKE '%\"author\"%'
            UNION ALL
           SELECT user_id ID, 'wp_caps_contributor'
             FROM wp_usermeta
            WHERE wp_usermeta.meta_value LIKE '%\"contributor\"%'
           -- UNION ALL
           -- SELECT user_id ID, 'wp_caps_subscriber' FROM wp_usermeta WHERE wp_usermeta.meta_value LIKE '%\"subscriber\"%'
       ) a
       LEFT JOIN (
                     SELECT user_id
                       FROM wp_usermeta
                      WHERE meta_key IN
                            ('wp_caps_edit_posts', 'wp_caps_administrator', 'wp_caps_editor', 'wp_caps_author',
                             'wp_caps_contributor', 'wp_caps_subscriber')
                 ) b ON a.user_id = b.user_id
 WHERE b.user_id IS NULL
-- LIMIT 5


```

## Error message

Right after adding a user from the entire site.

(Maybe a problem with users added by WP Demo)

```
Fatal error: Uncaught Error: Cannot create references to/from string offsets
in /var/www/ubu2010.plumislandmedia.local/wp-admin/includes/class-wp-users-list-table.php on line 191

Call stack:

WP_Users_List_Table::get_views()
wp-admin/includes/class-wp-list-table.php:398
WP_List_Table::views()
wp-admin/users.php:641
```