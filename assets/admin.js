( function () {
	'use strict';

	if ( ! window.jQuery ) {
		return;
	}

	var $ = window.jQuery;

	$( function () {
		if ( ! $.fn || ! $.fn.sortable ) {
			return;
		}

		/* 拖拽只移动 li；每个 li 里带一个同名 hidden，DOM 顺序就是提交顺序 */
		$( '.sticky-order-list' ).sortable( {
			handle: '.sticky-order-item__grip',
			axis: 'y',
			placeholder: 'sticky-order-list__placeholder',
			forcePlaceholderSize: true
		} );
	} );
}() );
