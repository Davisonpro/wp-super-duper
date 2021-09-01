<?php

class WP_Super_Duper_Block {

	public function __construct( $class ) {
		$this->SD = $class;
		$this->options = $class->options;

		add_action( 'admin_enqueue_scripts', array( $this, 'register_block' ) );
	}

	/**
	 * Add the dynamic block code inline when the wp-block in enqueued.
	 */
	public function register_block() {
		wp_add_inline_script( 'wp-blocks', $this->block() );
		if ( class_exists( 'SiteOrigin_Panels' ) ) {
			wp_add_inline_script( 'wp-blocks', $this->siteorigin_js() );
		}
	}

	/**
	 * Check if we need to show advanced options.
	 *
	 * @return bool
	 */
	public function block_show_advanced() {
		$show      = false;
		$this->arguments = $this->SD->get_arguments();
		$arguments = $this->arguments;

		if ( ! empty( $arguments ) ) {
			foreach ( $arguments as $argument ) {
				if ( isset( $argument['advanced'] ) && $argument['advanced'] ) {
					$show = true;
					break; // no need to continue if we know we have it
				}
			}
		}

		return $show;
	}

	/**
	 * Generate the block icon.
	 *
	 * Enables the use of Font Awesome icons.
	 *
	 * @note xlink:href is actually deprecated but href is not supported by all so we use both.
	 *
	 * @param $icon
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function get_block_icon( $icon ) {
		// check if we have a Font Awesome icon
		$fa_type = '';
		if ( substr( $icon, 0, 7 ) === "fas fa-" ) {
			$fa_type = 'solid';
		} elseif ( substr( $icon, 0, 7 ) === "far fa-" ) {
			$fa_type = 'regular';
		} elseif ( substr( $icon, 0, 7 ) === "fab fa-" ) {
			$fa_type = 'brands';
		} else {
			$icon = "'" . $icon . "'";
		}

		// set the icon if we found one
		if ( $fa_type ) {
			$fa_icon = str_replace( array( "fas fa-", "far fa-", "fab fa-" ), "", $icon );
			$icon    = "el('svg',{width: 20, height: 20, viewBox: '0 0 20 20'},el('use', {'xlink:href': '" . $this->SD->get_url() . "icons/" . $fa_type . ".svg#" . $fa_icon . "','href': '" . $this->SD->get_url() . "icons/" . $fa_type . ".svg#" . $fa_icon . "'}))";
		}

		return $icon;
	}

	/**
	 * Output the JS for building the dynamic Guntenberg block.
	 *
	 * @since 1.0.4 Added block_wrap property which will set the block wrapping output element ie: div, span, p or empty for no wrap.
	 * @since 1.0.9 Save numbers as numbers and not strings.
	 * @since 1.1.0 Font Awesome classes can be used for icons.
	 * @return mixed
	 */
	public function block() {
		ob_start();

		$show_advanced = $this->block_show_advanced();
		?>
		<script>
			/**
			 * BLOCK: Basic
			 *
			 * Registering a basic block with Gutenberg.
			 * Simple block, renders and saves the same content without any interactivity.
			 *
			 * Styles:
			 *        editor.css — Editor styles for the block.
			 *        style.css  — Editor & Front end styles for the block.
			 */
			(function () {
				var __ = wp.i18n.__; // The __() for internationalization.
				var el = wp.element.createElement; // The wp.element.createElement() function to create elements.
				var editable = wp.blocks.Editable;
				var blocks = wp.blocks;
				var registerBlockType = wp.blocks.registerBlockType; // The registerBlockType() to register blocks.
				var is_fetching = false;
				var prev_attributes = [];

				var term_query_type = '';
				var post_type_rest_slugs = <?php if(! empty( $this->arguments ) && isset($this->arguments['post_type']['onchange_rest']['values'])){echo "[".json_encode($this->arguments['post_type']['onchange_rest']['values'])."]";}else{echo "[]";} ?>;
				const taxonomies_<?php echo str_replace("-","_", $this->SD->base_id);?> = [{label: "Please wait", value: 0}];
				const sort_by_<?php echo str_replace("-","_", $this->SD->base_id);?> = [{label: "Please wait", value: 0}];

				/**
				 * Register Basic Block.
				 *
				 * Registers a new block provided a unique name and an object defining its
				 * behavior. Once registered, the block is made available as an option to any
				 * editor interface where blocks are implemented.
				 *
				 * @param  {string}   name     Block name.
				 * @param  {Object}   settings Block settings.
				 * @return {?WPBlock}          The block, if it has been successfully
				 *                             registered; otherwise `undefined`.
				 */
				registerBlockType('<?php echo str_replace( "_", "-", sanitize_title_with_dashes( $this->options['textdomain'] ) . '/' . sanitize_title_with_dashes( $this->options['class_name'] ) );  ?>', { // Block name. Block names must be string that contains a namespace prefix. Example: my-plugin/my-custom-block.
					title: '<?php echo addslashes( $this->options['name'] ); ?>', // Block title.
					description: '<?php echo addslashes( $this->options['widget_ops']['description'] )?>', // Block title.
					icon: <?php echo $this->get_block_icon( $this->options['block-icon'] );?>,//'<?php echo isset( $this->options['block-icon'] ) ? esc_attr( $this->options['block-icon'] ) : 'shield-alt';?>', // Block icon from Dashicons → https://developer.wordpress.org/resource/dashicons/.
					supports: {
						<?php
						if ( isset( $this->options['block-supports'] ) ) {
							echo $this->array_to_attributes( $this->options['block-supports'] );
						}
						?>
					},
					category: '<?php echo isset( $this->options['block-category'] ) ? esc_attr( $this->options['block-category'] ) : 'common';?>', // Block category — Group blocks together based on common traits E.g. common, formatting, layout widgets, embed.
					<?php if ( isset( $this->options['block-keywords'] ) ) {
						echo "keywords: " . $this->options['block-keywords'] . ",\n";
					}
					// maybe set no_wrap
					$no_wrap = isset( $this->options['no_wrap'] ) && $this->options['no_wrap'] ? true : false;
					if ( isset( $this->arguments['no_wrap'] ) && $this->arguments['no_wrap'] ) {
						$no_wrap = true;
					}
					if ( $no_wrap ) {
						$this->options['block-wrap'] = '';
					}

					$show_alignment = false;
					// align feature
					/*echo "supports: {";
					echo "	align: true,";
					echo "  html: false";
					echo "},";*/

					if ( ! empty( $this->arguments ) ) {
						echo "attributes: {\n";
						if ( $show_advanced ) {
							echo "show_advanced: {";
							echo "type: 'boolean',";
							echo " default: false";
							echo "},\n";
						}

						// block wrap element
						if ( ! empty( $this->options['block-wrap'] ) ) { //@todo we should validate this?
							echo "block_wrap: {";
							echo "type: 'string',";
							echo " default: '" . esc_attr( $this->options['block-wrap'] ) . "'";
							echo "},\n";
						}

						foreach ( $this->arguments as $key => $args ) {
							// set if we should show alignment
							if ( $key == 'alignment' ) {
								$show_alignment = true;
							}

							if ( $args['type'] == 'checkbox' ) {
								$type    = 'boolean';
								$default = isset( $args['default'] ) && $args['default'] ? 'true' : 'false';
							} elseif ( $args['type'] == 'number' ) {
								$type    = 'number';
								$default = isset( $args['default'] ) ? "'" . $args['default'] . "'" : "''";
							} elseif ( $args['type'] == 'select' && ! empty( $args['multiple'] ) ) {
								$type = 'array';
								if ( is_array( $args['default'] ) ) {
									$default = isset( $args['default'] ) ? "['" . implode( "','", $args['default'] ) . "']" : "[]";
								} else {
									$default = isset( $args['default'] ) ? "'" . $args['default'] . "'" : "''";
								}
							} elseif ( $args['type'] == 'multiselect' ) {
								$type    = 'array';
								$default = isset( $args['default'] ) ? "'" . $args['default'] . "'" : "''";
							} else {
								$type    = 'string';
								$default = isset( $args['default'] ) ? "'" . $args['default'] . "'" : "''";
							}
							echo str_replace( '-','__', $key ) . ": {";
							echo "type: '$type',";
							echo "default: $default";
							echo "},\n";
						}

						echo "content : {type : 'string',default: 'Please select the attributes in the block settings'},\n";
						echo "className: { type: 'string', default: '' },\n";

						echo "},";

					}
				?>

				// The "edit" property must be a valid function.
					edit: function (props) {
						var $value = '';
					<?php
						// if we have a post_type and a category then link them
						if( isset($this->arguments['post_type']) && isset($this->arguments['category']) && !empty($this->arguments['category']['post_type_linked']) ){
						?>
						if(typeof(prev_attributes[props.id]) != 'undefined' ){
							$pt = props.attributes.post_type;
							if(post_type_rest_slugs.length){
								$value = post_type_rest_slugs[0][$pt];
								}
								var run = false;

								if($pt != term_query_type){
									run = true;
									term_query_type = $pt;
								}

								// taxonomies
								if( $value && 'post_type' in prev_attributes[props.id] && 'category' in prev_attributes[props.id] && run ){
									wp.apiFetch({path: "<?php if(isset($this->arguments['post_type']['onchange_rest']['path'])){echo $this->arguments['post_type']['onchange_rest']['path'];}else{'/wp/v2/"+$value+"/categories';} ?>"}).then(terms => {
										while (taxonomies_<?php echo str_replace("-","_", $this->id);?>.length) {
										taxonomies_<?php echo str_replace("-","_", $this->id);?>.pop();
									}
									taxonomies_<?php echo str_replace("-","_", $this->id);?>.push({label: "All", value: 0});
									jQuery.each( terms, function( key, val ) {
										taxonomies_<?php echo str_replace("-","_", $this->id);?>.push({label: val.name, value: val.id});
									});

									// setting the value back and fourth fixes the no update issue that sometimes happens where it won't update the options.
									var $old_cat_value = props.attributes.category
									props.setAttributes({category: [0] });
									props.setAttributes({category: $old_cat_value });

									return taxonomies_<?php echo str_replace("-","_", $this->id);?>;
								});
								}

								// sort_by
								if( $value && 'post_type' in prev_attributes[props.id] && 'sort_by' in prev_attributes[props.id] && run ){
									var data = {
										'action': 'geodir_get_sort_options',
										'post_type': $pt
									};
									jQuery.post(ajaxurl, data, function(response) {
										response = JSON.parse(response);
										while (sort_by_<?php echo str_replace("-","_", $this->id);?>.length) {
											sort_by_<?php echo str_replace("-","_", $this->id);?>.pop();
										}

										jQuery.each( response, function( key, val ) {
											sort_by_<?php echo str_replace("-","_", $this->id);?>.push({label: val, value: key});
										});

										// setting the value back and fourth fixes the no update issue that sometimes happens where it won't update the options.
										var $old_sort_by_value = props.attributes.sort_by
										props.setAttributes({sort_by: [0] });
										props.setAttributes({sort_by: $old_sort_by_value });

										return sort_by_<?php echo str_replace("-","_", $this->id);?>;
									});

								}
							}
						<?php }?>

						var content = props.attributes.content;

						function onChangeContent() {
							$refresh = false;

							// Set the old content the same as the new one so we only compare all other attributes
							if(typeof(prev_attributes[props.id]) != 'undefined'){
								prev_attributes[props.id].content = props.attributes.content;
							}else if(props.attributes.content === ""){
								// if first load and content empty then refresh
								$refresh = true;
							}

							if ( ( !is_fetching &&  JSON.stringify(prev_attributes[props.id]) != JSON.stringify(props.attributes) ) || $refresh  ) {
								is_fetching = true;
								var data = {
									'action': 'super_duper_output_shortcode',
									'shortcode': '<?php echo $this->options['base_id'];?>',
									'attributes': props.attributes,
									'post_id': <?php global $post; if ( isset( $post->ID ) ) {
									echo $post->ID;
								}else{echo '0';}?>,
									'_ajax_nonce': '<?php echo wp_create_nonce( 'super_duper_output_shortcode' );?>'
								};

								jQuery.post(ajaxurl, data, function (response) {
									return response;
								}).then(function (env) {
									// if the content is empty then we place some placeholder text
									if (env == '') {
										env = "<div style='background:#0185ba33;padding: 10px;border: 4px #ccc dashed;'>" + "<?php _e( 'Placeholder for: ' );?>" + props.name + "</div>";
									}

									props.setAttributes({content: env});
									is_fetching = false;
									prev_attributes[props.id] = props.attributes;

									// if AUI is active call the js init function
									if (typeof aui_init === "function") {
										aui_init();
									}
								});

							}

							return props.attributes.content;

						}

						return [
							el(wp.blockEditor.BlockControls, {key: 'controls'},

								<?php if($show_alignment){?>
								el(
									wp.blockEditor.AlignmentToolbar,
									{
										value: props.attributes.alignment,
										onChange: function (alignment) {
											props.setAttributes({alignment: alignment})
										},
										key: props.attributes.alignment
									}
								)
								<?php }?>
							),
							el(wp.blockEditor.InspectorControls, {key: 'inspector'},
								<?php
									if(! empty( $this->arguments )){

									if ( $show_advanced ) {
								?>
								el('div', {
										style: {'paddingLeft': '16px','paddingRight': '16px'}
									},
									el(
										wp.components.ToggleControl,
										{
											label: 'Show Advanced Settings?',
											checked: props.attributes.show_advanced,
											onChange: function (show_advanced) {
												props.setAttributes({show_advanced: !props.attributes.show_advanced})
											},
											key: props.attributes.show_advanced
										}
									)
								),
								<?php
									}

									$arguments = $this->SD->group_arguments( $this->arguments );
									// Do we have sections?
									$has_sections = $arguments == $this->arguments ? false : true;

									if($has_sections){
										$panel_count = 0;
										foreach($arguments as $key => $args){
									?>
									el(wp.components.PanelBody, {
											title: '<?php esc_attr_e( $key ); ?>',
											initialOpen: <?php if ( $panel_count ) {
											echo "false";
										} else {
											echo "true";
										}?>
										},
										<?php
										foreach ( $args as $k => $a ) {
                                            $k = str_replace('-','__', $k);
											$this->block_row_start( $k, $a );
											$this->build_block_arguments( $k, $a );
											$this->block_row_end( $k, $a );
										}
										?>
									),
									<?php
									$panel_count ++;
										}
									} else {
									?>
									el(wp.components.PanelBody, {
										title: '<?php esc_attr_e( "Settings" ); ?>',
										initialOpen: true
									},
									<?php
										foreach ( $this->arguments as $key => $args ) {
                                            $key = str_replace('-','__', $key);
											$this->block_row_start( $key, $args );
											$this->build_block_arguments( $key, $args );
											$this->block_row_end( $key, $args );
										}
									?>
									),
									<?php
									}

								}
							?>
							),

							<?php
							// If the user sets block-output array then build it
							if ( ! empty( $this->options['block-output'] ) ) {
								$this->block_element( $this->options['block-output'] );
							} else {
								// if no block-output is set then we try and get the shortcode html output via ajax.
								?>
								el('div', {
									dangerouslySetInnerHTML: {__html: onChangeContent()},
									className: props.className,
									style: {'minHeight': '30px'}
                                    key: props.className + '-output'
								})
								<?php
								}
								?>
							]; // end return
						},

						// The "save" property must be specified and must be a valid function.
						save: function (props) {
							var attr = props.attributes;
							var align = '';

							// build the shortcode.
							var content = "[<?php echo $this->options['base_id'];?>";
							$html = '';
							<?php

							if(! empty( $this->arguments )){

							foreach($this->arguments as $key => $args){
                                $key = str_replace('-','__', $key);
								?>
								if (attr.hasOwnProperty("<?php echo esc_attr( $key );?>")) {
									if ('<?php echo esc_attr( $key );?>' == 'html') {
										$html = attr.<?php echo esc_attr( $key );?>;
									} else {
										content += " <?php echo str_replace( '__','-', esc_attr( $key ) );?>='" + attr.<?php echo esc_attr( $key );?>+ "' ";
									}
								}
								<?php
							}
							}

							?>
							content += "]";

							// if has html element
							if ($html) {
								content += $html + "[/<?php echo $this->options['base_id'];?>]";
							}

							// @todo should we add inline style here or just css classes?
							if (attr.alignment) {
								if (attr.alignment == 'left') {
									align = 'alignleft';
								}
								if (attr.alignment == 'center') {
									align = 'aligncenter';
								}
								if (attr.alignment == 'right') {
									align = 'alignright';
								}
							}

							<?php
								if(isset( $this->options['block-wrap'] ) && $this->options['block-wrap'] == ''){
							?>
							return content;
							<?php
							} else {
							?>
								var block_wrap = 'div';
								if (attr.hasOwnProperty("block_wrap")) {
									block_wrap = attr.block_wrap;
								}
								return el(block_wrap, {dangerouslySetInnerHTML: {__html: content}, className: align});
								<?php
							}
							?>

						}
					});
				})();
			</script>
		<?php
		$output = ob_get_clean();

		/*
		 * We only add the <script> tags for code highlighting, so we strip them from the output.
		 */

		return str_replace( array(
			'<script>',
			'</script>'
		), '', $output );
	}

