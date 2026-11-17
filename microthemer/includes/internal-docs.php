<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// get property options (pro groups load only when the pro definitions file is present)
include 'property-options.inc.php';
if (file_exists(__DIR__ . '/property-options-pro.inc.php')) {
	include 'property-options-pro.inc.php';
}

$ui_class = '';
require_once('common-inline-assets.php');
?>

<div id="tvr" class='wrap tvr-wrap tvr-docs <?php echo esc_attr($ui_class); ?>'>
	<div id='tvr-docs'>

		<!-- Support links -->
		<div id="g-search">
			<div class="support-header-wrap">
				<ul id="external-links">
					<li class="doc-item external">
						<a target="_blank" class="video" href="<?php echo esc_url($this->demo_video); ?>">Demo<br />Video</a>
					</li>
					<li class="doc-item external">
						<a target="_blank" href="https://themeover.com/support/">Online<br />Docs</a>
					</li>
					<li class="doc-item external">
						<a target="_blank" href="https://themeover.com/forum/">Support<br />Forum</a>
					</li>
				</ul>
			</div>
		</div>

		<div id="docs-container">

			<div id="docs-content">

				<?php

				$prop_group = $property = false;

				// support index page - not currently using
				if (isset($_GET['docs_index'])) {
					?>
					<div id="docs-index" class='ref-details docs-box'>


					</div>
					<?php
				}

				// CSS Reference
				if (isset($_GET['prop_group']) or isset($_GET['prop'])) {

					// single property
					$prop_group = sanitize_key(wp_unslash($_GET["prop_group"]));
					$property = sanitize_key(wp_unslash($_GET["prop"]));
					$pg_arr = $propertyOptions[$prop_group];
					$p_arr = $propertyOptions[$prop_group][$property];
					$cssf = str_replace('_', '-', $property);
					$icon_name = !empty($array['icon-name']) ? $array['icon-name'] : $cssf;
					?>
					<div id='<?php echo esc_attr($property); ?>' class='ref-details docs-box'>
						<h3 class="main-title">

							<span class="t-text">
								<?php echo esc_html($p_arr['label']); ?>
							</span>
							<?php
							echo wp_kses( $this->iconFont($icon_name, array(
								'class' => 'no-click'
							)), \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
							?>

						</h3>
						<div class="inner-box">
							<p><?php echo wp_kses_post($p_arr['ref_desc']); ?></p>
							<?php
							if (!empty($p_arr['ref_values']) and is_array($p_arr['ref_values'])){
								?>
								<table class="prop-vals">
									<thead>
									<tr class='heading'>
										<th class="value">Value</th>
										<th>Description</th>
									</tr>
									</thead>
									<tbody>
									<?php
									$i = 1;
									foreach ($p_arr['ref_values'] as $value => $desc) {
										?>
										<tr <?php if ($i&1) { echo 'class="odd"'; } ?>>
											<td class="value"><?php echo esc_html($value); ?></td><td><?php echo wp_kses_post($desc); ?></td>
										</tr>
										<?php
										++$i;
									}
									?>
									</tbody>
								</table>
								<?php
							}
							?>


						</div>
					</div>
					<div id="tutorials-refs" class="docs-box">

						<?php
						$ref_map = array(
							'can_i_use' => array(
								'name' => 'Can I Use',
								'icon' => ''
							),
							'css_tricks' => array(
								'name' => 'CSS Tricks',
								'icon' => ''
							),
							'mozilla' => array(
								'name' => 'Mozilla',
								'icon' => ''
							),
							'quackit' => array(
								'name' => 'Quackit',
								'icon' => ''
							),
							'w3s' => array(
								'name' => 'W3Schools',
								'icon' => ''
							)
						);
						// css ref
						if (!empty($p_arr['ref_links']) and is_array($p_arr['ref_links'])){
							echo '<h3>'.esc_html($p_arr['label']).' References</h3>
							<ul>';
							foreach ($p_arr['ref_links'] as $key => $url){
								echo '
								<li>
									<a target="_blank" href="'.esc_url($url).'">'
										.esc_html($ref_map[$key]['name']).': ' . esc_html(str_replace('_', '-', $property )). '
									</a>
								</li>';
							}
							echo '</ul>';
						}
						// tutorials
						if (!empty($p_arr['tutorials']) and is_array($p_arr['tutorials'])){
							// are the tutorials specific to the property or the property group?
							if (!empty($p_arr['group_tutorials'])){
								$scope = $this->property_option_groups[$prop_group];
								//$scope = $p_arr['pg_label'];
							} elseif (!empty($p_arr['subgroup_tutorials'])){
								$keys = array_keys($pg_arr);
								$scope = $pg_arr[$keys[0]]['sub_label'];
							} elseif (!empty($p_arr['related_tutorials'])){
								$scope = $p_arr['related_tutorials'];
							} else {
								$scope = $p_arr['label'];
							}
							echo '<h3>'.esc_html($scope).' Tutorials</h3>
							<ul>';
							foreach ($p_arr['tutorials'] as $key => $arr){
								echo '
								<li>
									<a target="_blank" href="'.esc_url($arr['url']).'">'
									.esc_html($arr['title']).'
									</a>
								</li>';
							}
							echo '</ul>';
						}
						?>
					</div>
				<?php

				}
				?>

			</div><!-- end content -->
		</div><!-- end container -->

		<?php

		// side menu
		$this->docs_menu($propertyOptions, $prop_group, $property);

		?>



	</div><!-- end tvr-docs -->
</div><!-- end wrap -->

