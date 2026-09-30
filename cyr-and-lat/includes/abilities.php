<?php
/**
 * Registers the plugin abilities with the WordPress Abilities API (WordPress 6.9+).
 *
 * @package Cyr_To_Lat
 */

defined( 'ABSPATH' ) || die( 'Direct access not allowed.' );

class WCTLR_Abilities {

	const ABILITY = 'slugs/transliterate';

	const CATEGORY = 'slugs';

	const DEFAULT_LIMIT = 100;

	const MAX_LIMIT = 500;

	const DEFAULT_TIME_BUDGET = 20;

	const MAX_TIME_BUDGET = 60;

	/**
	 * Target slugs claimed by earlier items of the current batch.
	 *
	 * @var array
	 */
	private $claimed = [];

	public function __construct() {
		add_action( 'wp_abilities_api_categories_init', [ $this, 'registerCategory' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'registerAbilities' ] );
	}

	public function registerCategory() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( self::CATEGORY ) ) {
			return;
		}

		wp_register_ability_category( self::CATEGORY, [
			'label'       => __( 'Slugs', 'cyr-to-lat-reloaded' ),
			'description' => __( 'Transliteration of post and term slugs.', 'cyr-to-lat-reloaded' ),
		] );
	}

	public function registerAbilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// The same ability can be provided by another transliteration plugin, it is registered once.
		if ( function_exists( 'wp_has_ability' ) && wp_has_ability( self::ABILITY ) ) {
			return;
		}

		wp_register_ability( self::ABILITY, [
			'label'               => __( 'Transliterate slugs', 'cyr-to-lat-reloaded' ),
			'description'         => __( 'Transliterates the existing non-Latin slugs of a scoped batch of posts and terms to Latin characters. With dry_run (default true) it only returns the plan, including slug collisions; with dry_run set to false it applies the plan, keeps a backup of every old slug and skips collisions. The work is done in chunks: while done is false, call again with the same input and the returned cursor.', 'cyr-to-lat-reloaded' ),
			'category'            => self::CATEGORY,
			'input_schema'        => $this->getInputSchema(),
			'output_schema'       => $this->getOutputSchema(),
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'checkPermission' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				],
				'show_in_rest' => true,
				'task'         => [
					'mode'        => 'cursor',
					'results_key' => 'items',
				],
			],
		] );
	}

	/**
	 * The same capability as WCTLR_Plugin::convertExistingSlugs().
	 *
	 * @return bool
	 */
	public function checkPermission() {
		return current_user_can( 'install_plugins' );
	}

	/**
	 * @return array
	 */
	private function getInputSchema() {
		$ids = [
			'type'     => 'array',
			'items'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'maxItems' => self::MAX_LIMIT,
		];

		$names = [
			'type'  => 'array',
			'items' => [ 'type' => 'string' ],
		];

		return [
			'type'                 => 'object',
			'properties'           => [
				'scope'       => [
					'type'                 => 'object',
					'description'          => __( 'What to process. At least one of the keys is required. Only posts with the publish, future or private status are processed.', 'cyr-to-lat-reloaded' ),
					'properties'           => [
						'post_types' => array_merge( $names, [ 'description' => __( 'Post type names, e.g. post, page.', 'cyr-to-lat-reloaded' ) ] ),
						'taxonomies' => array_merge( $names, [ 'description' => __( 'Taxonomy names, e.g. category, post_tag.', 'cyr-to-lat-reloaded' ) ] ),
						'post_ids'   => array_merge( $ids, [ 'description' => __( 'Post IDs.', 'cyr-to-lat-reloaded' ) ] ),
						'term_ids'   => array_merge( $ids, [ 'description' => __( 'Term IDs.', 'cyr-to-lat-reloaded' ) ] ),
					],
					'additionalProperties' => false,
				],
				'dry_run'     => [
					'type'        => 'boolean',
					'default'     => true,
					'description' => __( 'When true (default) nothing is changed and the plan is returned. Set to false to apply it.', 'cyr-to-lat-reloaded' ),
				],
				'limit'       => [
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => self::MAX_LIMIT,
					'default'     => self::DEFAULT_LIMIT,
					'description' => __( 'Maximum number of objects handled by one call.', 'cyr-to-lat-reloaded' ),
				],
				'cursor'      => [
					'type'        => 'string',
					'description' => __( 'Opaque cursor returned by the previous call. Send it back with the same input to continue.', 'cyr-to-lat-reloaded' ),
				],
				'time_budget' => [
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => self::MAX_TIME_BUDGET,
					'default'     => self::DEFAULT_TIME_BUDGET,
					'description' => __( 'Maximum number of seconds one call may work.', 'cyr-to-lat-reloaded' ),
				],
			],
			'required'             => [ 'scope' ],
			'additionalProperties' => false,
		];
	}

	/**
	 * @return array
	 */
	private function getOutputSchema() {
		return [
			'type'       => 'object',
			'properties' => [
				'dry_run'  => [ 'type' => 'boolean' ],
				'done'     => [ 'type' => 'boolean' ],
				'cursor'   => [
					'type'        => 'string',
					'description' => __( 'Empty when done.', 'cyr-to-lat-reloaded' ),
				],
				'progress' => [
					'type'       => 'object',
					'properties' => [
						'current' => [ 'type' => 'integer' ],
						'total'   => [ 'type' => 'integer' ],
						'message' => [ 'type' => 'string' ],
					],
				],
				'updated'  => [ 'type' => 'integer' ],
				'items'    => [
					'type'        => 'array',
					'description' => __( 'The objects handled by this call.', 'cyr-to-lat-reloaded' ),
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'object_type'    => [ 'type' => 'string', 'enum' => [ 'post', 'term' ] ],
							'id'             => [ 'type' => 'integer' ],
							'subtype'        => [
								'type'        => 'string',
								'description' => __( 'Post type or taxonomy.', 'cyr-to-lat-reloaded' ),
							],
							'title'          => [ 'type' => 'string' ],
							'from'           => [ 'type' => 'string' ],
							'to'             => [ 'type' => 'string' ],
							'collision'      => [ 'type' => 'boolean' ],
							'available_slug' => [
								'type'        => 'string',
								'description' => __( 'On a collision, a free slug suggested by WordPress. It is never applied automatically.', 'cyr-to-lat-reloaded' ),
							],
							'status'         => [
								'type' => 'string',
								'enum' => [ 'planned', 'updated', 'unchanged', 'collision', 'empty', 'forbidden', 'failed' ],
							],
						],
					],
				],
			],
		];
	}

	/**
	 * @param array $input
	 *
	 * @return array|WP_Error
	 */
	public function execute( $input = [] ) {
		$input   = is_array( $input ) ? $input : [];
		$scope   = isset( $input['scope'] ) && is_array( $input['scope'] ) ? $input['scope'] : [];
		$dry_run = ! isset( $input['dry_run'] ) || false !== $input['dry_run'];
		$limit   = isset( $input['limit'] ) ? absint( $input['limit'] ) : self::DEFAULT_LIMIT;
		$limit   = max( 1, min( self::MAX_LIMIT, $limit ) );
		$budget  = isset( $input['time_budget'] ) ? absint( $input['time_budget'] ) : self::DEFAULT_TIME_BUDGET;
		$budget  = max( 1, min( self::MAX_TIME_BUDGET, $budget ) );
		$started = microtime( true );
		$cursor  = $this->decodeCursor( isset( $input['cursor'] ) ? $input['cursor'] : '' );

		if ( is_wp_error( $cursor ) ) {
			return $cursor;
		}

		$post_types = $this->sanitizeNames( $scope, 'post_types' );
		$taxonomies = $this->sanitizeNames( $scope, 'taxonomies' );
		$post_ids   = $this->sanitizeIds( $scope, 'post_ids' );
		$term_ids   = $this->sanitizeIds( $scope, 'term_ids' );

		if ( empty( $post_types ) && empty( $taxonomies ) && empty( $post_ids ) && empty( $term_ids ) ) {
			return new WP_Error( 'cyr_to_lat_missing_scope', __( 'The scope must contain at least one of: post_types, taxonomies, post_ids, term_ids.', 'cyr-to-lat-reloaded' ), [ 'status' => 400 ] );
		}

		foreach ( $post_types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				// translators: %s is a post type name.
				return new WP_Error( 'cyr_to_lat_invalid_post_type', sprintf( __( 'Unknown post type: %s', 'cyr-to-lat-reloaded' ), $post_type ), [ 'status' => 400 ] );
			}
		}

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				// translators: %s is a taxonomy name.
				return new WP_Error( 'cyr_to_lat_invalid_taxonomy', sprintf( __( 'Unknown taxonomy: %s', 'cyr-to-lat-reloaded' ), $taxonomy ), [ 'status' => 400 ] );
			}
		}

		$candidates = [];

		if ( ! empty( $post_types ) || ! empty( $post_ids ) ) {
			$candidates = $this->findPosts( $post_types, $post_ids );
		}

		if ( ! empty( $taxonomies ) || ! empty( $term_ids ) ) {
			$candidates = array_merge( $candidates, $this->findTerms( $taxonomies, $term_ids ) );
		}

		// Updated objects leave the selection, so the cursor is the last handled object and not an offset.
		$candidates = $this->skipHandled( $candidates, $cursor );
		$current    = $cursor['done'];
		$total      = $current + count( $candidates );

		$this->claimed = [];
		$items         = [];
		$updated       = 0;
		$last          = null;

		foreach ( $candidates as $candidate ) {
			if ( ! empty( $items ) && ( count( $items ) >= $limit || microtime( true ) - $started >= $budget ) ) {
				break;
			}

			$item = 'post' === $candidate['object_type'] ? $this->processPost( $candidate, $dry_run ) : $this->processTerm( $candidate, $dry_run );

			if ( 'updated' === $item['status'] ) {
				$updated ++;
			}

			$items[] = $item;
			$last    = $candidate;
		}

		$current += count( $items );
		$done     = $current >= $total;

		return [
			'dry_run'  => $dry_run,
			'done'     => $done,
			'cursor'   => $done || null === $last ? '' : $this->encodeCursor( $last, $current ),
			'progress' => [
				'current' => $current,
				'total'   => $total,
				// translators: %1$d is the number of handled objects, %2$d is the number of objects to handle.
				'message' => sprintf( __( 'Handled %1$d of %2$d slugs.', 'cyr-to-lat-reloaded' ), $current, $total ),
			],
			'updated'  => $updated,
			'items'    => $items,
		];
	}

	/**
	 * @param array $candidate The last handled object.
	 * @param int   $done      Number of objects handled so far.
	 *
	 * @return string
	 */
	private function encodeCursor( $candidate, $done ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( wp_json_encode( [
			'type' => $candidate['object_type'],
			'id'   => (int) $candidate['id'],
			'done' => (int) $done,
		] ) );
	}

	/**
	 * @param mixed $cursor
	 *
	 * @return array|WP_Error
	 */
	private function decodeCursor( $cursor ) {
		if ( null === $cursor || '' === $cursor ) {
			return [
				'type' => 'post',
				'id'   => 0,
				'done' => 0,
			];
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$data = is_string( $cursor ) ? json_decode( (string) base64_decode( $cursor, true ), true ) : null;

		if ( ! is_array( $data ) || ! isset( $data['type'], $data['id'], $data['done'] ) || ! in_array( $data['type'], [ 'post', 'term' ], true ) || ! is_int( $data['id'] ) || ! is_int( $data['done'] ) || $data['id'] < 1 || $data['done'] < 1 ) {
			return new WP_Error( 'cyr_to_lat_invalid_cursor', __( 'The cursor is not valid. Use the cursor returned by the previous call, or omit it to start over.', 'cyr-to-lat-reloaded' ), [ 'status' => 400 ] );
		}

		return [
			'type' => $data['type'],
			'id'   => $data['id'],
			'done' => $data['done'],
		];
	}

	/**
	 * Drops the objects handled by the previous calls. The candidates are ordered: posts by ID, then terms by ID.
	 *
	 * @param array $candidates
	 * @param array $cursor
	 *
	 * @return array
	 */
	private function skipHandled( $candidates, $cursor ) {
		if ( $cursor['id'] < 1 ) {
			return $candidates;
		}

		$result = [];

		foreach ( $candidates as $candidate ) {
			if ( 'post' === $candidate['object_type'] && ( 'term' === $cursor['type'] || $candidate['id'] <= $cursor['id'] ) ) {
				continue;
			}

			if ( 'term' === $candidate['object_type'] && 'term' === $cursor['type'] && $candidate['id'] <= $cursor['id'] ) {
				continue;
			}

			$result[] = $candidate;
		}

		return $result;
	}

	/**
	 * The same selection as WCTLR_Plugin::convertExistingSlugs(), narrowed down to the scope.
	 *
	 * @param string[] $post_types
	 * @param int[]    $post_ids
	 *
	 * @return array
	 */
	private function findPosts( $post_types, $post_ids ) {
		global $wpdb;

		$where = '';

		if ( ! empty( $post_types ) ) {
			$where .= " AND post_type IN ('" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "')";
		}

		if ( ! empty( $post_ids ) ) {
			$where .= ' AND ID IN (' . implode( ',', array_map( 'absint', $post_ids ) ) . ')';
		}

		$posts = $wpdb->get_results( "SELECT ID, post_name FROM {$wpdb->posts}
					WHERE post_name REGEXP('[^_A-Za-z0-9\-]+') AND post_status IN ('publish', 'future', 'private'){$where}
					ORDER BY ID ASC" );

		$result = [];

		foreach ( (array) $posts as $post ) {
			$result[] = [
				'object_type' => 'post',
				'id'          => (int) $post->ID,
				'slug'        => $post->post_name,
			];
		}

		return $result;
	}

	/**
	 * The same selection as WCTLR_Plugin::convertExistingSlugs(), narrowed down to the scope.
	 *
	 * @param string[] $taxonomies
	 * @param int[]    $term_ids
	 *
	 * @return array
	 */
	private function findTerms( $taxonomies, $term_ids ) {
		global $wpdb;

		$where = '';

		if ( ! empty( $taxonomies ) ) {
			$where .= " AND tt.taxonomy IN ('" . implode( "','", array_map( 'esc_sql', $taxonomies ) ) . "')";
		}

		if ( ! empty( $term_ids ) ) {
			$where .= ' AND t.term_id IN (' . implode( ',', array_map( 'absint', $term_ids ) ) . ')';
		}

		$terms = $wpdb->get_results( "SELECT t.term_id, t.slug, tt.taxonomy FROM {$wpdb->terms} t
					INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
					WHERE t.slug REGEXP('[^_A-Za-z0-9\-]+'){$where}
					ORDER BY t.term_id ASC" );

		$result = [];

		foreach ( (array) $terms as $term ) {
			if ( isset( $result[ $term->term_id ] ) ) {
				continue;
			}

			$result[ $term->term_id ] = [
				'object_type' => 'term',
				'id'          => (int) $term->term_id,
				'slug'        => $term->slug,
				'taxonomy'    => $term->taxonomy,
			];
		}

		return array_values( $result );
	}

	/**
	 * @param array $candidate
	 * @param bool  $dry_run
	 *
	 * @return array
	 */
	private function processPost( $candidate, $dry_run ) {
		global $wpdb;

		$post = get_post( $candidate['id'] );
		$to   = (string) wbcr_ctlr_sanitize_title( urldecode( $candidate['slug'] ) );
		$item = [
			'object_type' => 'post',
			'id'          => $candidate['id'],
			'subtype'     => $post ? $post->post_type : '',
			'title'       => $post ? $post->post_title : '',
			'from'        => urldecode( $candidate['slug'] ),
			'to'          => $to,
			'collision'   => false,
			'status'      => 'planned',
		];

		if ( ! $post ) {
			$item['status'] = 'failed';

			return $item;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			$item['status'] = 'forbidden';

			return $item;
		}

		if ( '' === $to ) {
			$item['status'] = 'empty';

			return $item;
		}

		if ( $candidate['slug'] === $to ) {
			$item['status'] = 'unchanged';

			return $item;
		}

		$claim     = 'post:' . $post->post_type . ':' . $post->post_parent . ':' . $to;
		$available = wp_unique_post_slug( $to, $post->ID, $post->post_status, $post->post_type, $post->post_parent );

		if ( $available !== $to || isset( $this->claimed[ $claim ] ) ) {
			$item['collision'] = true;
			$item['status']    = 'collision';

			if ( $available !== $to ) {
				$item['available_slug'] = $available;
			}

			return $item;
		}

		$this->claimed[ $claim ] = true;

		if ( $dry_run ) {
			return $item;
		}

		// Same backup and update as WCTLR_Plugin::convertExistingSlugs(), so the old slugs stay backed up.
		add_post_meta( $post->ID, 'wbcr_wp_old_slug', $candidate['slug'] );

		$result = $wpdb->update( $wpdb->posts, [ 'post_name' => $to ], [ 'ID' => $post->ID ], [ '%s' ], [ '%d' ] );

		if ( false === $result ) {
			delete_post_meta( $post->ID, 'wbcr_wp_old_slug', $candidate['slug'] );
			$item['status'] = 'failed';

			return $item;
		}

		clean_post_cache( $post->ID );

		$item['status'] = 'updated';

		return $item;
	}

	/**
	 * @param array $candidate
	 * @param bool  $dry_run
	 *
	 * @return array
	 */
	private function processTerm( $candidate, $dry_run ) {
		global $wpdb;

		$term = get_term( $candidate['id'], $candidate['taxonomy'] );
		$to   = (string) wbcr_ctlr_sanitize_title( urldecode( $candidate['slug'] ) );
		$item = [
			'object_type' => 'term',
			'id'          => $candidate['id'],
			'subtype'     => $candidate['taxonomy'],
			'title'       => $term instanceof WP_Term ? $term->name : '',
			'from'        => urldecode( $candidate['slug'] ),
			'to'          => $to,
			'collision'   => false,
			'status'      => 'planned',
		];

		if ( ! $term instanceof WP_Term ) {
			$item['status'] = 'failed';

			return $item;
		}

		if ( ! current_user_can( 'edit_term', $term->term_id ) ) {
			$item['status'] = 'forbidden';

			return $item;
		}

		if ( '' === $to ) {
			$item['status'] = 'empty';

			return $item;
		}

		if ( $candidate['slug'] === $to ) {
			$item['status'] = 'unchanged';

			return $item;
		}

		$claim    = 'term:' . $term->taxonomy . ':' . $to;
		$existing = get_term_by( 'slug', $to, $term->taxonomy );

		if ( ( $existing instanceof WP_Term && (int) $existing->term_id !== (int) $term->term_id ) || isset( $this->claimed[ $claim ] ) ) {
			$item['collision']      = true;
			$item['status']         = 'collision';
			$item['available_slug'] = wp_unique_term_slug( $to, $term );

			return $item;
		}

		$this->claimed[ $claim ] = true;

		if ( $dry_run ) {
			return $item;
		}

		// Same backup and update as WCTLR_Plugin::convertExistingSlugs(), so the old slugs stay backed up.
		update_option( 'wbcr_wp_term_' . $term->term_id . '_old_slug', $candidate['slug'], false );

		$result = $wpdb->update( $wpdb->terms, [ 'slug' => $to ], [ 'term_id' => $term->term_id ], [ '%s' ], [ '%d' ] );

		if ( false === $result ) {
			delete_option( 'wbcr_wp_term_' . $term->term_id . '_old_slug' );
			$item['status'] = 'failed';

			return $item;
		}

		clean_term_cache( $term->term_id, $term->taxonomy );

		$item['status'] = 'updated';

		return $item;
	}

	/**
	 * @param array  $scope
	 * @param string $key
	 *
	 * @return string[]
	 */
	private function sanitizeNames( $scope, $key ) {
		if ( empty( $scope[ $key ] ) || ! is_array( $scope[ $key ] ) ) {
			return [];
		}

		$names = array_map( 'sanitize_key', array_filter( $scope[ $key ], 'is_scalar' ) );

		return array_values( array_unique( array_filter( $names ) ) );
	}

	/**
	 * @param array  $scope
	 * @param string $key
	 *
	 * @return int[]
	 */
	private function sanitizeIds( $scope, $key ) {
		if ( empty( $scope[ $key ] ) || ! is_array( $scope[ $key ] ) ) {
			return [];
		}

		$ids = array_map( 'absint', array_filter( $scope[ $key ], 'is_scalar' ) );
		$ids = array_values( array_unique( array_filter( $ids ) ) );

		return array_slice( $ids, 0, self::MAX_LIMIT );
	}
}
