<?php
/**
 * Plugin Name:       Etsy to WooCommerce Converter & Importer Helper
 * Plugin URI:        https://example.com/etsy-to-woocommerce-converter
 * Description:       Convierte un CSV de Etsy al formato de WooCommerce, facilita el acceso al importador, y prepara la estructura para importación vía API.
 * Version:           1.3.2-multifile
 * Author:            Tu Nombre / Gemini AI
 * Author URI:        https://yourwebsite.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       etsy-to-wc-csv-converter
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Definir constantes del plugin (opcional pero útil)
define( 'ETWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ETWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ETWC_VERSION', '1.3.2-multifile' );

// Incluir los archivos de funciones
require_once ETWC_PLUGIN_DIR . 'includes/etwc-admin-pages.php';
require_once ETWC_PLUGIN_DIR . 'includes/etwc-form-handlers.php';
require_once ETWC_PLUGIN_DIR . 'includes/etwc-data-helpers.php';
require_once ETWC_PLUGIN_DIR . 'includes/etwc-csv-operations.php';

// Registrar hooks principales
add_action( 'admin_menu', 'etwc_add_admin_menu' ); // Esta función estará en etwc-admin-pages.php
add_action( 'admin_init', 'etwc_handle_csv_upload_form_actions' ); // Estará en etwc-form-handlers.php
add_action( 'admin_init', 'etwc_handle_api_page_actions_and_oauth' ); // Estará en etwc-form-handlers.php

?>