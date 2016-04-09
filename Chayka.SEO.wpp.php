<?php
/**
 * Plugin Name: Chayka.SEO
 * Plugin URI: git@github.com:chayka/Chayka.SEO.wpp.git
 * Description: SEO WP plugin built with Chayka.Framework
 * Version: 0.0.1
 * Author: Boris Mossounov <borix@tut.by>
 * Author URI: https://anotherguru.me
 * License: Proprietary
 */

require_once __DIR__.'/vendor/autoload.php';

if(!class_exists('Chayka\WP\Plugin')){
    add_action( 'admin_notices', function () {
?>
    <div class="error">
        <p>Chayka.Core plugin is required in order for Chayka.SEO to work properly</p>
    </div>
<?php
	});
}else{
	add_action('init', ['Chayka\SEO\Plugin', 'init']);
}
