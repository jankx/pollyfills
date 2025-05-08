<?php
if (!defined('ABSPATH')) {
	exit();
}

// Thoát nếu WordPress version >= 6.4
if (version_compare(get_bloginfo('version'), '6.4', '>=')) {
	return;
}


/**
 * Retrieves block types hooked into the given block, grouped by anchor block type and the relative position.
 *
 * @since 6.4.0
 *
 * @return array[] Array of block types grouped by anchor block type and the relative position.
 */
if (!function_exists('get_hooked_blocks')) {
	function get_hooked_blocks()
	{
		$block_types = WP_Block_Type_Registry::get_instance()->get_all_registered();
		$hooked_blocks = array();
		foreach ($block_types as $block_type) {
			if (!($block_type instanceof WP_Block_Type) || !is_array($block_type->block_hooks)) {
				continue;
			}
			foreach ($block_type->block_hooks as $anchor_block_type => $relative_position) {
				if (!isset($hooked_blocks[$anchor_block_type])) {
					$hooked_blocks[$anchor_block_type] = array();
				}
				if (!isset($hooked_blocks[$anchor_block_type][$relative_position])) {
					$hooked_blocks[$anchor_block_type][$relative_position] = array();
				}
				$hooked_blocks[$anchor_block_type][$relative_position][] = $block_type->name;
			}
		}

		return $hooked_blocks;
	}
}


/**
 * Given an array of parsed block trees, applies callbacks before and after serializing them and
 * returns their concatenated output.
 *
 * Recursively traverses the blocks and their inner blocks and applies the two callbacks provided as
 * arguments, the first one before serializing a block, and the second one after serializing.
 * If either callback returns a string value, it will be prepended and appended to the serialized
 * block markup, respectively.
 *
 * The callbacks will receive a reference to the current block as their first argument, so that they
 * can also modify it, and the current block's parent block as second argument. Finally, the
 * `$pre_callback` receives the previous block, whereas the `$post_callback` receives
 * the next block as third argument.
 *
 * Serialized blocks are returned including comment delimiters, and with all attributes serialized.
 *
 * This function should be used when there is a need to modify the saved blocks, or to inject markup
 * into the return value. Prefer `serialize_blocks` when preparing blocks to be saved to post content.
 *
 * This function is meant for internal use only.
 *
 * @since 6.4.0
 * @access private
 *
 * @see serialize_blocks()
 *
 * @param array[]  $blocks        An array of parsed blocks. See WP_Block_Parser_Block.
 * @param callable $pre_callback  Callback to run on each block in the tree before it is traversed and serialized.
 *                                It is called with the following arguments: &$block, $parent_block, $previous_block.
 *                                Its string return value will be prepended to the serialized block markup.
 * @param callable $post_callback Callback to run on each block in the tree after it is traversed and serialized.
 *                                It is called with the following arguments: &$block, $parent_block, $next_block.
 *                                Its string return value will be appended to the serialized block markup.
 * @return string Serialized block markup.
 */
if (!function_exists('traverse_and_serialize_blocks')) {
	function traverse_and_serialize_blocks($blocks, $pre_callback = null, $post_callback = null)
	{
		$result = '';
		$parent_block = null; // At the top level, there is no parent block to pass to the callbacks; yet the callbacks expect a reference.

		$pre_callback_is_callable = is_callable($pre_callback);
		$post_callback_is_callable = is_callable($post_callback);

		foreach ($blocks as $index => $block) {
			if ($pre_callback_is_callable) {
				$prev = 0 === $index
					? null
					: $blocks[$index - 1];

				$result .= call_user_func_array(
					$pre_callback,
					array(&$block, &$parent_block, $prev)
				);
			}

			if ($post_callback_is_callable) {
				$next = count($blocks) - 1 === $index
					? null
					: $blocks[$index + 1];

				$post_markup = call_user_func_array(
					$post_callback,
					array(&$block, &$parent_block, $next)
				);
			}

			$result .= traverse_and_serialize_block($block, $pre_callback, $post_callback);
			$result .= isset($post_markup) ? $post_markup : '';
		}

		return $result;
	}
}


/**
 * Traverses a parsed block tree and applies callbacks before and after serializing it.
 *
 * Recursively traverses the block and its inner blocks and applies the two callbacks provided as
 * arguments, the first one before serializing the block, and the second one after serializing it.
 * If either callback returns a string value, it will be prepended and appended to the serialized
 * block markup, respectively.
 *
 * The callbacks will receive a reference to the current block as their first argument, so that they
 * can also modify it, and the current block's parent block as second argument. Finally, the
 * `$pre_callback` receives the previous block, whereas the `$post_callback` receives
 * the next block as third argument.
 *
 * Serialized blocks are returned including comment delimiters, and with all attributes serialized.
 *
 * This function should be used when there is a need to modify the saved block, or to inject markup
 * into the return value. Prefer `serialize_block` when preparing a block to be saved to post content.
 *
 * This function is meant for internal use only.
 *
 * @since 6.4.0
 * @access private
 *
 * @see serialize_block()
 *
 * @param array    $block         An associative array of a single parsed block object. See WP_Block_Parser_Block.
 * @param callable $pre_callback  Callback to run on each block in the tree before it is traversed and serialized.
 *                                It is called with the following arguments: &$block, $parent_block, $previous_block.
 *                                Its string return value will be prepended to the serialized block markup.
 * @param callable $post_callback Callback to run on each block in the tree after it is traversed and serialized.
 *                                It is called with the following arguments: &$block, $parent_block, $next_block.
 *                                Its string return value will be appended to the serialized block markup.
 * @return string Serialized block markup.
 */
if (!function_exists('traverse_and_serialize_block')) {
	function traverse_and_serialize_block($block, $pre_callback = null, $post_callback = null)
	{
		$block_content = '';
		$block_index = 0;

		foreach ($block['innerContent'] as $chunk) {
			if (is_string($chunk)) {
				$block_content .= $chunk;
			} else {
				$inner_block = $block['innerBlocks'][$block_index];

				if (is_callable($pre_callback)) {
					$prev = 0 === $block_index
						? null
						: $block['innerBlocks'][$block_index - 1];

					$block_content .= call_user_func_array(
						$pre_callback,
						array(&$inner_block, &$block, $prev)
					);
				}

				if (is_callable($post_callback)) {
					$next = count($block['innerBlocks']) - 1 === $block_index
						? null
						: $block['innerBlocks'][$block_index + 1];

					$post_markup = call_user_func_array(
						$post_callback,
						array(&$inner_block, &$block, $next)
					);
				}

				$block_content .= traverse_and_serialize_block($inner_block, $pre_callback, $post_callback);
				$block_content .= isset($post_markup) ? $post_markup : '';

				++$block_index;
			}
		}

		if (!is_array($block['attrs'])) {
			$block['attrs'] = array();
		}

		return get_comment_delimited_block_content(
			$block['blockName'],
			$block['attrs'],
			$block_content
		);
	}
}
