<?php
/**
* Plugin Name: Cyr to Lat reloaded
* Plugin URI: https://wordpress.org/plugins/cyr-and-lat/
* Description: Converts Cyrillic characters in post and term slugs to Latin characters. Useful for creating human-readable URLs. Allows to use both of cyrillic and latin slugs. <br><em>The plugin is in <strong>limited maintenance</strong>, we continue to provide security and critical bug fixes, but no new features.</em>
* Author: Themeisle
* Author URI: https://themeisle.com
* Version: 1.3.2
*/

// Exit if accessed directly
defined( 'ABSPATH' ) || die( 'Direct access not allowed.' );

if ( defined( 'WCTLR_PLUGIN_ACTIVE' ) ) {
	return;
}
define( 'WCTLR_PLUGIN_ACTIVE', true );
define ('WCTRL_BASEFILE', __FILE__);
define( 'WCTLR_PLUGIN_DIR', dirname( __FILE__ ) );
define( 'WCTLR_PLUGIN_BASE', plugin_basename( __FILE__ ) );
define( 'WCTLR_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'WCTLR_PRODUCT_SLUG', basename( dirname( __FILE__ ) ) );

require_once WCTLR_PLUGIN_DIR . '/vendor/autoload.php';
class WCTLR_Plugin {

	protected $is_ru_segment = false;

	public function __construct() {
		require_once WCTLR_PLUGIN_DIR . '/includes/functions.php';
		require_once WCTLR_PLUGIN_DIR . '/includes/3rd-party.php';

		$this->is_ru_segment = in_array( get_locale(), array( 'ru_RU', 'bel', 'kk', 'uk', 'bg', 'bg_BG', 'ka_GE' ) );

		add_filter( 'plugin_row_meta', array( $this, 'setPluginMeta' ), 10, 2 );
		add_filter( 'sanitize_title', 'wbcr_ctlr_sanitize_title', 9 );
		add_filter( 'sanitize_file_name', 'wbcr_ctlr_sanitize_title' );
		add_filter( 'init', array( $this, 'init' ) );

		add_filter( 'themeisle_sdk_products', [ __CLASS__, 'register_sdk' ] );
		add_filter( 'themeisle_sdk_blackfriday_data', [ $this, 'add_black_friday_data' ] );
	}

	/**
	 * Register product into SDK.
	 *
	 * @param array $products All products.
	 *
	 * @return array Registered product.
	 */
	public static function register_sdk( $products ) {
		$products[] = WCTRL_BASEFILE;

		return $products;
	}

	public function init() {
		require_once WCTLR_PLUGIN_DIR . '/includes/admin-notices.php';
		WCTLR_Admin_Notices::instance( __FILE__ );

		if ( isset( $_GET['wctlr_convert_existing_slugs'] ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'convert_exising_slugs' ) ) {
				wp_die( 'You don\'t have permission to perform this action.' );
			}

			$this->convertExistingSlugs();

			$url = remove_query_arg( array( 'wctlr_convert_existing_slugs', '_wpnonce' ) );
			$url = add_query_arg( 'wctlr_success_converted_slugs', 1, site_url( $url ) );

			wp_redirect( $url );
			die();
		}

		if ( isset( $_GET['wctlr_success_converted_slugs'] ) ) {
			add_action( 'admin_notices', array( $this, 'showSuccessContvertNotice' ) );
		}
	}

	public function showSuccessContvertNotice() {
		$close_url = remove_query_arg( array( 'wctlr_success_converted_slugs' ) );
		?>
        <div class="notice notice-success">
			<?php if ( $this->is_ru_segment ): ?>
                <p>Постоянные ссылки Ваших записей, страниц, рубрик, меток и других сущностей были успешно преобразованы
                    в латиницу! Вы можете <a href="<?= esc_url( $close_url ) ?>">закрыть уведомление</a>.</p>
			<?php else: ?>
                <p><?php printf( __( 'Permalinks to your posts, pages, categories, tags and other entities have been successfully converted to Latin! You can <a href="%s">Dismiss Notification</a>.', 'cyr-to-lat-reloaded' ), esc_url( $close_url ) ); ?></p>
			<?php endif; ?>
        </div>
		<?php
	}

	/**
	 * Link in plugin metadata
	 *
	 * @param $links
	 * @param $file
	 *
	 * @return array
	 */
	public function setPluginMeta( $links, $file ) {
		if ( $file == plugin_basename( __FILE__ ) ) {
				$links[] = '<a href="https://wordpress.org/support/plugin/cyr-and-lat/" target="_blank">' . __( 'Support', 'cyr-to-lat-reloaded' ) . '</a>';
		}

		return $links;
	}

	/**
	 * Translates for old entries, headings, tags in which the slug has already been created,
	 * this method also provides the ability to rollback and compatibility with the Cyrlitera plugin.
	 *
	 * @since 1.1.1
	 * @return void
	 */
	public function convertExistingSlugs() {
		global $wpdb;

		$posts = $wpdb->get_results( "SELECT ID, post_name FROM {$wpdb->posts} WHERE post_name REGEXP('[^_A-Za-z0-9\-]+') AND post_status IN ('publish', 'future', 'private')" );

		foreach ( (array) $posts as $post ) {
			$sanitized_name = wbcr_ctlr_sanitize_title( urldecode( $post->post_name ) );

			if ( $post->post_name != $sanitized_name ) {
				add_post_meta( $post->ID, 'wbcr_wp_old_slug', $post->post_name );

				$wpdb->update( $wpdb->posts, array( 'post_name' => $sanitized_name ), array( 'ID' => $post->ID ), array( '%s' ), array( '%d' ) );
			}
		}

		$terms = $wpdb->get_results( "SELECT term_id, slug FROM {$wpdb->terms} WHERE slug REGEXP('[^_A-Za-z0-9\-]+') " );

		foreach ( (array) $terms as $term ) {
			$sanitized_slug = wbcr_ctlr_sanitize_title( urldecode( $term->slug ) );

			if ( $term->slug != $sanitized_slug ) {
				update_option( 'wbcr_wp_term_' . $term->term_id . '_old_slug', $term->slug, false );
				$wpdb->update( $wpdb->terms, array( 'slug' => $sanitized_slug ), array( 'term_id' => $term->term_id ), array( '%s' ), array( '%d' ) );
			}
		}

		// Advanced Custom Fields integration - Translates existing forum and topic slugs.
		wbcr_ctlr_conver_asgaros_forum_existing_slugs();

		// Plugin integration BuddyPress
		// Our integration makes use of the translation of already created slugs of groups.
		wbcr_ctlr_convert_buddypress_existings_slugs();
	}

	public function add_black_friday_data( $configs ) {
		$config = $configs['default'];

		if ( defined( 'NEVE_VERSION' ) ) {
			return $configs;
		}

		// translators: 1. Number of free licenses, 2. The price of the product.
		$config['message'] = sprintf( __( 'You\'re using Cyr to Lat Reloaded, and the team behind it is celebrating Black Friday by giving away %1$s licences of Neve Pro. A premium WordPress theme worth %2$s, packed with starter sites, a header builder, and WooCommerce layouts. Claim yours before they run out.', 'cyr-to-lat-reloaded' ), 100, '$69' );
		$config['cta_label'] = __( 'Get Neve Pro free', 'cyr-to-lat-reloaded' );
		$config['plugin_meta_message'] = __( 'Black Friday Sale - Get Neve Pro free', 'cyr-to-lat-reloaded' );
		$config['sale_url'] = add_query_arg(
			array(
				'utm_term' => 'free',
			),
			tsdk_translate_link( tsdk_utmify( 'https://themeisle.link/neve-claim-bf', 'bfcm', 'cyr-to-lat' ) )
		);

		$configs[ WCTLR_PRODUCT_SLUG ] = $config;

		return $configs;
	}
}

new WCTLR_Plugin();

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ctl_add_upgrade_link' );

function ctl_add_upgrade_link( $links ) {
	// Generate the nonce-protected install URL for Cyrlitera
	$install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=cyrlitera' ), 'install-plugin_cyrlitera' );

	// Create the link
	$upgrade_link = '<a href="' . esc_url( $install_url ) . '" style="color: #d63638; font-weight: bold;">Migrate to Cyrlitera</a>';

	// Add it to the list of links
	array_push( $links, $upgrade_link );
	return $links;
}
?>
