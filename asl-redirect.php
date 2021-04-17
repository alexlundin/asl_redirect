<?php
/**
 * Plugin Name: All Pages Redirect by Alex Lundin
 * Author:      Alex Lundin
 * Version:     1.2.12
 * Description: All Pages Redirect by Alex Lundin
 * License:     GPL2
 * Text Domain: asl-redirect
 * Domain Path: /lang
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'asl-redirect', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
} );

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

/*
 * Создание метабокса
 */
function asl_redirect_meta_boxes() {
	add_meta_box( 'asl_redirect_box', __( 'Настройки редиректа', 'asl-redirect' ), 'asl_redir_box', array(
		'post',
		'page'
	), 'side', 'high' );
}

add_action( 'admin_menu', 'asl_redirect_meta_boxes' );


function asl_redir_box( $post ) {
	wp_nonce_field( basename( __FILE__ ), 'asl_redirect_metabox_nonce' );
	$html = '<label><input type="checkbox" name="noindex"';
	$html .= 'value="1"';
	$html .= ( get_post_meta( $post->ID, 'asl_redirect', true ) == '1' ) ? ' checked="checked"' : '';
	$html .= ' />';
	$html .= __( 'Включить редирект страницы?', 'asl-redirect' );
	$html .= '</label>';
	echo $html;
}


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

/*
 * Создание страницы настроек
 */
$asl_page = 'asl-redirect-page.php';

function asl_redirect_options() {
	global $asl_page;
	add_options_page( __( 'Настройки редиректа', 'asl-redirect' ), __( 'Настройки редиректа', 'asl-redirect' ), 'manage_options', $asl_page, 'asl_redirect_page' );
}

add_action( 'admin_menu', 'asl_redirect_options' );

function asl_redirect_page() {
	global $asl_page;
	?>
    <div class="wrap">
    <form method="post" enctype="multipart/form-data" action="options.php">
		<?php
		settings_fields( 'asl_redirect_options' ); // меняем под себя только здесь (название настроек)
		do_settings_sections( $asl_page );
		?>
        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e( 'Сохранить', 'asl-redirect' ) ?>"/>
        </p>
    </form>
    </div><?php
}

function asl_redirect_settings() {
	global $asl_page;
	register_setting( 'asl_redirect_options', 'asl_redirect_options', 'asl_redirect_validate' );

	add_settings_section( 'asl_redirect_section', __( 'Параметры редиректа', 'asl-redirect' ), '', $asl_page );

	$asl_redirect_fields = array(
		'type'      => 'text', // тип
		'id'        => 'asl_redirect_uri',
		'desc'      => __( 'Ссылка на который будет происходить редирект', 'asl-redirect' ),
		'label_for' => 'asl_redirect_uri'
	);
	add_settings_field( 'asl_redirect_url', __( 'URL для редиректа', 'asl-redirect' ), 'asl_redirect_display_settings', $asl_page, 'asl_redirect_section', $asl_redirect_fields );


	$asl_redirect_fields = array(
		'type' => 'radio',
		'id'   => 'asl_redirect_radio',
		'vals' => array(
			'all'         => __( 'Редирект всего сайта', 'asl-redirect' ),
			'except_main' => __( 'Редирект всего сайта кроме главной страницы', 'asl-redirect' ),
			'paginate'    => __( 'Постраничный редирект', 'asl-redirect' )
		)
	);
	add_settings_field( 'asl_redirect_radio', __( 'Варианты редиректа', 'asl-redirect' ), 'asl_redirect_display_settings', $asl_page, 'asl_redirect_section', $asl_redirect_fields );

}

add_action( 'admin_init', 'asl_redirect_settings' );

function asl_redirect_display_settings( $args ) {
	extract( $args );

	$option_name = 'asl_redirect_options';

	$o = get_option( $option_name );

	switch ( $type ) {
		case 'text':
			$o[ $id ] = esc_attr( stripslashes( $o[ $id ] ) );
			echo "<input class='regular-text' type='text' id='$id' name='" . $option_name . "[$id]' value='$o[$id]' />";
			echo ( $desc != '' ) ? "<br /><span class='description'>$desc</span>" : "";
			break;
		case 'radio':
			echo "<fieldset>";
			foreach ( $vals as $v => $l ) {
				$checked = ( $o[ $id ] == $v ) ? "checked='checked'" : '';
				echo "<label><input type='radio' name='" . $option_name . "[$id]' value='$v' $checked />$l</label><br />";
			}
			echo "</fieldset>";
			break;
	}
}

function asl_redirect_validate( $input ) {
	foreach ( $input as $k => $v ) {
		$valid_input[ $k ] = trim( $v );

		/* Вы можете включить в эту функцию различные проверки значений, например
		if(! задаем условие ) { // если не выполняется
			$valid_input[$k] = ''; // тогда присваиваем значению пустую строку
		}
		*/
	}

	return $valid_input;
}

global $post;

$all_options   = get_option( 'asl_redirect_options' );
$radio_options = $all_options['asl_redirect_radio'];

$address = $all_options['asl_redirect_uri'];
$address = ( gettype( strpos( $address, 'https://' ) ) == "integer" || gettype( strpos( $address, 'http://' ) ) == "integer" ) ? $address : 'https://' . $address;

switch ( $radio_options ) {
	case( 'all' ):
		add_action( 'template_redirect', function () use ( $post, $address ) {

			$paginate_address = substr( $address, - 1 ) === '/' ? $address . get_post( $post->ID )->post_name : $address . '/' . get_post( $post->ID )->post_name;
			if ( ! is_front_page() ) {
				wp_redirect( $paginate_address, 301 );
			} else {
				wp_redirect( $address, 301 );
			}
			exit;
		} );
		break;
	case( 'except_main' ):
		add_action( 'template_redirect', function () use ( $post, $address ) {
			$paginate_address = substr( $address, - 1 ) === '/' ? $address . get_post( $post->ID )->post_name : $address . '/' . get_post( $post->ID )->post_name;
			if ( ! is_front_page() ) {
				wp_redirect( $paginate_address, 301 );
				exit;
			}
		} );
		break;
	case( 'paginate' ):
		add_action( 'template_redirect', function () use ( $address ) {
			global $post;
			$paginate_address = substr( $address, - 1 ) === '/' ? $address . get_post( $post->ID )->post_name : $address . '/' . get_post( $post->ID )->post_name;
			if ( get_post_meta( $post->ID, 'asl_redirect', true ) == 1 ) {
				wp_redirect( $paginate_address, 301 );
				exit();
			}
		} );
		break;
}
wp_reset_postdata();