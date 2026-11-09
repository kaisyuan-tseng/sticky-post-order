<?php
/**
 * Plugin Name: Sticky Post Order
 * Description: Turns sticky posts into ordered queues. Sticky decides which posts are included; this plugin decides their order, independent of publish dates — no need to backdate a post just to promote it. Sort by drag and drop from the admin page under Posts, optionally grouped by any taxonomy (or use one shared queue). Exposes the read-only REST fields sticky_order_rank and sticky_order_group for headless front ends. Newly stickied posts append to the tail; unstickied or unpublished posts drop out automatically. No core, theme, or date changes.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: kaisyuan-tseng
 * License: GPL-2.0-or-later
 * Text Domain: sticky-order
 *
 * Generic plugin: no site-specific term names, post types, or UI copy.
 * Site policy is injected through the filters listed in readme.txt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STICKY_ORDER_VERSION', '1.0.0' );
define( 'STICKY_ORDER_DIR', plugin_dir_path( __FILE__ ) );
define( 'STICKY_ORDER_URL', plugin_dir_url( __FILE__ ) );

/* ------------------------------------------------------------------ 公共 API */

/**
 * 分组清单：array( group_slug => 显示名 )。
 *
 * 返回空数组时插件退化成「单队列」模式 —— 所有置顶文章共用一条队列，
 * 后台只有一栏，REST 的 rank 是全局序号。
 */
function sticky_order_groups() {
	$groups = (array) apply_filters( 'sticky_order_groups', array() );

	$normalized = array();
	foreach ( $groups as $slug => $label ) {
		$slug = (string) $slug;
		if ( '' === $slug ) {
			continue;
		}
		$normalized[ $slug ] = '' !== (string) $label ? (string) $label : $slug;
	}

	return $normalized;
}

/**
 * 队列 key 列表：把「没配分组 = 单队列 default」这条规则收在一处，
 * 队列计算、后台渲染、保存校验都读它，不必各自判断一遍。
 *
 * @return string[]
 */
function sticky_order_group_keys() {
	$groups = sticky_order_groups();

	return $groups ? array_keys( $groups ) : array( 'default' );
}

/** 分组依据的 taxonomy（按 term slug 与分组 slug 对齐） */
function sticky_order_group_taxonomy() {
	return (string) apply_filters( 'sticky_order_group_taxonomy', 'category' );
}

/** 参与排序的文章类型 */
function sticky_order_post_types() {
	$types = (array) apply_filters( 'sticky_order_post_types', array( 'post' ) );

	return array_values( array_filter( array_map( 'strval', $types ) ) );
}

/** 存队列的 option 名 */
function sticky_order_option_name() {
	return (string) apply_filters( 'sticky_order_option_name', 'sticky_order_queues' );
}

/** 后台页所需权限 */
function sticky_order_capability() {
	return (string) apply_filters( 'sticky_order_capability', 'edit_others_posts' );
}

/** 后台菜单挂载位置 */
function sticky_order_menu_parent() {
	return (string) apply_filters( 'sticky_order_menu_parent', 'edit.php' );
}

/**
 * 后台界面文案。默认中文；非中文站点用 sticky_order_labels 整包替换。
 * 带 %s / %d 的是 printf 模板，替换时占位符要保持一致。
 */
function sticky_order_labels() {
	$defaults = array(
		'page_title'    => '置顶排序',
		'menu_title'    => '置顶排序',
		'single_title'  => '全部置顶文章',
		'intro'         => '这里的顺序只影响前台读取置顶队列的地方（列表首屏、导航精选等）；文章列表本身、相关文章、发布日期都不受影响。想让某篇进队列，仍然在编辑页勾选「置顶」。',
		'empty_group'   => '这个分组还没有置顶文章。在编辑页勾选「置顶」后，它会自动出现在这里。',
		'drag_hint'     => '拖动左侧手柄调序，越靠上越靠前。',
		'save'          => '保存排序',
		'saved'         => '排序已保存。',
		'summary'       => '全站 %1$d 篇置顶，其中 %2$d 篇进入队列。',
		'view_all'      => '查看 %s 的全部文章',
		'no_thumb'      => '无封面',
		'orphans_title' => '这些置顶文章不属于任何分组，不会出现在队列里：',
		'orphans_hint'  => '给它们指定一个分组对应的分类即可。',
	);

	/* 缺 key 时回退默认值，站点只改一两句也不用整包重写 */
	return array_merge( $defaults, (array) apply_filters( 'sticky_order_labels', $defaults ) );
}

/* ------------------------------------------------------------------- 启动 */

require_once STICKY_ORDER_DIR . 'includes/class-sticky-order-queue.php';
require_once STICKY_ORDER_DIR . 'includes/class-sticky-order-rest.php';
require_once STICKY_ORDER_DIR . 'includes/class-sticky-order-admin.php';

Sticky_Order_REST::init();
Sticky_Order_Admin::init();
