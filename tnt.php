<?php
/**
 * Plugin Name: Tiny News Ticker (TNT)
 * Plugin URI: https://github.com/quimo/tnt.git
 * Description: Semplice news ticker che recupera dati da una fonte json e li mostra in un widget scorrevole.
 * Version: 1.0.0
 * Author: Scuola Mohole
 * Author URI: https://scuola.mohole.it
 */

// termino l'esecuzione se il plugin è richiamato direttamente
if (!defined('WPINC')) die;

// inclusioni
include_once plugin_dir_path( __FILE__ ) . 'inc/tnt-settings-page.php';

// costanti
define('TNT_TEMPLATES_FOLDER', plugin_dir_path( __FILE__ ) . 'assets/templates');

// attivazione e disattivazione del plugin
register_activation_hook( __FILE__, 'tnt_activate' );
function tnt_activate(){
    tnt_add_settings();
};
register_deactivation_hook( __FILE__, 'tnt_deactivate' );
function tnt_deactivate(){
    tnt_remove_settings();
};

// registrazione di stili e script
add_action('wp_enqueue_scripts', 'tnt_init');
function tnt_init() {
    wp_enqueue_style( 'tnt', plugin_dir_url( __FILE__ ) . 'assets/css/tnt.css' , array(), '1');
    if (!is_admin()) {
        wp_deregister_script('jquery');
        wp_register_script('jquery', "https://code.jquery.com/jquery-3.3.1.min.js", false, '3.3.1', true);
    }
    wp_enqueue_script( 'marquee', plugin_dir_url( __FILE__ ) . 'js/jquery.marquee.min.js', array('jquery'), '1', true );
    wp_enqueue_script( 'tnt', plugin_dir_url( __FILE__ ) . 'js/tnt.js', array('jquery', 'marquee'), '1', true );
    //creo un oggetto init_ajax in cui salvare il percorso dell'endpoint ajax di WordPress
	wp_localize_script('init', 'init_ajax', array( 'url' => admin_url( 'admin-ajax.php' )));
}

/**
 * aggiunta dello scortcode
 * esempi:
 * [tnt]
 * [tnt template="tnt-mod"]
 */
add_action('init', function(){
    add_shortcode('tnt', 'tnt_render_shortcode');
});

// impostazione dello shortcode e chiamata al rendering
function tnt_render_shortcode($atts, $content = null) {
    extract(shortcode_atts(
        array(
            'template' => '',
        ),
        $atts,
        'tnt_render_shortcode'
    ));
    return tnt_render($template);
}

/**
 * tnt_render
 * funzione che assembla il widget: dati + template
 */
function tnt_render($template) {
    // recupero il template
    $template = ($template) ? $template : get_option('tnt_data_source_selected');
    $tmpl_url = TNT_TEMPLATES_FOLDER . '/' . $template.'.html';
    $template_content = file_get_contents($tmpl_url); 
    $template_repeated_section = tnt_get_string_between($template_content, '[+tnt+]', '[+/tnt+]');
    // recupero i dati
    $data = tnt_get_data($template);
    if ($data) {
        $html = '';
        for ($i = 0; $i < count($data); $i++) {
            $dummy = $template_repeated_section;
            foreach ($data[$i] as $key => $value) {
                $dummy = str_replace('[+' . $key . '+]', $value, $dummy);
            }
            $html .= $dummy;
        }
        // salvo il widget nel buffer e lo ritorno
        ob_start();
        echo str_replace('[+tnt+]' . $template_repeated_section . '[+/tnt+]', $html, $template_content);
        $saved = ob_get_contents();
        ob_end_clean();
        return $saved;
    }   
    return false;
}

/**
 * tnt_get_string_between
 * funzione di servizio che estrae una sottostringa da una stringa
 */
function tnt_get_string_between($string, $start, $end) {
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

/**
 * funzione che recupera la fonte dati json
 */
function tnt_get_data($template) {
    if ($template) {
        // cerco la fonte associata al template
        $datasources = get_option('tnt_data_source');
        for ($i = 0; $i < count($datasources); $i++) {
            if ($datasources[$i]['template'] == $template) {
                $data = file_get_contents($datasources[$i]['source']);
                return json_decode($data);
            }
        }
    } else {
        // recupero la fonte di default
        echo get_option('tnt_data_source_selected');
        $data = file_get_contents(get_option('tnt_data_source_selected'));
        return json_decode($data);
    }
}

// aggiorno la fonte delle informazioni
add_action( 'wp_ajax_tnt_refresh_data_source', 'tnt_refresh_data_source' );
add_action( 'wp_ajax_nopriv_tnt_refresh_data_source', 'tnt_refresh_data_source' );
function tnt_refresh_data_source() {
    die();
}