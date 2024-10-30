<?php
/*
Plugin Name: CrossPost to Friendika
Plugin URI: http://blog.duthied.com/2011/09/12/friendika-cross-poster-wordpress-plugin/
Description: This plugin allows you to cross post to your Friendika account.
Version: 1.2
Author: Devlon Duthie
Author URI: http://blog.duthied.com
*/

/*  Copyright 2011 Devlon Duthie (email: duthied@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define("xpost_to_friendika_path", WP_PLUGIN_URL . "/" . str_replace(basename( __FILE__), "", plugin_basename(__FILE__)));
define("xpost_to_friendika_version", "1.2");
$plugin_dir = basename(dirname(__FILE__));
$plugin = plugin_basename(__FILE__); 

define("xpost_to_friendika_acct_name", "xpost_to_friendika_admin_options");

function xpost_to_friendika_deactivate() {
	delete_option(xpost_to_friendika_seed_location);
	delete_option(xpost_to_friendika_acct_name);
	delete_option(xpost_to_friendika_user_name);
	delete_option(xpost_to_friendika_password);
}

function xpost_to_friendika_get_seed_location() {
	return get_option(xpost_to_friendika_seed_location);
}

function xpost_to_friendika_get_acct_name() {
	return get_option(xpost_to_friendika_acct_name);
}

function xpost_to_friendika_get_user_name() {
	return get_option(xpost_to_friendika_user_name);
}

function xpost_to_friendika_get_password() {
	return get_option(xpost_to_friendika_password);
}

function xpost_to_friendika_post($post_id) {
	
	$post = get_post($post_id);
	
	// if meta has been set
	if (get_post_meta($post_id, "xpost_to_friendika", true) == 1) {
		
		$user_name = xpost_to_friendika_get_user_name();
		$password = xpost_to_friendika_get_password();
		$seed_location = xpost_to_friendika_get_seed_location();
		
		if ((isset($user_name)) && (isset($password)) && (isset($seed_location))) {
			// remove potential comments
			$message = preg_replace('/<!--(.*)-->/Uis', '', $post->post_content);

			// get any tags and make them hashtags
			$post_tags = get_the_tags($post_id);
			if ($post_tags) {
				foreach($post_tags as $tag) {
			    	$tag_string .= "#" . $tag->name . " "; 
			  	}
			}

			$message = $post->post_title . "<br /><br />" . $message;

			$message .= "<br /><br />permalink: " . $post->guid;

			if (isset($tag_string)) {
				$message .=  "<br />$tag_string";	
			}

			$bbcode = xpost_to_html2bbcode($message);
			
			$url = "http://" . $seed_location . '/api/statuses/update';
			
			$headers = array('Authorization' => 'Basic '.base64_encode("$user_name:$password"));
			$body = array('status' => $bbcode);
			
			// post:
			$request = new WP_Http;
			$result = $request->request($url , array( 'method' => 'POST', 'body' => $body, 'headers' => $headers));
		}
		
	}
}

function xpost_to_friendika_displayAdminContent() {
	
	$seed_url = xpost_to_friendika_get_seed_location();
	$password = xpost_to_friendika_get_password();
	$user_acct = xpost_to_friendika_get_acct_name();
	
	// debug...
	// echo "seed location: $seed_url</br>";
	// echo "password: $password</br>";
	// echo "user_acct: $user_acct</br>";
	
	echo <<<EOF
	<div class='wrap'>
		<h2>CrossPost to Friendika</h2>
		<p>This plugin allows you to cross post to your Friendika account.</p>
	</div>
	
	<div class="wrap">
		<h2>Configuration</h2>
		<form method="post" action="{$_SERVER["REQUEST_URI"]}">
			Enter the email address of the Friendika Account that you want to cross-post to:(example: user@friendika.org)<br /><br />
			<input type="text" name="xpost_to_friendika_acct_name" value="{$user_acct}"/> &nbsp;
			<input type="password" name="xpost_to_friendika_password" value="{$password}"/> &nbsp;
			<input type="submit" value="Save" name="submit" />
		</form>
		<p></p>
	</div>
EOF;

	if(isset($_POST['submit']))	{
		echo "<div style='text-align:center;padding:4px;width:200px;background-color:#FFFF99;border:1xp solid #CCCCCC;color:#000000;'>Settings Saved!</div>";
	}
}

function xpost_to_friendika_post_checkbox() {
    add_meta_box(
        'xpost_to_friendika_meta_box_id', 
        'Cross Post to Friendika',
        'xpost_to_friendika_post_meta_content',
        'post',
        'normal',
        'default'
    );
}

function xpost_to_friendika_post_meta_content($post_id) {
    wp_nonce_field(plugin_basename( __FILE__ ), 'xpost_to_friendika_nonce');
    echo '<input type="checkbox" name="xpost_to_friendika" value="1" /> Cross post?';
}

function xpost_to_friendika_post_field_data($post_id) {

    // check if this isn't an auto save
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;

    // security check
    if (!wp_verify_nonce( $_POST['xpost_to_friendika_nonce'], plugin_basename( __FILE__ )))
        return;

    // now store data in custom fields based on checkboxes selected
    if (isset($_POST['xpost_to_friendika'])) {
		update_post_meta($post_id, 'xpost_to_friendika', 1);
	} else {
		update_post_meta($post_id, 'xpost_to_friendika', 0);
	}
}

function xpost_to_friendika_display_admin_page() {
	
	if ((isset($_REQUEST["xpost_to_friendika_acct_name"])) && (isset($_REQUEST["xpost_to_friendika_password"]))) {
		
		$password = $_REQUEST["xpost_to_friendika_password"];
		
		$tmp_account_array = explode("@", $_REQUEST["xpost_to_friendika_acct_name"]);
		if (isset($tmp_account_array[1])) {
			$username = $tmp_account_array[0];
			$hostname = $tmp_account_array[1];
		} else {
			unset($tmp_account_array);
		}
		
		update_option(xpost_to_friendika_acct_name, $_REQUEST["xpost_to_friendika_acct_name"]);
		update_option(xpost_to_friendika_user_name, $username);
		update_option(xpost_to_friendika_seed_location, $hostname);
		update_option(xpost_to_friendika_password, $password);
		
	}
	
	xpost_to_friendika_displayAdminContent();
}

function xpost_to_friendika_settings_link($links) { 
	$settings_link = '<a href="options-general.php?page=xpost-to-friendika">Settings</a>'; 
  	array_unshift($links, $settings_link); 
  	return $links; 
}

function xpost_to_friendika_admin() {
	add_options_page("CrossPost to Friendika", "CrossPost to Friendika", "manage_options", "xpost-to-friendika", "xpost_to_friendika_display_admin_page");
}

register_deactivation_hook( __FILE__, 'xpost_to_friendika_deactivate' );

add_filter("plugin_action_links_$plugin", "xpost_to_friendika_settings_link");

add_action("admin_menu", "xpost_to_friendika_admin");
add_action('publish_post', 'xpost_to_friendika_post');
add_action('add_meta_boxes', 'xpost_to_friendika_post_checkbox');
add_action('save_post', 'xpost_to_friendika_post_field_data');

// from:
// http://www.docgate.com/tutorial/php/how-to-convert-html-to-bbcode-with-php-script.html
function xpost_to_html2bbcode($text) {
	$htmltags = array(
		'/\<b\>(.*?)\<\/b\>/is',
		'/\<i\>(.*?)\<\/i\>/is',
		'/\<u\>(.*?)\<\/u\>/is',
		'/\<ul.*?\>(.*?)\<\/ul\>/is',
		'/\<li\>(.*?)\<\/li\>/is',
		'/\<img(.*?) src=\"(.*?)\" alt=\"(.*?)\" title=\"Smile(y?)\" \/\>/is',		// some smiley
		'/\<img(.*?) src=\"http:\/\/(.*?)\" (.*?)\>/is',
		'/\<img(.*?) src=\"(.*?)\" alt=\":(.*?)\" .*? \/\>/is',						// some smiley
		'/\<div class=\"quotecontent\"\>(.*?)\<\/div\>/is',	
		'/\<div class=\"codecontent\"\>(.*?)\<\/div\>/is',	
		'/\<div class=\"quotetitle\"\>(.*?)\<\/div\>/is',	
		'/\<div class=\"codetitle\"\>(.*?)\<\/div\>/is',
		'/\<cite.*?\>(.*?)\<\/cite\>/is',
		'/\<blockquote.*?\>(.*?)\<\/blockquote\>/is',
		'/\<div\>(.*?)\<\/div\>/is',
		'/\<code\>(.*?)\<\/code\>/is',
		'/\<br(.*?)\>/is',
		'/\<strong\>(.*?)\<\/strong\>/is',
		'/\<em\>(.*?)\<\/em\>/is',
		'/\<a href=\"mailto:(.*?)\"(.*?)\>(.*?)\<\/a\>/is',
		'/\<a .*?href=\"(.*?)\"(.*?)\>http:\/\/(.*?)\<\/a\>/is',
		'/\<a .*?href=\"(.*?)\"(.*?)\>(.*?)\<\/a\>/is'
	);

	$bbtags = array(
		'[b]$1[/b]',
		'[i]$1[/i]',
		'[u]$1[/u]',
		'[list]$1[/list]',
		'[*]$1',
		'$3',
		'[img]http://$2[/img]',
		':$3',
		'\[quote\]$1\[/quote\]',
		'\[code\]$1\[/code\]',
		'',
		'',
		'',
		'\[quote\]$1\[/quote\]',
		'$1',
		'\[code\]$1\[/code\]',
		"\n",
		'[b]$1[/b]',
		'[i]$1[/i]',
		'[email=$1]$3[/email]',
		'[url]$1[/url]',
		'[url=$1]$3[/url]'
	);

	$text = str_replace ("\n", ' ', $text);
	$ntext = preg_replace ($htmltags, $bbtags, $text);
	$ntext = preg_replace ($htmltags, $bbtags, $ntext);

	// for too large text and cannot handle by str_replace
	if (!$ntext) {
		$ntext = str_replace(array('<br>', '<br />'), "\n", $text);
		$ntext = str_replace(array('<strong>', '</strong>'), array('[b]', '[/b]'), $ntext);
		$ntext = str_replace(array('<em>', '</em>'), array('[i]', '[/i]'), $ntext);
	}

	$ntext = strip_tags($ntext);
	
	$ntext = trim(html_entity_decode($ntext,ENT_QUOTES,'UTF-8'));
	return $ntext;
}