	public function block_row_start($key, $args){
		// check for row
		if(!empty($args['row'])){
			if(!empty($args['row']['open'])){
				// element require
				$element_require = ! empty( $args['element_require'] ) ? $this->block_props_replace( $args['element_require'], true ) . " && " : "";
				echo $element_require;
					if(false){?><script><?php }?>
						el('div', {
							className: 'bsui components-base-control',
						},
						<?php if(!empty($args['row']['title'])){ ?>
						el('label', {
								className: 'components-base-control__label',
								key: '<?php esc_attr($key)?>-title'
							},
							'<?php echo addslashes( $args['row']['title'] ); ?>'
						),
						<?php }?>
						<?php if(!empty($args['row']['desc'])){ ?>
						el('p', {
								className: 'components-base-control__help mb-0',
								key: '<?php esc_attr($key)?>-desc'
							},
							'<?php echo addslashes( $args['row']['desc'] ); ?>'
						),
						<?php }?>
						el(
							'div',
							{
								className: 'row mb-n2 <?php if(!empty($args['row']['class'])){ echo esc_attr($args['row']['class']);} ?>',
							},
							el(
								'div',
								{
									className: 'col pr-2',
								},
					<?php
					if(false){?></script><?php }
					} elseif(!empty($args['row']['close'])) {
						if(false){?><script><?php }?>
						el(
							'div',
							{
								className: 'col pl-0',
							},
					<?php
					if(false){?></script><?php }
				}else{
					if(false){?><script><?php }?>
						el(
							'div',
							{
								className: 'col pl-0 pr-2',
							},
					<?php
					if(false){?></script><?php }
				}
		}
	}

	public function block_row_end($key, $args){
		if(!empty($args['row'])){
			// maybe close
			if(!empty($args['row']['close'])){
				echo "))";
			}
			echo "),";
		}
	}

	public function build_block_arguments( $key, $args ) {
		$custom_attributes = ! empty( $args['custom_attributes'] ) ? $this->SD->array_to_attributes( $args['custom_attributes'] ) : '';
		$options           = '';
		$extra             = 'key: \'' . $key . '\',' . "\n";

		// `content` is a protected and special argument
		if ( $key == 'content' ) {
			return;
		}

		// icon
		$icon = '';
		if( !empty( $args['icon'] ) ){
			$icon .= "el('div', {";
								$icon .= "dangerouslySetInnerHTML: {__html: '".self::get_widget_icon( esc_attr($args['icon']))."'},";
								$icon .= "className: 'text-center',";
								$icon .= "title: '".addslashes( $args['title'] )."',";
							$icon .= "}),";
			// blank title as its added to the icon.
			$args['title'] = '';
		}

		// require advanced
		$require_advanced = ! empty( $args['advanced'] ) ? "props.attributes.show_advanced && " : "";

		// element require
		$element_require = ! empty( $args['element_require'] ) ? $this->block_props_replace( $args['element_require'], true ) . " && " : "";
		$onchange  = "props.setAttributes({ $key: $key } )";
		$onchangecomplete  = "";
		$value     = "props.attributes.$key";
		$text_type = array( 'text', 'password', 'number', 'email', 'tel', 'url', 'colorx' );
		if ( in_array( $args['type'], $text_type ) ) {
			$type = 'TextControl';
			// Save numbers as numbers and not strings
			if ( $args['type'] == 'number' ) {
				$onchange = "props.setAttributes({ $key: Number($key) } )";
			}
		}
		elseif ( $args['type'] == 'color' ) {
			$type = 'ColorPicker';
			$onchange = "";
			$extra .= "color: $value,";
			if(!empty($args['disable_alpha'])){
				$extra .= "disableAlpha: true,";
			}
			$onchangecomplete = "onChangeComplete: function($key) {
			value =  $key.rgb.a && $key.rgb.a < 1 ? \"rgba(\"+$key.rgb.r+\",\"+$key.rgb.g+\",\"+$key.rgb.b+\",\"+$key.rgb.a+\")\" : $key.hex;
                        props.setAttributes({
                            $key: value
                        });
                    },";
		} elseif ( $args['type'] == 'checkbox' ) {
			$type = 'CheckboxControl';
			$extra .= "checked: props.attributes.$key,";
			$onchange = "props.setAttributes({ $key: ! props.attributes.$key } )";
		} elseif ( $args['type'] == 'textarea' ) {
			$type = 'TextareaControl';
		} elseif ( $args['type'] == 'select' || $args['type'] == 'multiselect' ) {
			$type = 'SelectControl';
				if($args['name'] == 'category' && !empty($args['post_type_linked'])){
				$options .= "options: taxonomies_".str_replace("-","_", $this->id).",";
			}elseif($args['name'] == 'sort_by' && !empty($args['post_type_linked'])){
				$options .= "options: sort_by_".str_replace("-","_", $this->id).",";
			}else {
					if ( ! empty( $args['options'] ) ) {
					$options .= "options: [";
					foreach ( $args['options'] as $option_val => $option_label ) {
						$options .= "{ value: '" . esc_attr( $option_val ) . "', label: '" . addslashes( $option_label ) . "' },";
					}
					$options .= "],";
				}
			}
			if ( isset( $args['multiple'] ) && $args['multiple'] ) { //@todo multiselect does not work at the moment: https://github.com/WordPress/gutenberg/issues/5550
				$extra .= ' multiple: true, ';
			}
		} elseif ( $args['type'] == 'alignment' ) {
			$type = 'AlignmentToolbar'; // @todo this does not seem to work but cant find a example
		}elseif ( $args['type'] == 'margins' ) {
		} else {
			return;// if we have not implemented the control then don't break the JS.
		}

		// color input does not show the labels so we add them
		if($args['type']=='color'){
			// add show only if advanced
			echo $require_advanced;
			// add setting require if defined
			echo $element_require;
			echo "el('div', {style: {'marginBottom': '8px'}}, '".addslashes( $args['title'] )."'),";
		}

		// add show only if advanced
		echo $require_advanced;
		// add setting require if defined
		echo $element_require;

		// icon
		echo $icon;
		?>
		el( wp.components.<?php echo $type; ?>, {
		label: '<?php echo addslashes( $args['title'] ); ?>',
		help: '<?php if ( isset( $args['desc'] ) ) {
			echo addslashes( $args['desc'] );
		} ?>',
		value: <?php echo $value; ?>,
		<?php if ( $type == 'TextControl' && $args['type'] != 'text' ) {
			echo "type: '" . addslashes( $args['type'] ) . "',";
		} ?>
		<?php if ( ! empty( $args['placeholder'] ) ) {
			echo "placeholder: '" . addslashes( $args['placeholder'] ) . "',";
		}
		echo $options;
        echo $extra;
        echo $custom_attributes;
        echo $onchangecomplete . "\n";?>
		onChange: function ( <?php echo $key; ?> ) {<?php echo $onchange; ?>}
		} ),
		<?php
	}


	/**
	 * A self looping function to create the output for JS block elements.
	 *
	 * This is what is output in the WP Editor visual view.
	 *
	 * @param $args
	 */
	public function block_element( $args ) {
		if ( ! empty( $args ) ) {
			foreach ( $args as $element => $new_args ) {
				if ( is_array( $new_args ) ) { // its an element
					if ( isset( $new_args['element'] ) ) {
						if ( isset( $new_args['element_require'] ) ) {
							echo str_replace( array(
									"'+",
									"+'"
								), '', $this->block_props_replace( $new_args['element_require'] ) ) . " &&  ";
							unset( $new_args['element_require'] );
						}

						echo "\n el( '" . $new_args['element'] . "', {";

						// get the attributes
						foreach ( $new_args as $new_key => $new_value ) {
							if ( $new_key == 'element' || $new_key == 'content' || $new_key == 'element_require' || $new_key == 'element_repeat' || is_array( $new_value ) ) {
								// do nothing
							} else {
								echo $this->block_element( array( $new_key => $new_value ) );
							}
						}
						echo "key:" . $element ."},";// end attributes

						// get the content
						$first_item = 0;
						foreach ( $new_args as $new_key => $new_value ) {
							if ( $new_key === 'content' || is_array( $new_value ) ) {
								if ( $new_key === 'content' ) {
									echo "'" . $this->block_props_replace( wp_slash( $new_value ) ) . "'";
								}

								if ( is_array( $new_value ) ) {
									if ( isset( $new_value['element_require'] ) ) {
										echo str_replace( array(
												"'+",
												"+'"
											), '', $this->block_props_replace( $new_value['element_require'] ) ) . " &&  ";
										unset( $new_value['element_require'] );
									}

									if ( isset( $new_value['element_repeat'] ) ) {
										$x = 1;
										while ( $x <= absint( $new_value['element_repeat'] ) ) {
											$this->block_element( array( '' => $new_value ) );
											$x ++;
										}
									} else {
										$this->block_element( array( '' => $new_value ) );
									}
								}
								$first_item ++;
							}
						}
						echo ")";// end content
						echo ", \n";
					}
				} else {
					if ( substr( $element, 0, 3 ) === "if_" ) {
						echo str_replace( "if_", "", $element ) . ": " . $this->block_props_replace( $new_args, true ) . ",";
					} elseif ( $element == 'style' ) {
						echo $element . ": " . $this->block_props_replace( $new_args ) . ",";
					} else {
						echo $element . ": '" . $this->block_props_replace( $new_args ) . "',";
					}
				}
			}
		}
	}

	/**
	 * Replace block attributes placeholders with the proper naming.
	 *
	 * @param $string
	 *
	 * @return mixed
	 */
	public function block_props_replace( $string, $no_wrap = false ) {
		if ( $no_wrap ) {
			$string = str_replace( array( "[%", "%]" ), array( "props.attributes.", "" ), $string );
		} else {
			$string = str_replace( array( "[%", "%]" ), array( "'+props.attributes.", "+'" ), $string );
		}

		return $string;
	}

}
