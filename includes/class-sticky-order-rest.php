<?php
/**
 * REST 字段 —— 把队列位置挂到文章响应上，而不是单开一个 endpoint。
 *
 * 无头前端拉文章列表时顺手就带回来了：不用多发请求，也不用按 slug 做二次关联。
 * 未置顶（或不在队列里）为 null，前端据此回退到「按日期」。
 *
 * 字段：
 *   sticky_order_rank   队列内的位置，0 起；不在队列里为 null
 *   sticky_order_group  所属队列的 key（单队列模式下是 default）；不在队列里为 null
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sticky_Order_REST {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$post_types = sticky_order_post_types();
		if ( ! $post_types ) {
			return;
		}

		$fields = array(
			'sticky_order_rank'  => array(
				'method'      => 'rank_of',
				'description' => 'Index within its sticky queue (0-based); null when the post is not queued.',
				'type'        => array( 'integer', 'null' ),
			),
			'sticky_order_group' => array(
				'method'      => 'group_of',
				'description' => 'Key of the queue the post belongs to; null when the post is not queued.',
				'type'        => array( 'string', 'null' ),
			),
		);

		foreach ( $fields as $name => $field ) {
			register_rest_field(
				$post_types,
				$name,
				array(
					'get_callback' => static function ( $post_arr ) use ( $field ) {
						$id = isset( $post_arr['id'] ) ? (int) $post_arr['id'] : 0;

						return call_user_func( array( 'Sticky_Order_Queue', $field['method'] ), $id );
					},
					'schema'       => array(
						'description' => $field['description'],
						'type'        => $field['type'],
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
				)
			);
		}
	}
}
