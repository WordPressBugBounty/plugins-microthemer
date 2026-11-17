<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// is edge mode active?
if (!empty($this->edge_mode['available']) and !empty($this->preferences['edge_mode'])){
	$this->edge_mode['active'] = true;
}

// dev tool - refresh option-icon css after updating icon-size-x in property options file
$dev_tasks = false;
if ($dev_tasks and TVR_DEV_MODE){

    // update the option icons - after adding new properties
	//include $this->thisplugindir . 'includes/regenerate-option-icons.inc.php';

	// update the animation array - if new version of animate.css
	include $this->thisplugindir . 'includes/regenerate-animations.inc.php';

}

// set interface classes

// don't show panel content that depends on an el by default, this class will be removed if a valid triggerEl
$ui_class = 'mt-zero-elements tvr-no-sels hide-mt-suggestions';

$this->preferences['admin_bar_preview'] ? $ui_class.= ' show-admin-bar' : $ui_class.= ' do-not-show-admin-bar';
//$this->preferences['auto_capitalize'] ? $ui_class.= ' tvr-caps' : false;
$this->preferences['mt_dark_mode'] ? $ui_class.= ' mt_dark_mode' : false;

$ui_class = $this->extFilter('ui_class', $ui_class);
$this->preferences['hide_interface'] ? $ui_class.= ' hide_interface' : false;
($this->preferences['css_important'] != 1) ? $ui_class.= ' manual-css-important' : false;
$this->preferences['show_code_editor'] ? $ui_class.= ' show_code_editor' : false;
$this->preferences['show_text_labels'] ? $ui_class.= ' show_text_labels' : false;
$this->preferences['show_rulers'] ? $ui_class.= ' show_rulers' : false;
$this->preferences['draft_mode'] ? $ui_class.= ' draft_mode' : false;
$this->preferences['dock_wizard_right'] ? $ui_class.= ' dock_wizard_right' : false;
$this->preferences['dock_settings_right'] ? $ui_class.= ' dock_settings_right' : false;
$this->preferences['dock_ai_right'] ? $ui_class.= ' dock_ai_right' : false;
$this->preferences['hover_inspect'] ? $ui_class.= ' hover_inspect' : false;
$this->preferences['selname_code_synced'] ? $ui_class.= ' selname_code_synced' : false;
$this->preferences['wizard_expanded'] ? $ui_class.= ' wizard_expanded' : false;
$this->preferences['code_manual_resize'] ? $ui_class.= ' code_manual_resize' : false;
$this->preferences['show_extra_actions'] ? $ui_class.= ' show_extra_actions' : false;
$this->preferences['specificity_preference'] ? $ui_class.= ' specificity_preference' : false;
$this->preferences['auto_save_mode'] ? $ui_class.= ' auto_save_mode' : false;
$this->preferences['auto_publish_mode'] ? $ui_class.= ' auto_publish_mode' : false;
$this->preferences['sticky_styles_toolbar'] ? $ui_class.= ' sticky_styles_toolbar' : false;
$this->preferences['dock_folders_left'] ? $ui_class.= ' dock_folders_left' : false;
$this->preferences['dock_styles_left'] ? $ui_class.= ' dock_styles_left' : false;
$this->preferences['dock_editor_left'] ? $ui_class.= ' dock_editor_left' : false;
$this->preferences['full_height_left_sidebar'] ? $ui_class.= ' full_height_left_sidebar' : false;
$this->preferences['full_height_right_sidebar'] ? $ui_class.= ' full_height_right_sidebar' : false;
$this->preferences['expand_device_tabs'] ? $ui_class.= ' expand_device_tabs' : false;
$ui_class.= ' mt-left-cols-'. $this->preferences['layout']['left']['effective_num_columns'];
$ui_class.= ' mt-right-cols-'. $this->preferences['layout']['right']['effective_num_columns'];
$ui_class.= ' mt-top-rows-'. $this->preferences['layout']['top']['effective_num_rows'];

$this->preferences['detach_preview'] ? $ui_class.= ' detach_preview' : false;
!empty($this->preferences['show_sampled_values']) ? $ui_class.= ' show_sampled_values' : false;
!empty($this->preferences['show_sampled_variables']) ? $ui_class.= ' show_sampled_variables' : false;
!empty($this->preferences['tape_measure_slider']) ? $ui_class.= ' tape_measure_slider' : false;
!empty($this->preferences['show_setup_screen_first_time']) ? $ui_class.= ' show_setup_screen_first_time' : false;
!empty($this->preferences['show_setup_screen_first_time']) ? $ui_class.= ' show_setup_screen_first_time' : false;
!empty($this->preferences['show_snippet_adv']) ? $ui_class.= ' show_snippet_adv' : false;
!empty($this->preferences['mt_rich_text']) ? $ui_class.= ' mt_rich_text' : false;
!empty($this->preferences['mt_rich_text_code']) ? $ui_class.= ' mt_rich_text_code' : false;

// signal if error reporting is disabled
$repPerm = $this->preferences['reporting']['permission'];
if (empty($repPerm['file']) && empty($repPerm['data'])){
	$ui_class.= ' error_reporting_disabled';
}

// signal if 3rd party plugins are active
$no_integrations_available = true;
foreach ($this->integrations as $intKey => $val){
	if (!empty($val)){
		$ui_class.= ' ' . $intKey.'_active';
		$no_integrations_available = false;
	}
}

// signal that some integrations are available for e.g. launch builder checkbox
if ($no_integrations_available){
	$ui_class.= ' no_integrations_available';
} else {
	$ui_class.= ' integrations_available';
}

// page specific class is added if at least one option is on
if (is_array($this->css_filters)){
	foreach ($this->css_filters as $key => $arr){
		if (empty($arr['items']) || !is_array($arr['items'])){
			continue;
		}
		foreach ($arr['items'] as $i => $val){
			if (!empty($this->preferences[$key][$i])){
				$ui_class.= ' '.$key;
				break;
			}
		}
	}
}

// Signal plugin capabilities
$corePlugin = $this->isContentEditor()
	? 'core-amender'
	: 'core-microthemer'
;

$ui_class.= ' ' . $corePlugin;
if (!$this->supportContent()){
	$ui_class.= ' microthemer-only';
} else {
	$ui_class.= ' support-amender';
	if ($this->canPublishContent()){
		$ui_class.= ' amender-pro';
	}
}

if (!$this->supportGUICSS()){
	$ui_class.= ' amender-only';
} else {
	$ui_class.= ' support-microthemer';
	if ($this->extendedCSS()){
		$ui_class.= ' microthemer-pro';
	}
}

$ui_class.= ' original-install-'.preg_replace("/[ +]+/", "", strtolower($this->preferences['original_install']));



//$this->show_me = '$this->supportGUICSS(): ' . $this->supportGUICSS();

// edge mode interface classes
if (!empty($this->edge_mode['active'])){
	if (!empty($this->edge_mode['config']) && is_array($this->edge_mode['config'])){
		foreach ($this->edge_mode['config'] as $key => $value){
			$ui_class.= ' '.$key.'-'.$value;
		}
	}
}


// set the css filters here so that favourites get updated regardless of display order of main filters
// (note this is also run on the detached preview page
$css_filters =  $this->display_css_filters();

$for_main_ui = true;

require_once('common-inline-assets.php');

?>

