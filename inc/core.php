<?php
/*
Part of Plugin: Ozh' Admin Drop Down Menu
http://planetozh.com/blog/my-projects/wordpress-admin-menu-drop-down-css/
*/

function wp_ozh_adminmenu() {
	global $wp_ozh_adminmenu;

	$menu = wp_ozh_adminmenu_build();
	
	echo "</ul>"; // close original <ul id="dashmenu"> before we add ours
	
	$ozh_menu = '<ul id="ozhmenu">'; 
	
	foreach ($menu as $k=>$v) {
		$url 	= $v['url'];
		$name 	= $k;
		$id 	= 'oam_'.str_replace('.php','',$k);
		$anchor = $v['name'];
		$class	= $v['class'];
		if ($wp_ozh_adminmenu['toplinks']) {
			$href = "href='$url'";
		} else {
			$href =  ( $v['sub'] )? '' : "href='$url'" ;
		}
		
		$ozh_menu .= "\t<li class='ozhmenu_toplevel' id='$id'><a $href $class><span>$anchor</span></a>";
		if (is_array($v['sub'])) {
			
			$ulclass='';
			if ($class) $ulclass = " class='ulcurrent'";
			$ozh_menu .= "\n\t\t<ul$ulclass>\n";

			foreach ($v['sub'] as $subk=>$subv) {
				$id = 'oamsub_'.str_replace(array('.php','.','/'),array('','_','_'),$subk);
				$suburl = $subv['url'];
				$subanchor = $subv['name'];
				$icon = $subv['icon'];
				if ($subv['hook']) {
					// we're dealing with a plugin, does it have a special icon?
					$plugin_icon = apply_filters('ozh_adminmenu_icon', $subv['hook']);
					// if no filter is defined, $plugin_icon = $subv['hook']
					if ($plugin_icon != $subv['hook']) {
						$plugin_icons[str_replace(array('.php','.','/'),array('','_','_'),$subv['hook'])] = $plugin_icon;
					}
				}
				$subclass='';
				if (array_key_exists('class',$subv)) $subclass=$subv['class'];
				$ozh_menu .= "\t\t\t<li class='ozhmenu_sublevel $icon' id='$id'><a href='$suburl'$subclass>$subanchor</a></li>\n";
			}
			$ozh_menu .= "\t</ul>\n";
		}
		$ozh_menu .="\t</li>\n";
	}
	
	if ($plugin_icons) {
		echo "\n<!-- special plugin icons! -->\n".'<style type="text/css">'."\n";
		foreach($plugin_icons as $hook=>$icon) {
			$hook = plugin_basename($hook);
			echo "#oamsub_$hook a {background-image:url($icon);}\n";
		}
		echo "</style>\n";
	}
	
	echo $ozh_menu;
	
}
 
 
/* Core stuff : builds an array populated with all the infos needed for menu and submenu */
function wp_ozh_adminmenu_build () {
	global $menu, $submenu, $plugin_page, $pagenow;
	
	/* Most of the following garbage are bits from admin-header.php,
	 * modified to populate an array of all links to display in the menu
	 */
	 
	$self = preg_replace('|^.*/wp-admin/|i', '', $_SERVER['PHP_SELF']);
	$self = preg_replace('|^.*/plugins/|i', '', $self);
	
	// Other plugins can use add_filter('pre_ozh_adminmenu_menu', 'my_function') to modify $menu as WP defines it
	$menu = apply_filters('pre_ozh_adminmenu_menu', $menu);
	
	/* Make sure that "Manage" always stays the same. Stolen from Andy @ YellowSwordFish */
	$menu[5][0] = __("Write");
	$menu[5][1] = "edit_posts";
	$menu[5][2] = "post-new.php";
	$menu[10][0] = __("Manage");
	$menu[10][1] = "edit_posts";
	$menu[10][2] = "edit.php";

	// Other plugins can use add_filter('ozh_adminmenu_menu', 'my_function') to modify our modified $menu
	$menu = apply_filters('ozh_adminmenu_menu', $menu);
	
	// The array containing all menu entries
	$altmenu = array();
	
	// Other plugins can use add_filter('pre_ozh_adminmenu_altmenu', 'my_function') to "pre-populate" $altmenu
	$altmenu = apply_filters('pre_ozh_adminmenu_altmenu', $altmenu );
	
	/* Step 1 : populate first level menu as per user rights */
	foreach ($menu as $item) {
		// 0 = name, 1 = capability, 2 = file
		if ( current_user_can($item[1]) ) {
			if ( file_exists(ABSPATH . "wp-content/plugins/{$item[2]}") )
				$altmenu[$item[2]]['url'] = get_option('siteurl') . "/wp-admin/admin.php?page={$item[2]}";			
			else
				$altmenu[$item[2]]['url'] = get_option('siteurl') . "/wp-admin/{$item[2]}";

			if (( strcmp($self, $item[2]) == 0 && empty($parent_file)) || ($parent_file && ($item[2] == $parent_file)))
			$altmenu[$item[2]]['class'] = " class='current'";
			
			$altmenu[$item[2]]['name'] = $item[0];

			/* Windows installs may have backslashes instead of slashes in some paths, fix this */
			$altmenu[$item[2]]['name'] = str_replace(chr(92),chr(92).chr(92),$altmenu[$item[2]]['name']);
		}
	}
	
	/* Step 2 : populate second level menu */
	foreach ($submenu as $k=>$v) {
		foreach ($v as $item) {
			if (array_key_exists($k,$altmenu) and current_user_can($item[1])) {
				
				// What's the link ?
				$menu_hook = get_plugin_page_hook($item[2], $k);

				if (file_exists(ABSPATH . "wp-content/plugins/{$item[2]}") || ! empty($menu_hook)) {
					list($_plugin_page,$temp) = explode('?',$altmenu[$k]['url']);
					$link = $_plugin_page.'?page='.$item[2];
					$altmenu[$k]['sub'][$item[2]]['icon'] = 'oam_plugin';
					$altmenu[$k]['sub'][$item[2]]['hook'] = $item[2];
				} else {
					$link =  $item[2];
					$altmenu[$k]['sub'][$item[2]]['icon'] = 'oam_'.str_replace(array('.php','.','/'),array('','_','_'),$item[2]);
				}
				
				/* Windows installs may put backslashes instead of slashes in paths, fix this */
				$link = str_replace(chr(92),chr(92).chr(92),$link);
				
				$altmenu[$k]['sub'][$item[2]]['url'] = $link;
				
				// Is it current page ?
				$class = '';
				if ( (isset($plugin_page) && $plugin_page == $item[2] && $pagenow == $k) || (!isset($plugin_page) && $self == $item[2] ) ) $class=" class='current'";
				if ($class) {
					$altmenu[$k]['sub'][$item[2]]['class'] = $class;
					$altmenu[$k]['class'] = $class;
				}
				
				// What's its name again ?
				$altmenu[$k]['sub'][$item[2]]['name'] = $item[0];
			}
		}
	}
	
	// Dirty debugging: break page and dies
	/**
	echo "</ul><pre style='font-size:9px'>";
	//echo '__MENU ';print_r($menu);
	//echo 'SUBMENU ';print_r($submenu);
	echo 'ALTMENU ';print_r($altmenu);
	die();
	/**/
		
	// Clean debugging: prints after footer
	/**
	global $wpdb;
	$wpdb->wp_ozh_adminmenu_neat_array = "<pre style='font-size:80%'>Our Oh-So-Beautiful-4-Levels-".htmlentities(print_r($altmenu,true))."</pre>";
	add_action('admin_footer', create_function('', 'global $wpdb; echo $wpdb->wp_ozh_adminmenu_neat_array;')); 
	/**/
	
	// Other plugins can use add_filter('ozh_adminmenu_array', 'my_function') to modify $altmenu
	$altmenu = apply_filters('ozh_adminmenu_altmenu', $altmenu );

	return ($altmenu);
}


