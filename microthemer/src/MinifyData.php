<?php

// Minifiers for generated CSS and JS. The bundled minifier treats a value that names a readable
// file as that file, inlines @import targets and base64-encodes url() targets. Generated code can
// hold user or imported values, so none of that may touch the filesystem: an @import of
// "var/www/wp-config.php" would otherwise copy the file into a public stylesheet.
// Loaded by FileTrait::maybeLoadMinify(), after the bundled classes.
namespace Microthemer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MinifyDataCSS extends Dependencies\Minify\CSS {
	protected function canImportFile($path) {
		return false;
	}
}

class MinifyDataJS extends Dependencies\Minify\JS {
	protected function canImportFile($path) {
		return false;
	}
}
