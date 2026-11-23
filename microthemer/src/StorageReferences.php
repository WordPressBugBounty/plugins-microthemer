<?php

namespace Microthemer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Points references to the old storage folder (wp-content/micro-themes) at the current one, see StorageTrait.
 *
 * There are two kinds:
 * - URLs and paths of files in the folder. Only this site's are changed: another site's micro-themes
 *   URL (in an imported pack, say) must keep pointing at that site.
 * - relative url()s that climb out of the folder, like url(../uploads/a.png). The new folder is a level
 *   deeper, so those would point somewhere else. They become root-relative, which no later move can break.
 */
class StorageReferences {

	private $from;
	private $to;
	private $replacements;
	private $pattern;

	// $from and $to are Helper::legacyStorage() and Helper::uploadsStorage()
	function __construct($from, $to){
		$this->from = $from;
		$this->to = $to;
		$this->replacements = $this->replacements();
		$this->pattern = $this->pattern(array_keys($this->replacements));
	}

	function replace($string){

		$replacements = $this->replacements;
		$updated = preg_replace_callback($this->pattern, function ($match) use ($replacements) {
			return $replacements[$match[0]];
		}, $string);

		return $updated === null ? $string : $updated;
	}

	function replaceInData($data){

		if (is_string($data)){
			return $this->replace($data);
		}

		if (is_array($data)){
			foreach ($data as $key => $value){
				$data[$key] = $this->replaceInData($value);
			}
		}

		// not incomplete classes, which can't be written to
		elseif ($data instanceof \stdClass){
			$data = clone $data;
			foreach (get_object_vars($data) as $key => $value){
				$data->$key = $this->replaceInData($value);
			}
		}

		return $data;
	}

	/**
	 * Fix relative url()s in CSS that was written for the old folder.
	 *
	 * @param string $css
	 * @param string $subDir       where the stylesheet is within the folder, e.g. mt/conditional/active/
	 * @param bool   $onlyIfBroken for CSS that may have been written since the move: only change a URL whose
	 *                             file is missing where it points now and exists where it pointed before
	 */
	function fixRelative($css, $subDir = '', $onlyIfBroken = false){

		if (strpos($css, '..') === false){
			return $css;
		}

		$pattern = '~(url\(\s*(?:\\\\?["\'])?|@import\s+\\\\?["\'])((?:\.\.\\\\?/)+)((?:[^"\'()\s\\\\]|\\\\/)*)~i';

		$updated = preg_replace_callback($pattern, function ($match) use ($subDir, $onlyIfBroken) {

			$climbs = substr_count($match[2], '..');
			if ($climbs <= count($this->segments($subDir))){
				return $match[0]; // stays inside the folder, which moved as a whole
			}

			$before = $this->climb($this->from['url'], $subDir, $climbs);

			if ($onlyIfBroken){
				$rest = str_replace('\\/', '/', $match[3]);
				if (!$this->fileExists($before . $rest) ||
				    $this->fileExists($this->climb($this->to['url'], $subDir, $climbs) . $rest)){
					return $match[0];
				}
			}

			// a root-relative URL would be looked up on the stylesheet's host
			if (!$this->sameHost()){
				$before = $this->origin($this->from['url']) . $before;
			}

			if (strpos($match[2], '\\/') !== false){
				$before = str_replace('/', '\\/', $before);
			}

			return $match[1] . $before . $match[3];
		}, $css);

		return $updated === null ? $css : $updated;
	}

	// Fix relative url()s throughout a copy of the settings
	function fixRelativeInSettings($settings, $onlyIfBroken = false){

		if (!is_array($settings)){
			return $settings;
		}

		$subDirs = array();
		foreach ($settings as $slug => $folder){
			if ($slug !== 'non_section' && is_array($folder)){
				$subDirs[$slug] = self::folderSubDir(isset($folder['this']['logic']) ? $folder['this']['logic'] : null);
				if ($subDirs[$slug] !== null){
					$settings[$slug] = $this->fixRelativeInData($folder, $subDirs[$slug], $onlyIfBroken);
				}
			}
		}

		if (empty($settings['non_section']) || !is_array($settings['non_section'])){
			return $settings;
		}

		foreach (array('hand_coded_css', 'hand_coded_css_compiled') as $key){
			if (isset($settings['non_section'][$key])){
				$settings['non_section'][$key] =
					$this->fixRelativeInData($settings['non_section'][$key], '', $onlyIfBroken);
			}
		}

		if (!empty($settings['non_section']['m_query']) && is_array($settings['non_section']['m_query'])){
			foreach ($settings['non_section']['m_query'] as $query => $folders){
				if (!is_array($folders)){
					continue;
				}
				foreach ($folders as $slug => $folder){
					if (isset($subDirs[$slug])){
						$settings['non_section']['m_query'][$query][$slug] =
							$this->fixRelativeInData($folder, $subDirs[$slug], $onlyIfBroken);
					}
				}
			}
		}

		return $settings;
	}

