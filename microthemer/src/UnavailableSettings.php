<?php

namespace Microthemer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preserve saved CSS settings that the active property map cannot represent.
 *
 * The lite build deliberately has no definitions or UI for pro-only properties.
 * This class only compares opaque saved keys with the active map; it does not
 * need to know what an unavailable property means.
 */
class UnavailableSettings {

	/**
	 * Selector-level maps keyed by group/subgroup rather than by property.
	 *
	 * Some flows rebuild these from the DOM, so entries belonging to groups this
	 * build cannot render would otherwise read as deletions.
	 */
	private static $group_keyed_maps = array(
		'pg_disabled',
		'pgtab_disabled',
		'pg_chained',
		'multi_key',
	);

	/**
	 * Return style groups/properties that are absent from the active map.
	 */
	public static function styles($selector, $property_options) {
		$styles = isset($selector['styles']) && is_array($selector['styles'])
			? $selector['styles']
			: array();

		return self::groups($styles, $property_options);
	}

	/**
	 * Return groups/properties absent from the active map.
	 *
	 * Used for both selector styles and the flatter important-flag tree.
	 */
	public static function groups($groups, $property_options) {
		$unavailable = array();
		if (!is_array($groups)) {
			return $unavailable;
		}

		foreach ($groups as $group => $properties) {
			if (!isset($property_options[$group]) || !is_array($property_options[$group])) {
				$unavailable[$group] = $properties;
				continue;
			}

			if (!is_array($properties)) {
				continue;
			}

			foreach ($properties as $property => $value) {
				if (!array_key_exists($property, $property_options[$group])) {
					$unavailable[$group][$property] = $value;
				}
			}
		}

		return $unavailable;
	}

	/**
	 * Cheap existence check: does this stored options tree contain any group or
	 * property absent from the active map? Stops at the first hit rather than
	 * collecting everything (see styles()/groups() for that). Used only for a
	 * "you may have had settings from a different edition" hint - not part of
	 * the save-preservation path, so it deliberately skips the rarer important
	 * tree that preserve() covers exhaustively.
	 */
	public static function hasAny($stored, $property_options) {
		if (!is_array($stored) || empty($property_options)) {
			return false;
		}

		foreach ($stored as $section => $section_data) {
			if ($section === 'non_section' || !is_array($section_data)) {
				continue;
			}

			foreach ($section_data as $selector => $selector_data) {
				if ($selector === 'this' || !is_array($selector_data)) {
					continue;
				}

				if (self::styles($selector_data, $property_options)) {
					return true;
				}
			}
		}

		$mq_sections = self::array_path($stored, array('non_section', 'm_query'));
		if (is_array($mq_sections)) {
			foreach ($mq_sections as $sections) {
				if (!is_array($sections)) {
					continue;
				}

				foreach ($sections as $section => $selectors) {
					if ($section === 'this' || !is_array($selectors)) {
						continue;
					}

					foreach ($selectors as $selector => $selector_data) {
						if ($selector === 'this' || !is_array($selector_data)) {
							continue;
						}

						if (self::styles($selector_data, $property_options)) {
							return true;
						}
					}
				}
			}
		}

		return false;
	}

	/**
	 * Restore unavailable settings after a full save or a partial save package.
	 *
	 * Only selectors that still exist in the incoming data are supplemented, so
	 * an explicit selector/folder deletion remains a deletion.
	 */
	public static function preserve($stored, $incoming, $property_options, $build_group_keys = array()) {
		if (!is_array($stored) || !is_array($incoming) || empty($property_options)) {
			return $incoming;
		}

		foreach ($stored as $section => $section_data) {
			if ($section === 'non_section' || !is_array($section_data) || !isset($incoming[$section])) {
				continue;
			}

			self::preserve_section($section_data, $incoming[$section], $property_options, $build_group_keys);
		}

		$stored_mq = self::array_path($stored, array('non_section', 'm_query'));
		if (is_array($stored_mq)) {
			foreach ($stored_mq as $mq_key => $sections) {
				if (!is_array($sections) || !isset($incoming['non_section']['m_query'][$mq_key])) {
					continue;
				}

				foreach ($sections as $section => $section_data) {
					if (!is_array($section_data)) {
						continue;
					}

					foreach ($section_data as $selector => $stored_selector) {
						if ($selector === 'this' || !is_array($stored_selector)) {
							continue;
						}

						// The MQ tab may have been cleaned away because lite could
						// not represent its only values. Restore when the selector
						// itself was not deleted.
						if (!isset($incoming[$section][$selector]) || !is_array($incoming[$section][$selector])) {
							continue;
						}

						if (!isset($incoming['non_section']['m_query'][$mq_key][$section]) ||
							!is_array($incoming['non_section']['m_query'][$mq_key][$section])) {
							$incoming['non_section']['m_query'][$mq_key][$section] = array();
						}

						if (!isset($incoming['non_section']['m_query'][$mq_key][$section][$selector]) ||
							!is_array($incoming['non_section']['m_query'][$mq_key][$section][$selector])) {
							$incoming['non_section']['m_query'][$mq_key][$section][$selector] = array();
						}

						self::preserve_selector(
							$stored_selector,
							$incoming['non_section']['m_query'][$mq_key][$section][$selector],
							$property_options,
							$build_group_keys
						);
					}
				}
			}
		}

		$stored_important = self::array_path($stored, array('non_section', 'important'));
		if (is_array($stored_important)) {
			if (!isset($incoming['non_section']['important']) || !is_array($incoming['non_section']['important'])) {
				$incoming['non_section']['important'] = array();
			}

			self::preserve_important_tree(
				$stored_important,
				$incoming,
				$property_options
			);
		}

		return $incoming;
	}

