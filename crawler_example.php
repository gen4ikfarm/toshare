<?php

namespace Example\inc;

/**
 * Class Crawler
 *
 * @package Example\inc
 */
class Crawler {

	/**
	 * Reusable class instance
	 *
	 * @var Crawler class instance
	 */
	private static $instance;


	/**
	 * Initialize Crawler class
	 *
	 * @return Crawler
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Force crawling process
	 *
	 * @param false $return Return sitemap html.
	 *
	 * @return array|string|void
	 */
	public function force_crawl_start( $return = true ) {
		$event = $this->register_a_job( 2 );
		if ( is_wp_error( $event ) ) {
			return [ 'error' => esc_attr( $event->get_error_message() ) ];
		}

		if ( ! $return ) {
			return;
		}
		$links = get_option( EXAMPLE_DATA_OPTION, false );
		if ( is_array( $links ) ) {
			return $this->generate_sitemap( $links, site_url(), false );
		}

		return esc_attr__( 'Task successfully started. Please wait a minute and refresh a page.', 'rocket-test' );
	}


	/**
	 * Start crawling hook
	 */
	public function crawl_start() {
		$this->start();
	}


	/**
	 * Start crawling
	 *
	 * @param int $range Delay before start a new event.
	 *
	 * @return array|array[]
	 */
	public function start( $range = 3600 ) {
		$this->crear_data();

		$site_url = site_url();
		$content  = $this->get_page( $site_url );

		if ( empty( $content ) ) {
			return [ 'error' => esc_attr__( 'Empty response, please try again latter', 'rocket-test' ) ];
		}

		if ( is_wp_error( $content ) ) {
			return [ 'error' => esc_attr( $content->get_error_message() ) ];
		}

		$links = $this->get_links( $content, $site_url );

		update_option( EXAMPLE_DATA_OPTION, $links );

		$sitemap = $this->generate_sitemap( $links, $site_url );
		$this->put_contents( EXAMPLE_SITEMAP_PATH, $sitemap );

		// Add a new cron job.
		$event = $this->register_a_job( $range );
		if ( is_wp_error( $event ) ) {
			return [ 'error' => esc_attr( $event->get_error_message() ) ];
		}

	}


	/**
	 * Generates a sitemap html from an array.
	 *
	 * @param array  $links Array of links.
	 * @param string $site_url Current site url.
	 * @param bool   $full_page Render a full page.
	 *
	 * @return string
	 */
	public function generate_sitemap( $links, $site_url, $full_page = true ) {

		$sitemap = '<ol>
		<li><a href="' . esc_attr( $site_url ) . '">' . esc_attr__( 'Homepage', 'rocket-test' ) . '</a></li>';
		foreach ( $links as $link => $text ) {
			$sitemap .= '<li><a href="'
				. esc_attr( $link )
				. '">'
				. esc_attr( $text[0] )
				. '</a> ( '
				. esc_attr( $link )
				. ' )</li>';
		}

		$sitemap .= '</ol>';

		if ( $full_page ) {
			$output = '<html>
				<head></head>
				<body>
				<h2>Sitemap</h2>
				' . $sitemap . '
				</body>
				</html>';
		} else {
			$output = $sitemap;
		}

		return $output;
	}


	/**
	 * Clear existing files and DB records
	 */
	public function crear_data() {
		// Delete existing sitemap file.
		if ( file_exists( EXAMPLE_SITEMAP_PATH ) ) {
			wp_delete_file( EXAMPLE_SITEMAP_PATH );
		}

		// Delete existing content backup file.
		if ( file_exists( EXAMPLE_HOMEPAGE_BACKUP_PATH ) ) {
			wp_delete_file( EXAMPLE_HOMEPAGE_BACKUP_PATH );
		}

		// Remove DB records.
		delete_option( EXAMPLE_DATA_OPTION );
	}


	/**
	 * Write content to a file.
	 *
	 * @param string $file_path Path to file.
	 * @param string $content File contents.
	 *
	 * @return bool
	 */
	public function put_contents( $file_path, $content ) {
		global $wp_filesystem;
		if ( ! is_object( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		return $wp_filesystem->put_contents( $file_path, $content );
	}


	/**
	 * Register a new wp cron job
	 *
	 * @param int $range Delay before start a new event.
	 *
	 * @return bool|WP_Error
	 */
	public function register_a_job( $range = 3600 ) {
		wp_clear_scheduled_hook( EXAMPLE_EVENT_NAME );

		return wp_schedule_single_event( time() + $range, EXAMPLE_EVENT_NAME, [], true );
	}


	/**
	 * Parse a string to link
	 *
	 * @param array  $content Page content.
	 * @param string $domain Homepage url.
	 *
	 * @return array
	 */
	public function get_links( array $content, string $domain ) {
		$links = [];

		if ( is_array( $content ) && array_key_exists( 'body', $content ) ) {
			$page_content = $content['body'];
			$this->put_contents( EXAMPLE_HOMEPAGE_BACKUP_PATH, $page_content );
			if ( preg_match_all( "/<a\s[^>]*href=[\"']??([^\" '>]*?)[^>]*>(.*)<\/a>/siU", $page_content, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {

					if ( strpos( $match[0], $domain ) <= 0 || in_array( $match[1], $links, true ) ) {
						continue;
					}
					$links[ $match[1] ] = [ $match[2] ];
				}
			}
		}

		return $links;
	}


	/**
	 * Gets a page source
	 *
	 * @param string $page_url Remote page url.
	 */
	public function get_page( string $page_url = '' ) {
		if ( empty( $page_url ) ) {
			return;
		}

		return wp_remote_get( $page_url );
	}
}
