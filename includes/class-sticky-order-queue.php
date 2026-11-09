<?php
/**
 * 队列计算 —— 插件的全部业务规则都在这里，且只在请求内算一次。
 *
 * 规则：
 *   1. 只有「已发布 + 已置顶」的文章进队列 —— 置顶开关仍由 WordPress 原生的
 *      sticky 机制管，插件不新增任何入口；
 *   2. 顺序以本插件保存的 option 为准；
 *   3. option 里没有的新置顶，按发布日期倒序追加到队伍末尾（不会插队）；
 *   4. option 里有、但已经取消置顶或转成草稿的，直接丢弃 —— 取消置顶即刻生效；
 *   5. 分组模式下，一篇文章只进一条队列：取它第一个匹配到分组 slug 的 term。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sticky_Order_Queue {

	/** 单次请求内的结果缓存 */
	private static $cache = null;

	/**
	 * 解析出全部队列。
	 *
	 * @return array{
	 *     queues: array<string,int[]>,
	 *     ranks: array<int,int>,
	 *     groups: array<int,string>,
	 *     posts: array<int,WP_Post>,
	 *     single: bool
	 * }
	 */
	public static function resolve() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$groups = sticky_order_groups();
		$queues = array_fill_keys( sticky_order_group_keys(), array() );

		$result = array(
			'queues' => $queues,
			'ranks'  => array(),
			'groups' => array(),
			'posts'  => array(),
			'single' => empty( $groups ),
		);

		$sticky = array_values( array_unique( array_map( 'intval', (array) get_option( 'sticky_posts', array() ) ) ) );
		if ( ! $sticky ) {
			self::$cache = $result;
			return self::$cache;
		}

		$posts = get_posts(
			array(
				'post__in'            => $sticky,
				'post_type'           => sticky_order_post_types(),
				'post_status'         => 'publish',
				'posts_per_page'      => -1,
				'ignore_sticky_posts' => true,
				'orderby'             => 'date',
				'order'               => 'DESC',
			)
		);

		/* 归组：候选集保持发布日期倒序，作为「未排序部分」的默认顺序 */
		$buckets = $queues;
		$indexed = array();

		foreach ( $posts as $post ) {
			$id = (int) $post->ID;

			/* 没分到组的也要收进索引：后台的「孤儿」提示靠它 */
			$indexed[ $id ] = $post;

			$group = self::group_for( $post, $groups );
			if ( null !== $group && isset( $buckets[ $group ] ) ) {
				$buckets[ $group ][] = $id;
			}
		}

		$saved = get_option( sticky_order_option_name(), array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		foreach ( $buckets as $group => $candidates ) {
			$preferred = isset( $saved[ $group ] ) ? array_map( 'intval', (array) $saved[ $group ] ) : array();
			$ordered   = array();

			foreach ( $preferred as $id ) {
				if ( in_array( $id, $candidates, true ) && ! in_array( $id, $ordered, true ) ) {
					$ordered[] = $id;
				}
			}

			foreach ( $candidates as $id ) {
				if ( ! in_array( $id, $ordered, true ) ) {
					$ordered[] = $id;
				}
			}

			$queues[ $group ] = $ordered;

			foreach ( $ordered as $position => $id ) {
				$result['ranks'][ $id ]  = $position;
				$result['groups'][ $id ] = (string) $group;
			}
		}

		$result['queues'] = $queues;
		$result['posts']  = $indexed;

		self::$cache = $result;

		return self::$cache;
	}

	/**
	 * 某篇文章归属的队列 key；不属于任何队列时返回 null。
	 * $groups 为空即单队列模式，全部归到 default。
	 *
	 * @param WP_Post              $post   文章。
	 * @param array<string,string> $groups 分组清单（调用方传入，避免逐篇重复跑 filter）。
	 * @return string|null
	 */
	private static function group_for( $post, array $groups ) {
		if ( ! $groups ) {
			return 'default';
		}

		$terms = get_the_terms( $post, sticky_order_group_taxonomy() );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return null;
		}

		foreach ( $terms as $term ) {
			if ( isset( $groups[ $term->slug ] ) ) {
				return $term->slug;
			}
		}

		return null;
	}

	/**
	 * 置顶了、但不属于任何分组的文章 ID —— 后台提示出来，
	 * 否则会出现「明明置顶了却哪都不显示」的哑谜。
	 *
	 * @return int[]
	 */
	public static function orphans() {
		$data = self::resolve();

		if ( $data['single'] ) {
			return array();
		}

		$orphans = array();
		foreach ( array_keys( $data['posts'] ) as $id ) {
			if ( ! isset( $data['groups'][ $id ] ) ) {
				$orphans[] = (int) $id;
			}
		}

		return $orphans;
	}

	/** 某文章在其队列里的位置；不在队列里返回 null。 */
	public static function rank_of( $post_id ) {
		$data = self::resolve();
		$id   = (int) $post_id;

		return isset( $data['ranks'][ $id ] ) ? (int) $data['ranks'][ $id ] : null;
	}

	/** 某文章所属的队列 key；不在队列里返回 null。 */
	public static function group_of( $post_id ) {
		$data = self::resolve();
		$id   = (int) $post_id;

		return isset( $data['groups'][ $id ] ) ? (string) $data['groups'][ $id ] : null;
	}
}
