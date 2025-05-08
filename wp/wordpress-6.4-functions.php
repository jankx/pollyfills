<?php

/**
 * Retrieves block types hooked into the given block, grouped by anchor block type and the relative position.
 *
 * @since 6.4.0
 *
 * @return array[] Array of block types grouped by anchor block type and the relative position.
 */
function get_hooked_blocks() {
	$block_types   = WP_Block_Type_Registry::get_instance()->get_all_registered();
	$hooked_blocks = array();
	foreach ( $block_types as $block_type ) {
		if ( ! ( $block_type instanceof WP_Block_Type ) || ! is_array( $block_type->block_hooks ) ) {
			continue;
		}
		foreach ( $block_type->block_hooks as $anchor_block_type => $relative_position ) {
			if ( ! isset( $hooked_blocks[ $anchor_block_type ] ) ) {
				$hooked_blocks[ $anchor_block_type ] = array();
			}
			if ( ! isset( $hooked_blocks[ $anchor_block_type ][ $relative_position ] ) ) {
				$hooked_blocks[ $anchor_block_type ][ $relative_position ] = array();
			}
			$hooked_blocks[ $anchor_block_type ][ $relative_position ][] = $block_type->name;
		}
	}

	return $hooked_blocks;
}