<div id="tvr" class='wrap tvr-wrap <?php echo esc_attr($ui_class); ?>'>
<!-- <div id='tvr-ui'>-->

		<?php
		// root ui toggle for showing/hiding extra action icons in folders and selectors menu
		echo wp_kses( $this->extra_actions_icon('show_extra_actions'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

		// root toggle for showing variables
        $sampledTypes = array('variables', 'values');
        foreach ($sampledTypes as $sType){
            $sKey = 'show_sampled_'.$sType;
	        echo wp_kses( $this->ui_toggle(
		        $sKey,
		        esc_attr__('Expand variables', 'microthemer'),
		        esc_attr__('Collapse variables', 'microthemer'),
		        !empty($this->preferences[$sKey]),
		        'sampled-'.$sType.'-toggle tvr-icon',
		        $sKey
	        ), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
        }


		/*echo $this->ui_toggle(
			'show_extra_actions',
			esc_attr__('Show more actions', 'microthemer'),
			esc_attr__('Show less actions', 'microthemer'),
			$this->preferences['show_extra_actions'],
			'extra-actions-toggle tvr-icon',
			'show_extra_actions'
		);
		*/
		?>

        <!--<input type="text" id="tvr-width-input" class="property-input combobox" name="non_section[tvr_width_input]" value="" />-->

        <span id="inputWidthCalc"></span>
        <span id="codeInputWidthCalc"></span>

		<span id="ui-nonce"><?php echo esc_html(wp_create_nonce("tvr_microthemer_ui_load_styles")); ?></span>
		<span id="fonts-api" rel="<?php echo esc_url($this->thispluginurl.'includes/fonts-api.php'); ?>"></span>
		<span id="ui-url" rel="<?php echo esc_attr("admin.php?page=" . $this->microthemeruipage); ?>"></span>
		<span id="admin-url" rel="<?php echo esc_url($this->wp_blog_admin_url); ?>"></span>
		<span id="micro-url" rel="<?php echo esc_url($this->micro_root_url); ?>"></span>
		<span id="user-browser" rel="<?php echo esc_attr($this->check_browser()); ?>"></span>
		<span id="clean-ui-url" rel="<?php echo isset($_GET['_wpnonce']) ? 1 : 0; ?>"></span>

		<span id="wpAjaxUrl" rel="<?php echo esc_url($this->wp_ajax_url); ?>"></span>
		<span id="wpMediaFakePostID"></span>

		<span id='site-url' rel="<?php echo esc_url($this->site_url); ?>"></span>
		<span id='home-url' rel="<?php echo esc_url($this->home_url); ?>"></span>
		<span id="active-styles-url" rel="<?php echo esc_url($this->micro_root_url . 'active-styles.css') ?>"></span>


		<span id='all_devices_default_width' rel='<?php echo esc_attr($this->preferences['all_devices_default_width']); ?>'></span>

		<span id='plugin-url' rel='<?php echo esc_url($this->thispluginurl); ?>'></span>
		<?php // the stock animations (animate.css) ship with pro builds only ?>
		<span id='code-editors' rel='<?php echo $this->canEditCode() ? 1 : 0; ?>'></span>
		<span id='animate-css-url' rel='<?php echo file_exists($this->thisplugindir . 'css/animate.min.css') ? esc_url($this->thispluginurl . 'css/animate.min.css?v=2') : ''; ?>'></span>
		<span id='docs-url' rel='<?php echo esc_attr("admin.php?page=" . $this->docspage); ?>'></span>
		<span id='tooltip_delay' rel='<?php echo esc_attr($this->preferences['tooltip_delay']); ?>'></span>

        <span id='inno-firewall-issue' rel='<?php echo $this->innoFirewall ? 1 : 0; ?>'></span>


        <?php
        // root toggle for manual resize of editor
      /*  echo $this->ui_toggle(
            'code_manual_resize',
            esc_attr__('Make editor height drag resizable', 'microthemer'),
            esc_attr__('Auto-set editor height', 'microthemer'),
            $this->preferences['code_manual_resize'],
            'code-manual-resize tvr-icon',
            'code_manual_resize' // id
        );*/

        // root element highlight toggle
        $keyboard = ' (Ctrl + Alt + H)';
        echo wp_kses( $this->ui_toggle(
	        'mt_highlight',
	        esc_attr__('Enable highlighting', 'microthemer').$keyboard,
	        esc_attr__('Disable highlighting', 'microthemer').$keyboard,
	        0, // this gets turned on by JS (so classes get added too)
	        'toggle-highlighting',
	        'toggle-highlighting', // id
	        array('dataAtts' => array(
	            'fHTML' => 1,
		        'no-save' => 1
	        ))
        ), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
        ?>

		<?php
		// edge mode settings
		if (!empty($this->edge_mode['active'])){
			?>
			<span id='edge-mode' rel='1'></span>
			<?php
			if (!empty($this->edge_mode['config']) && is_array($this->edge_mode['config'])){
				foreach ($this->edge_mode['config'] as $key => $value){
					echo '<span id="'.esc_attr($key).'" rel="'.esc_attr($value).'"></span>';
				}
			}
		}
		?>
		<?php $this->extAction('ui_state_elements'); ?>

		<form method="post" name="tvr_microthemer_ui_serialised" id="tvr_microthemer_ui_serialised" autocomplete="off">
			<textarea id="tvr-serialised-data" name="tvr_serialized_data"></textarea>
		</form>

		<form method="post" name="tvr_microthemer_ui_save" id="tvr_microthemer_ui_save" autocomplete="off">
		<?php wp_nonce_field('tvr_microthemer_ui_save'); ?>
		<input type="hidden" name="action" value="tvr_microthemer_ui_save" />
		<!--<textarea id="user-action" name="tvr_mcth[non_section][meta][user_action]"></textarea>-->


		<div id="visual-view" class="visual-view">

			<div id="mt-top-controls">

                <?php
                // the code editors ship with the pro property map only and need the user's permission (see Admin::canEditCode)
                if ($this->canEditCode()){
                    include $this->thisplugindir . 'includes/code-editors/full-code-editor.php';
                }
                ?>


                <div id="tvr-nav" class="tvr-nav">


                    <div id="current-folder-item" class="tvr-input-wrap">

                        <div id="quick-nav" class="mt-no-flex">

		                    <?php
		                    echo
			                    wp_kses( $this->iconFont('arrow-alt-circle-left-regular', array(
				                    'id' => 'vb-focus-prev',
				                    'class' => 'scroll-buttons',
				                    'title' => __("Previous selector (Ctrl+Alt+,)", 'microthemer'),
			                    ))
			                    .
			                    $this->iconFont('arrow-alt-circle-right-regular', array(
				                    'id' => 'vb-focus-next',
				                    'class' => 'scroll-buttons mt-icon-divider',
				                    'title' => __("Next selector (Ctrl+Alt+.)", 'microthemer'),
			                    )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() )

		                    ?>

                        </div>

                        <span class="bc-sep mt-no-flex"></span>

                        <?php //echo  $this->svg('chevron');  debug later ?>

                        <div id="tvr-main-menu" class="tvr-main-menu-wrap hierarchy-item mt-no-flex">

		                    <?php
		                    echo wp_kses( $this->iconFont('folder', array(
			                    'id' => 'main-menu-tip-trigger',
			                    'class' => 'folder-icon main-menu-tip-trigger',
				                    'title' => __('View all folders', 'microthemer'),
			                    'data-trigger' => 'all',
			                    'data-forpopup' => 'folders',
		                    )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
		                    ?>

                        </div>

                        <span id="sec-in-focus" class="quick-menu-text sec-in-focus"
                              data-trigger="folder" data-forpopup="folders"
                              title="<?php echo esc_attr__('View current folder', 'microthemer'); ?>"></span>
                        <input id="sec-hierarchy-input" type="text" rel="cur_folders" data-appto="#style-components" spellcheck="false"
                               class="sec-hierarchy-input hierarchy-input combobox has-arrows" name="sec_hierarchy_input" value="">
                        <span class="combo-arrow cur-item-dropdown"></span>
                        <span class="bc-sep folder-sel-sep mt-no-flex"></span>

                        <?php
                        echo wp_kses( $this->ui_toggle(
	                        'selector_auto_name',
	                        esc_attr__('Enable auto-naming', 'microthemer'),
	                        esc_attr__('Disable auto-naming (temporarily)', 'microthemer'),
	                        !empty($this->preferences['selector_auto_name']),
	                        'selector-auto-name ' . $this->iconFont('bolt', array(
		                        'onlyClass' => 1
	                        )),
	                        'selector_auto_name',
	                        array('dataAtts' => array(
		                        'no-save' => 1
	                        ))
                        ), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                        ?>

                        <input id="sel-hierarchy-input" type="text" rel="alt_label_suggestions"
                               data-appto="#style-components" spellcheck="false" class="sel-hierarchy-input hierarchy-input combobox has-suggestions" name="sel_hierarchy_input" value=""
                               title="<?php echo esc_attr__('Edit selector label', 'microthemer'); ?>">
                        <span class="combo-arrow combo-dots cur-item-dropdown sel-label-dot-menu"></span>

                        <div class="save-selector-label tvr-button" title="<?php echo esc_attr__('Save selector', 'microthemer'); ?>">
                           <?php echo esc_html__('Save', 'microthemer'); ?>
                        </div>

                        <?php
                        echo wp_kses( $this->iconFont('times-circle-regular', array(
                                'class' => "cancel-selector-label-edit",
	                                'title' => __('Cancel', 'microthemer')
                             )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                        echo wp_kses( $this->ui_toggle(
		                    'selname_code_synced',
		                    esc_attr__('Sync label and code', 'microthemer'),
		                    esc_attr__('Unsync label and code', 'microthemer'),
		                    $this->preferences['selname_code_synced'],
		                    'code-chained-icon selname-code-sync current-item-sync ' . $this->iconFont('chain', array(
			                    'onlyClass' => 1
		                    )),
		                    'selname_code_synced',
		                    array('dataAtts' => array(
			                    'fhtml' => 1 // for quick options font. Note: $.data() is case insensitive
		                    ))

	                    ), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                        // combo-dots, has-suggestions removed to make input easier to search
                        //
                        ?>

                        <input id="code-hierarchy-input" type="text"
                               data-appto="#style-components"
                               rel="all_sel_suggestions" spellcheck="false"
                               class="code-hierarchy-input hierarchy-input has-arrows combobox" name="code_hierarchy_input" value="">
                        <span class="combo-arrow cur-item-dropdown"></span>

                       <!-- <span class="num-els-icon-wrap">
                            <span class="num-els-icon"></span>
                        </span>-->

                        <?php
                        // CSS modifiers toggle (in future maybe have JS event classes here)
	                        $pos_title = __('Show selector modifiers', 'microthemer');
	                        $neg_title = __('Hide selector modifiers', 'microthemer');
                        echo wp_kses( $this->iconFont('cog', array(
	                        'id' => "show_css_filters-toggle",
	                        'class' => "toggle-css-modifiers toggle-cm-using-atts",
	                        'title' => $pos_title,
	                        'data-filter' => '#cm-css-filters',
	                        'data-forpopup' => 'contextMenu',
	                        'data-pos' => $pos_title,
	                        'data-neg' => $neg_title
                        )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
                        ?>

	                    <?php
	                    // save / update buttons
	                    echo  wp_kses( '
                    <div id="create-sel-wrap" class="create-sel-wrap mt-no-flex">

                        <div class="save-draft-selector tvr-button" title="'.esc_attr__('Save selector', 'microthemer').'">
                            '.esc_html__('Save', 'microthemer').'
                        </div>
                    
                        <div id="wizard-update-cur" class="wizard-update-cur tvr-button" title="'.esc_attr__('Update selector', 'microthemer').'"> 
                            '.esc_html__('Update', 'microthemer').'
                        </div>
                        
                        
	                         '.
// attribute value, so escaping here as well would double-encode translated strings containing
// quotes or ampersands.
	                         $this->iconFont('times-circle-regular', array(
			                    'class' => "cancel-current-selector-edit",
			                    'title' => __('Cancel', 'microthemer')
		                    ))
	                         .'
                    </div>', \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
	                    ?>


                        <div id="mt-current-item-actions" class="mt-no-flex">

		                    <?php
		                    echo wp_kses( $this->item_manage_icons('selector', 'selector_css', array(

			                    'context' => 'current-item',
			                    'sub_context' => 'main'

			                    /* 'html_before' =>
								 $this->iconFont('arrow-alt-circle-left-regular', array(
									 'id' => 'vb-focus-prev',
									 'class' => 'scroll-buttons',
								 'title' => __("Previous selector (Ctrl+Alt+,)", 'microthemer'),
								 ))
								 .
								 $this->iconFont('arrow-alt-circle-right-regular', array(
									 'id' => 'vb-focus-next',
									 'class' => 'scroll-buttons mt-icon-divider',
								 'title' => __("Next selector (Ctrl+Alt+.)", 'microthemer'),
								 ))*/
		                    )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
		                    ?>

                        </div>



                    </div>



                    <div id="starter-message" class="hidden">
		                <?php esc_html_e('Click anything on the page to begin', 'microthemer'); ?>
                    </div>

                </div>


                <div id="responsive-bar">
					<?php
                    echo wp_kses( $this->iconFont('devices', array(
                        'class' => 'edit-mq triggers-more-options show-dialog',
	                        'title' => __('Edit media queries', 'microthemer'),
                        'rel' => 'edit-media-queries'
                    ))
                    . $this->global_media_query_tabs(), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
                    ?>
                </div>

                <div id="status-and-settings">

                    <div id="mt-notifications">

                        <div id="status-board" class="dont-show-full-logs">

                            <div class="short-full-status-wrap tvr-popdown-wrap"">

                                <div id="status-short" class="mt-fixed-opacity mt-fixed-color"></div>

                                <div id="full-logs" class="tvr-popdown scrollable-area">

                                    <div class="heading ensure-selectable">
                                        <?php esc_html_e('Notifications', 'microthemer'); ?>
                                    </div>

                                    <div id="tvr-dismiss">

		                                <?php
		                                echo wp_kses( $this->iconFont('times-circle-regular', array(
			                                'class' => 'dismiss-status'
		                                )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
		                                ?>

                                        <span class="dismiss-status">
                                            <?php esc_html_e('dismiss', 'microthemer'); ?>
                                        </span>

                                    </div>

		                            <?php
		                            echo wp_kses( $this->display_log(), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
		                            ?>

                                </div>

                            </div>
                        </div>

                        <?php
                            echo wp_kses( $this->icon('instant-undo', array(
                                    'id' => 'mt-instant-undo',
                                    'class' => 'ss-icon disabled',
	                                'title' => __('Undo', 'microthemer'),
                                    'data-action' => 'undo',
                                    'data-mtc' => 'TvrUi.iterateRevisionStack'
                                )) .
                                $this->icon('instant-redo', array(
	                                'id' => 'mt-instant-redo',
	                                'class' => 'ss-icon disabled',
	                                'title' => __('Redo', 'microthemer'),
	                                'data-action' => 'redo',
	                                'data-mtc' => 'TvrUi.iterateRevisionStack'
	                            )) .
                                $this->icon('save', array(
                                    'class' => 'ss-icon mt-save-action',
	                                    'title' => __('Save settings', 'microthemer')
                                )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
                        ?>

                        <div id="mt-publish-action" class="mt-publish-action">
                            <?php
                            esc_html_e('Publish', 'microthemer');
                            ?>
                        </div>


                    </div>

                    <?php

                    // AI assistant (AI-enabled builds only)
                    if ($this->aiPanel && empty($this->preferences['disable_ai'])){
	                    echo wp_kses( $this->ui_toggle(
		                    'ai_assistant',
		                    esc_attr__('AI assistant', 'microthemer'),
		                    esc_attr__('AI assistant', 'microthemer'),
		                    0,
		                    'ai-expand-toggle ' . $this->iconFont('chatbot-icon', array('onlyClass' => 1)),
		                    'mt-ai-assistant',
		                    array(
			                    'dataAtts' => array(
				                    'forpopup' => 'aiAssistant'
			                    ),
		                    )
	                    ), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
                    }


                    // settings
                    echo wp_kses( $this->iconFont('cog', array(
                        'id' => 'program-settings-icon',
                        'class' => 'program-settings-icon',
                        'data-forpopup' => 'settingsMenu',
                    )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                    // toolbar icons added by extension components
                    $this->extAction('toolbar_icons');

                    // interface collapse
                    echo wp_kses( $this->ui_toggle(
	                    'hide_interface',
	                    esc_attr__('Collapse interface', 'microthemer')."\n",
	                    esc_attr__('Expand interface', 'microthemer')."\n",
	                    $this->preferences['hide_interface'],
	                    'toggle-mt-interface '.$this->iconFont('double-chevron-up', array('onlyClass' => 1)),
	                    'toggle-mt-interface',
	                    array(
		                    'dataAtts' => array(
			                    'fhtml' => 1
		                    ),
	                    )
                    ), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

				?>

                </div>

                <?php
                //echo $this->show_me;
				?>

			</div>

            <!-- add mts-sm class by default so action icons don't take up space briefly while the page loads -->
            <div id="mt-folders" class="mt-folders mts-sm" data-popupName="folders">

                <div class="folders-panel-header">

                    <?php
                    echo wp_kses( $this->ui_toggle(
	                    'expand_all_folders',
	                    esc_attr__('Expand all folders', 'microthemer'),
	                    esc_attr__('Collapse all folders', 'microthemer'),
	                    0, //!empty($this->preferences['expand_all_folders']), // this will be done via JS
	                    'toggle-all-folders ' .$this->iconFont('chevron-right', array('onlyClass' => 1)),
	                    'toggle-all-folders',
	                    array(
		                    'dataAtts' => array(
			                    'no-save' => 1
		                    )
	                    )
                    ), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() )
                    ?>

                    <div class="tvr-input-wrap search-folders-wrap">
                        <input type="text" id="search-folders-input" class="search-folders-input combobox has-arrows" name="search_folders" rel="search_folders" />
                        <span class="combo-arrow"></span>
                        <span class="mt-clear-field"></span>
                        <span class="search-folders-placeholder">
                            <?php echo esc_html__('Search folders', 'microthemer'); ?>
                        </span>
                    </div>


                    <?php
                    echo wp_kses( $this->iconFont('add', array(
                            'class' => 'add-folder-toggle',
                            'data-forpopup' => 'contextMenu',
	                            'title' => __('Add folder', 'microthemer')
                    )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                    ?>
                </div>


                <div class="scrollable-area menu-scrollable">
                    <ul id='tvr-menu'>
						<?php
						foreach ( $this->options as $section_name => $array) {
							// if non_section continue
							if ($section_name == 'non_section') {
								continue;
							}
							// section menu item (trigger function for menu selectors too)
							echo wp_kses( $this->menu_section_html($section_name, $array), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
							++$this->total_sections;
						}
						?>
                    </ul>
                </div>


                <!-- keep track of total sections & selectors (hidden) -->
                <div id="ui-totals-count">

                    <span id="section-count-state" class='section-count-state' rel='<?php echo esc_attr($this->total_selectors); ?>'></span>

                    <span id="total-sec-count"><?php echo esc_html($this->total_sections); ?></span>
                    <span class="total-folders"><?php esc_html_e('Folders', 'microthemer'); ?>&nbsp;&nbsp;</span>


                    <span id="total-sel-count"><?php echo esc_html($this->total_selectors); ?></span>
                    <span><?php esc_html_e('Selectors', 'microthemer'); ?></span>

                </div>

            </div>


            <div id="css-group-icons">
				<?php echo wp_kses( $this->css_group_icons(), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>
            </div>

            <ul id='tvr-options'>
				<?php
				foreach ( $this->options as $section_name => $array) {
					// if non_section continue
					if ($section_name == 'non_section') {
						continue;
					}
					// section menu item (trigger function for menu selectors too)
					echo wp_kses( $this->section_html($section_name, $array), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
				}

				include $this->thisplugindir . 'includes/grid-control.inc.php';
				echo wp_kses( $grid_control, \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
				?>

            </ul>


            <?php
            if ($this->canEditCode()){
                include $this->thisplugindir . 'includes/code-editors/inline-editor.php';
            }
            ?>

            <?php
            // draggable panel resizer divs for left columns
            echo wp_kses( $this->panel_resizers(array(
	            'context' => 'main_columns',
	            'dimension' => 'width',
	            'total' => 5,
	            'side_division' => 3,
	            'side_1' => 'left',
	            'side_2' => 'right',
            )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

            // resizers for editor and inspection panels
            echo wp_kses( $this->panel_resizers(array(
	            'context' => 'element_heights',
	            'dimension' => 'height',
	            'total' => 2,
	            'side_division' => 1,
	            'side_1' => 'editor_height',
	            'side_2' => 'inspection_height',
            )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
            ?>



            <?php
            $page_context = $this->microthemeruipage;
            include $this->thisplugindir . 'includes/tvr-microthemer-preview-wrap.php';
            ?>



            <div id="wizard-panes" class="wizard-panes"<?php echo wp_kses( $this->layout_element_height('inspection_height'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>>

				<?php
				// save the configuration of the css tab
				$adv_wizard_focus = !empty($this->preferences['adv_wizard_tab'])
					? $this->preferences['adv_wizard_tab']
					: 'css-computed';
				?>

                <div class="adv-area-html-inspector adv-area">

                    <div id="html-preview" class="wizard-inner">

                        <div class="css-code-wrap">
                            <textarea name="inspector_html" class="dont-serialize"></textarea>
                            <pre id="wizard_inspector_html" class="wizard_inspector_html"
                                 data-mode="customhtml"></pre>
							<?php

							echo wp_kses( $this->iconFont('sync-alt', array(
								'id' => 'refresh-html-pane',
								'class' => 'refresh-icon refresh-html-pane',
								'title' => __("Refresh HTML pane", 'microthemer'),
							)), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
							?>
                        </div>

						<?php

						/*
						?>
						echo $this->ui_toggle(
							'ace_full_page_html',
							esc_attr__('Show full page HTML', 'microthemer'),
							esc_attr__('Show reduced HTML', 'microthemer'),
							$this->preferences['ace_full_page_html'],
							'full-page-html'
						);
						*/
						?>

                    </div>



                </div>


                <div class="adv-area-css-inspector adv-area scrollable-area <?php
				if ($adv_wizard_focus == 'css-inspector') {
					echo 'show';
				}
				?>">

                    <div id="actual-styles" class="actual-styles wizard-inner"></div>

                </div>


                <div class="adv-area-css-computed adv-area scrollable-area <?php
				if ($adv_wizard_focus == 'css-computed') {
					echo 'show';
				}
				?>">

                    <div id="key-computed"><?php // echo $this->key_computed_info(); ?></div>

                    <div id="html-computed-css" class="accordion-wrapper">

						<?php

						foreach ($this->property_option_groups as $property_group => $pg_label) {
							?>
                            <div id="comp-<?php echo esc_attr($property_group); ?>" class="accordion-menu property-menu">

                                <div class="css-group-heading accordion-heading mt-expandable-heading">
									<?php echo esc_html($pg_label); ?>
                                </div>

                                <ul class="mt-expandable-panel mt-computed-panel"></ul>

                            </div>
							<?php
						}
						?>
                    </div>

                </div>


                <div id="refine-targeting-pane" class="adv-area adv-area-refine-targeting">

					<?php echo  wp_kses( $this->targeting_suggestions('panel'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>

                </div>

                <div id="adv-tabs" class="query-tabs menu-style-tabs">
					<?php

					$tabHeadings = array(
						'html-inspector' => esc_html__('HTML', 'microthemer'),
						'css-computed' => esc_html__('Computed', 'microthemer'),
						'css-inspector' => esc_html__('Styles', 'microthemer'),
						'refine-targeting' => esc_html__('Targeting', 'microthemer'),
					);
					foreach ($tabHeadings as $key => $value) {
						if ($key == $adv_wizard_focus){
							$active_c = 'active';
						} else {
							$active_c = '';
						}
						echo '<span class="adv-tab mt-tab adv-tab-'.esc_attr($key).' show '.esc_attr($active_c).'" rel="'.esc_attr($key).'">'.esc_html($value).'</span>';
					}
					// this is redundant (preferences store focus) but kept for consistency with other tab remembering
					?>
                    <!--<input class="adv-wizard-focus" type="hidden"
							   name="tvr_mcth[non_section][adv_wizard_focus]"
							   value="<?php /*echo $adv_wizard_focus; */?>" />-->
                </div>

				<?php
				echo wp_kses( $this->panel_resizers(array(
					'context' => 'inspection_columns',
					'dimension' => 'width',
					'total' => 2,
					'side_division' => 1,
					'side_1' => 'left_inspect',
					'side_2' => 'right_inspect',
				)), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
				?>


            </div>

            <div id="advanced-wizard">

                <div id="footer-shortcuts">

                    <div id="folder-organisation">

			            <?php
			            $autoFoldersOn = !empty($this->preferences['auto_folders']) ? ' on' : '';
			            $autoFoldersChecked = $autoFoldersOn ? 'checked="checked"' : '';
			            $autoPageOn = !empty($this->preferences['auto_folders_page']) ? ' on' : '';
			            $autoPageChecked = $autoPageOn ? 'checked="checked"' : '';
			            ?>

                        <input type="checkbox" <?php echo wp_kses( $autoFoldersChecked, \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?> name="auto_folders" value="1" />

			            <?php
			            $posTitle = __('If the current folder doesn\'t apply to the page, auto-assign selectors to folders', 'microthemer');
			            $negTitle = __('Disable automatic folder assignment', 'microthemer');
			            echo wp_kses( $this->iconFont('tick-box-unchecked', array(
				            'id' => 'toggle-auto-folders',
				            'class' => 'uit-par fake-checkbox toggle-auto_folders'.$autoFoldersOn,
				            'title' => $autoFoldersOn ? $negTitle : $posTitle,
				            'data-pos' => $posTitle,
				            'data-neg' => $negTitle,
				            'data-dyn-tt-root' => 'toggle-auto-folders',
				            'data-toggle-feature' => 1,
				            'data-aspect' => "auto_folders",
			            )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
			            ?>

                        <span><?php echo esc_html__('Auto folder', 'microthemer'); ?></span>

                        <div id="auto-folders-mode" class="mt-binary-buttons context-binary uit-par <?php echo esc_attr($autoPageOn); ?>" data-run="mod.MTfolder.autoFolderOptions" data-always-run="1" data-aspect="auto_folders_page" data-toggle-feature="1">

                                <span class="binary-button-option folder-option global-folder-option" title="<?php echo esc_attr__('Assign selectors to global folders', 'microthemer'); ?>" rel="0" data-forpopup="contextMenu">
                                    <?php echo esc_html__('Global', 'microthemer'); ?>
                                </span>

                            <input type="checkbox" <?php echo wp_kses( $autoPageChecked, \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?> name="auto_folders[page]" value="1" />

                            <span class="binary-button-option folder-option page-folder-option" title="<?php echo esc_attr__('Assign selectors to page-specific folders', 'microthemer'); ?>"  rel="1" data-forpopup="contextMenu">
                                    <?php echo esc_html__('Page', 'microthemer'); ?>
                                </span>

                        </div>

                    </div>

                </div>

                <div id="nav-bread">

	                <?php
	                echo wp_kses( $this->ui_toggle(
		                'wizard_expanded',
		                esc_attr__('Advanced inspection', 'microthemer'),
		                esc_attr__('Advanced inspection', 'microthemer'),
		                $this->preferences['wizard_expanded'],
		                'wizard-expand-toggle '.$this->iconFont('cog', array('onlyClass' => 1)),
		                'wizard-expand-toggle',
		                array(
			                'text' => esc_attr__('Inspect', 'microthemer'),
                            'dataAtts' => array(
	                            'layout-preset' => 'view'
                            )
		                )
	                ), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
	                ?>

                    <div id="refine-target-controls">

		                <?php
		                echo wp_kses( $this->iconFont('caret-up', array(
			                'class' => 'tvr-parent refine-button disabled',
			                'title' => __("Parent element", 'microthemer'),
		                )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
		                echo wp_kses( $this->iconFont('caret-down', array(
			                'class' => 'tvr-child refine-button disabled',
			                'title' => __("Child element", 'microthemer'),
		                )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
		                echo wp_kses( $this->iconFont('caret-left', array(
			                'class' => 'tvr-prev-sibling refine-button disabled',
			                'title' => __("Previous sibling", 'microthemer'),
		                )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
		                echo wp_kses( $this->iconFont('caret-right', array(
			                'class' => 'tvr-next-sibling refine-button disabled',
			                'title' => __("Next sibling", 'microthemer'),
		                )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
		                ?>

                    </div>

                    <div id="dom-bread" class="drag-port">
                        <div class="drag-containment">
                            <div id="full-breadcrumbs" class="drag-content mt-breadcrumb-nav"></div>
                        </div>
                    </div>

                    <div id="on-canvas-behaviour">

                        <div class="mt-oncanvas-dropdown-wrap">
                            <select id="mt-oncanvas-dropdown" class="mt-select">
                                <optgroup label="<?php echo esc_attr__('Visual controls', 'microthemer'); ?>">
                                <?php
                                $canvas_controls = array(
	                                'size_and_spacing' => esc_html__('Size and Spacing', 'microthemer'),
	                                'border' => esc_html__('Border', 'microthemer'),
	                                'position' => esc_html__('Position', 'microthemer'),
	                                'transform' => esc_html__('Transform', 'microthemer'),
	                                'grig' => esc_html__('Grid', 'microthemer'),
                                );
                                foreach ($canvas_controls as $key => $label){
                                    $selected = ($key === 'size_and_spacing') ? 'selected="selected"' : '';
                                    echo wp_kses( '<option value="'.esc_attr($key).'" '.$selected.'>'.esc_html($label).'</option>', \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
                                }
                                ?>
                                </optgroup>
                            </select>
                        </div>



                        <div class="targeting-mode-toggle">

                            <span><?php echo esc_html__('Targeting mode', 'microthemer'); ?></span>

                            <?php
	                        echo wp_kses( $this->toggle('hover_inspect', array(
		                        // have off by default, so we can see when targeting fails to apply (actually no) issue with detached preview when site is already loaded
	                            'toggle' => $this->preferences['hover_inspect'], // !empty($this->preferences['hover_inspect']),
	                            'toggle_id' => 'hover-inspect-toggle',
		                        'data-pos' => esc_attr__('Enable targeting', 'microthemer'),
		                        'data-neg' => esc_attr__('Disable targeting', 'microthemer'),
                            )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
	                        //echo $this->hover_inspect_button('hover-inspect-toggle');
	                        // onCanvas dropdown menu (Size & Spacing, border, grid, transform, position)
	                        // targeting mode switch
	                        ?>

                        </div>

                    </div>

                </div>

                <div id="footer-wordpress">

                    <span class="exit-label"><?php echo esc_html__('Exit', 'microthemer'); ?></span>

                    <div class="mt-exit-wrap">
			            <?php
			            echo wp_kses( $this->iconFont('wordpress', array(
				            'class' => 'mt-exit-options',
				            'tag' => 'a',
				            // Validate the URL scheme before the icon builder applies attribute escaping.
				            'href' => esc_url($this->wp_blog_admin_url)
				            //'title' => esc_html__('Exit to WordPress', 'microthemer')
			            )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
			            echo wp_kses( $this->menu_panel_sub('exit', $this->menu['exit'], 'bottom-row')['areas_html'], \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
			            ?>

                    </div>

                </div>

            </div>

            <?php
            // AI assistant panel — AI-enabled builds only (src/AIPanel.php is excluded from
            // the wp.org package, which also removes every remote script import).
            if ($this->aiPanel){
                $this->aiPanel->render();
            }
            ?>


            <div id="program-settings-menu" class="scrollable-area" data-popupName="settingsMenu">
	            <?php
	            echo wp_kses( $this->ui_toggle(
		            'expand_all_settings',
		            esc_attr__('Expand all settings', 'microthemer'),
		            esc_attr__('Collapse all settings', 'microthemer'),
		            0,
		            'mt-panel-column-heading toggle-all-settings ',
		            'toggle-all-settings',
		            array(
			            'tag' => 'div',
			            'text' => esc_html__('Settings', 'microthemer'),
			            'inner_icon' => $this->iconFont('chevron-right', array(
				            'class' => 'toggle-all-settings'
			            )),
		            )
	            ), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
	            ?>
				<?php
				//echo $this->system_menu();
				echo wp_kses( $this->settings_menu(), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
				?>
            </div>


            <div id="mt-context-menu" data-popupName="contextMenu">

	            <?php

                echo wp_kses( $this->context_menu_heading('', array('close' => 1)), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

	            // selector variation options
	            /*echo $this->context_menu_content(array(
		            'base_key' => 'item-extra-options',
		            'title' => esc_html__('Current item options', 'microthemer'),
		            'sections' => array(
                        // dynamically added with JS
                        $this->item_manage_icons('selector', 'selector_css', array(
                            'context' => 'current-item',
                            'sub_context' => 'extra'
                        )),
                        $this->context_menu_heading(
							esc_html__("State selector", 'microthemer')
						),
                        '<div id="mt-selector-state-options"></div>'
		            )
	            ));*/



	            // selector modifier options (must come before targeting options for fav filters)
	            echo wp_kses( $this->context_menu_content(array(
		            'base_key' => 'css-filters',
		            'title' => esc_attr__('Selector modifiers', 'microthemer'),
		            'sections' => array(
			            $css_filters
		            )
	            )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

               if ($this->supportContent()){
	               $this->contentMethod('groupContextMenu');
               }

	            // selector modifier options (must come before targeting options for fav filters)
	            echo wp_kses( $this->context_menu_content(array(
		            'base_key' => 'suggestions',
		            'title' => esc_attr__('Targeting options', 'microthemer'),
		            'sections' => array(
			            $this->targeting_suggestions('menu')
		            )
	            )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                // Add folder
                echo wp_kses( $this->add_edit_section_form('add'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                // Folder options (rename, add item, action icons)
                echo wp_kses( $this->add_edit_section_form('edit'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                // options for switching to between global and page-specific styles
	            echo wp_kses( $this->context_menu_content(array(
		            'base_key' => 'switch-folder-mode',
		            'title' => esc_attr__('Assign selectors to global folders - set initial folder', 'microthemer'),
		            'sections' => array(
			            $this->switchAutoFolder()
		            )
	            )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                // selector (item) options
                echo wp_kses( $this->context_menu_content(array(
	                'base_key' => 'selector-options',
	                'title' => esc_attr__('Edit selector', 'microthemer'),
	                'sections' => array(
		                $this->add_edit_selector_form('edit')
	                )
                )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                // Code editor options
                echo wp_kses( $this->context_menu_content(array(
	                'base_key' => 'editor-options',
	                'title' => esc_attr__('Code editor options', 'microthemer'),
	                'sections' => array(
		                $this->context_menu_actions('editor', array(

		                    'wrap' => 1,
			                'actions' => array(
				                /*'fontsize' => array(
					                'custom' =>
						                '<div class="code-font-size"><span>'
						                .esc_html__('Set code font-size', 'microthemer')
						                .'</span>'
						                .$this->toggle('code_manual_resize', array(
							                'toggle' => $this->preferences['code_manual_resize'],
							                'toggle_id' => 'code_manual_resize',
							                'data-pos' => esc_attr__('Enable drag resize', 'microthemer'),
							                'data-neg' => esc_attr__('Auto-set editor height', 'microthemer'),
						                )).'</div>'

				                ),*/
			                    'resize' => array(
			                       'custom' =>
                                       '<div class="code-manual-resize-wrap"><span>'
                                       .esc_html__('Editor height resizable', 'microthemer')
                                       .'</span>'
                                       .$this->toggle('code_manual_resize', array(
	                                       'toggle' => $this->preferences['code_manual_resize'],
	                                       'toggle_id' => 'code_manual_resize',
	                                       'data-pos' => esc_attr__('Enable drag resize', 'microthemer'),
	                                       'data-neg' => esc_attr__('Auto-set editor height', 'microthemer'),
                                       )).'</div>'

			                    ),

				                'code' => array(
					                'class' => 'beautify-editor-code',
					                'wrap' => array(),
					                'adjacentText' =>  array(
								'text' => __('Beautify code', 'microthemer'),
						                'class' => 'mti-text beautify-editor-code'
					                )
				                )
                            )
                        ))
	                )
                )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );


	            // Responsive tab options
	            echo wp_kses( $this->context_menu_content(array(
		            'base_key' => 'responsive-tab-options',
		            'title' => esc_attr__('Responsive tab', 'microthemer'),
		            'sections' => array(

			            $this->context_menu_form('edit-responsive-tab', array(
				            'wrap' => 1,
				            //'wrapClass' => '',
				            'fields' => array(
					            'label' => array(
						            'label' => esc_html__("Label", 'microthemer'),
						            'type' => 'input',
					            ),
					            'query' => array(
						            'label' => esc_html__("Media Query", 'microthemer'),
						            'type' => 'input',
					            ),
					            'filler' => array(
						            'custom' => '<span></span>' // filler
					            ),
					            'icon-line' => array(
						            'custom' => '
                                    <div class="responsive-tab-actions mt-icon-line" data-context="popup-form">'
                                        . $this->icon_control(false,'disabled', false, 'tab')
                                        . $this->clear_icon('tab'). '
                                    </div>',
					            ),
				            ),
				            'button' => array(
					            'text' => esc_html__("Update", 'microthemer'),
				            )
			            ))
		            )
	            )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );


                // AI settings menu — AI-enabled builds only
                if ($this->aiPanel){
                // Advanced AI options
                $aiTest = TVR_DEV_MODE
                    ? '<div id="test-ai-output-wrap">
                            <div>Test AI Response:</div>
                            <textarea id="test-ai-output"></textarea>
                            <span id="test-ai-output-button" class="tvr-button" data-mtc="mod.MTai.testAIResponse">Submit</span>
                        </div>'
                    : '';
	            echo wp_kses( $this->context_menu_content(array(
		            'base_key' => 'ai-advanced',
		            'title' => esc_attr__('AI settings', 'microthemer'),
		            'sections' => array(

			            $this->context_menu_form('edit-ai-settings', array(
				            'wrap' => 1,
				            //'wrapClass' => '',
				            'fields' => array(
					            'ai_admin_access' => array(
						            'custom' =>
							            '<div class="context-toggle-wrap"><span>'
							            .esc_html__('Allow AI assistant on admin pages', 'microthemer')
							            .'</span>'
							            .$this->toggle('ai_admin_access', array(
								            'toggle' => !empty($this->preferences['ai_admin_access']),
								            'toggle_id' => 'ai_admin_access',
							            )).'</div>' .
							            (function() {
								            $whitelisted = !empty($this->preferences['whitelisted_domains']) ? $this->preferences['whitelisted_domains'] : array();
								            if (empty($whitelisted) || $whitelisted[0] === '' && count($whitelisted) === 1)  return '';
								            $items = '';
								            foreach ($whitelisted as $domain) {
												if ($domain === '') continue;
									            $items .= $this->whitelist_ui_item($domain, true);
								            }
								            return '<div class="mt-ai-settings-whitelist-wrap">
                                                <div class="mt-ai-whitelist-heading">' . esc_html__('Whitelisted domains', 'microthemer') . '</div>
                                                <div class="mt-ai-whitelist-list">' . $items . '</div>
                                            </div>';
							            })()
							            . $aiTest

					            ),
					            /*'api_key' => array(
						            'label' => esc_html__("API Key", 'microthemer'),
						            'type' => 'input',
					            ),
					            'filler' => array(
						            'custom' => '<span></span>'
					            ),*/

				            ),
				            /*'button' => array(
					            'text' => esc_html__("Save", 'microthemer'),
				            )*/
			            ))
		            )
	            )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
                }

                ?>


            </div>

            <div id="style-components"></div>
            <div id="combo-markup-do-not-clear"></div>

            <div id="mt-slider-set">

                <span class="mtss-buttons">
                    <span class="mtss-button mtss-decr-button"></span>
                    <span class="mtss-button mtss-incr-button"></span>
                </span>

                <div class="mtss-slider-port drag-port">

                    <div class="mtss-slider-containment drag-containment">
                        <div id="mtss-dragbar" class="mtss-drag-content drag-content">
                            <div class="mousedown-fix"></div>

		                    <?php
		                    /*for ($x = 0; $x < 360; $x+= 10) {
			                    echo '<span class="mtss-block">'.$x.'</span>';
		                    }*/
		                    ?>
                        </div>
                    </div>

                    <div class="mtss-marker">
                        <div class="mtss-marker-top"></div>
                        <div class="mtss-marker-bottom"></div>
                    </div>

                </div>

                <span class="mtss-unit-menu">

                    <span class="mtss-current-unit" data-forpopup="units"></span>

                    <span class="mtss-numeric">
                        <span class="mtss-numeric-value"></span>
                        <span class="mtss-px-equiv">
                            = <span class="mtss-px-value">20</span><span class="mtss-px">px</span>
                        </span>
                    </span>

                </span>

            </div>

            <div id="mtss-units" class="mtss-units">
				<?php
				$html = '';
				//$units = array_merge(array(''))
				foreach ($this->css_units as $unit_cat => $cat_units){
					$first_unit_key = array_keys($cat_units)[0];
					$html.= '
	                        <ul class="unit-cat unit-cat-'.esc_attr($cat_units[$first_unit_key]['type']).'">
	                            <li class="unit-cat-heading">'.esc_html($unit_cat).'</li>';
					foreach ($cat_units as $unit => $unit_data){
						$html.= '<li class="mt-unit-item" data-unit="'.esc_attr($unit).'"
	                                    title="'.esc_attr($unit_data['desc']).'">'.esc_html($unit).'</li>';
					}
					$html.= '
                            </ul>';
				}
				echo wp_kses( $html, \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
				?>
            </div>



		</div>



		</form>


		<!-- </div>end tvr-ui -->
	<?php

	// output dynamic JS here as it changes on page load: inline JS variables from extension
	// components, then the dynamic data (js-dynamic.php sets $data)
	ob_start();
	$this->extAction('ui_script_vars');
	$extension_js = ob_get_clean();

	include $this->thisplugindir . '/includes/js-dynamic.php';

	wp_print_inline_script_tag($extension_js . $data);


	if (!$this->optimisation_test){
		?>
		<div id="dialogs">


            <!-- Initial Setup -->
            <form name='mt_initial_setup' id="mt-setup-form" method="post"
	                  enctype="multipart/form-data" action="<?php echo esc_url('admin.php?page=' . $this->microthemeruipage);?>">
	            <?php wp_nonce_field('mt_initial_setup_form'); ?>

			<?php
			 echo wp_kses( $this->start_dialog(
                    'mt-initial-setup',
				    $this->appNameFull . ' ' . __('Setup', 'microthemer'),
                    '',
                    array(),
                    array(
                        'text' => __('Skip setup', 'microthemer'),
                        'class' => 'mt-skip-setup',
                        'link_class' => 'close-dialog'
                    )
            ), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>

            <div id="setup-options">

                <?php
                if ($this->setupError){
                    echo  wp_kses( '<div class="setup-notification"> '. $this->display_log() . '</div>', \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
                    echo wp_kses( $this->show_me, \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                }
                ?>

                <div class="setup-preferences">

                    <?php
                    // Initial preferences / import preferences
                    $sectionHTML = '';
                    $optionalData = array(
	                    'my_props' => 'CSS property menu values',
	                    'm_queries' => 'Media queries',
	                    'enq_js' => 'Global JS dependencies', // also pull in active_scripts_deps
                    );

                    foreach ($optionalData as $key => $label) {
	                    $sectionHTML.= '<input type="checkbox" name="tvr_optional_preferences['.esc_attr($key).']" value="1" checked="checked" />' .
	                         $this->iconFont( 'tick-box-unchecked', array(
		                         'class' => 'fake-checkbox on',
	                         )) .
		                '<span class="optional-pref-label">'.esc_html($label).'</span>';
                    }

                    echo wp_kses( $this->dialogSectionTabs(array(
	                    'dev-preferences' => array(
		                    'title' => 'Set Initial Preferences',
		                    'content' => '<p class="setup-section-intro">Optionally adjust some default preferences, which are set up for non-coders:</p>
                            <ul class="mt-form-settings main-preferences-grid initial-preferences-fields">'.$this->preferences_grid_items($this->initial_preference_options).'</ul>'
	                    ),
	                    'import-preferences' => array(
		                    'title' => 'Import Preferences',
		                    'content' => '<p class="setup-section-intro">Get file from another site via: <i>Settings > General > Preferences > Download preferences</i></p>
                            <div class="import-preferences-checkboxes">'.$sectionHTML.'</div>
	                            <input type="hidden" name="MAX_FILE_SIZE" value="'.esc_attr($this->maxUploadPrefSize).'" />
                            <input id="preferences-file" type="file" name="preferences_file" />'
	                    )
                    )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                    ?>

                </div>

                <div class="setup-videos">
                    
                    <?php
                    echo wp_kses( $this->dialogSectionTabs(array(
	                    'microthemer-videos' => array(
                            'skip' => !$this->supportGUICSS(),
		                    'title' => 'Microthemer Videos',
		                    'content' => '<div class="mt-video-thumbs">'.$this->generateVideoButtons(array(
				                    'introducing-microthemer-7' => "Introduction",
				                    'basic-workflow' => "Workflow",
				                    'dark-mode-and-layout-options' => "Theme & layout",
				                    'selecting-elements' => "Select elements",
				                    'styling-options' => "Style elements",
				                    'html-and-css-inspection' => "HTML & CSS",
				                    'folders' => "Folders",
				                    'automatic-page-speed-optimisation' => "Page speed",
				                    'uninstall-but-keep-changes' => "Uninstall",
				                    'troubleshooting' => "Troubleshooting",
			                    )).'</div>'
	                    ),
	                    'amender-videos' => array(
		                    'skip' => !$this->supportContent(),
		                    'title' => 'Amender Videos',
		                    'content' => '
                            <div class="mt-video-thumbs">'.$this->generateVideoButtons(array(
                                'introducing-amender' => "Introduction",
                            )).'</div>'
	                    )
                    )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                    ?>

                </div>

                <?php
                // setup dialog sections added by extension components
                $this->extAction('setup_sections');
                ?>


                <div class="setup-support">

                    <?php
                    echo wp_kses( $this->dialogSectionTabs(array(
	                    'microthemer-docs' => array(
		                    'skip' => !$this->supportGUICSS(),
		                    'title' => 'Microthemer Docs',
		                    'content' => '
                            <p class="setup-section-intro">Dive into the full documentation, get help in the forum, or join our Facebook community.</p>
                            <div class="support-buttons">
                                <a target="_blank" href="https://themeover.com/introducing-microthemer-7/">Video docs</a>
                                <a target="_blank" href="https://themeover.com/font-family/">CSS reference</a>
                                <a target="_blank" href="https://themeover.com/forum/">Support forum</a>
                                <a target="_blank" href="https://www.facebook.com/groups/microthemer">Facebook group</a>
                            </div>'
	                    ),
	                    'amender-docs' => array(
		                    'skip' => !$this->supportContent(),
		                    'title' => 'Amender Docs',
		                    'content' => '<p class="setup-section-intro">Take the Amender tour, and reach out if you need help.</p>
                            <div class="support-buttons">
                                <a target="_blank" href="https://themeover.com/introducing-amender/">Video docs</a>
                                
                                <a target="_blank" href="https://themeover.com/forum/">Support forum</a>
                                <a target="_blank" href="https://www.facebook.com/groups/microthemer">Facebook group</a>
                            </div>'
	                    )
                    )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
                    ?>

                </div>

                <div class="setup-debug">

                    <div class="heading error-reporting-heading">
                        <span>Error reporting</span>
                        <span id="toggle-preview-report-data" class="link toggle-preview-report-data" title="Preview the data Microthemer will send">Preview</span>
                        <span class="tvr-button tvr-gray mt-manual-error-report" title="Send error report">Send report</span>
                    </div>

                    <!--<p class="setup-section-intro">Optionally share errors with us, so we can fix bugs immediately.</p>-->

                    <div class="error-reporting-grid">

                        <?php
                        $freq = $this->reporting['max']['dataSends'] > 1
                            ? $this->reporting['max']['dataSends'] . ' times'
                            : 'once';
                        $reportTypes = array(
                              'file' =>  "Send info about JS errors in plugins / themes (for conflict resolution)",
                              'data' =>  "Include non-sensitive ".$this->appName." settings for error replication and UX fixes (".$freq." a day max)",
                              'contact' =>  "Include your domain & Themeover account number (for debugging follow-up questions)",
                        );


                        foreach ($reportTypes as $key => $label){
	                        $on = !empty($this->preferences['reporting']['permission'][$key]) ? ' on' : '';
	                        $checked = $on ? 'checked="checked"' : '';
                            echo wp_kses( '
	                            <input type="checkbox" name="reporting_permission['.esc_attr($key).']"
                               value="1" '.$checked.' />' .
                            $this->iconFont('tick-box-unchecked', array(
                                'class' => 'fake-checkbox mt-reporting-permission' . $on,
                                'data-permission-key' => $key,
                            )) .
	                            '<span>'.esc_html($label).'</span>', \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
                        }

                        ?>


                    </div>

                    <div class="setup-save">
                        <input class="tvr-button" type="submit" name="mt_initial_setup_submit"
                               value="Save all settings" />
                    </div>



                </div>

                <div class="setup-review-mt">

                    <div class="heading">Leave a Review</div>
                    <p class="setup-section-intro">If you like Microthemer, please consider giving it 5 stars on wordpress.org</p>

                    <div class="rate-themeover">
	                    <?php
	                    if ($this->supportGUICSS()){
		                    echo ' <p><a target="_blank" href="https://wordpress.org/plugins/microthemer/#reviews">Rate Microthemer</a></p>';
	                    } /*if ($this->supportContent()){
		                    echo ' <p><a target="_blank" href="https://wordpress.org/plugins/amender/#reviews">Rate Amender</a></p>';
	                    }*/
	                    ?>
                    </div>

                </div>

                <div id="data-send-preview">
                    <div class="mt-panel-header">
                        <div class="mt-panel-title ui-draggable-handle">Error reporting data <?php echo esc_html($this->appName); ?> will send</div>
                        <span class="mtif mtif-times-circle-regular toggle-preview-report-data"></span>
                    </div>
                    <div class="report-data-dump">
                        <pre></pre>
                    </div>
                </div>

            </div>

			<?php
            echo wp_kses( $this->end_dialog(esc_html_x('Close', 'verb', 'microthemer'), 'span', 'close-dialog'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
            ?>
            </form>


			<?php
			// this is a separate include because it needs to have separate page for changing gzip
			$page_context = $this->microthemeruipage;
			include $this->thisplugindir . 'includes/tvr-microthemer-preferences.php';
			?>

			<!-- Edit Media Queries -->
			<form id="edit-media-queries-form" name='tvr_media_queries_form' method="post" autocomplete="off"
				action="<?php echo esc_url('admin.php?page=' . $this->microthemeruipage);?>" >
				<input type="hidden" name="tvr_media_queries_submit" value="1" />
				<?php echo wp_kses( $this->start_dialog('edit-media-queries', esc_html__('Edit Media Queries (For Designing Responsively)', 'microthemer'), 'small-dialog'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>

				<div class="content-main">

					<ul class="form-field-list">
						<?php

						// yes no options
						$yes_no = array(
							'initial_scale' => array(
								'label' => __('Set device viewport zoom level to "1"', 'microthemer'),
								'explain' => __('Set this to yes if you\'re using media queries to make your site look good on mobile devices. Otherwise mobile phones etc will continue to scale your site down automatically as if you hadn\'t specified any media queries.', 'microthemer')
							)

						);
						// text options
						$text_input = array(
							'all_devices_default_width' => array(
								'label' => __('Default screen width for "All Devices" tab', 'microthemer'),
								'explain' => __('Leave this blank to let the frontend preview fill the full width of your screen when you\'re on the "All Devices" tab. However, if you\'re designing "mobile first" you can set this to "480px" (for example) and then use min-width media queries to apply styles that will only have an effect on larger screens.', 'microthemer')
							),
						);

						// mq set combo
						$media_query_sets = array(
							'load_mq_set' => array(
								'combobox' => 'mq_sets',
								'label' => __('Select a media query set', 'microthemer'),
								'explain' => __('Choose from a list of media query "sets". If you are trying to make a non-responsive site look good on mobile, you may want to use the default "Desktop-first device MQs" set. If you designing mobile first, you may want to try an alternative set.', 'microthemer')
							)
						);

						// overwrite options
						$overwrite = array(
							'overwrite_existing_mqs' => array(
								//'default' => 'yes',
								'label' => __('Overwrite your existing media queries?', 'microthemer'),
								'explain' => __('You can overwrite your current media queries by choosing "Yes". However, if you would like to merge the selected media query set with your existing media queries please choose "No".', 'microthemer')
							)
						);

						$this->output_radio_input_lis($yes_no);

						$this->output_text_combo_lis($text_input);
						?>
						<li><span class="reveal-hidden-form-opts link reveal-mq-sets" rel="mq-set-opts"><?php esc_html_e('Load an alternative media query set', 'microthemer'); ?></span></li>
						<?php

						$this->output_text_combo_lis($media_query_sets, 'hidden mq-set-opts');

						$this->output_radio_input_lis($overwrite, 'hidden mq-set-opts');


						?>
					</ul>

					<div class="heading"><?php esc_html_e('Media Queries', 'microthemer'); ?></div>


					<?php
					 echo wp_kses( $this->dyn_menu(
						$this->preferences['m_queries'], // data
						$this->mq_structure, // structure
						array('controls' => 1) // config
					), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>

					<div class="explain">
						<div class="heading link explain-link"><?php esc_html_e('About this feature', 'microthemer'); ?></div>

						<div class="full-about">

							<p><?php esc_html_e('If you\'re not using responsive tabs to make your site look good on mobile devices you don\'t need to set the viewport zoom level to 1. You will be passing judgement over to the devices (e.g. an iPhone) to display your site by automatically scaling it down. But if you are using media queries you NEED to set this setting to "Yes" in order for things to work as expected on mobile devices (otherwise mobile devices will just show a proportionally reduced version of the full-size site).', 'microthemer'); ?></p>
							<p><?php echo wp_kses (
								sprintf(
									/* translators: %s: HTML attributes for the anchor tag */
									__('You may want to read <a %1$s>this tutorial which gives a bit of background on the viewport meta tag</a>.', 'microthemer'),
									'target="_blank" href="http://www.paulund.co.uk/understanding-the-viewport-meta-tag"'
								),
								array( 'a' => array( 'href' => array(), 'target' => array() ) )
							); ?></p>
							<p><?php esc_html_e('Feel free to rename the media queries and change the media query code. You can also reorder the media queries by dragging and dropping them. This will determine the order in which the media queries are written to the stylesheet and the order that they are displayed in the interface.', 'microthemer'); ?></p>
							<p><?php esc_html_e('TIP: to reset the default media queries simply delete all media query boxes and then save your settings', 'microthemer'); ?></p>
						</div>
					</div>

				</div>

				<?php echo wp_kses( $this->end_dialog(esc_html__('Update Media Queries', 'microthemer'), 'span', 'update-media-queries'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>
			</form>

			<!-- Manage JS libraries -->
            <?php
            $tabs = array(
                esc_html__('Global WordPress JavaScript', 'microthemer'),
            );
            if ($this->supportContent()){
                array_unshift($tabs, esc_html__('JavaScript modules', 'microthemer'));
            }
            ?>
			<form id="mt-enqueue-js" name='mt_enqueue_js' method="post" autocomplete="off"
				  action="<?php echo esc_url('admin.php?page=' . $this->microthemeruipage);?>" >

				<input type="hidden" name="mt_enqueue_js_submit" value="1" />
				<?php
				 echo wp_kses( $this->start_dialog(
					'mt-enqueue-js',
					esc_html__('Manage JavaScript Libraries', 'microthemer'),
					'small-dialog',
			        $tabs
				), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>

				<div class="content-main">

					<?php
					foreach ($tabs as $i => $name){
						$show = $i == 0 ? 'show' : '';
						// design pack import
						?>
                        <div class="dialog-tab-field dialog-tab-field-<?php echo (int)$i; ?> hidden <?php echo esc_attr($show); ?>">
							<?php

                            // JavaScript modules
                            if ($this->supportContent() && $i == 0){

                                echo wp_kses( $this->moduleUI->modules_tab(), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

							}

                            // Global WordPress JavaScript
							else {

								?>
                                <p class="enqueue-intro"><?php echo esc_html__('If you want to write custom JavaScript code that depends on jQuery or any other JS library, you can enqueue it here. The dropdown menu below only includes the most popular script handles.', 'microthemer'); ?>
                                <p>
                                    <a href="https://developer.wordpress.org/reference/functions/wp_register_script/" target="_blank">
                                        <?php echo esc_html__('View more WP script handles online.', 'microthemer'); ?>
                                    </a>
                                </p>

                                </p>

								<?php
								 echo wp_kses( $this->dyn_menu(
									$this->preferences['enq_js'], // data
									$this->enq_js_structure, // structure
									array('controls' => 1) // config
								), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                            }

                          ?>
                        </div>
						<?php
					}
					?>


				</div>

				<?php echo wp_kses( $this->end_dialog(esc_html__('Update JS Libraries', 'microthemer'), 'span', 'update-enqjs'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>

			</form>


			<!-- Display (Potentially) External CSS file -->
			<?php
			 echo wp_kses( $this->start_dialog(
				'inspect-stylesheet',
				esc_html__('Inspect CSS Stylesheet', 'microthemer'),
				'medium-dialog'
			), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>
			<div class="content-main">
				<div class="css-code-wrap">
					<textarea name="inspect_stylesheet" class="dont-serialize"></textarea>
					<pre id="inspect_stylesheet_preview" class="inspect_stylesheet_preview" data-mode="css"></pre>
				</div>
			</div>
			<?php echo wp_kses( $this->end_dialog(esc_html_x('Close', 'verb', 'microthemer'), 'span', 'close-dialog'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>



			<!-- Import dialog -->
			<?php
				$tabs = array(
					esc_html__('MT Design Pack', 'microthemer'),
					esc_html__('CSS Stylesheet', 'microthemer'),
				);
			?>
			<form method="post" id="microthemer_ui_settings_import" autocomplete="off">
				<input type="hidden" name="import_pack_or_css" value="1" />
				<?php echo wp_kses( $this->start_dialog('import-from-pack', esc_html__('Import settings from a design pack or CSS Stylesheet', 'microthemer'), 'medium-dialog', $tabs), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>

				<div class="content-main dialog-tab-fields">

					<?php
					foreach ($tabs as $i => $name){
						$show = $i == 0 ? 'show' : '';
						// design pack import
						?>
						<div class="dialog-tab-field dialog-tab-field-<?php echo (int)$i; ?> hidden <?php echo esc_attr($show); ?>">
						<?php
						if ($i == 0){
							?>

							<p><?php esc_html_e('Select a design pack to import', 'microthemer'); ?></p>
							<p class="combobox-wrap tvr-input-wrap">
								<input type="text" class="combobox has-arrows" id="import_from_pack_name" name="import_from_pack_name" rel="directories"
									   value="" />
								<span class="combo-arrow"></span>
							</p>
							<p class="enter-name-explain"><?php esc_html_e('Choose to overwrite or merge the imported settings with your current settings', 'microthemer'); ?></p>

							<ul id="overwrite-merge" class="checkboxes fake-radio-parent">
								<li><input name="tvr_import_method" type="radio" value="<?php esc_attr_e('Overwrite', 'microthemer'); ?>" id='ui-import-overwrite'
										   class="radio ui-import-method" />

                                    <?php echo wp_kses( $this->iconFont('radio-btn-unchecked', array('class' => 'fake-radio')), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>
									<span class="ef-label"><?php esc_html_e('Overwrite', 'microthemer'); ?></span>
								</li>
								<li><input name="tvr_import_method" type="radio" value="<?php esc_attr_e('Merge', 'microthemer'); ?>" id='ui-import-merge'
										   class="radio ui-import-method" />
									<?php echo wp_kses( $this->iconFont('radio-btn-unchecked', array('class' => 'fake-radio')), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>
									<span class="ef-label"><?php esc_html_e('Merge', 'microthemer'); ?></span>
								</li>
							</ul>
							<?php /*
				<p class="button-wrap"><?php echo $this->dialog_button(__('Import', 'microthemer'), 'span', 'ui-import'); ?></p>*/
							?>
							<div class="explain">
								<div class="heading link explain-link"><?php esc_html_e('About this feature', 'microthemer'); ?></div>
								<div class="full-about">
									<p><?php echo wp_kses(
											sprintf(
												/* translators: %s: HTML attributes for the Export span element */
									__('A design pack contains folders, selectors, hand-coded CSS, and background images that someone else has created while working with Microthemer or Amender. Of course, it may not be someone else, you can create design packs too using the "<span %1$s>Export</span>" feature!', 'microthemer'),
												'class="link show-dialog" rel="export-to-pack"'
											),
											array( 'i' => array(), 'span' => array() )
										); ?> </p>
									<p><?php printf(
											/* translators: %s: dialog window name */
									esc_html__('Note: you can install other people\'s design packs via the "%s" window.', 'microthemer'),
											'<span class="link show-dialog" rel="manage-design-packs">' . esc_html__('Manage Design Packs', 'microthemer') . '</span>'
										); ?></p>
									<p><b><?php esc_html_e('You may want to make use of this feature for the following reasons:', 'microthemer'); ?></b></p>
									<ul>
										<li><?php printf(
												/* translators: %s: website link HTML */
										esc_html__('You\'ve downloaded and installed a design pack that you found on %s for restyling a theme, contact form, or any other WordPress content you can think of. Importing it will load the folders and hand-coded CSS into the interface.', 'microthemer'),
												'<a target="_blank" href="http://themeover.com/">themeover.com</a>'
											); ?></li>
										<li><?php esc_html_e('You previously exported your own work as a design pack and now you would like to reload it back into the interface.', 'microthemer'); ?></li>
									</ul>
								</div>
							</div>
							<br /><br /><br /><br />
							<?php
						}
						// css stylesheet import
						else {
							// textarea for posting
							?>
							<textarea id="stylesheet_import_json" name="stylesheet_import_json"></textarea>
							<textarea id="get_remote_images" name="get_remote_images"></textarea>
							<?php

							// combobox for previously entered stylesheets and suggest theme/MT stylesheets.
							$default_sheet = !empty($this->preferences['viewed_import_stylesheets'][0])
								? $this->preferences['viewed_import_stylesheets'][0]
                                : '';
							?>
							<p>
							</p>

							<div class="stylesheet-to-import">
								<span class="combobox-wrap tvr-input-wrap">
								<input id="stylesheet_to_import" type="text" name="stylesheet_to_import" class="combobox has-arrows"
									   rel="viewed_import_stylesheets" value="<?php echo esc_attr($default_sheet); ?>" title="<?php echo
								esc_attr__('Enter or select a CSS stylesheet URL', 'microthemer'); ?>" />

								<span class="combo-arrow"></span>

								<span class="refresh-icon refresh-stylesheet-list tvr-icon" title="<?php echo
									esc_attr__('Get stylesheets affecting the current page', 'microthemer'); ?>"></span>


							</span>

							<span class="tvr-button view-import-stylesheet" title="<?php echo
							esc_attr__('Load stylesheet contents into the editor below', 'microthemer'); ?>">
									<?php echo esc_html__('Load Stylesheet', 'microthemer'); ?>
								</span>
							</div>


							<div id="imp-css-preview" class="imp-css-preview">

								<p class="imp-editor-extra">

									<span class="how-to-css-import" title="<?php echo esc_attr__('You can paste arbitrary CSS into the editor below. Or load the contents of a stylesheet using the option above. NOTE: use the \'Only import selected text\' option if you just want to import CSS code that you have highlighted with your mouse.', 'microthemer'); ?>">

										<span class="tvr-icon info-icon"></span>
										<span> Help</span>
									</span>

									<span class="only-import-selected-text">
										<?php
										$checked = '';
										$on = '';
										if (!empty($this->preferences['css_imp_only_selected'])){
											$checked = 'checked="checked"';
											$on = 'on';
										}
										?>
										<input type="checkbox" name="tvr_preferences[css_imp_only_selected]"
											<?php echo wp_kses( $checked, \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?> value="1" />
                                        <?php
                                        echo wp_kses( $this->iconFont('tick-box-unchecked', array(
	                                        'class' => 'fake-checkbox toggle-import-selected-text '.$on,
                                        )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
                                        ?>

										<span class="ef-label import-selected-label">
											<?php esc_html_e('Only import selected text', 'microthemer'); ?>
										</span>
									</span>

								</p>
								<div class="css-code-wrap">
									<textarea name="css_to_import" class="dont-serialize"></textarea>
								<pre id="preview-import-css-0" class="preview-import-css preview-import-css-0"
									 data-mode="css"></pre>
								</div>

								<div class="heading"><?php echo esc_html__('Stylesheet Import Options', 'microthemer'); ?></div>
								<ul id="user-import-css-opts" class="form-field-list">
									<?php

									// yes no options
									$yes_no = array(
										'css_imp_mqs' => array(
											'label' => __('Import media queries', 'microthemer'),
											'explain' => __('Media queries will be imported (recommended).', 'microthemer')
										),
										'css_imp_sels' => array(
											'label' => __('Import selectors', 'microthemer'),
											'explain' => __('CSS Selectors will be imported. You can set this to "No" if you just want to import your theme\'s media queries.', 'microthemer')
										),
										'css_imp_styles' => array(
											'label' => __('Import styles', 'microthemer'),
											'explain' => __('CSS properties and values will be added to the imported selectors. The above "Import Selectors" option must be set to "Yes" for this option to work.', 'microthemer')
										),
										'css_imp_friendly' => array(
											'label' => __('Give selectors friendly names', 'microthemer'),
											'explain' => __('Give selectors more human readable names.', 'microthemer')
										),
										'css_imp_adjust_paths' => array(
											'label' => __('Make relative URLs absolute', 'microthemer'),
											'explain' => __('This will ensure @import and url() file paths are valid even though the location of the stylesheet will change. An URL to the original source of the CSS must be provided above the editor for this to work (even if you have not used the "LOAD STYLESHEET" button).', 'microthemer')
										),

										'css_imp_copy_remote' => array(
											'label' => __('Copy images to WP media library', 'microthemer'),
											'explain' => __('Copy any images referenced in the stylesheet to your WordPress media library. Image file paths will be automatically adjusted.',
												'microthemer')

										/* 'label' => __('Copy remote images to WP media library', 'microthemer'),
										'explain' => __('Microthemer will copy any remote images referenced in the stylesheet to your WordPress media library. Image file paths will be automatically adjusted. Local images (on this domain) will not be copied.',
											'microthemer') */
										),
										/*'css_imp_always_cus_code' => array(
											'label' => __('Always add styles to GUI selector code field', 'microthemer'),
											'explain' => __('Always add imported styles to a GUI selector\'s custom code editor - even when a dedicated GUI field exists for the CSS property. This normally only happens when no GUI field exists.', 'microthemer')
										),*/
									);

									if (!empty($this->preferences['allow_scss'])){
										$yes_no['css_imp_always_cus_code'] = array(
											'label' => __('Always add styles to GUI selector code field', 'microthemer'),
											'explain' => __('Always add imported styles to a GUI selector\'s custom code editor - even when a dedicated GUI field exists for the CSS property. This normally only happens when no GUI field exists.', 'microthemer')
										);
                                    }

									// text options
									$text_input = array(
										'css_imp_max' => array(
											'label' => __('Max @import rules to follow', 'microthemer'),
											'explain' => __('Instead of adjusting @import file paths, follow them and combine CSS code with the initial stylesheet. Thus doing a deep import of the CSS into the interface.', 'microthemer')
										),
									);

									$this->output_radio_input_lis($yes_no);

									// $this->output_text_combo_lis($text_input); // add this feature later

									?>
								</ul>

								<p>
									<span class="tvr-button view-import-stats">
										<?php echo esc_html__('Review Before Importing', 'microthemer'); ?>
									</span>
								</p>

								<div id="import-stats" class="hidden">
									<div class="heading">Import Stats</div>
									<p>Preview the data before importing. For long lists of styles or selectors, type in the fields to filter down the results.</p>
									<br />
									<?php
									$stats = array(
										/*'errors' => array(
											'desc' => __('Import errors', 'microthemer'),
											'type' => 'combo'
										),*/
										'media' => array(
											'desc' => __('GUI media queries', 'microthemer'),
											'type' => 'combo'
										),
										'folders' => array(
											'desc' => __('GUI folders', 'microthemer'),
											'type' => 'combo'
										),
										'selectors' => array(
											'desc' => __('GUI selectors', 'microthemer'),
											'type' => 'combo'
										),
										'declarations' => array(
											'desc' => __('GUI field styles', 'microthemer'),
											'type' => 'combo'
										),
										'gui_custom' => array(
											'desc' => __('GUI code editor styles', 'microthemer'),
											'type' => 'combo'
										),
										'remote_images' => array(
											'desc' => __('Images to be copied', 'microthemer'),
											'type' => 'combo'
										),
										'full_custom' => array(
											'desc' => __('CSS code that has no matching GUI field', 'microthemer'),
											'type' => 'ace'
										),
									);

									//
									foreach ($stats as $key => $arr){

										if ($arr['type'] == 'ace'){
											?>
											<div class="ace-stats-wrap">
												<p><?php echo esc_html($arr['desc']) ?></p>
												<div class="css-code-wrap">
													<textarea name="stats_<?php echo esc_attr($key); ?>" class="dont-serialize"></textarea>
													<pre id="stats-<?php echo esc_attr($key); ?>" class="stats-<?php echo esc_attr($key); ?>"
														 data-mode="css"></pre>
												</div>
											</div>
											<?php
										} else {
											?>
											<div id="stats-<?php echo esc_attr($key); ?>" class="stats-wrap">

												<label class="stat-label"><?php echo esc_html($arr['desc']) ?> <span class="stat-count"></span></label>
												<div class="tvr-input-wrap">

													<input type="text" name="stats_<?php echo esc_attr($key); ?>" rel="<?php echo esc_attr($key); ?>"
													 class="combobox has-arrows stats-<?php echo esc_attr($key); ?>"
													title="<?php echo esc_attr($arr['desc']) ?>" />
													<span class="combo-arrow"></span>

												</div>
											</div>

											<?php
										}
									}
									?>
								</div>
							</div>

							<?php

							//echo '<pre>'.print_r($this->preferences, true). '</pre>';

						}
						?>
						</div>
					<?php
					}
					?>


				</div>
				<?php echo wp_kses( $this->end_dialog(esc_html_x('Import', 'verb', 'microthemer'), 'span', 'ui-import'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>
			</form>



			<!-- Export dialog -->
			<form method="post" id="microthemer_ui_settings_export" action="#" autocomplete="off">
			<?php echo wp_kses( $this->start_dialog('export-to-pack', esc_html__('Export your work as a design pack', 'microthemer'), 'small-dialog'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>

			<div class="content-main export-form">
				<input type='hidden' id='only_export_selected' name='only_export_selected' value='1' />
				<input type='hidden' id='export_to_pack' name='export_to_pack' value='0' />
				<input type='hidden' id='new_pack' name='new_pack' value='0' />

				<p class="enter-name-explain"><?php esc_html_e('Enter a new name or export to an existing design pack. Uncheck any folders or custom CSS you don\'t want included in the export.', 'microthemer'); ?></p>
				<p class="combobox-wrap tvr-input-wrap">
					<input type="text" class="combobox has-arrows" id="export_pack_name" name="export_pack_name" rel="directories"
						value="<?php //echo $this->readable_name($this->preferences['theme_in_focus']); ?>" autocomplete="off" />
					<span class="combo-arrow"></span>

				</p>


				<div class="heading"><?php esc_html_e('Folders', 'microthemer'); ?></div>
				<ul id="toggle-checked-folders" class="checkboxes">
					<li><input type="checkbox" name="toggle_checked_folders" />
						<?php
						echo wp_kses( $this->iconFont('tick-box-unchecked', array(
							'class' => 'fake-checkbox toggle-checked-folders',
						)), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
						?>
						<span class="ef-label check-all-label"><?php esc_html_e('Check All', 'microthemer'); ?></span>
					</li>
				</ul>
				<ul id="available-folders" class="checkboxes"></ul>

                <?php
                    if ($this->supportContent()){
	                    echo wp_kses( '
                        <div class="heading amender-snippets-export">
                            <input type="checkbox" name="export_sections[active_snippets]" value="1" checked="checked" />' .
                            $this->iconFont('tick-box-unchecked', array(
                                'class' => 'fake-checkbox all-snippets-toggle on',
                            )) . '
                            <span>' . esc_html__('All Amender Snippets', 'microthemer') . '</span>
                        </div>', \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
                    }
                ?>

				<div class="heading"><?php esc_html_e('Global custom code', 'microthemer'); ?></div>
				<ul id="custom-css" class="checkboxes">
					<?php
					foreach ($this->custom_code_flat as $key => $arr) {
						$name = 'export_sections'; /*($key == 'hand_coded_css' or $key == 'js') ?
							'export_sections' : 'export_sections[ie_css]';*/
						?>
						<li>
							<input type="checkbox" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($key); ?>]" />
							<?php
							echo wp_kses( $this->iconFont('tick-box-unchecked', array(
								'class' => 'fake-checkbox custom-css-'.$arr['tab-key'],
							)), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
							?>
							<span class="code-icon tvr-icon"></span>
							<span class="ef-label"><?php echo esc_html($arr['label']); ?></span>
						</li>
						<?php
					}
					?>
				</ul>
				<?php /*
				<p class="button-wrap"><?php echo $this->dialog_button('Export', 'span', 'export-dialog-button'); ?></p>
 */ ?>

				<div class="explain">
					<div class="heading link explain-link"><?php esc_html_e('About this feature', 'microthemer'); ?></div>

					<div class="full-about">
						<p><?php echo wp_kses(
							sprintf(
								/* translators: 1: HTML attributes for the import span element, 2: export directory path HTML */
								__('Export your current work to a design pack for later use (you can <span %1$s>import</span> it back). The export will be located in %2$s', 'microthemer'),
								'class="link show-dialog" rel="import-from-pack"',
								'<code>' . esc_html($this->display_path($this->micro_root_dir)) . '</code>'
								),
							array( 'span' => array('class' => true, 'rel' => true), 'code' => array() )
						); ?></p>
						<p><b><?php esc_html_e('You may want to make use of this feature for the following reasons:', 'microthemer'); ?></b></p>
						<ul>
							<li><?php printf(
								/* translators: %s: dialog window name */
								esc_html__('To make extra sure that your work is backed up (even though there is an automatic revision restore feature). After exporting your work to a design pack you can also download it as a zip package for extra reassurance. You can do this from the "%s" window.', 'microthemer'),
								'<span class="link show-dialog" rel="manage-design-packs">' . esc_html__('Manage Design Packs', 'microthemer') . '</span>'
							); ?></li>
							<li><?php esc_html_e('To save your current work but then start a fresh (using the "reset" option in the left-hand menu)', 'microthemer'); ?></li>
							<li><?php esc_html_e('To save one aspect of your design for reuse in other projects (e.g. styling for a menu). You can do this by organising the styles you plan to reuse into a folder and then export only that folder to a design pack by unchecking the other folders before clicking the "Export" button.', 'microthemer'); ?></li>
							<li><?php printf(
								/* translators: %s: website link HTML */
								esc_html__('To submit a design pack for sale or free download on %s', 'microthemer'),
								'<a target="_blank" href="http://themeover.com/">themeover.com</a>'
							); ?></li>
						</ul>
					</div>

				</div>

			</div>
			<?php

			echo wp_kses( $this->end_dialog(esc_html_x('Export', 'verb', 'microthemer'),

				'span', 'export-dialog-button', esc_attr__('Export settings', 'microthemer')), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>
			</form>


			<!-- View CSS -->
			<?php
			// begin dialog
			$tabs = array('CSS'); // dummy tab to create the container element, but tabs are added with JS
			echo wp_kses( $this->start_dialog('display-css-code',
                esc_html__('View your CSS code', 'microthemer'),
                'medium-dialog', $tabs), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>

			<div class="content-main dialog-tab-fields">

				<div id="view-css-areas">

                    <div class="dialog-tab-field">

                        <div id="view-file" class="view-file">
                            <?php
							$title = __('View file on server', 'microthemer');
                            ?>
                            <a class="draft-file" href="#" target="_blank" title="<?php echo esc_attr($title); ?>"></a>
                            <a class="published-file" href="#" target="_blank" title="<?php echo esc_attr($title); ?>"></a>
                        </div>

                        <div id="scss-error-notes" class="hidden">
                            <p><b>NOTE: </b> for maximum performance Microthemer selectively compiles Sass code (previous compile shown below). To compile everything use <b>Ctrl + Alt + P</b></p>
                        </div>

                        <div class="css-code-wrap">
                            <textarea class="gen-css-holder dont-serialize"></textarea>
                            <pre id="generated-code" class="generated-code"></pre>
                        </div>

                    </div>

				</div>

			</div>
			<?php echo wp_kses( $this->end_dialog(esc_html_x('Close', 'verb', 'microthemer'), 'span', 'close-dialog'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>

			<!-- Restore Settings -->
			<?php echo wp_kses( $this->start_dialog('display-revisions', esc_html__('Restore settings from a previous save point', 'microthemer'), 'small-dialog'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>

			<div class="content-main">
				<div id='revisions'>
					<div id='revision-area'></div>
				</div>
				<span id="view-revisions-trigger" rel="display-revisions"></span>
				<div class="explain">
				<div class="heading link explain-link"><?php esc_html_e('About this feature', 'microthemer'); ?></div>
					<div class="full-about">
					<p><?php esc_html_e('Click the "restore" link in the right hand column of the table to restore your workspace settings to a previous save point.', 'microthemer'); ?></p>
					</div>
				</div>
			</div>
			<?php echo wp_kses( $this->end_dialog(esc_html_x('Close', 'verb', 'microthemer'), 'span', 'close-dialog'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>


		<!-- Manage Design Packs -->
		<?php echo wp_kses( $this->start_dialog('manage-design-packs', esc_html__('Install & Manage Design Packs', 'microthemer')), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>
		<iframe id="manage_iframe" class="microthemer-iframe" frameborder="0" name="manage_iframe"
				rel="<?php echo esc_attr('admin.php?page='.$this->microthemespage); ?>"
				src="about:blank"
                loading="lazy"
				data-frame-loaded="0"></iframe>
		<?php echo wp_kses( $this->end_dialog(esc_html_x('Close', 'verb', 'microthemer'), 'span', 'close-dialog'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>

        <!-- Google Fonts -->
		<?php echo wp_kses( $this->start_dialog('google-fonts', esc_html__('Google Fonts Library', 'microthemer')), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>
        <iframe id="google_fonts_iframe" class="microthemer-iframe" name="google_fonts_iframe"
                rel="<?php echo esc_attr('admin.php?page='.$this->fontspage); ?>"
                src="about:blank"
                loading="lazy"
                data-frame-loaded="0"></iframe>
		<?php echo wp_kses( $this->end_dialog(esc_html_x('Close', 'verb', 'microthemer'), 'span', 'close-dialog'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>

		<!-- Program Docs -->
		<?php echo wp_kses( $this->start_dialog('program-docs', esc_html__('Help Centre', 'microthemer')), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>
		<iframe id="docs_iframe" class="microthemer-iframe" frameborder="0" name="docs_iframe"
				rel='<?php echo esc_attr("admin.php?page=" . $this->docspage); ?>'
				src="about:blank"
                loading="lazy"
				data-frame-loaded="0"></iframe>
		<?php echo wp_kses( $this->end_dialog(esc_html_x('Close', 'verb', 'microthemer'), 'span', 'close-dialog'), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>




        </div>


        <?php
        // install popup the Amender module (mt-html.js) drives
        if ($this->moduleUI){
            echo wp_kses( $this->moduleUI->install_popup(), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
        }
        ?>


		<?php
	}
	?>



	<!-- error report form -->
	<form id="error-report-form" name="error_report" method="post">
		<textarea name="tvr_php_error"></textarea>
		<textarea name="tvr_serialised_data"></textarea>
		<textarea name="tvr_browser_info"></textarea>
	</form>

	<!-- color picker mini and large palettes -->
	<div id="mt-picker-palette" class="hidden">
		<ul class="mt-picker-palette palette-list">
		<li class="view-full-palette">

			<?php
			echo wp_kses( $this->ui_toggle(
				'full_color_palette',
				esc_attr__('More colors', 'microthemer'),
				esc_attr__('Less colors', 'microthemer'),
				false, // never on initially
				'full-palette-toggle ' . $this->iconFont('dots-horizontal', array(
				        'onlyClass' => 1
                )),
				false,
				array(
					//'text' => '...',
					'dataAtts' => array(
						'no-save' => 1
					)
				)
			), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() )
			?>

			<div class="full-palette-popup">
				<?php
				$palettes = array(
					// not using this right now
					'recent' => array(
						'title' => __('Recent colors', 'microthemer'),
						'icons_buttons' => array(
							'clear-icon tvr-icon' => array(
								'title' => __('Clear recent colors', 'microthemer')
							),
						)
					),
					'saved' => array(
						'title' => __('Saved colors', 'microthemer'),
						'icons_buttons' => array(
							'palette-button mt-save-color show' => array(
								'text' => __('Save', 'microthemer'),
								'title' => __('Add to saved colors', 'microthemer')
							),
							'palette-button tvr-secondary mt-remove-color' => array(
								'text' => __('Remove', 'microthemer'),
								'title' => __('Remove from saved colors', 'microthemer')
							)
						)
					),
					'site' => array(
						'title' => __('Site colors', 'microthemer'),
						'icon_class' => $this->iconFont('sync-alt', array(
						        'onlyClass' => 1
                        )),
						'icons_buttons' => array(
							'refresh-icon' => array(
								'title' => __('Resample colors affecting the current page', 'microthemer')
							)
						)
					)
				);
				foreach ($palettes as $key => $arr){
					?>
					<div class="<?php echo esc_attr($key); ?>-colors-wrap p-colors-wrap">
						<div class="palette-heading">
							<span class="palette-heading-text"><?php echo esc_html($arr['title']); ?></span>
							<?php
							// output buttons/icons
							if (!empty($arr['icons_buttons'])){
								foreach ($arr['icons_buttons'] as $class => $array){

								    if (!empty($arr['icon_class'])){
									    $class.= ' '.$arr['icon_class'];
								    }

									$text = !empty($array['text']) ? $array['text'] : '';
									$title = !empty($array['title']) ? 'title="'.esc_attr($array['title']).'"' : '';
									echo wp_kses( '<span class="'.esc_attr($class).'" '.$title.'>'.esc_html($text).'</span>', \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
								}
							}
							?>
						</div>
						<ul class="palette-list full-palette <?php echo esc_attr($key); ?>-full-palette"></ul>
					</div>
					<?php
				}
				?>
				<span class="tvr-icon close-icon close-palette"></span>

			</div>
		</li>
	</ul>
	</div>

	<!-- html templates -->
	<form action='#' name='dummy' id="html-templates">
		<?php
		if (!$this->optimisation_test){

			// warning template
			?>
			<!--<div id="notice-template" class="tvr-message tvr-template-notice tvr-warning">
				<span class="mt-notice-icon"></span>
				<span class="mt-notice-text"></span>
			</div>-->
			<?php

			// ajax loaders
			//$this->hidden_ajax_loaders();

			// template for displaying save error and error report option
			/*$short = __('Error saving settings', 'microthemer');
			$long =
				'<p>' . sprintf(
					esc_html__('Please %s. The error report sends us information about your current Microthemer settings, server and browser information, and your WP admin email address. We use this information purely for replicating your issue and then contacting you with a solution.', 'microthemer'),
					'<span id="email-error" class="link">' . __('click this link to email an error report to Themeover', 'microthemer') . '</span>'
				) . '</p>
				<p>' . wp_kses(
					__('<b>Note:</b> reloading the page is normally a quick fix for now. However, unsaved changes will need to be redone.', 'microthemer'),
					array( 'b' => array() )
				). '</p>';
			echo $this->display_log_item('error', array('short'=> $short, 'long'=> $long), 0, 'id="log-item-template"');*/

			// dynamic menu items
			//echo $this->dyn_item($this->enq_js_structure, 'item', array('display_name' => 'item')); // enq_js
			//echo $this->dyn_item($this->mq_structure, 'item', array('label' => 'item')); // mq
			// mqs
			// custom code

            // define template for menu section
			//echo $this->menu_section_html('selector_section', 'section_label');

			// define template for menu selector
			//echo $this->menu_selector_html('selector_section', 'selector_css', array('selector_code', 'selector_label'), 1);

			// define template for section
			//echo $this->section_html('selector_section', array());

			// define template for selector
			//echo $this->single_selector_html('selector_section', 'selector_css', '', true);

            // define property group templates
			/*foreach ($this->propertyoptions as $property_group_name => $property_group_array) {

			    // we want root keys only for $property_group_array, to match propertyOptions format
			    $array_keys = array_keys($property_group_array);
			    $property_group_array_root = array();
			    foreach($array_keys as $prop_slug){
				    $property_group_array_root[$prop_slug] = '';
                }

			    echo $this->single_option_fields(
					'selector_section',
					'selector_css',
					array(),
				    $property_group_array_root,
					$property_group_name,
					'',
					true);
			}*/
		}
		?>

	</form>
	<!-- end html templates -->


</div><!-- end #tvr -->
