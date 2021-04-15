<?php
/**
 * Plugin Name: All Pages Redirect by Alex Lundin
 * Author:      Alex Lundin
 * Version:     0.1
 * Description: All Pages Redirect by Alex Lundin
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

require 'plugin-update-checker-4.11/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/alexlundin/asl_redirect/',
	__FILE__,
	'asl-redirect'
);

$myUpdateChecker->getVcsApi()->enableReleaseAssets();

register_activation_hook( __FILE__, 'asl_redirect_activate' );

function asl_redirect_activate() {
	global $wpdb;

	$posts_ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'page' OR post_type = 'post'" );

	$option = get_option( 'asl_settings_checkbox_name' ) ? get_option( 'asl_settings_checkbox_name' ) : null;

	foreach ( $posts_ids as $id ) {
		$wpdb->insert( $wpdb->postmeta, [
			'post_id'    => $id,
			'meta_key'   => 'asl_redirect',
			'meta_value' => $option
		] );
	}
}

register_deactivation_hook( __FILE__, 'asl_redirect_deactivate' );

function asl_redirect_deactivate() {
	global $wpdb;

	$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => 'asl_redirect' ] );
}


add_action( 'admin_init', 'asl_redirect_init' );

function asl_redirect_init() {
	add_settings_section(
		'asl_setting_sections',
		'Настройки редиректа для всего сайта, кроме главной страницы',
		'',
		'reading'
	);

//	add_settings_field(
//		'asl_settings_checkbox_name',
//		'Включить редирект?',
//		'asl_settings_callback_check',
//		'reading',
//		'asl_setting_sections'
//	);

	add_settings_field(
		'asl_setting_name2',
		'URL (с https://)',
		'asl_settings_callback_input',
		'reading',
		'asl_setting_sections'
	);

	register_setting( 'reading', 'asl_settings_checkbox_name' );
	register_setting( 'reading', 'asl_setting_name2' );
}

function asl_settings_callback_check() {
	echo '<input 
		name="asl_settings_checkbox_name" 
		type="checkbox" 
		' . checked( 1, get_option( 'asl_settings_checkbox_name' ), false ) . ' 
		value="1" 
		class="code" 
	/>';
}

function asl_settings_callback_input() {
	echo '<input 
		name="asl_setting_name2"  
		type="text" 
		value="' . get_option( 'asl_setting_name2' ) . '" 
		class="code2"
	 />';
}

function asl_redirect_meta_boxes() {
	add_meta_box( 'asl_redirect_box', 'Настройки редиректа', 'asl_redir_box', array(
		'post',
		'page'
	), 'side', 'high' );
}

add_action( 'admin_menu', 'asl_redirect_meta_boxes' );


/*
 * Этап 2. Заполнение
 */
function asl_redir_box( $post ) {
	wp_nonce_field( basename( __FILE__ ), 'asl_redirect_metabox_nonce' );
	$html = '<label><input type="checkbox" name="noindex"';
	$html .= 'value="1"';
	$html .= ( get_post_meta( $post->ID, 'asl_redirect', true ) == '1' ) ? ' checked="checked"' : '';
	$html .= ' /> Включить редирект страницы?</label>';
	echo $html;
}

/*
 * Этап 3. Сохранение
 */
function asl_redirect_save_box_data( $post_id ) {
	// проверяем, пришёл ли запрос со страницы с метабоксом
	if ( ! isset( $_POST['asl_redirect_metabox_nonce'] )
	     || ! wp_verify_nonce( $_POST['asl_redirect_metabox_nonce'], basename( __FILE__ ) ) ) {
		return $post_id;
	}
	// проверяем, является ли запрос автосохранением
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return $post_id;
	}
	// проверяем, права пользователя, может ли он редактировать записи
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}
	// теперь также проверим тип записи
	$post = get_post( $post_id );
	if ( $post->post_type == 'post' || $post->post_type == 'page' ) { // укажите собственный
		update_post_meta( $post_id, 'asl_redirect', $_POST['noindex'] );
	}

	return $post_id;
}

add_action( 'save_post', 'asl_redirect_save_box_data' );

if ( get_option( 'asl_settings_checkbox_name' ) == 1 ) {
	add_action( 'template_redirect', function () {
		$new = str_replace( get_home_url(), get_option( 'asl_setting_name2' ), get_permalink() );
		$new = str_replace( "//", "/", $new );
		if ( ! is_front_page() ) {
			wp_redirect( $new, 301 );
			exit;
		}
	} );
}

add_action( 'template_redirect', function () {
	global $post;
	$post_id = $post->ID;
	if ( get_post_meta( $post_id, 'asl_redirect', true ) == 1 ) {
		$new = get_option( 'asl_setting_name2' ) . '/' . get_post( $post_id )->post_name;
		$new = str_replace( "//", "/", $new );

		if ( is_page( $post_id ) ) {
			wp_redirect( $new, 301 );
			exit();
		}
	}
} );

$val = array();

$posts = get_posts( array(
	'numberposts'      => - 1,
	'meta_key'         => 'asl_redirect',
	'post_type'        => [ 'post', 'page' ],
	'suppress_filters' => true, // подавление работы фильтров изменения SQL запроса
) );

foreach ( $posts as $post ) {
	setup_postdata( $post );
	if ( get_option( 'page_on_front' ) != $post->ID ) {

		$val[$post->ID] = get_post_meta( $post->ID, 'asl_redirect', true );
//		if ( get_option( 'asl_settings_checkbox_name' ) == 1 && ( get_option( 'page_on_front' ) != $post->ID ) ) {
//			global $wpdb;
//			$wpdb->update( $wpdb->postmeta, [ 'meta_value' => 1 ], [ 'meta_key' => 'asl_redirect' ], '%d' );
//
//		} elseif ( get_option( 'asl_settings_checkbox_name' ) == null ) {
//			global $wpdb;
//			$wpdb->update( $wpdb->postmeta, [ 'meta_value' => null ], [ 'meta_key' => 'asl_redirect' ], '%d' );
//
//		} else

		if ( get_post_meta( $post->ID, 'asl_redirect', true ) == 1 ) {

//			global $wpdb;
//			$wpdb->update( $wpdb->options, [ 'option_value' => 1 ], [ 'option_name' => 'asl_settings_checkbox_name' ], '%d' );
		} elseif ( get_post_meta( $post->ID, 'asl_redirect', true ) == null ) {

//			global $wpdb;
//			$wpdb->update( $wpdb->postmeta, [ 'meta_value' => null ], [ 'meta_key' => 'asl_redirect' ], '%d' );
		}
	}


}

if ( get_option( 'page_on_front' ) == $post->ID ) {
	unset( $val[ $post->ID ] );
}

if (in_array(null, $val, true)) {
	$wpdb->update( $wpdb->options, [ 'option_value' => null ], [ 'option_name' => 'asl_settings_checkbox_name' ], '%d' );
} else {
	$wpdb->update( $wpdb->options, [ 'option_value' => 1 ], [ 'option_name' => 'asl_settings_checkbox_name' ], '%d' );
}

wp_reset_postdata(); // сброс