	/**
	 * Put these groups back exactly as stored on every selector, in the regular and media query
	 * data, including their compiled AST. A group stored nowhere is removed, so unlike preserve()
	 * this also stops a save adding the group, to an existing selector or a new one. The exception
	 * is a selector key that isn't stored, carrying a value that is stored elsewhere: that's a
	 * rename, move or copy, which rebuilds the data under a new key and adds no new code.
	 */
	public static function lockGroups($stored, $incoming, $groups) {
		if (!is_array($incoming)) {
			return $incoming;
		}

		$known = self::stored_group_values($stored, $groups);

		foreach ($incoming as $section => &$section_data) {
			if ($section !== 'non_section' && is_array($section_data)) {
				self::lock_section(is_array($stored) && isset($stored[$section]) ? $stored[$section] : array(), $section_data, $groups, $known);
			}
		}
		unset($section_data);

		if (isset($incoming['non_section']['m_query']) && is_array($incoming['non_section']['m_query'])) {
			foreach ($incoming['non_section']['m_query'] as $mq_key => &$sections) {
				foreach ((array) $sections as $section => $section_data) {
					if (is_array($section_data)) {
						$stored_section = self::array_path($stored, array('non_section', 'm_query', $mq_key, $section));
						self::lock_section(is_array($stored_section) ? $stored_section : array(), $sections[$section], $groups, $known);
					}
				}
			}
			unset($sections);
		}

		return $incoming;
	}

	// Fingerprints of every stored value of these groups, by where they sit on a selector
	private static function stored_group_values($stored, $groups) {
		$sections = is_array($stored) ? array_values(array_diff_key($stored, array('non_section' => 1))) : array();
		foreach ((array) self::array_path($stored, array('non_section', 'm_query')) as $mq_sections) {
			$sections = array_merge($sections, array_values((array) $mq_sections));
		}

		$known = array();
		foreach ($sections as $section_data) {
			foreach ((array) $section_data as $selector => $selector_data) {
				if ($selector === 'this' || !is_array($selector_data)) {
					continue;
				}
				foreach ($groups as $group) {
					foreach (array(array('styles', $group), array('ast', 'rules', $group)) as $path) {
						$value = self::array_path($selector_data, $path);
						if ($value !== null) {
							$known[self::fingerprint($path, $value)] = true;
						}
					}
				}
			}
		}

		return $known;
	}

	private static function fingerprint($path, $value) {
		return md5(implode('.', $path) . serialize($value));
	}

	private static function lock_section($stored, &$section_data, $groups, $known) {
		foreach ($section_data as $selector => &$selector_data) {
			if ($selector === 'this' || !is_array($selector_data)) {
				continue;
			}
			$is_new = !isset($stored[$selector]);
			$stored_selector = $is_new ? array() : $stored[$selector];
			foreach ($groups as $group) {
				self::lock_path($stored_selector, $selector_data, array('styles', $group), $is_new ? $known : array());
				self::lock_path($stored_selector, $selector_data, array('ast', 'rules', $group), $is_new ? $known : array());
			}
		}
	}

	private static function lock_path($stored, &$incoming, $path, $known) {
		$full_path = $path;
		$key = array_pop($path);
		$parent = self::array_path($stored, $path);

		if (is_array($parent) && array_key_exists($key, $parent)) {
			self::ensure_path($incoming, $path);
		} elseif (!is_array(self::array_path($incoming, $path))) {
			return;
		}

		$target = &$incoming;
		foreach ($path as $step) {
			$target = &$target[$step];
		}

		if (is_array($parent) && array_key_exists($key, $parent)) {
			$target[$key] = $parent[$key];
		} elseif (!isset($target[$key]) || empty($known[self::fingerprint($full_path, $target[$key])])) {
			unset($target[$key]);
		}
	}

