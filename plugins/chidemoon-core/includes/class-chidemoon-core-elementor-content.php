<?php
/** Convert supported editorial bodies to native, individually editable Elementor elements. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_Core_Elementor_Content {
	public const BACKUP_META = '_chidemoon_pre_native_content_20260930';
	private const META_KEYS = array( '_elementor_data', '_elementor_edit_mode', '_elementor_version', '_elementor_pro_version', '_elementor_page_settings', '_elementor_template_type', '_elementor_css', '_elementor_element_cache', '_elementor_controls_usage', '_elementor_page_assets' );

	/** No writes. Unsupported markup is an explicit conflict, never an HTML-widget fallback. */
	public static function convert_content( string $content, string $seed = 'content' ): array {
		if ( function_exists( 'has_blocks' ) && has_blocks( $content ) ) {
			return self::convert_blocks( parse_blocks( $content ), $seed );
		}
		return self::convert_html( function_exists( 'wpautop' ) ? wpautop( $content ) : $content, $seed );
	}

	public static function convert_html( string $html, string $seed = 'content' ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}
		if ( ! class_exists( 'DOMDocument' ) ) {
			throw new RuntimeException( 'PHP DOM is required for native content conversion.' );
		}
		if ( preg_match( '/\[\/?[a-zA-Z][a-zA-Z0-9_-]*(?:\s|\]|\/)/', $html ) ) {
			throw new RuntimeException( 'Body contains a shortcode; preserve it for manual conversion.' );
		}
		$document = new DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		try {
			$loaded = $document->loadHTML( '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="chidemoon-import-root">' . $html . '</div></body></html>', LIBXML_NONET );
			$errors = libxml_get_errors();
			libxml_clear_errors();
		} finally {
			libxml_use_internal_errors( $previous );
		}
		$root = $document->getElementById( 'chidemoon-import-root' );
		if ( ! $loaded || ! $root ) {
			throw new RuntimeException( 'Body HTML could not be parsed.' );
		}
		foreach ( $errors as $error ) {
			if ( LIBXML_ERR_ERROR <= $error->level && 801 !== $error->code ) {
				throw new RuntimeException( 'Body HTML is malformed; preserve it for manual conversion.' );
			}
		}
		return self::convert_nodes( $root->childNodes, $seed );
	}

	/** A read-only migration plan; current Elementor JSON takes precedence over older post_content. */
	public static function plan( int $post_id ): array {
		$post = get_post( $post_id );
		$result = array( 'post_id' => $post_id, 'status' => 'unchanged', 'message' => '', 'source' => '', 'elements' => array(), 'widget_count' => 0, 'notes' => array() );
		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'product' ), true ) || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			$result['status'] = 'manual';
			$result['message'] = 'Only existing articles and product descriptions are supported.';
			return $result;
		}
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		try {
			$elements = array();
			if ( '' !== $raw && false !== $raw && null !== $raw ) {
				$elements = is_array( $raw ) ? $raw : json_decode( (string) $raw, true, 512, JSON_THROW_ON_ERROR );
				if ( ! is_array( $elements ) || array_keys( $elements ) !== array_keys( array_values( $elements ) ) || ( is_string( $raw ) && str_starts_with( ltrim( $raw ), '{' ) ) ) {
					throw new RuntimeException( 'Existing Elementor data is not a document array.' );
				}
			}
			if ( $elements ) {
				$result['source'] = 'elementor';
				$changed = false;
				$result['elements'] = self::split_native_bodies( $elements, $changed, $result['notes'] );
				$result['status'] = $changed || 'builder' !== get_post_meta( $post_id, '_elementor_edit_mode', true ) ? 'planned' : 'unchanged';
				$result['message'] = $changed ? 'Split supported native text bodies into separate content widgets.' : 'Existing native Elementor layout preserved.';
			} elseif ( 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) && '' !== $raw ) {
				// An editor may intentionally have cleared the canvas. Never resurrect stale body copy.
				$result['source'] = 'elementor';
				$result['message'] = 'Intentionally empty Elementor canvas preserved.';
			} else {
				$result['source'] = 'post_content';
				$body = self::convert_content( (string) $post->post_content, 'post-' . $post_id );
				if ( $body ) {
					$result['elements'] = array( self::container( 'post-' . $post_id . '-body', $body ) );
					$result['status'] = 'planned';
					$result['message'] = 'Convert supported body content to native Elementor widgets.';
				} else {
					$result['message'] = 'Body is empty.';
				}
			}
			$result['widget_count'] = count( self::widget_index( $result['elements'] ) );
			$result['signature'] = self::signature( $post_id );
		} catch ( Throwable $exception ) {
			$result['status'] = 'manual';
			$result['message'] = $exception->getMessage();
			$result['elements'] = array();
		}
		return $result;
	}

	/** Apply one plan with an immutable backup and rollback if Elementor rejects the document. */
	public static function upgrade( int $post_id, bool $apply = false ): array {
		$result = self::plan( $post_id );
		if ( ! $apply || 'planned' !== $result['status'] ) {
			return $result;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			$result['status'] = 'error';
			$result['message'] = 'You cannot edit this content.';
			return $result;
		}
		$original = null;
		$mutated = false;
		try {
			if ( ! class_exists( '\Elementor\Plugin' ) || ! \Elementor\Plugin::$instance ) {
				throw new RuntimeException( 'Elementor must be active before applying the conversion.' );
			}
			$plugin = \Elementor\Plugin::$instance;
			foreach ( self::widget_index( $result['elements'] ) as $element ) {
				if ( ! $plugin->widgets_manager->get_widget_types( $element['widgetType'] ) ) {
					throw new RuntimeException( 'Required widget is unavailable: ' . $element['widgetType'] );
				}
			}
			$document = $plugin->documents->get( $post_id, false );
			if ( ! $document ) {
				throw new RuntimeException( 'Elementor could not load this document.' );
			}
			if ( ! hash_equals( $result['signature'], self::signature( $post_id ) ) ) {
				throw new RuntimeException( 'Content changed after planning; run the conversion again.' );
			}
			$original = self::snapshot( $post_id );
			if ( ! metadata_exists( 'post', $post_id, self::BACKUP_META ) && ! add_post_meta( $post_id, self::BACKUP_META, wp_slash( $original ), true ) ) {
				throw new RuntimeException( 'Could not create a backup; conversion cancelled.' );
			}
			update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
			$mutated = true;
			if ( ! $document->save( array( 'elements' => $result['elements'] ) ) ) {
				throw new RuntimeException( 'Elementor could not save this document.' );
			}
			$saved = get_post_meta( $post_id, '_elementor_data', true );
			$saved = is_array( $saved ) ? $saved : json_decode( (string) $saved, true, 512, JSON_THROW_ON_ERROR );
			$expected_widgets = self::widget_index( $result['elements'] );
			$saved_widgets = self::widget_index( is_array( $saved ) ? $saved : array() );
			foreach ( $expected_widgets as $id => $expected ) {
				if ( ! isset( $saved_widgets[ $id ] ) || $expected['widgetType'] !== $saved_widgets[ $id ]['widgetType'] || $expected['settings'] != $saved_widgets[ $id ]['settings'] ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
					throw new RuntimeException( 'Elementor did not preserve all planned widget content.' );
				}
			}
			$result['status'] = 'updated';
		} catch ( Throwable $exception ) {
			if ( $original && $mutated ) {
				self::restore_snapshot( $post_id, $original );
			}
			$result['status'] = 'error';
			$result['message'] = $exception->getMessage();
		}
		return $result;
	}

	private static function convert_blocks( array $blocks, string $seed ): array {
		$elements = array();
		foreach ( $blocks as $index => $block ) {
			$name = $block['blockName'] ?? null;
			$key = $seed . '-block-' . $index;
			if ( 'chidemoon/shop-the-look' === $name ) {
				$attrs = $block['attrs'] ?? array();
				if ( ! empty( $attrs['style'] ) ) {
					throw new RuntimeException( 'Styled Shop the Look block needs manual conversion.' );
				}
				$image_id = (int) ( $attrs['imageId'] ?? 0 );
				if ( ! $image_id || ! wp_get_attachment_url( $image_id ) ) {
					throw new RuntimeException( 'Shop the Look block has no valid media image.' );
				}
				$hotspots = array();
				foreach ( (array) ( $attrs['hotspots'] ?? array() ) as $spot_index => $spot ) {
					if ( ! is_array( $spot ) ) {
						throw new RuntimeException( 'Shop the Look block has invalid hotspot data.' );
					}
					$hotspots[] = array( '_id' => self::id( $key . '-spot-' . $spot_index ), 'product_id' => (string) ( $spot['productId'] ?? $spot['product_id'] ?? '' ), 'product_source_key' => (string) ( $spot['productSourceKey'] ?? $spot['product_source_key'] ?? '' ), 'label' => (string) ( $spot['label'] ?? '' ), 'x' => array( 'unit' => '%', 'size' => (float) ( $spot['x'] ?? 50 ) ), 'y' => array( 'unit' => '%', 'size' => (float) ( $spot['y'] ?? 50 ) ) );
				}
				$settings = array( 'image' => array( 'id' => $image_id, 'url' => wp_get_attachment_url( $image_id ) ), 'image_alt' => (string) ( $attrs['imageAlt'] ?? '' ), 'caption' => (string) ( $attrs['caption'] ?? '' ), 'hotspots' => $hotspots );
				if ( ! empty( $attrs['anchor'] ) ) { $settings['_element_id'] = $attrs['anchor']; }
				if ( ! empty( $attrs['className'] ) ) { $settings['_css_classes'] = $attrs['className']; }
				$elements[] = self::widget( 'chidemoon-shop-the-look', $settings, $key );
			} elseif ( 'core/group' === $name ) {
				$attrs = $block['attrs'] ?? array();
				if ( ! empty( $attrs['style'] ) || ! empty( $attrs['backgroundColor'] ) || ! empty( $attrs['textColor'] ) || ! in_array( $attrs['layout']['type'] ?? 'constrained', array( 'constrained', 'default' ), true ) ) {
					throw new RuntimeException( 'Styled or arranged group block needs manual conversion.' );
				}
				$settings = array();
				if ( ! empty( $attrs['anchor'] ) ) { $settings['_element_id'] = $attrs['anchor']; }
				if ( ! empty( $attrs['className'] ) ) { $settings['css_classes'] = $attrs['className']; }
				$elements[] = self::container( $key, self::convert_blocks( $block['innerBlocks'] ?? array(), $key ), $settings );
			} elseif ( null === $name || in_array( $name, array( 'core/paragraph', 'core/heading', 'core/list', 'core/image', 'core/quote', 'core/separator', 'core/table', 'core/html', 'core/freeform' ), true ) ) {
				self::assert_static_blocks( $block['innerBlocks'] ?? array() );
				$elements = array_merge( $elements, self::convert_html( self::block_html( $block ), $key ) );
			} else {
				throw new RuntimeException( 'Unsupported block preserved: ' . $name );
			}
		}
		return $elements;
	}

	private static function assert_static_blocks( array $blocks ): void {
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? null;
			if ( null !== $name && ! in_array( $name, array( 'core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/image', 'core/quote', 'core/separator', 'core/table', 'core/html', 'core/freeform' ), true ) ) { throw new RuntimeException( 'Unsupported nested block preserved: ' . $name ); }
			self::assert_static_blocks( $block['innerBlocks'] ?? array() );
		}
	}

	/** Reassemble static block HTML without invoking dynamic renderers or losing nested list items. */
	private static function block_html( array $block ): string {
		if ( empty( $block['innerContent'] ) ) { return (string) ( $block['innerHTML'] ?? '' ); }
		$html = '';
		$child = 0;
		foreach ( $block['innerContent'] as $part ) {
			$html .= null === $part ? self::block_html( $block['innerBlocks'][ $child++ ] ) : $part;
		}
		return $html;
	}

	private static function convert_nodes( DOMNodeList $nodes, string $seed ): array {
		$elements = array();
		foreach ( $nodes as $index => $node ) {
			$key = $seed . '-node-' . $index;
			if ( $node instanceof DOMComment || ( $node instanceof DOMText && '' === trim( $node->textContent ) ) ) { continue; }
			if ( $node instanceof DOMText ) {
				$elements[] = self::widget( 'text-editor', array( 'editor' => '<p>' . htmlspecialchars( $node->textContent, ENT_QUOTES, 'UTF-8' ) . '</p>' ), $key );
				continue;
			}
			if ( ! $node instanceof DOMElement ) { throw new RuntimeException( 'Unsupported HTML node preserved.' ); }
			$tag = strtolower( $node->tagName );
			if ( preg_match( '/^h[1-6]$/', $tag ) ) {
				self::assert_attributes( $node, array( 'class', 'id' ) );
				self::assert_inline_content( $node );
				$elements[] = self::widget( 'heading', array_merge( array( 'title' => self::inner_html( $node ), 'header_size' => $tag ), self::html_settings( $node ) ), $key );
			} elseif ( in_array( $tag, array( 'p', 'ul', 'ol', 'blockquote', 'table' ), true ) ) {
				// Rich text retains lists, links, emphasis, table cells and local text styling.
				self::assert_rich_content( $node );
				$images = $node->getElementsByTagName( 'img' );
				if ( 'p' === $tag && 1 === $images->length && '' === trim( $node->textContent ) ) {
					self::assert_attributes( $node, array( 'class', 'id' ) );
					self::assert_image_shell( $node );
					$elements[] = self::image_widget( $images->item( 0 ), $key );
				} elseif ( $images->length ) {
					throw new RuntimeException( 'An image mixed with text needs manual conversion.' );
				} else {
					$elements[] = self::widget( 'text-editor', array( 'editor' => $node->ownerDocument->saveHTML( $node ) ), $key );
				}
			} elseif ( 'img' === $tag ) {
				$elements[] = self::image_widget( $node, $key );
			} elseif ( 'a' === $tag && 1 === $node->getElementsByTagName( 'img' )->length && '' === trim( $node->textContent ) ) {
				self::assert_image_shell( $node );
				$elements[] = self::image_widget( $node->getElementsByTagName( 'img' )->item( 0 ), $key );
			} elseif ( 'figure' === $tag ) {
				self::assert_attributes( $node, array( 'class', 'id' ) );
				$images = $node->getElementsByTagName( 'img' );
				$captions = $node->getElementsByTagName( 'figcaption' );
				if ( 1 !== $images->length || 1 < $captions->length ) {
					throw new RuntimeException( 'Complex image figure needs manual conversion.' );
				}
				self::assert_image_shell( $node );
				$children = array( self::image_widget( $images->item( 0 ), $key . '-image' ) );
				if ( $captions->length ) {
					self::assert_attributes( $captions->item( 0 ), array( 'class', 'id' ) );
					self::assert_inline_content( $captions->item( 0 ) );
					$children[] = self::widget( 'text-editor', array_merge( array( 'editor' => '<p>' . self::inner_html( $captions->item( 0 ) ) . '</p>' ), self::html_settings( $captions->item( 0 ) ) ), $key . '-caption' );
				}
				$settings = self::html_settings( $node );
				if ( isset( $settings['_css_classes'] ) ) { $settings['css_classes'] = $settings['_css_classes']; unset( $settings['_css_classes'] ); }
				$elements[] = self::container( $key, $children, $settings );
			} elseif ( 'hr' === $tag ) {
				self::assert_attributes( $node, array( 'class', 'id' ) );
				$elements[] = self::widget( 'divider', self::html_settings( $node ), $key );
			} else {
				throw new RuntimeException( 'Unsupported HTML preserved: ' . $tag );
			}
		}
		return $elements;
	}

	private static function image_widget( DOMElement $image, string $seed ): array {
		self::assert_attributes( $image, array( 'src', 'alt', 'class', 'id', 'width', 'height', 'loading', 'decoding', 'srcset', 'sizes' ) );
		$url = $image->getAttribute( 'src' );
		if ( '' === $url || ! preg_match( '~^(https?://|/)~i', $url ) ) { throw new RuntimeException( 'Image URL needs manual conversion.' ); }
		$alt = $image->getAttribute( 'alt' );
		$image_id = preg_match( '/\bwp-image-(\d+)\b/', $image->getAttribute( 'class' ), $matches ) ? (int) $matches[1] : 0;
		if ( ! $image_id && function_exists( 'attachment_url_to_postid' ) ) { $image_id = attachment_url_to_postid( $url ); }
		// Elementor reads local media alt from the attachment. Use its URL form when copy differs,
		// retaining per-use alt without changing an attachment that other articles share.
		if ( $image_id && ( $alt !== (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) || $url !== wp_get_attachment_url( $image_id ) ) ) { $image_id = 0; }
		$settings = array_merge( array( 'image' => array( 'id' => $image_id ?: '', 'url' => $url, 'alt' => $alt ), 'image_size' => 'full', 'link_to' => 'none' ), self::html_settings( $image ) );
		if ( $image->parentNode instanceof DOMElement && 'a' === strtolower( $image->parentNode->tagName ) ) {
			$link = $image->parentNode;
			self::assert_attributes( $link, array( 'href', 'target', 'rel', 'title', 'class', 'id' ) );
			if ( ! in_array( $link->getAttribute( 'target' ), array( '', '_self', '_blank' ), true ) ) { throw new RuntimeException( 'Custom image link target needs manual conversion.' ); }
			$settings['link_to'] = 'custom';
			$settings['link'] = array( 'url' => $link->getAttribute( 'href' ), 'is_external' => '_blank' === $link->getAttribute( 'target' ), 'nofollow' => in_array( 'nofollow', preg_split( '/\s+/', $link->getAttribute( 'rel' ) ), true ) );
			if ( $link->getAttribute( 'rel' ) ) { $settings['link']['custom_attributes'] = 'rel|' . $link->getAttribute( 'rel' ); }
		}
		return self::widget( 'image', $settings, $seed );
	}

	private static function assert_image_shell( DOMElement $wrapper ): void {
		foreach ( $wrapper->childNodes as $child ) {
			if ( $child instanceof DOMComment || ( $child instanceof DOMText && '' === trim( $child->textContent ) ) ) { continue; }
			if ( ! $child instanceof DOMElement || ! in_array( strtolower( $child->tagName ), array( 'img', 'a', 'figcaption' ), true ) ) { throw new RuntimeException( 'Additional image-wrapper content needs manual conversion.' ); }
			if ( 'a' === strtolower( $child->tagName ) ) { self::assert_image_shell( $child ); }
		}
	}

	private static function assert_attributes( DOMElement $node, array $allowed ): void {
		foreach ( $node->attributes as $attribute ) {
			if ( ! in_array( strtolower( $attribute->name ), $allowed, true ) ) { throw new RuntimeException( 'Custom ' . $node->tagName . ' attributes need manual conversion: ' . $attribute->name ); }
		}
	}

	private static function assert_inline_content( DOMElement $node ): void {
		foreach ( $node->getElementsByTagName( '*' ) as $child ) {
			if ( ! in_array( strtolower( $child->tagName ), array( 'a', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'small', 'br', 'sub', 'sup', 'code', 'mark' ), true ) ) { throw new RuntimeException( 'Complex heading or caption needs manual conversion.' ); }
			self::assert_attributes( $child, array( 'href', 'target', 'rel', 'title', 'class', 'id', 'style' ) );
		}
	}

	private static function assert_rich_content( DOMElement $node ): void {
		$allowed = array( 'p', 'ul', 'ol', 'li', 'blockquote', 'cite', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'a', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'small', 'br', 'sub', 'sup', 'code', 'mark', 'img' );
		$all = array( $node );
		foreach ( $node->getElementsByTagName( '*' ) as $child ) { $all[] = $child; }
		foreach ( $all as $child ) {
			if ( ! in_array( strtolower( $child->tagName ), $allowed, true ) ) { throw new RuntimeException( 'Embedded or custom rich content needs manual conversion.' ); }
			foreach ( $child->attributes as $attribute ) {
				if ( str_starts_with( strtolower( $attribute->name ), 'on' ) ) { throw new RuntimeException( 'Interactive HTML needs manual conversion.' ); }
			}
		}
	}

	private static function inner_html( DOMElement $node ): string {
		$html = '';
		foreach ( $node->childNodes as $child ) { $html .= $node->ownerDocument->saveHTML( $child ); }
		return $html;
	}

	private static function html_settings( DOMElement $node ): array {
		$settings = array();
		if ( $node->hasAttribute( 'id' ) ) { $settings['_element_id'] = $node->getAttribute( 'id' ); }
		if ( $node->hasAttribute( 'class' ) ) { $settings['_css_classes'] = $node->getAttribute( 'class' ); }
		return $settings;
	}

	private static function split_native_bodies( array $elements, bool &$changed, array &$notes ): array {
		foreach ( $elements as &$element ) {
			if ( ! is_array( $element ) || ! isset( $element['id'], $element['elType'] ) || ( 'widget' === $element['elType'] && empty( $element['widgetType'] ) ) ) { throw new RuntimeException( 'Existing native document contains an invalid element.' ); }
			if ( 'widget' === $element['elType'] && 'text-editor' === ( $element['widgetType'] ?? '' ) ) {
				$settings = $element['settings'] ?? array();
				// Existing typography, dynamic bindings, responsive settings and custom CSS are editor-owned.
				if ( ! array_diff( array_keys( $settings ), array( 'editor', '_css_classes', '_element_id' ) ) ) {
					try { $body = self::convert_html( (string) ( $settings['editor'] ?? '' ), 'native-' . $element['id'] ); }
					catch ( Throwable $exception ) { $body = array(); $notes[] = 'Preserved native text editor ' . $element['id'] . ': ' . $exception->getMessage(); }
					if ( count( $body ) > 1 || ( 1 === count( $body ) && 'text-editor' !== ( $body[0]['widgetType'] ?? '' ) ) ) {
						$box_settings = array();
						if ( isset( $settings['_css_classes'] ) ) { $box_settings['css_classes'] = $settings['_css_classes']; }
						if ( isset( $settings['_element_id'] ) ) { $box_settings['_element_id'] = $settings['_element_id']; }
						$replacement = self::container( 'native-' . $element['id'], $body, $box_settings );
						$replacement['id'] = $element['id'];
						$element = $replacement;
						$changed = true;
					}
				}
			}
			if ( ! empty( $element['elements'] ) ) { $element['elements'] = self::split_native_bodies( $element['elements'], $changed, $notes ); }
		}
		unset( $element );
		return $elements;
	}

	private static function widget( string $type, array $settings, string $seed ): array {
		return array( 'id' => self::id( $seed ), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}

	private static function container( string $seed, array $elements, array $settings = array() ): array {
		return array( 'id' => self::id( $seed ), 'elType' => 'container', 'settings' => array_merge( array( 'content_width' => 'full', 'flex_direction' => 'column', 'padding' => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0' ) ), $settings ), 'elements' => $elements );
	}

	private static function id( string $seed ): string { return substr( md5( $seed ), 0, 8 ); }

	private static function widget_index( array $elements ): array {
		$widgets = array();
		foreach ( $elements as $element ) {
			if ( 'widget' === ( $element['elType'] ?? '' ) ) { $widgets[ $element['id'] ] = $element; }
			$widgets += self::widget_index( $element['elements'] ?? array() );
		}
		return $widgets;
	}

	private static function signature( int $post_id ): string {
		$post = get_post( $post_id );
		return hash( 'sha256', (string) wp_json_encode( array( $post->post_content, get_post_meta( $post_id, '_elementor_data', true ), get_post_meta( $post_id, '_elementor_edit_mode', true ) ) ) );
	}

	private static function snapshot( int $post_id ): array {
		$post = get_post( $post_id );
		$meta = array();
		foreach ( self::META_KEYS as $key ) { $meta[ $key ] = array( 'exists' => metadata_exists( 'post', $post_id, $key ), 'value' => get_post_meta( $post_id, $key, true ) ); }
		return array( 'created_at' => gmdate( 'c' ), 'post_id' => $post_id, 'post_content' => $post->post_content, 'post_excerpt' => $post->post_excerpt, 'post_title' => $post->post_title, 'meta' => $meta );
	}

	private static function restore_snapshot( int $post_id, array $snapshot ): void {
		$post = get_post( $post_id );
		$fields = array( 'ID' => $post_id );
		foreach ( array( 'post_content', 'post_excerpt', 'post_title' ) as $key ) { if ( $post->$key !== $snapshot[ $key ] ) { $fields[ $key ] = $snapshot[ $key ]; } }
		if ( count( $fields ) > 1 ) { wp_update_post( wp_slash( $fields ) ); }
		foreach ( $snapshot['meta'] as $key => $value ) {
			if ( $value['exists'] ) { update_post_meta( $post_id, $key, wp_slash( $value['value'] ) ); }
			else { delete_post_meta( $post_id, $key ); }
		}
	}
}
