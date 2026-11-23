<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// is edge mode active?
if (!empty($this->edge_mode['available']) and !empty($this->preferences['edge_mode'])){
	$this->edge_mode['active'] = true;
}

// standalone needs dynamic JS called
if ($page_context == 'tvr-microthemer-preferences.php'){
	include $this->thisplugindir . '/includes/js-dynamic.php';
	wp_print_inline_script_tag($data);
}

$this->display_log();

$preference_tabs = array(
	esc_html_x('General', '(General Preferences)', 'microthemer' ),
	esc_html__('Units', 'microthemer'),
);
if ($this->supportContent()) {
	$preference_tabs[] = esc_html__('Amender', 'microthemer');
}
$preference_tabs[] = esc_html__('Inactive', 'microthemer');
$preference_tabs[] = esc_html__('Legacy', 'microthemer');

?>

<!-- Edit Preferences -->
<form id="tvr-preferences" name='preferences_form' method="post" autocomplete="off"
	action="<?php echo esc_url('admin.php?page=' . $page_context);?>">
	<?php //wp_nonce_field('tvr_preferences_submit'); ?>
	<input type="hidden" name="tvr_preferences_form" value="1" />
	<?php
	 echo wp_kses( $this->start_dialog(
        'display-preferences',
        __('Preferences', 'microthemer'),
        'medium-dialog',
		$preference_tabs,
		array(
			'text' => __('Download preferences', 'microthemer'),
			'link_class' => 'download-preferences'
		)
	), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>


<div class="content-main dialog-tab-fields">

	<?php
	$tab_count = -1; // start at -1 so ++ can be used for all tabs
	?>

	<!-- Tab 1 (General Preferences) -->
	<div class="dialog-tab-field dialog-tab-field-<?php echo (int) ++$tab_count; ?> hidden show">

		<?php
		//$this->show_me = '<pre>potatoes: '.print_r($this->preferences, true).'</pre>';
		//echo $this->show_me;
        $pref_cats = array(
            'css_sass' => array(
              'label' => __('CSS and Sass', 'microthemer'),
              'items' => array(
	              'css_important' => $this->initial_preference_options['css_important'],
	              'allow_scss' => $this->initial_preference_options['allow_scss'],
	              'minify_css' => array(
		              'label' => __('Minify published CSS files', 'microthemer'),
		              'explain' => __('Minify the CSS code - not necessary is you use an asset optimisation plugin' , 'microthemer')
	              ),
	              'color_as_hex' => array(
		              'label' => __('Report color in hex values instead of RGB/A', 'microthemer'),
		              'explain' => __('By default, Microthemer will report computed CSS color values in RGB/A format. Set this to "Yes" if you prefer the hex format.', 'microthemer')
	              ),
	              'abs_image_paths' => array(
		              'label' => __('Use absolute background image URL paths', 'microthemer'),
		              'explain' => __('If you install WordPres in a sub-directory, setting this to "Yes" can fix issues with image paths.', 'microthemer'),
	              ),

	              /*'server_scss' => array(
		              'label' => __('Compile Sass on the server (usually slower)', 'microthemer'),
		              'explain' => __('Client-side SCSS compilation is a new feature. Enable server-side compilation if you notice any problems with client-side compilation' , 'microthemer')
	              ),*/
	              'global_styles_on_login' => array(
		              'label' => __('Enable global CSS on WordPress login pages', 'microthemer'),
		              'explain' => __('Load the global active-styles.css file on WordPress login, registration, and forgot password pages' , 'microthemer')
	              ),
              )
            ),
            'wordpress_toolbar' => array(
	            'label' => __('WordPress Admin', 'microthemer'),
	            'items' => array(
		            'admin_bar_shortcut' => array(
			            'label' => __('Add a shortcut to the WP admin bar', 'microthemer'),
			            'explain' => __('Include a link to the interface from the WordPress admin toolbar at the top of every page.', 'microthemer'),
			            //'default' => 'yes'
		            ),
		            'top_level_shortcut' => array(
			            'label' => __('If yes to above, include as a top level link', 'microthemer'),
			            'explain' => __('If you are enabling the shortcut in the admin bar, you can either have it as a top level menu link or as a sub-menu item of the main menu.', 'microthemer'),
			            //'default' => 'yes'
		            ),
		            'admin_bar_preview' => array(
			            'label' => __('On site preview, display WP admin bar ', 'microthemer'),
			            'explain' => __('Display the WordPress admin bar at the top of every page in the site preview', 'microthemer')
		            ),
		            'admin_asset_loading' => array(
			            'label' => __('Support admin area style loading', 'microthemer'),
			            'explain' => __('Individual folders can load on admin pages, including Gutenberg pages', 'microthemer')
		            ),
		            'admin_asset_editing' => array(
			            'label' => __('Support admin area point and click editing', 'microthemer'),
			            'explain' => __('Customise the design of the admin area just like on the frontend', 'microthemer')
		            ),
                    'add_block_classes_all' => array(
	                    'label' => __('Always add classes to blocks (even outside of MT)', 'microthemer'),
	                    /* translators: 1: application name, 2: application name */
	                    'explain' => sprintf(__('When loading Gutenberg inside %1$s, it adds a unique class (e.g. "mctr-e5i34p") to all  blocks to make styling easier. You can also have this happen outside of %2$s if you prefer using separate tabs with synced CSS and content changes.', 'microthemer'), $this->appName, $this->appName)
                    ),
	            )
            ),
            'fonts' => array(
	            'label' => __('Fonts', 'microthemer'),
	            'items' => array(
		            'gfont_subset' => array(
			            'is_text' => 1,
			            //'one_line' => 1,
			            'label' => __('Google Font subset URL parameter', 'microthemer'),
			            'explain' => __('You can instruct Google Fonts to include a font subset by entering an URL parameter here. For example "&subset=latin,latin-ext" (without the quotes). Note: Microthemer only generates a Google Font URL if it detects that you have applied Google Fonts in your design.', 'microthemer'),
		            ),
	            )
            ),

            'tools' => array(
	            'label' => __('Tools', 'microthemer'),
	            'items' => array(
		            'manual_recompile_all_css' => array(
			            'label' => __('Regenerate all CSS (can fix certain issues)', 'microthemer'),
			            'explain' => __('If an error occurs, this can sometimes fix the issue', 'microthemer')
		            ),
		            'refresh_template_map' => array(
			            'label' => __('Regenerate Gutenberg template cache', 'microthemer'),
			            'explain' => __('Do this if you\'ve uploaded new block template/part/pattern files to your theme directory', 'microthemer')
		            ),
		            'edge_mode' => array(
			            'label' => __('Enable edge mode. ', 'microthemer'),
			            'link' => '<a target="_blank" href="'.$this->edge_mode['edge_forum_url'].'">' . __('Read about/comment here', 'microthemer') .'</a>',
			            'explain' => $this->edge_mode['cta'],
		            ),
	            )
            ),

            'interface' => array(
	            'label' => __('Interface', 'microthemer'),
	            'items' => array(
		            'preview_url' => array(
			            'is_text' => 1,
			            'input_id' => 'pref-preview-url-input',
			            'label' => __('Frontend preview URL to edit', 'microthemer'),
			            'explain' => __('Manually specify a link to the page you would like edit. This option can be used via the standalone preferences pages to fix any issues caused by an invalid URL.', 'microthemer')
		            ),
		            'num_history_points' => array(
			            'is_text' => 1,
			            'one_line' => 1,
			            'label' => __('Number of recent revisions to store', 'microthemer'),
			            'explain' => __('Choose how many revisions to store in the Database. The allowed range is 1-300. The default is 50. Saved revisions do not count towards the quota. Nor do pre-upgrade backups.', 'microthemer'),
			            'combobox' => 'num_history_points',
		            ),
		            'fold_threshold' => array(
			            'is_text' => 1,
			            'one_line' => 1,
			            'label' => __('Below fold threshold for async css', 'microthemer'),
			            'explain' => __('Set the screen height below which elements will be styled with asynchronous (non-blocking) CSS, for maximum page speed ("Auto folder" must be enabled for this)', 'microthemer'),
			            'combobox' => 'fold_threshold',
		            ),
		            'code_font_size' => array(
			            'is_text' => 1,
			            'one_line' => 1,
			            'label' => __('Set the font-size for the code editor', 'microthemer'),
			            'explain' => ''
		            ),
                    //
		            'tape_measure_slider' => array(
			            'label' => __('Enable tape measure style sliders', 'microthemer'),
			            'explain' => __('The numbers in the tape measure design may be helpful, but it involves dragging left to increase values, which may feel unintuitive', 'microthemer'),

		            ),
		            'gzip' => array(
			            'label' => __('Gzip the UI page for faster loading', 'microthemer'),
			            'explain' =>__('Having this gzip option enabled will speed up the initial page loading, but you can switch it off if this setting is not compatible with your server.', 'microthemer')
		            ),
		            'hover_inspect_off_initially' => array(
			            'label' => __('Disable targeting mode when the interface first loads', 'microthemer'),
			            'explain' =>__('Useful for devs that prefer to code selectors manually, without assistance', 'microthemer')
		            ),
		            'scroll_to_elements' => array(
			            'label' => __('Auto-scroll to the current element, if out of view', 'microthemer'),
			            'explain' =>__('If your selector targets elements that are off-screen, the browser will auto-scroll them into view', 'microthemer')
		            ),
		            'autofocus_editor' => array(
			            'label' => __('Auto-focus the code editor cursor on element select', 'microthemer'),
			            'explain' =>__('This saves time clicking on the code editor after selecting an element, but can also lead to accidental typing in the editor', 'microthemer')
		            ),
		            'disable_ai' => array(
			            'label' => __('Disable the AI Assistant', 'microthemer'),
			            'explain' =>__('Useful if you don\'t need AI and want the "Settings" panel included in the "L" layout preset.', 'microthemer')
		            ),

		            /*'tooltip_delay' => array(
			            'label' => __('Tooltip delay time (in milliseconds)', 'microthemer'),
			            'explain' => __('Control how long it takes for a Microthemer tooltip to display. Set to "0" for instant, "native" to use the browser default tooltip on hover, or some value like "2000" for a 2 second delay (so it never shows when you don\'t need it to). The default is 500 milliseconds.', 'microthemer')
		            ),*/

	            )
            ),

            'javascript' => array(
	            'label' => __('JavaScript', 'microthemer'),
	            'items' => array(
		            'monitor_js_errors' => array(
			            'label' => __('Monitor general JavaScript errors on your site', 'microthemer'),
			            /* translators: 1: application name, 2: application name */
	            'explain' => sprintf(__('General JavaScript errors on your site can interfere with %1$s, and other plugins. %2$s can check for errors and warn you about them.', 'microthemer'), $this->appName, $this->appName)
		            ),
		            'minify_js' => array(
			            'label' => __('Minify published JavaScript files', 'microthemer'),
			            'explain' => __('Minify the custom JavaScript you add - not necessary is you use an asset optimisation plugin' , 'microthemer')
		            ),
		            'active_scripts_footer' => array(
			            'label' => __('Load global JavaScript in the footer', 'microthemer'),
			            'explain' => __('Load your active-scripts.js file just before the closing body tag', 'microthemer')
		            ),
		            'active_scripts_deps' => array(
			            'is_text' => 1,
			            'label' => __('List WP script handles your global JS depends on', 'microthemer'),
			            'explain' => __('If your custom JavaScript depends on a library, enter the library handles (comma separated)', 'microthemer')
		            ),
	            )
            ),




        );

		// preference categories added by extension components
		$pref_cats = $this->extFilter('preference_categories', $pref_cats);

		// output
        echo wp_kses( $this->preferences_grid($pref_cats, 'main-preferences-grid'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

        ?>

	</div>

    <!-- Tab 2 (CSS Units) -->
    <div class="dialog-tab-field dialog-tab-field-<?php echo (int) ++$tab_count; ?> hidden">
        <?php include $this->thisplugindir . 'src/CSS/templates/css-units.php'; ?>
    </div>


	<?php if ($this->supportContent()): ?>
		<!-- Tab 3 (Amender) -->
		<div class="dialog-tab-field dialog-tab-field-<?php echo (int) ++$tab_count; ?> hidden">
			<?php include $this->thisplugindir . 'src/Content/templates/content-preferences.php'; ?>
		</div>
	<?php endif; ?>

	<!-- Tab 4 (Inactive) -->
	<div class="dialog-tab-field dialog-tab-field-<?php echo (int) ++$tab_count; ?> hidden">
		<ul class="form-field-list delete-upon-install">
			<?php
			// yes no options
			$yes_no = array(
				'clean_uninstall' => array(
				'label' => sprintf(
                            /* translators: %s: plugin type label */
                            __('Upon Uninstall, Delete ALL %s Data', 'microthemer'),
						    'plugin'
                    ),
				'explain' => sprintf(
					/* translators: 1: application name, 2: application name */
					__('%1$s database settings and the contents of its folder in uploads are not deleted by default when you uninstall %2$s. But they can be if you set this option to Yes.', 'microthemer'),
					$this->appName,
					$this->appName
				),
				)
			);
			$this->output_radio_input_lis($yes_no);

			?>
		</ul>

		<div id="functions-php">
			<div class="heading"><?php echo esc_html__('Deactivating keeps your changes', 'microthemer'); ?></div>
			<p class="instruction-text"><?php /* translators: 1: application name, 2: application name */ echo sprintf(esc_html__('Deactivating or uninstalling %1$s never deletes your work unless you set the above option to Yes. Your styles stop loading while %2$s is inactive, and load again as soon as it is reactivated.', 'microthemer'), esc_html($this->appName), esc_html($this->appName)); ?></p>
			<?php $this->extAction('inactive_loading_info'); ?>
		</div>
	</div>

    <!-- Tab 5 (Legacy) -->
    <div class="dialog-tab-field dialog-tab-field-<?php echo (int) ++$tab_count; ?> hidden">

        <p>The following settings are rarely needed. Or, they only have relevance to previous versions but still need to be configurable.</p>

        <ul class="form-field-list compatibility-settings">
			<?php

            // ensure this setting is off by default
			$this->preferences['remove_all_bricks_container_hacks'] = 0;

			$options = array(
				'integrations' => array(
					'label' => __('Stylesheet', 'microthemer'),
					'items' => array(
						'stylesheet_in_footer' => array(
							'label' => __('Load the Stylesheet in the footer', 'microthemer'),
							'explain' => __("Load Microthemer's CSS in the footer", 'microthemer'),

						),
						'stylesheet_order' => array(
							'is_text' => 1,
							'one_line' => 1,
							'combobox' => 'stylesheet_order_options',
							'label' => __('Stylesheet loading order', 'microthemer'),
							'explain' => __("Set the order Microthemer's active-styles.css stylesheet loads with respect to other stylesheets", 'microthemer'),

						),
						'page_class_prefix' => array(
							'is_text' => 1,
							'one_line' => 1,
							'combobox' => 'page_class_prefix_options',
							'label' => __('Prefix for page-specific body element classes', 'microthemer'),
							'explain' => __("Change this value if the default clashes with a utility library like Bootstrap or Tailwind", 'microthemer'),

						),
						//support this soon: https://wordpress.org/support/article/custom-fields/
						'insert_custom_field_classes' => array(
							'label' => __('Insert body classes defined using', 'microthemer'),
							'explain' => __("Append WordPress post/page custom field values (metadata) to the body tag class attribute when the field key is: my_body_classes", 'microthemer'),
							'link' => sprintf(
								'<a href="%s" target="_blank">%s</a>',
								esc_url( 'https://wordpress.org/support/article/custom-fields/' ),
								esc_html__( 'custom fields', 'microthemer' )
							)

						)
					)
				),
				'compatibility' => array(
					'label' => __('Compatibility', 'microthemer'),
					'items' => array(
						'bricks_container_hack' => array(
							'label' => __('Enable Bricks grid workaround (BGW)', 'microthemer'),
							'explain' => __('Ensure styles applied to Bricks container elements also work when Bricks is active - by creating special selectors that will be tagged with [BGW]', 'microthemer'),
						),
						'remove_all_bricks_container_hacks' => array(
							'label' => __('Remove all Bricks grid workarounds (BGW)', 'microthemer'),
							'explain' => __('Automatically update all BGW selectors to remove the workaround that may not be necessary anymore', 'microthemer'),
						),
					)
				),
				'misc' => array(
					'label' => __('Miscellaneous', 'microthemer'),
					'items' => array(
						'first_and_last' => array(
							'label' => __('Add "first" and "last" classes to menu items', 'microthemer'),
							'explain' => __('Microthemer can insert "first" and "last" classes on WordPress menus so that you can style the first or last menu items a bit differently from the rest. Note: this only works with "Custom Menus" created on the Appearance > Menus page.', 'microthemer')
						),
						/*'hide_ie_tabs' => array(
							'label' => __('Hide the legacy Internet Explorer tabs', 'microthemer'),
							'explain' => __('Microthemer\'s IE tabs are not really needed these days, and so are hidden by default. Set this option to No if you still want them.' , 'microthemer')
						),*/
					)
				),
			);

			echo wp_kses( $this->preferences_grid($options, 'main-preferences-grid'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

			?>
        </ul>
    </div>

	<?php
	// standalone needs inline button
	if ($page_context == 'tvr-microthemer-preferences.php'){
		echo wp_kses( $this->dialog_button(esc_html__('Save Preferences', 'microthemer'), 'span', 'save-preferences'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
	}
	?>

</div>

<?php echo wp_kses( $this->end_dialog(esc_html__('Save Preferences', 'microthemer'), 'span', 'save-preferences'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>
</form>