	/**
	 * Keep preference defaults/suggestions for properties the active map omits.
	 *
	 * savePreferences replaces whole top-level keys, so a rebuilt my_props
	 * object must not be treated as a deletion of unavailable groups.
	 */
	public static function preserve_my_props($stored, $incoming, $property_options) {
		if (!is_array($stored) || !is_array($incoming) || empty($property_options)) {
			return $incoming;
		}

		$mapped_props = array();
		foreach ($property_options as $group => $properties) {
			if (!is_array($properties)) {
				continue;
			}
			foreach ($properties as $property => $meta) {
				$mapped_props[$property] = true;
			}
		}

		foreach ($stored as $group => $data) {
			if ($group === 'sug_values' || $group === 'sug_variables') {
				continue;
			}

			if (!isset($property_options[$group]) || !is_array($property_options[$group])) {
				if (!array_key_exists($group, $incoming)) {
					$incoming[$group] = $data;
				}
				continue;
			}

			$stored_props = isset($data['pg_props']) && is_array($data['pg_props']) ? $data['pg_props'] : array();
			if (!$stored_props) {
				continue;
			}

			$incoming_props = self::array_path($incoming, array($group, 'pg_props'));
			$incoming_props = is_array($incoming_props) ? $incoming_props : array();

			foreach ($stored_props as $property => $prop_data) {
				if (array_key_exists($property, $property_options[$group]) ||
					array_key_exists($property, $incoming_props)) {
					continue;
				}

				// Only materialise the group once there is something to keep.
				self::ensure_path($incoming, array($group, 'pg_props'));
				$incoming[$group]['pg_props'][$property] = $prop_data;
			}
		}

		foreach (array('sug_values', 'sug_variables') as $sug_key) {
			if (!isset($stored[$sug_key]) || !is_array($stored[$sug_key])) {
				continue;
			}

			if (!isset($incoming[$sug_key]) || !is_array($incoming[$sug_key])) {
				$incoming[$sug_key] = array();
			}

			foreach ($stored[$sug_key] as $root_cat => $values) {
				if (!isset($mapped_props[$root_cat]) && !array_key_exists($root_cat, $incoming[$sug_key])) {
					$incoming[$sug_key][$root_cat] = $values;
				}
			}
		}

		return $incoming;
	}

	private static function preserve_section($stored, &$incoming, $property_options, $build_group_keys = array()) {
		if (!is_array($incoming)) {
			return;
		}

		foreach ($stored as $selector => $stored_selector) {
			if ($selector === 'this' || !is_array($stored_selector) || !isset($incoming[$selector]) || !is_array($incoming[$selector])) {
				continue;
			}

			self::preserve_selector($stored_selector, $incoming[$selector], $property_options, $build_group_keys);
		}
	}

	private static function preserve_selector($stored, &$incoming, $property_options, $build_group_keys = array()) {
		$stored_styles = isset($stored['styles']) && is_array($stored['styles']) ? $stored['styles'] : array();
		$incoming_styles = isset($incoming['styles']) && is_array($incoming['styles']) ? $incoming['styles'] : array();
		if ($stored_styles) {
			$incoming['styles'] = self::merge_groups($stored_styles, $incoming_styles, $property_options);
		}

		// Unknown groups cannot be recompiled without their definitions. Retain
		// their already-compiled AST so edits to visible groups can still combine it.
		// non_gui isn't a group: it holds the selector's own hand-written CSS (e.g. variables),
		// which every build round-trips, so the incoming copy always wins. Keeping the stored
		// one undid every edit to it.
		$stored_rules = self::array_path($stored, array('ast', 'rules'));
		if (is_array($stored_rules)) {
			$incoming_rules = self::array_path($incoming, array('ast', 'rules'));
			$incoming_rules = is_array($incoming_rules) ? $incoming_rules : array();
			$merged_rules = array();

			foreach ($stored_rules as $group => $rules) {
				if (!isset($property_options[$group]) && $group !== 'non_gui') {
					$merged_rules[$group] = $rules;
				} elseif (array_key_exists($group, $incoming_rules)) {
					$merged_rules[$group] = $incoming_rules[$group];
				}
			}
			foreach ($incoming_rules as $group => $rules) {
				if (!array_key_exists($group, $merged_rules)) {
					$merged_rules[$group] = $rules;
				}
			}

			if (!isset($incoming['ast']) || !is_array($incoming['ast'])) {
				$incoming['ast'] = array();
			}
			$incoming['ast']['rules'] = $merged_rules;
		}

		if (!array_key_exists('compiled_css', $incoming) && array_key_exists('compiled_css', $stored)) {
			$incoming['compiled_css'] = $stored['compiled_css'];
		}

		self::preserve_group_keyed_maps($stored, $incoming, $build_group_keys);
	}

