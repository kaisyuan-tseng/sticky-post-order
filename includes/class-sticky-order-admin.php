<?php
/**
 * 后台页面：把队列按分组铺开，拖拽调序，一次保存。
 *
 * 提交顺序 = DOM 顺序：每个 li 里带一个同名 hidden，拖拽只移动 li，
 * 所以不需要把顺序序列化成 JSON，也不需要额外的前端状态。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sticky_Order_Admin {

	/** 本页的 hook suffix，用于只在本页加载资源 */
	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_sticky_order_save', array( __CLASS__, 'save' ) );
	}

	public static function menu() {
		$labels = sticky_order_labels();

		self::$hook = add_submenu_page(
			sticky_order_menu_parent(),
			$labels['page_title'],
			$labels['menu_title'],
			sticky_order_capability(),
			'sticky-order',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * @param string $hook 当前后台页的 hook suffix。
	 */
	public static function assets( $hook ) {
		if ( $hook !== self::$hook ) {
			return;
		}

		wp_enqueue_style( 'sticky-order-admin', STICKY_ORDER_URL . 'assets/admin.css', array(), STICKY_ORDER_VERSION );
		wp_enqueue_script(
			'sticky-order-admin',
			STICKY_ORDER_URL . 'assets/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			STICKY_ORDER_VERSION,
			true
		);
	}

	/** 保存顺序 */
	public static function save() {
		if ( ! current_user_can( sticky_order_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'sticky-order' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'sticky_order_save' );

		$raw = isset( $_POST['sticky_order'] ) ? (array) $_POST['sticky_order'] : array();

		/* 只接受插件自己声明过的分组，避免任意 key 被写进 option */
		$clean = array();
		foreach ( sticky_order_group_keys() as $group ) {
			$ids = isset( $raw[ $group ] ) ? array_map( 'intval', (array) $raw[ $group ] ) : array();
			$ids = array_values( array_unique( array_filter( $ids ) ) );

			$clean[ (string) $group ] = $ids;
		}

		update_option( sticky_order_option_name(), $clean, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sticky-order',
					'updated' => '1',
				),
				admin_url( sticky_order_menu_parent() )
			)
		);
		exit;
	}

	/** 一行：手柄 + 缩略图 + 标题 + 作者 + 日期 */
	private static function item( $id, $group, $post, $labels ) {
		$thumb  = get_the_post_thumbnail_url( $id, 'thumbnail' );
		$edit   = get_edit_post_link( $id );
		$date   = get_the_date( (string) get_option( 'date_format' ), $post );
		$author = get_the_author_meta( 'display_name', (int) $post->post_author );
		?>
		<li class="sticky-order-item" data-id="<?php echo esc_attr( (string) $id ); ?>">
			<input type="hidden" name="sticky_order[<?php echo esc_attr( (string) $group ); ?>][]" value="<?php echo esc_attr( (string) $id ); ?>">
			<span class="sticky-order-item__grip dashicons dashicons-menu" aria-hidden="true"></span>
			<span class="sticky-order-item__thumb">
				<?php if ( $thumb ) : ?>
					<img src="<?php echo esc_url( $thumb ); ?>" alt="" width="60" height="34">
				<?php else : ?>
					<span class="sticky-order-item__thumb-empty"><?php echo esc_html( $labels['no_thumb'] ); ?></span>
				<?php endif; ?>
			</span>
			<span class="sticky-order-item__main">
				<?php if ( $edit ) : ?>
					<a class="sticky-order-item__title" href="<?php echo esc_url( $edit ); ?>">
						<?php echo esc_html( get_the_title( $id ) ); ?>
					</a>
				<?php else : ?>
					<span class="sticky-order-item__title"><?php echo esc_html( get_the_title( $id ) ); ?></span>
				<?php endif; ?>
				<?php if ( $author ) : ?>
					<span class="sticky-order-item__author"><?php echo esc_html( $author ); ?></span>
				<?php endif; ?>
				<span class="sticky-order-item__meta"><?php echo esc_html( $date ); ?></span>
			</span>
		</li>
		<?php
	}

	/** 页面 */
	public static function render() {
		if ( ! current_user_can( sticky_order_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'sticky-order' ), '', array( 'response' => 403 ) );
		}

		$labels = sticky_order_labels();
		$data   = Sticky_Order_Queue::resolve();
		$queues = $data['queues'];
		$by_id  = $data['posts'];

		$groups = $data['single'] ? array( 'default' => $labels['single_title'] ) : sticky_order_groups();

		$orphans = Sticky_Order_Queue::orphans();
		?>
		<div class="wrap sticky-order">
			<h1 class="wp-heading-inline"><?php echo esc_html( $labels['page_title'] ); ?></h1>
			<hr class="wp-header-end">

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 只读提示位。 ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $labels['saved'] ); ?></p></div>
			<?php endif; ?>

			<p class="description sticky-order__intro"><?php echo wp_kses_post( $labels['intro'] ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sticky_order_save">
				<?php wp_nonce_field( 'sticky_order_save' ); ?>

				<?php foreach ( $groups as $slug => $label ) : ?>
				<?php
				$slug = (string) $slug;
				$ids  = isset( $queues[ $slug ] ) ? $queues[ $slug ] : array();
				?>
					<div class="postbox sticky-order-lane">
						<div class="postbox-header">
							<h2 class="hndle"><?php echo esc_html( $label ); ?></h2>
						</div>

						<div class="inside">
							<?php if ( ! $ids ) : ?>
								<p class="sticky-order-lane__empty"><?php echo wp_kses_post( $labels['empty_group'] ); ?></p>
							<?php else : ?>
								<ul class="sticky-order-list" data-group="<?php echo esc_attr( $slug ); ?>">
									<?php
									foreach ( $ids as $id ) {
										if ( isset( $by_id[ $id ] ) ) {
											self::item( $id, $slug, $by_id[ $id ], $labels );
										}
									}
									?>
								</ul>
								<p class="description sticky-order-lane__hint"><?php echo esc_html( $labels['drag_hint'] ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>

				<?php if ( $orphans ) : ?>
					<div class="notice notice-warning inline sticky-order-orphans">
						<p><strong><?php echo esc_html( $labels['orphans_title'] ); ?></strong></p>
						<ul>
							<?php foreach ( $orphans as $id ) : ?>
								<li>
									<?php $edit = get_edit_post_link( $id ); ?>
									<?php if ( $edit ) : ?>
										<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a>
									<?php else : ?>
										<?php echo esc_html( get_the_title( $id ) ); ?>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
						<p><?php echo esc_html( $labels['orphans_hint'] ); ?></p>
					</div>
				<?php endif; ?>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php echo esc_html( $labels['save'] ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}
}