	// Where a folder's CSS is loaded from, or null if it's printed in the page: those URLs are relative
	// to the page, so moving the storage folder doesn't affect them.
	static function folderSubDir($logic){

		if (empty($logic['expr'])){
			return ''; // the global stylesheet
		}

		return empty($logic['css_external']) ? null : 'mt/conditional/active/';
	}

	private function fixRelativeInData($data, $subDir, $onlyIfBroken){

		if (is_string($data)){
			return $this->fixRelative($data, $subDir, $onlyIfBroken);
		}

		if (is_array($data)){
			foreach ($data as $key => $value){
				$data[$key] = $this->fixRelativeInData($value, $subDir, $onlyIfBroken);
			}
		}

		return $data;
	}

	// old => new
	private function replacements(){

		$replacements = array();
		$urls = array_merge(array($this->from['url']), isset($this->from['aliases']) ? $this->from['aliases'] : array());

		foreach ($urls as $fromUrl){

			$from = wp_parse_url($fromUrl);
			if (empty($from['host'])){
				continue;
			}

			$port = !empty($from['port']) ? ':' . $from['port'] : '';
			$hosts = array($from['host']);
			if (strpos($from['host'], '.') !== false){
				$hosts[] = stripos($from['host'], 'www.') === 0 ? substr($from['host'], 4) : 'www.' . $from['host'];
			}

			// whichever scheme, and with or without www, they were saved with
			foreach ($hosts as $host){
				$url = '//' . $host . $port . $from['path'];
				$replacements['http:' . $url] = $this->to['url'];
				$replacements['https:' . $url] = $this->to['url'];
				$replacements[$url] = preg_replace('#^https?:#i', '', $this->to['url']);
			}

			$replacements[$from['path']] = $this->sameHost()
				? wp_parse_url($this->to['url'], PHP_URL_PATH)
				: $this->to['url'];
		}

		// file paths (stored in some preferences), with either kind of slash
		$replacements[$this->from['dir']] = $this->to['dir'];
		if (preg_match('#^[a-z]:/#i', $this->from['dir'])){
			$replacements[str_replace('/', '\\', $this->from['dir'])] = str_replace('/', '\\', $this->to['dir']);
		}

		// JSON escapes slashes (\/)
		$escaped = array();
		foreach ($replacements as $old => $new){
			if (strpos($old, '/') !== false){
				$escaped[str_replace('/', '\\/', $old)] = str_replace('/', '\\/', $new);
			}
		}

		return $replacements + $escaped;
	}

	// One pass, longest first. Anything starting with a slash must start the URL or path, or it would
	// also match the end of another site's URL. PHP files don't move, so neither do references to them.
	private function pattern($olds){

		usort($olds, function ($a, $b) {
			return strlen($b) - strlen($a);
		});

		$parts = array();
		foreach ($olds as $old){
			$startsWithSlash = $old[0] === '/' || strpos($old, '\\/') === 0;
			$parts[] = ($startsWithSlash ? '(?<![a-zA-Z0-9._~%:/\\\\-])' : '') . preg_quote($old, '#');
		}

		return '#(?:' . implode('|', $parts) . ')(?![^\s"\'()<>?\#]*\.php\b)#';
	}

	private function segments($path){
		return array_values(array_filter(explode('/', (string) $path), 'strlen'));
	}

	// the root-relative folder that climbing out of a storage folder lands in
	private function climb($url, $subDir, $climbs){

		$segments = array_merge($this->segments(wp_parse_url($url, PHP_URL_PATH)), $this->segments($subDir));
		$segments = array_slice($segments, 0, max(0, count($segments) - $climbs));

		return $segments ? '/' . implode('/', $segments) . '/' : '/';
	}

	private function origin($url){
		return preg_replace('#^((?:https?:)?//[^/]+).*$#i', '$1', $url);
	}

	private function sameHost(){
		return strtolower((string) wp_parse_url($this->from['url'], PHP_URL_HOST))
			=== strtolower((string) wp_parse_url($this->to['url'], PHP_URL_HOST));
	}

	// whether a root-relative URL is a file in wp-content or uploads
	private function fileExists($url){

		$url = rawurldecode(preg_replace('/[?#].*$/', '', $url));

		// resolve any ../ left in the middle
		$resolved = array();
		foreach ($this->segments($url) as $segment){
			if ($segment === '..'){
				array_pop($resolved);
			} elseif ($segment !== '.'){
				$resolved[] = $segment;
			}
		}
		$url = '/' . implode('/', $resolved);

		$uploads = wp_upload_dir(null, false);
		$bases = array(
			$this->from['url'] => $this->to['dir'], // its files have moved
			$uploads['baseurl'] => $uploads['basedir'],
			content_url() => WP_CONTENT_DIR,
		);

		foreach ($bases as $baseUrl => $baseDir){
			$base = rtrim((string) wp_parse_url($baseUrl, PHP_URL_PATH), '/') . '/';
			if (strpos($url, $base) === 0){
				return is_file(rtrim($baseDir, '/\\') . '/' . substr($url, strlen($base)));
			}
		}

		return false;
	}
}