function wp_ozh_adminmenu_js($init = true) {
	global $wp_ozh_adminmenu;
	
	$submenu = $wp_ozh_adminmenu['display_submenu'] ? 'false': 'true';
	$toomanyplugins = $wp_ozh_adminmenu['too_many_plugins'];
	$fluency = function_exists('wp_admin_fluency_css') ? 'true' : 'false';

	if ($init) {
		$plugin_url = WP_PLUGIN_URL.'/'.plugin_basename(dirname(__FILE__));
		$insert_main_js = '<script src="'.$plugin_url.'/adminmenu.js" type="text/javascript"></script>';
	} else {
		$insert_main_js = '';
	}

	echo <<<JS
<script type="text/javascript"><!--//--><![CDATA[//><!--
var oam_toomanypluygins = $toomanyplugins;
var oam_adminmenu = false;
var oam_fluency = $fluency;
var oam_hidesubmenu = $submenu;

jQuery(document).ready(function() {
	// Do we need to init everything ?
	var ozhmenu_uselesslinks = jQuery('#user_info p').html();
	if (ozhmenu_uselesslinks) {
		oam_adminmenu = true;
	}
})
//--><!]]></script>
$insert_main_js
JS;

}


function wp_ozh_adminmenu_css($init = true) {
	global $wp_ozh_adminmenu, $pagenow;
	
	$submenu = ($wp_ozh_adminmenu['display_submenu'] or ($pagenow == "media-upload.php") ) ? '' : '#wpwrap #submenu li {display:none;}';
	$fluency = (function_exists('wp_admin_fluency_css')) ? "#TB_overlay {z-index:99001;}\n#TB_window {z-index:99002;}" : '' ;
	if ($submenu or $fluency) echo <<<CSS
<style type="text/css">
$submenu
$fluency
</style>
CSS;

	if ($init) {
		$plugin = wp_make_link_relative(WP_PLUGIN_URL.'/'.plugin_basename(dirname(__FILE__)));
		$admin = wp_make_link_relative(get_option('siteurl') . '/wp-admin');
		$icons = $wp_ozh_adminmenu['icons'];
		echo '<link rel="stylesheet" href="'.$plugin."/adminmenu.css.php?admin=$admin&plugin=$plugin&icons=$icons\" type=\"text/css\" media=\"all\" />\n";
	}
}


