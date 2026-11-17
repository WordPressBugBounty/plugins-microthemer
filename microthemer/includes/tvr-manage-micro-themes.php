<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// get valid design packs (not loose files or sass/scss folders)
$design_packs = $this->get_design_packs($this->file_structure);

// calc pagination stuff
$total_packs = $design_packs['count'];
$results_per_page = 20;
$total_pages = ceil($total_packs/$results_per_page);
if (isset($_GET['packs_page'])) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified by verifyPageLinkNonces() on admin_init
	$page = intval($_GET["packs_page"]);
} else {
	$page = 1;
}
// determine start and end
$start = (($page-1)*$results_per_page)+1;
$end = ($start+$results_per_page)-1;

if ($end > $total_packs) {
	$end = $total_packs;
}

$ui_class = '';
require_once('common-inline-assets.php');

?>
<div id="tvr" class='wrap tvr-wrap tvr-manage <?php echo esc_attr($ui_class); ?>'>
	<div id='tvr-manage'>

		<?php
		// ajax loaders, meta spans, logs tmpl
		//$this->hidden_ajax_loaders();
		$this->manage_packs_meta();
		$this->manage_packs_header($this->microthemespage);
		?>

		<div id="full-logs">
			<?php echo wp_kses( $this->display_log(), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); ?>
		</div>

		<?php
		$this->pack_pagination($page, $total_pages, $total_packs, $start, $end);
		?>

		<ul id="all-packs">
			<?php
			// output packs
			$i = 0;
			foreach ($design_packs['directories'] as $pack => $array){

			    ++$i;
				if ($i < $start) {
					continue;
				} else if ($i > $end) {
					break;
				}
				?>
				<li>
					<div class="pack">
						<?php
						if (
						    !empty($array['screenshot'])
                            and file_exists($this->micro_root_dir . $pack.'/' . $array['screenshot'])
                        ) {
							$screenshot = $this->micro_root_url . $pack.'/' . $array['screenshot'];
						} else {
							$screenshot = $this->thispluginurl . 'images/screenshot-placeholder-small.gif';
						}
						$url = wp_nonce_url('admin.php?page=' . $this->managesinglepage . '&design_pack=' . $pack, 'mt_manage_packs');
						$name = $this->readable_name($pack);
						?>
						<a href="<?php echo esc_url($url); ?>">
							<img src="<?php echo esc_url($screenshot); ?>" width="145" height="83"
								 title="<?php /* translators: %s: design pack name */ printf(esc_attr__('Edit %s', 'microthemer'), esc_attr($name)); ?>" />
						</a>
						<div class="pack-quick-icons mt-icon-line">

                            <?php

                            // delete
                            echo wp_kses( $this->iconFont('bin', array(
                                    'class' => 'delete-pack-multi download-icon',
                                    'rel' => 'delete',
                                    'data-dir-name' => $pack,
                                    'data-dir-label' => $name,
                                    /* translators: %s: design pack name */
                                    'title' => sprintf(esc_attr__('Delete %s', 'microthemer'), $name)
                            )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                            // download
                            echo wp_kses( $this->iconFont('cloud-download-alt', array(
	                            'class' => 'download-pack-multi',
	                            'rel' => 'download',
	                            'data-dir-name' => $pack,
	                            /* translators: %s: design pack name */
	                            'title' => sprintf(esc_attr__('Download %s', 'microthemer'), $name)
                            )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                            // Edit
                            echo wp_kses( $this->iconFont('edit', array(
                                'tag' => 'a',
	                            'href' => $url,
	                            //'class' => '',
	                            //'data-dir-name' => $pack,
	                            /* translators: %s: design pack name */
	                            'title' => sprintf(esc_attr__('Edit %s', 'microthemer'), $name)
                            )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );

                            // Import
                            echo wp_kses( $this->iconFont('import', array(
	                            'class' => 'quick-import show-parent-dialog',
	                            'rel' => 'import-from-pack',
	                            'data-pack-name' => $pack,
	                            /* translators: %s: design pack name */
	                            'title' => sprintf(esc_attr__('Import %s into the interface', 'microthemer'), $name)
                            )), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
                            ?>

						</div>

						<?php
						// get meta info
						$meta_info = $this->read_meta_file($this->micro_root_dir . $pack . '/meta.txt');
						$h2 = '
						<a href="'.esc_url($url).'" class="title-text" title="' . sprintf(/* translators: %s: design pack name */ esc_attr__('Edit %s', 'microthemer'), esc_attr($name)) . '">
							' . esc_html($this->readable_name($pack));
							// version
							if (!empty($meta_info['Version'])) {
								//$h2.=' <span class="version">'.$meta_info['Version'] . '</span>';
							}
							$h2.= '
						</a>';
						// author
						if (!empty($meta_info['Author'])) {
							$h2.= '<p class="author-name">' . sprintf( /* translators: %s: author name */ esc_html__( 'by %s', 'microthemer'), esc_html($meta_info['Author']) ) . '</p>';
						}
						echo wp_kses( $h2, \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
						?>
					</div>
				</li>
				<?php
			}
			?>

		</ul>

		<?php
		$this->pack_pagination($page, $total_pages, $total_packs, $start, $end);
		?>

	</div>
</div><!-- end wrap -->
