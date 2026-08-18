<?php
/**
 * Core plugin bootstrap.
 *
 * @package HaloPress_Live2D
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HaloPress_Live2D {
	const OPTION_NAME = 'halopress_live2d_settings';

	/** @var HaloPress_Live2D|null */
	private static $instance = null;

	/**
	 * Return the singleton instance.
	 *
	 * @return HaloPress_Live2D
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Defaults used on activation and when reading incomplete settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'                => 1,
			'desktop_enabled'        => 1,
			'mobile_enabled'         => 0,
			'mobile_breakpoint'      => 768,
			'position'               => 'left',
			'horizontal_offset'      => 0,
			'bottom_offset'          => 0,
			'size'                   => 300,
			'welcome_message'        => '欢迎来到 {site_name}！',
			'idle_messages'          => "今天也要保持好心情呀。\n累了就休息一下吧。\n欢迎回来，我一直都在。",
			'idle_interval'          => 20,
			'hitokoto_enabled'       => 1,
			'hitokoto_api_url'       => 'https://v1.hitokoto.cn/',
			'model_url'              => '',
			'cubism2_core_url'       => '',
			'draggable'              => 0,
			'show_toggle_after_quit' => 1,
		);
	}

	/**
	 * Seed options without overwriting an existing installation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults() );
		}
	}

	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( HALOPRESS_LIVE2D_FILE ), array( $this, 'add_settings_link' ) );

		if ( is_admin() ) {
			require_once HALOPRESS_LIVE2D_DIR . 'includes/class-halopress-live2d-admin.php';
			new HaloPress_Live2D_Admin();
		}
	}

	/**
	 * Add a direct settings link on the Plugins screen.
	 *
	 * @param array<int,string> $links Existing action links.
	 * @return array<int,string>
	 */
	public function add_settings_link( $links ) {
		$url = admin_url( 'options-general.php?page=halopress-live2d' );
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $url ),
				esc_html__( '设置', 'halopress-live2d' )
			)
		);
		return $links;
	}

	/**
	 * Load translations when language files are provided.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'halopress-live2d',
			false,
			dirname( plugin_basename( HALOPRESS_LIVE2D_FILE ) ) . '/languages'
		);
	}

	/**
	 * Return normalized plugin settings.
	 *
	 * @return array<string,mixed>
	 */
	public function settings() {
		$value = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( is_array( $value ) ? $value : array(), self::defaults() );
	}

	/**
	 * Load the GPL widget only when it can render on a public page.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		if ( is_admin() || is_feed() || is_trackback() ) {
			return;
		}

		$settings = $this->settings();
		if ( empty( $settings['enabled'] ) || empty( $settings['model_url'] ) || empty( $settings['cubism2_core_url'] ) ) {
			return;
		}

		wp_enqueue_style(
			'halopress-live2d',
			HALOPRESS_LIVE2D_URL . 'live2d-widget/widget.css',
			array(),
			HALOPRESS_LIVE2D_VERSION
		);

		wp_enqueue_script(
			'halopress-live2d',
			HALOPRESS_LIVE2D_URL . 'live2d-widget/widget.js',
			array(),
			HALOPRESS_LIVE2D_VERSION,
			true
		);

		$idle_messages = preg_split( '/\R/u', (string) $settings['idle_messages'] );
		$idle_messages = array_values(
			array_filter(
				array_map( 'trim', is_array( $idle_messages ) ? $idle_messages : array() )
			)
		);

		$config = array(
			'coreUrl'             => (string) $settings['cubism2_core_url'],
			'modelUrl'            => (string) $settings['model_url'],
			'desktopEnabled'      => ! empty( $settings['desktop_enabled'] ),
			'mobileEnabled'       => ! empty( $settings['mobile_enabled'] ),
			'mobileBreakpoint'    => (int) $settings['mobile_breakpoint'],
			'position'            => (string) $settings['position'],
			'size'                => (int) $settings['size'],
			'horizontalOffset'    => (int) $settings['horizontal_offset'],
			'bottomOffset'        => (int) $settings['bottom_offset'],
			'welcomeMessage'      => str_replace( '{site_name}', get_bloginfo( 'name' ), (string) $settings['welcome_message'] ),
			'idleMessages'        => $idle_messages,
			'idleInterval'        => (int) $settings['idle_interval'],
			'hitokotoEnabled'     => ! empty( $settings['hitokoto_enabled'] ),
			'hitokotoApiUrl'      => (string) $settings['hitokoto_api_url'],
			'draggable'           => ! empty( $settings['draggable'] ),
			'showToggleAfterQuit' => ! empty( $settings['show_toggle_after_quit'] ),
			'infoUrl'             => 'https://github.com/YumengOvO/HaloPress-Live2D',
			'storagePrefix'       => 'halopress-live2d-',
			'labels'              => array(
				'hitokoto' => __( '一言', 'halopress-live2d' ),
				'photo'    => __( '拍照', 'halopress-live2d' ),
				'info'     => __( '项目信息', 'halopress-live2d' ),
				'quit'     => __( '关闭', 'halopress-live2d' ),
				'toggle'   => __( '显示 Live2D 看板娘', 'halopress-live2d' ),
				'loadError'=> __( '模型加载失败，请检查资源地址和跨域配置。', 'halopress-live2d' ),
				'photoError'=> __( '无法保存图片，请检查模型贴图的跨域配置。', 'halopress-live2d' ),
				'hitokotoError'=> __( '一言获取失败，请稍后再试。', 'halopress-live2d' ),
			),
		);

		wp_add_inline_script(
			'halopress-live2d',
			'window.HaloPressLive2DConfig = ' . wp_json_encode( $config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';',
			'before'
		);

		$position_property = 'right' === $settings['position'] ? 'right' : 'left';
		$inline_css = sprintf(
			':root{--halopress-live2d-size:%1$dpx;--halopress-live2d-bottom:%2$dpx;--halopress-live2d-%3$s:%4$dpx;}',
			(int) $settings['size'],
			(int) $settings['bottom_offset'],
			$position_property,
			(int) $settings['horizontal_offset']
		);
		wp_add_inline_style( 'halopress-live2d', $inline_css );
	}
}