	/**
	 * Restore per-group flags (disabled/chained/active tab) for groups this build
	 * has no definition for. Groups the build does know about are left alone, so
	 * clearing a flag in the UI still reads as a deletion.
	 */
	private static function preserve_group_keyed_maps($stored, &$incoming, $build_group_keys) {
		if (empty($build_group_keys)) {
			return;
		}

		foreach (self::$group_keyed_maps as $map_key) {
			if (!isset($stored[$map_key]) || !is_array($stored[$map_key])) {
				continue;
			}

			foreach ($stored[$map_key] as $group => $value) {
				if (isset($build_group_keys[$group])) {
					continue;
				}

				if (!isset($incoming[$map_key]) || !is_array($incoming[$map_key])) {
					$incoming[$map_key] = array();
				}

				$incoming[$map_key][$group] = $value;
			}
		}
	}

	private static function preserve_important_tree($stored, &$incoming, $property_options) {
		foreach ($stored as $key => $value) {
			if ($key === 'm_query' && is_array($value)) {
				foreach ($value as $mq_key => $sections) {
					if (!is_array($sections) || !isset($incoming['non_section']['m_query'][$mq_key])) {
						continue;
					}

					foreach ($sections as $section => $selectors) {
						if (!is_array($selectors)) {
							continue;
						}

						foreach ($selectors as $selector => $groups) {
							if (!is_array($groups) || !isset($incoming[$section][$selector])) {
								continue;
							}

							self::ensure_path(
								$incoming['non_section']['important'],
								array('m_query', $mq_key, $section, $selector)
							);
							$incoming['non_section']['important']['m_query'][$mq_key][$section][$selector] = self::merge_groups(
								$groups,
								$incoming['non_section']['important']['m_query'][$mq_key][$section][$selector],
								$property_options
							);
						}
					}
				}
				continue;
			}

			if (!is_array($value) || !isset($incoming[$key]) || !is_array($incoming[$key])) {
				continue;
			}

			foreach ($value as $selector => $groups) {
				if (!is_array($groups) || !isset($incoming[$key][$selector]) || !is_array($incoming[$key][$selector])) {
					continue;
				}

				self::ensure_path($incoming['non_section']['important'], array($key, $selector));
				$incoming['non_section']['important'][$key][$selector] = self::merge_groups(
					$groups,
					$incoming['non_section']['important'][$key][$selector],
					$property_options
				);
			}
		}
	}

	/**
	 * Merge mapped incoming values with opaque stored values while retaining the
	 * stored order. Array order is part of WordPress's serialized option bytes.
	 */
	private static function merge_groups($stored, $incoming, $property_options) {
		$merged = array();
		$incoming = is_array($incoming) ? $incoming : array();

		foreach ($stored as $group => $stored_properties) {
			if (!isset($property_options[$group]) || !is_array($property_options[$group])) {
				$merged[$group] = $stored_properties;
				continue;
			}

			$incoming_properties = isset($incoming[$group]) && is_array($incoming[$group])
				? $incoming[$group]
				: array();
			$merged_properties = array();
			if (is_array($stored_properties)) {
				foreach ($stored_properties as $property => $stored_value) {
					if (!array_key_exists($property, $property_options[$group])) {
						$merged_properties[$property] = $stored_value;
					} elseif (array_key_exists($property, $incoming_properties)) {
						$merged_properties[$property] = $incoming_properties[$property];
					}
				}
			}

			foreach ($incoming_properties as $property => $incoming_value) {
				if (!array_key_exists($property, $merged_properties)) {
					$merged_properties[$property] = $incoming_value;
				}
			}

			if ($merged_properties || array_key_exists($group, $incoming)) {
				$merged[$group] = $merged_properties;
			}
		}

		foreach ($incoming as $group => $properties) {
			if (!array_key_exists($group, $merged)) {
				$merged[$group] = $properties;
			}
		}

		return $merged;
	}

	private static function ensure_path(&$array, $path) {
		$current =& $array;
		foreach ($path as $key) {
			if (!isset($current[$key]) || !is_array($current[$key])) {
				$current[$key] = array();
			}
			$current =& $current[$key];
		}
	}

	private static function array_path($array, $path) {
		foreach ($path as $key) {
			if (!is_array($array) || !array_key_exists($key, $array)) {
				return null;
			}
			$array = $array[$key];
		}

		return $array;
	}
}
