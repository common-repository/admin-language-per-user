<?php
/**
 Plugin Name: Admin Language Per User
 Description: Lets you have your backend administration panel in english or any installed language, even if the rest of your blog is translated into another language. Language preferences can be set per user basis in user profile screen.  
 Version: 1.0.12
 Author: unclego 
 License: GPLv3
 License URI: http://www.gnu.org/licenses/gpl.html
 Tags: translation, translations, i18n, admin, english, localization, backend, language, multisite
 Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=6G9LJ5H8GHQ3S
 */

/**
 * Admin Language Per User
 * Copyright (C) 2016, Unclego
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace admin_language_per_user;
// Disallow direct HTTP access.
if (!defined('ABSPATH')) { 
	exit; 
}

require_once( ABSPATH . 'wp-admin/includes/translation-install.php' );
/**
 * @author unclego
 * @package admin_language_per_user
 */
class Admin_Language {
	/**
	 * The option name used internally.
	 *
	 * @var string
	 */
	const name = 'Admin Language Per User';
	/**
	 * The option name used internally.
	 *
	 * @var string
	 */
	const meta_name = 'admin_language';
	/**
	 * The option name used internally.
	 *
	 * @var string
	 */
	const textdomain = 'admin-language-per-user';
	/**
	 * Hooks setup 
	 */
	public static function _loader() {
		// add locale filter at proper time 
		add_action( 'plugins_loaded', array(get_class(), 'plugins_loaded') );

		// add admin language option to user profile
		add_action( 'show_user_profile', array(get_class(), 'user_profile') );
		add_action( 'edit_user_profile', array(get_class(), 'user_profile') );

		// save language option to user profile
		add_action( 'personal_options_update', array(get_class(), 'process_user_option_update') );
		add_action( 'edit_user_profile_update', array(get_class(), 'process_user_option_update') );

		// Handle user registration email locale
		// Remove locale filter before send notification email (hook with priority 5)
		add_action( 'register_new_user', array(get_class(), 'before_wp_send_new_user_notifications'), 5 );
		add_action( 'edit_user_created_user', array(get_class(), 'before_wp_send_new_user_notifications'), 5 );

		// Wordpress minimum version check
		register_activation_hook( __FILE__, array(get_class(), 'activate') );

		// cleanup
		register_uninstall_hook(__FILE__, array(get_class(), 'uninstall') );
	}
	/**
	 * Checks system requirements and exits if they are not met.
	 *
	 * This first checks to ensure minimum WordPress and PHP versions
	 * have been satisfied. If not, the plugin deactivates and exits.
	 *
	 * This prevents a parse error when activated on older versions
	 * of PHP or failures when activated on unsupported WP versions.
	 *
	 * @global $wp_version
	 *
	 * @uses $wp_version
	 * @uses deactivate_plugins()
	 * @uses plugin_basename()
	 *
	 * @return void
	 */
	public static function activate() {
		global $wp_version;
		$min_wp_version = '3.0.1';

		if ( version_compare( $min_wp_version, $wp_version ) > 0 ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( sprintf(
					__('%1$s requires at least WordPress version %2$s. You have WordPress version %3$s.', self::textdomain),
					self::name, $min_wp_version, $wp_version
					));
		}
	}
	/**
	 * Delete all custom user profile data.
	 */
	public static function uninstall() {
		foreach (get_users() as $user) {
			delete_user_meta($user->ID, self::meta_name);
		}
	}
	/**
	 * Add the inputs to the User Profile page
	 *
	 * @param WP_User $user User instance to output for.
	 */
	public static function user_profile( $user ) {
		$languages = get_available_languages();
		$translations = wp_get_available_translations();

		$locale = get_the_author_meta(self::meta_name, $user->ID);

		if( empty($locale) ) {
			$locale = get_locale();
		}

		if ( !in_array( $locale, $languages ) ) {
			$locale = '';
		}

		wp_nonce_field( self::meta_name. '_profile_update', self::meta_name . '_nonce' );

		require_once('templates/user-profile.php');
	}
	/**
	 * Updates the user metas that (might) have been set on the user profile page.
	 *
	 * @param    int $user_id of the updated user.
	 */
	public static function process_user_option_update( $user_id ) {
		$nonce_value = filter_input( INPUT_POST, self::meta_name . '_nonce' );
		if ( empty( $nonce_value ) ) { // Submit from alternate forms.
			return;
		}

		check_admin_referer( self::meta_name. '_profile_update', self::meta_name . '_nonce' );

		$language = filter_input( INPUT_POST, self::meta_name );
		if( empty($language) ) {
			// use default
			$language = 'en_US';
		}
		update_user_meta( $user_id, self::meta_name, $language );
	}
	/**
	 * Add locale filter after plugins are loaded 
	 */
	public static function plugins_loaded() {
		if( is_admin() ) {
			add_filter( 'locale', array(get_class(), 'locale') );
		}
	}
	/**
	 * Remove plugin locale filter
	 * and reload default textdomain
	 */
	public static function before_wp_send_new_user_notifications() {
		global $locale;
		// trick load_default_textdomain() to call get_locale() again
		$locale = null;

		// remove filter
		remove_filter( 'locale', array(get_class(), 'locale') );

		// and reload default textdomain
		load_default_textdomain();
	}
	/**
	 * @link 	https://codex.wordpress.org/Plugin_API/Filter_Reference/locale
	 *
	 * @param   string $locale default site locale	
	 * @return 	string new locale
	 */
	public static function locale( $locale ) {
		if( (is_admin() || self::is_tiny_mce()) && !self::is_frontend_ajax() ) {
			$admin_language = get_the_author_meta('admin_language', get_current_user_id());
			return empty($admin_language) ? $locale : $admin_language;
		}

		return $locale;
	}
	/**
	 * Frontend AJAX call check helper
	 * @internal
	 *
	 * @return boolean
	 */
	private static function is_frontend_ajax() {
		return defined( 'DOING_AJAX' ) && DOING_AJAX && false === strpos( wp_get_referer(), '/wp-admin/' );
	}
	/**
	 * TinyMCE check helper
	 * @internal
	 *
	 * @return boolean
	 */
	private static function is_tiny_mce() {
		return false !== strpos( $_SERVER['REQUEST_URI'], '/wp-includes/js/tinymce/');
	}
}

Admin_Language::_loader();