function wp_ozh_adminmenu_head($init = true) {
	if ($init === '') $init = true; // $init set to '' when this func is triggered by the add_action('admin_head')
	wp_ozh_adminmenu_css($init);
	wp_ozh_adminmenu_js($init);

}


// Read plugin options or set default values
function wp_ozh_adminmenu_init() {
	global $wp_ozh_adminmenu;

	if ( !defined('WP_CONTENT_URL') )
		define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
	if ( !defined('WP_PLUGIN_URL') )
		define( 'WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins' );
	if ( !defined('WP_CONTENT_DIR') )
		define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	if ( !defined('WP_PLUGIN_DIR') )
		define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' ); // full path, no trailing slash
	

	$defaults = array(
		'display_submenu' => 0,
		'too_many_plugins' => 30,
		'toplinks' => 1,
		'icons' => 1);
		
	if (!count($wp_ozh_adminmenu)) {
		$wp_ozh_adminmenu = (array)get_option('ozh_adminmenu');
		unset($wp_ozh_adminmenu[0]);
	}
	
	$wp_ozh_adminmenu = array_merge($defaults, $wp_ozh_adminmenu);
	
	// This plugin will have its own icon of course
	add_filter( 'ozh_adminmenu_icon', 'wp_ozh_adminmenu_customicon');
}


function wp_ozh_adminmenu_customicon($in) {
	if ($in == 'ozh_admin_menu') return WP_PLUGIN_URL.'/'.plugin_basename(dirname(__FILE__)).'/images/ozh.png';
	return $in;
}

function wp_ozh_adminmenu_add_page() {
	add_options_page('Admin Drop Down Menu', 'Admin Menu', 'manage_options', 'ozh_admin_menu', 'wp_ozh_adminmenu_options_page_includes');
	add_filter( 'plugin_action_links', 'wp_ozh_adminmenu_plugin_actions', -10, 2);
}

function wp_ozh_adminmenu_options_page_includes() {
	require_once(dirname(__FILE__).'/options.php');
	wp_ozh_adminmenu_options_page();
}


function wp_ozh_adminmenu_plugin_actions($links, $file) {
	if ($file == plugin_basename(dirname(dirname(__FILE__)).'/wp_ozh_adminmenu.php'))
		$links[] = "<a href='options-general.php?page=ozh_admin_menu'><b>Settings</b></a>";
	return $links;
}



function wp_ozh_adminmenu_footer() {
	echo <<<HTML
Thank you for using <a href="http://planetozh.com/blog/my-projects/wordpress-admin-menu-drop-down-css/">Admin Drop Down Menu</a>, a wonderful plugin by <a href="http://planetozh.com/blog/">Ozh</a><br/>
HTML;
}


?>