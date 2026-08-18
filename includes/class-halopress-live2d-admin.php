<?php
/**
 * Native WordPress settings page.
 *
 * @package HaloPress_Live2D
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HaloPress_Live2D_Admin {
	const PAGE_SLUG = 'halopress-live2d';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'configuration_notice' ) );
	}

	/** @return void */
	public function add_settings_page() {
		add_options_page(
			__( 'HaloPress-Live2D 设置', 'halopress-live2d' ),
			__( 'HaloPress-Live2D', 'halopress-live2d' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/** @return void */
	public function register_settings() {
		register_setting(
			'halopress_live2d',
			HaloPress_Live2D::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => HaloPress_Live2D::defaults(),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Validate settings before they are stored.
	 *
	 * @param mixed $input Submitted value.
	 * @return array<string,mixed>
	 */
	public function sanitize_settings( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = HaloPress_Live2D::defaults();
		$output   = $defaults;

		foreach ( array( 'enabled', 'desktop_enabled', 'mobile_enabled', 'hitokoto_enabled', 'draggable', 'show_toggle_after_quit' ) as $key ) {
			$output[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$output['position']          = isset( $input['position'] ) && 'right' === $input['position'] ? 'right' : 'left';
		$output['size']              = $this->bounded_integer( $input, 'size', 120, 800, $defaults['size'] );
		$output['horizontal_offset'] = $this->bounded_integer( $input, 'horizontal_offset', -500, 500, $defaults['horizontal_offset'] );
		$output['bottom_offset']     = $this->bounded_integer( $input, 'bottom_offset', -500, 500, $defaults['bottom_offset'] );
		$output['mobile_breakpoint'] = $this->bounded_integer( $input, 'mobile_breakpoint', 320, 1920, $defaults['mobile_breakpoint'] );
		$output['idle_interval']     = $this->bounded_integer( $input, 'idle_interval', 10, 3600, $defaults['idle_interval'] );

		$output['welcome_message'] = isset( $input['welcome_message'] ) && is_string( $input['welcome_message'] )
			? sanitize_text_field( wp_unslash( $input['welcome_message'] ) )
			: $defaults['welcome_message'];
		$output['idle_messages'] = isset( $input['idle_messages'] ) && is_string( $input['idle_messages'] )
			? sanitize_textarea_field( wp_unslash( $input['idle_messages'] ) )
			: $defaults['idle_messages'];

		foreach ( array( 'model_url', 'cubism2_core_url', 'hitokoto_api_url' ) as $key ) {
			$raw            = isset( $input[ $key ] ) && is_string( $input[ $key ] ) ? trim( wp_unslash( $input[ $key ] ) ) : '';
			$output[ $key ] = esc_url_raw( $raw, array( 'http', 'https' ) );
			if ( '' !== $raw && '' === $output[ $key ] ) {
				add_settings_error(
					HaloPress_Live2D::OPTION_NAME,
					'invalid_' . $key,
					__( '资源地址必须是有效的 HTTP 或 HTTPS URL。', 'halopress-live2d' )
				);
			}
		}

		return $output;
	}

	/**
	 * Normalize a bounded integer setting.
	 *
	 * @param array<string,mixed> $input Input values.
	 * @param string              $key Setting key.
	 * @param int                 $minimum Minimum value.
	 * @param int                 $maximum Maximum value.
	 * @param int                 $fallback Fallback value.
	 * @return int
	 */
	private function bounded_integer( $input, $key, $minimum, $maximum, $fallback ) {
		if ( ! isset( $input[ $key ] ) || ! is_numeric( $input[ $key ] ) ) {
			return (int) $fallback;
		}

		return max( $minimum, min( $maximum, (int) $input[ $key ] ) );
	}

	/** @return void */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'halopress-live2d-admin',
			HALOPRESS_LIVE2D_URL . 'assets/admin.css',
			array(),
			HALOPRESS_LIVE2D_VERSION
		);
	}

	/** @return void */
	public function configuration_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen && 'settings_page_' . self::PAGE_SLUG === $screen->id ) {
			return;
		}

		$settings = wp_parse_args( get_option( HaloPress_Live2D::OPTION_NAME, array() ), HaloPress_Live2D::defaults() );
		if ( empty( $settings['enabled'] ) || ( ! empty( $settings['model_url'] ) && ! empty( $settings['cubism2_core_url'] ) ) ) {
			return;
		}

		$url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'HaloPress-Live2D 尚未加载：请先填写 Cubism 2 Core URL 和模型 JSON URL。', 'halopress-live2d' ),
			esc_url( $url ),
			esc_html__( '前往设置', 'halopress-live2d' )
		);
	}

	/** @return void */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = wp_parse_args( get_option( HaloPress_Live2D::OPTION_NAME, array() ), HaloPress_Live2D::defaults() );
		?>
		<div class="wrap halopress-live2d-admin">
			<h1><?php esc_html_e( 'HaloPress-Live2D', 'halopress-live2d' ); ?></h1>
			<p class="description"><?php esc_html_e( '为网站配置一个不携带模型和 Cubism 运行时的 Live2D 看板娘。', 'halopress-live2d' ); ?></p>

			<?php settings_errors( HaloPress_Live2D::OPTION_NAME ); ?>

			<form action="options.php" method="post">
				<?php settings_fields( 'halopress_live2d' ); ?>

				<div class="halopress-card">
					<h2><?php esc_html_e( '基本设置', 'halopress-live2d' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php $this->checkbox_row( $settings, 'enabled', __( '启用看板娘', 'halopress-live2d' ), __( '关闭后不会向前台加载任何插件资源。', 'halopress-live2d' ) ); ?>
						<?php $this->checkbox_row( $settings, 'desktop_enabled', __( '桌面端显示', 'halopress-live2d' ), '' ); ?>
						<?php $this->checkbox_row( $settings, 'mobile_enabled', __( '移动端显示', 'halopress-live2d' ), __( '默认关闭，避免遮挡小屏幕内容。', 'halopress-live2d' ) ); ?>
						<?php $this->number_row( $settings, 'mobile_breakpoint', __( '移动端断点', 'halopress-live2d' ), 320, 1920, __( '像素；视口宽度不大于此值时视为移动端。', 'halopress-live2d' ) ); ?>
					</table>
				</div>

				<div class="halopress-card">
					<h2><?php esc_html_e( '模型资源', 'halopress-live2d' ); ?></h2>
					<p><?php esc_html_e( '插件不提供、代理或保存 Cubism Core 和模型资源。请确保地址允许浏览器跨域访问，并确认你拥有相应使用权。Core URL 指向的 JavaScript 会在访客浏览器中执行，因此只能填写可信来源。', 'halopress-live2d' ); ?></p>
					<table class="form-table" role="presentation">
						<?php $this->url_row( $settings, 'cubism2_core_url', __( 'Cubism 2 Core URL', 'halopress-live2d' ), __( '指向兼容的 Cubism 2 Web Core JavaScript 文件。', 'halopress-live2d' ) ); ?>
						<?php $this->url_row( $settings, 'model_url', __( '模型 JSON URL', 'halopress-live2d' ), __( '指向 Cubism 2 模型的 model.json 或 index.json 文件。', 'halopress-live2d' ) ); ?>
					</table>
				</div>

				<div class="halopress-card">
					<h2><?php esc_html_e( '位置与大小', 'halopress-live2d' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="halopress-position"><?php esc_html_e( '位置', 'halopress-live2d' ); ?></label></th>
							<td>
								<select id="halopress-position" name="<?php echo esc_attr( HaloPress_Live2D::OPTION_NAME ); ?>[position]">
									<option value="left" <?php selected( $settings['position'], 'left' ); ?>><?php esc_html_e( '左下角', 'halopress-live2d' ); ?></option>
									<option value="right" <?php selected( $settings['position'], 'right' ); ?>><?php esc_html_e( '右下角', 'halopress-live2d' ); ?></option>
								</select>
							</td>
						</tr>
						<?php $this->number_row( $settings, 'size', __( '显示尺寸', 'halopress-live2d' ), 120, 800, __( '像素；画布保持正方形。', 'halopress-live2d' ) ); ?>
						<?php $this->number_row( $settings, 'horizontal_offset', __( '水平偏移', 'halopress-live2d' ), -500, 500, __( '像素；正数向页面内侧移动。', 'halopress-live2d' ) ); ?>
						<?php $this->number_row( $settings, 'bottom_offset', __( '底部偏移', 'halopress-live2d' ), -500, 500, __( '像素；正数向上移动。', 'halopress-live2d' ) ); ?>
					</table>
				</div>

				<div class="halopress-card">
					<h2><?php esc_html_e( '对话', 'halopress-live2d' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="halopress-welcome-message"><?php esc_html_e( '欢迎语', 'halopress-live2d' ); ?></label></th>
							<td><input class="regular-text" id="halopress-welcome-message" name="<?php echo esc_attr( HaloPress_Live2D::OPTION_NAME ); ?>[welcome_message]" type="text" value="<?php echo esc_attr( $settings['welcome_message'] ); ?>"><p class="description"><?php esc_html_e( '支持 {site_name} 占位符。', 'halopress-live2d' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="halopress-idle-messages"><?php esc_html_e( '空闲提示语', 'halopress-live2d' ); ?></label></th>
							<td><textarea class="large-text" id="halopress-idle-messages" name="<?php echo esc_attr( HaloPress_Live2D::OPTION_NAME ); ?>[idle_messages]" rows="6"><?php echo esc_textarea( $settings['idle_messages'] ); ?></textarea><p class="description"><?php esc_html_e( '每行一条，触发时随机显示。', 'halopress-live2d' ); ?></p></td>
						</tr>
						<?php $this->number_row( $settings, 'idle_interval', __( '空闲触发间隔', 'halopress-live2d' ), 10, 3600, __( '秒；访客持续无操作时显示随机提示。', 'halopress-live2d' ) ); ?>
						<?php $this->checkbox_row( $settings, 'hitokoto_enabled', __( '启用一言按钮', 'halopress-live2d' ), __( '仅在访客点击按钮时请求外部 API。', 'halopress-live2d' ) ); ?>
						<?php $this->url_row( $settings, 'hitokoto_api_url', __( '一言 API URL', 'halopress-live2d' ), __( 'API 应返回包含 hitokoto 或 text 字段的 JSON。', 'halopress-live2d' ) ); ?>
					</table>
				</div>

				<div class="halopress-card">
					<h2><?php esc_html_e( '行为', 'halopress-live2d' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php $this->checkbox_row( $settings, 'draggable', __( '允许拖动', 'halopress-live2d' ), __( '访客可拖动画布，位置仅保存在当前浏览器。', 'halopress-live2d' ) ); ?>
						<?php $this->checkbox_row( $settings, 'show_toggle_after_quit', __( '关闭后显示唤醒按钮', 'halopress-live2d' ), __( '关闭时隐藏 24 小时；取消勾选后将保持隐藏，直到访客清理站点存储。', 'halopress-live2d' ) ); ?>
					</table>
					<p><?php esc_html_e( '工具栏固定保留：一言、拍照、项目信息、关闭。模型切换和换装功能不在首版范围内。', 'halopress-live2d' ); ?></p>
				</div>

				<?php submit_button( __( '保存设置', 'halopress-live2d' ) ); ?>
			</form>

			<div class="halopress-card halopress-license-note">
				<h2><?php esc_html_e( '许可证提醒', 'halopress-live2d' ); ?></h2>
				<p><?php esc_html_e( 'HaloPress-Live2D 本体采用 GPL-3.0。模型、贴图、动作及 Cubism 运行时不属于本插件，使用者必须分别遵守其权利人的许可条款。', 'halopress-live2d' ); ?></p>
				<p><a href="https://github.com/YumengOvO/HaloPress-Live2D" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '查看项目与完整许可证说明', 'halopress-live2d' ); ?></a></p>
			</div>
		</div>
		<?php
	}

	/** Render a checkbox setting row. */
	private function checkbox_row( $settings, $key, $label, $description ) {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label><input name="<?php echo esc_attr( HaloPress_Live2D::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" type="checkbox" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?>> <?php echo esc_html( $description ); ?></label>
			</td>
		</tr>
		<?php
	}

	/** Render a numeric setting row. */
	private function number_row( $settings, $key, $label, $minimum, $maximum, $description ) {
		?>
		<tr>
			<th scope="row"><label for="halopress-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><input class="small-text" id="halopress-<?php echo esc_attr( $key ); ?>" min="<?php echo esc_attr( $minimum ); ?>" max="<?php echo esc_attr( $maximum ); ?>" name="<?php echo esc_attr( HaloPress_Live2D::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" type="number" value="<?php echo esc_attr( $settings[ $key ] ); ?>"> <span class="description"><?php echo esc_html( $description ); ?></span></td>
		</tr>
		<?php
	}

	/** Render a URL setting row. */
	private function url_row( $settings, $key, $label, $description ) {
		?>
		<tr>
			<th scope="row"><label for="halopress-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><input class="large-text code" id="halopress-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( HaloPress_Live2D::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" type="url" value="<?php echo esc_attr( $settings[ $key ] ); ?>" placeholder="https://"> <p class="description"><?php echo esc_html( $description ); ?></p></td>
		</tr>
		<?php
	}
}
