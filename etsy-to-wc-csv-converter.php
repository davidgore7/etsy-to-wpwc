<?php
/**
 * Plugin Name:       Etsy to WooCommerce CSV Converter & Importer Helper
 * Description:       Convierte un CSV de Etsy al formato de WooCommerce, facilita el acceso al importador, y prepara la estructura para importación vía API.
 * Version:           1.3.0
 * Author:            Tu Nombre / Gemini AI
 * Author URI:        https://yourwebsite.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       etsy-to-wc-csv-converter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// --- SECCIÓN EXISTENTE: MANEJO DE CSV SUBIDOS (SIN CAMBIOS SIGNIFICATIVOS) ---

add_action( 'admin_menu', 'etwc_add_admin_menu' );
add_action( 'admin_init', 'etwc_handle_csv_upload_form_actions' ); // Renombrado para claridad

function etwc_handle_csv_upload_form_actions() { // Anteriormente etwc_handle_form_actions
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'etsy-to-wc-converter' && // Pagina CSV
         'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['etwc_nonce_field'] ) ) {

        if ( ! wp_verify_nonce( $_POST['etwc_nonce_field'], 'etwc_action_nonce' ) ) {
            set_transient('etwc_admin_error_message', 'Error de seguridad (nonce inválido). Inténtalo de nuevo.', 60);
            wp_safe_redirect( admin_url( 'tools.php?page=etsy-to-wc-converter' ) );
            exit;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            set_transient('etwc_admin_error_message', 'No tienes permisos para realizar esta acción.', 60);
            wp_safe_redirect( admin_url( 'tools.php?page=etsy-to-wc-converter' ) );
            exit;
        }

        $ignore_stock = isset( $_POST['ignore_stock'] ) && $_POST['ignore_stock'] === '1';

        if ( isset( $_POST['submit_download_csv'] ) ) {
            if ( isset( $_FILES['etsy_csv_file'] ) && $_FILES['etsy_csv_file']['error'] == UPLOAD_ERR_OK ) {
                $file_tmp_path = $_FILES['etsy_csv_file']['tmp_name'];
                $file_name = sanitize_file_name( $_FILES['etsy_csv_file']['name'] );
                $file_ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

                if ( $file_ext === 'csv' ) {
                    etwc_process_and_download_csv( $file_tmp_path, $ignore_stock ); 
                } else {
                     set_transient('etwc_admin_error_message', 'Error: Por favor, sube un archivo CSV válido para convertir.', 60);
                }
            } else {
                $error_message = 'Error: Debes seleccionar un archivo CSV para convertir y descargar.';
                 if (isset($_FILES['etsy_csv_file']['error']) && $_FILES['etsy_csv_file']['error'] !== UPLOAD_ERR_OK && $_FILES['etsy_csv_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                    switch ($_FILES['etsy_csv_file']['error']) {
                        case UPLOAD_ERR_INI_SIZE: case UPLOAD_ERR_FORM_SIZE: 
                            $error_message = 'El archivo subido excede el tamaño máximo permitido.'; break;
                        default: 
                            $error_message = 'Error desconocido al subir el archivo. Código: ' . $_FILES['etsy_csv_file']['error'];
                    }
                }
                set_transient('etwc_admin_error_message', $error_message, 60);
            }
        } 
        elseif ( isset( $_POST['submit_go_to_wc_importer'] ) ) {
            $importer_url = admin_url( 'edit.php?post_type=product&page=product_importer' );
            wp_safe_redirect( $importer_url );
            exit;
        }
        
        wp_safe_redirect( admin_url( 'tools.php?page=etsy-to-wc-converter' ) );
        exit;
    }
}

// --- FIN SECCIÓN EXISTENTE ---


// --- NUEVA SECCIÓN: IMPORTACIÓN VÍA API DE ETSY (ESTRUCTURA Y PLACEHOLDERS) ---

// Hook para manejar acciones de la nueva página API (si las hubiera en el futuro, como guardar claves)
add_action( 'admin_init', 'etwc_handle_api_page_actions' );

/**
 * Añade los submenús del plugin.
 */
function etwc_add_admin_menu() {
    // Submenú existente para la subida de CSV
    add_submenu_page(
        'tools.php',
        'Etsy CSV Tool', // Título de la página
        'Etsy CSV Tool', // Título del menú
        'manage_options',
        'etsy-to-wc-converter', // Slug para la página de CSV
        'etwc_csv_upload_page_html' // Función para renderizar la página de CSV
    );

    // Nuevo submenú para la importación vía API
    add_submenu_page(
        'tools.php',
        'Etsy API Import (Beta)',    // Título de la página
        'Etsy API Import',         // Título del menú
        'manage_options',
        'etsy-api-importer',       // Slug para la nueva página API
        'etwc_api_import_page_html'  // Función para renderizar la página API
    );
}

/**
 * Renderiza la página de administración para la subida de CSV (anteriormente etwc_admin_page_html).
 */
function etwc_csv_upload_page_html() { // Renombrada para claridad
    ob_start(); 
    ?>
    <div class="wrap">
        <h1>Conversor de CSV de Etsy a WooCommerce</h1>
        <p>Sube tu archivo CSV de Etsy (EtsyListingsDownload).</p>
        <p><b>Paso 1:</b> Convierte tu CSV de Etsy y descárgalo en formato WooCommerce.<br>
           <b>Paso 2:</b> Ve al importador de WooCommerce y sube el archivo generado.</p>
        
        <?php
        if ( $message = get_transient( 'etwc_admin_notice_message' ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
            delete_transient( 'etwc_admin_notice_message' );
        }
        if ( $error_message = get_transient( 'etwc_admin_error_message' ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_message ) . '</p></div>';
            delete_transient( 'etwc_admin_error_message' );
        }
        ?>

        <form method="POST" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'tools.php?page=etsy-to-wc-converter' ) ); ?>">
            <?php wp_nonce_field( 'etwc_action_nonce', 'etwc_nonce_field' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="etsy_csv_file">Archivo CSV de Etsy (para convertir):</label>
                    </th>
                    <td>
                        <input type="file" id="etsy_csv_file" name="etsy_csv_file" accept=".csv">
                        <p class="description">Selecciona el archivo CSV si vas a usar el botón "Convertir y Descargar".</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="ignore_stock">Opciones de Stock (para convertir):</label>
                    </th>
                    <td>
                        <fieldset>
                            <label for="ignore_stock">
                                <input type="checkbox" id="ignore_stock" name="ignore_stock" value="1">
                                Ignorar gestión de stock (establecer productos como "en stock" sin cantidad específica en el archivo convertido)
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            <p>
                <button type="submit" name="submit_download_csv" class="button button-primary">Convertir y Descargar CSV de WooCommerce</button>
                <button type="submit" name="submit_go_to_wc_importer" class="button button-secondary">Ir al Importador de WooCommerce</button>
            </p>
        </form>
    </div>
    <?php
    $html_output = ob_get_clean();
    echo $html_output;
}


/**
 * Renderiza la nueva página de administración para la importación vía API de Etsy.
 */
function etwc_api_import_page_html() {
    ob_start();
    ?>
    <div class="wrap">
        <h1>Importar Productos desde Etsy vía API (Estructura Inicial)</h1>
        <p>Esta sección es una estructura inicial para la futura importación de productos directamente desde la API de Etsy.</p>
        <p><strong>Nota:</strong> La conexión real con la API de Etsy y la importación de datos aún no están implementadas. La autenticación con Etsy (OAuth 2.0) es un paso complejo que se requiere antes.</p>

        <?php 
        if ( $message = get_transient( 'etwc_api_admin_notice_message' ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
            delete_transient( 'etwc_api_admin_notice_message' );
        }
        if ( $error_message = get_transient( 'etwc_api_admin_error_message' ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_message ) . '</p></div>';
            delete_transient( 'etwc_api_admin_error_message' );
        }
        
        $api_settings = get_option('etwc_api_settings', ['keystring' => '', 'shared_secret' => '']);
        ?>

        <form method="POST" action="<?php echo esc_url( admin_url( 'tools.php?page=etsy-api-importer' ) ); ?>">
            <?php wp_nonce_field( 'etwc_api_action_nonce', 'etwc_api_nonce_field' ); ?>
            
            <h2>Configuración API de Etsy (Placeholder)</h2>
            <p>Aquí irían los campos para las credenciales de tu aplicación de Etsy (Client ID, Client Secret) y el botón para iniciar la autorización OAuth 2.0.</p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="etwc_api_keystring">Etsy App Keystring (Client ID):</label></th>
                    <td><input type="text" id="etwc_api_keystring" name="etwc_api_keystring" value="<?php echo esc_attr($api_settings['keystring']); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="etwc_api_shared_secret">Etsy App Shared Secret (Client Secret):</label></th>
                    <td><input type="text" id="etwc_api_shared_secret" name="etwc_api_shared_secret" value="<?php echo esc_attr($api_settings['shared_secret']); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <p>
                <button type="submit" name="etwc_save_api_settings" class="button">Guardar Configuración (Placeholder)</button>
                <button type="button" class="button" disabled>Conectar con Etsy (OAuth - No implementado)</button>
            </p>

            <hr>
            <h2>Proceso de Importación (Simulado)</h2>
            <p>Este botón simulará la obtención de datos de Etsy API y generará un CSV para descargar.</p>
            <p>
                 <label for="api_ignore_stock">
                    <input type="checkbox" id="api_ignore_stock" name="api_ignore_stock" value="1">
                    Ignorar gestión de stock para productos importados vía API (si se implementara)
                </label>
            </p>
            <p>
                <button type="submit" name="etwc_fetch_and_generate_csv_from_api" class="button button-primary">Obtener de API y Generar CSV (Simulado)</button>
            </p>
        </form>
    </div>
    <?php
    $html_output = ob_get_clean();
    echo $html_output;
}

/**
 * Maneja las acciones de la página de API (guardar settings, iniciar importación simulada).
 */
function etwc_handle_api_page_actions() {
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'etsy-api-importer' &&
         'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['etwc_api_nonce_field'] ) ) {

        if ( ! wp_verify_nonce( $_POST['etwc_api_nonce_field'], 'etwc_api_action_nonce' ) ) {
            set_transient('etwc_api_admin_error_message', 'Error de seguridad (nonce inválido).', 60);
            wp_safe_redirect( admin_url( 'tools.php?page=etsy-api-importer' ) );
            exit;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            set_transient('etwc_api_admin_error_message', 'No tienes permisos para esta acción.', 60);
            wp_safe_redirect( admin_url( 'tools.php?page=etsy-api-importer' ) );
            exit;
        }

        if ( isset( $_POST['etwc_save_api_settings'] ) ) {
            $keystring = isset($_POST['etwc_api_keystring']) ? sanitize_text_field($_POST['etwc_api_keystring']) : '';
            $shared_secret = isset($_POST['etwc_api_shared_secret']) ? sanitize_text_field($_POST['etwc_api_shared_secret']) : '';
            update_option('etwc_api_settings', ['keystring' => $keystring, 'shared_secret' => $shared_secret]);
            set_transient('etwc_api_admin_notice_message', 'Configuración API (placeholder) guardada.', 60);
        }

        if ( isset( $_POST['etwc_fetch_and_generate_csv_from_api'] ) ) {
            // Aquí es donde llamarías a la lógica real de la API en el futuro.
            // Por ahora, usaremos datos de ejemplo.
            $mock_api_listings = etwc_fetch_etsy_listings_via_api_placeholder();
            
            if (empty($mock_api_listings)) {
                set_transient('etwc_api_admin_error_message', 'No se obtuvieron datos (simulados) de la API de Etsy.', 60);
            } else {
                $ignore_stock_api = isset( $_POST['api_ignore_stock'] ) && $_POST['api_ignore_stock'] === '1';
                $processed_api_data = etwc_transform_api_data_to_wc_format_placeholder($mock_api_listings, $ignore_stock_api);
                
                // Usar una función genérica para descargar el CSV
                etwc_download_data_as_csv($processed_api_data, 'wc_products_from_api_placeholder.csv');
                // La función de descarga llama a exit(), por lo que no es necesario redirigir aquí si tiene éxito.
            }
        }
        
        wp_safe_redirect( admin_url( 'tools.php?page=etsy-api-importer' ) );
        exit;
    }
}

/**
 * Placeholder: Simula la obtención de listados de la API de Etsy.
 * En una implementación real, esto haría llamadas HTTP a la API de Etsy.
 * @return array Array de listados de ejemplo.
 */
function etwc_fetch_etsy_listings_via_api_placeholder() {
    // Estos son datos de ejemplo que simulan lo que podría devolver la API de Etsy.
    // La estructura real de la API V3 de Etsy será diferente.
    return [
        [
            'listing_id' => 123,
            'title' => 'Producto API de Ejemplo 1 (Taza)',
            'description' => 'Esta es una descripción detallada para la taza de ejemplo obtenida vía API.',
            'price' => ['amount' => 1500, 'divisor' => 100, 'currency_code' => 'USD'], // Precio $15.00
            'quantity' => 10,
            'sku' => ['SKU-API-001'], // SKU es un array en la API v3
            'tags' => ['ejemplo', 'api', 'taza'],
            'materials' => ['ceramica', 'pintura'],
            'images' => [ // Simulación de objetos de imagen
                ['url_fullxfull' => 'https://i.etsystatic.com/ejemplo/imagen1_fullxfull.jpg'],
                ['url_fullxfull' => 'https://i.etsystatic.com/ejemplo/imagen2_fullxfull.jpg']
            ],
            'variations' => [ // Simulación de variaciones (esto es complejo en la API real)
                // Ejemplo: Un atributo "Color" con opciones "Rojo", "Azul"
                // La API V3 tiene un sistema de "inventory" para esto.
            ]
        ],
        [
            'listing_id' => 124,
            'title' => 'Producto API de Ejemplo 2 (Camiseta Digital)',
            'description' => 'Diseño digital para camiseta, obtenido vía API.',
            'price' => ['amount' => 500, 'divisor' => 100, 'currency_code' => 'USD'], // Precio $5.00
            'quantity' => 999, // Típico para digitales
            'sku' => ['SKU-API-002'],
            'is_digital' => true, // Indicador de producto digital
            'tags' => ['digital', 'camiseta', 'diseño', 'STL_file_download'], // Añadido tag digital
            'images' => [
                ['url_fullxfull' => 'https://i.etsystatic.com/ejemplo/imagen_digital_fullxfull.jpg']
            ]
        ]
    ];
}

/**
 * Placeholder: Transforma los datos "API" de ejemplo al formato que espera etwc_generate_woocommerce_csv_data.
 * @param array $api_listings Array de listados de la API (simulados).
 * @param bool  $ignore_stock Si se debe ignorar el stock.
 * @return array Datos procesados listos para CSV ['headers' => [], 'products' => []].
 */
function etwc_transform_api_data_to_wc_format_placeholder($api_listings, $ignore_stock = false) {
    // Reutilizar la definición de columnas y tags digitales de la función existente.
    // Esto es una simplificación; en la realidad, el mapeo sería más complejo.
    $digital_product_tags_definition = [ // Copiado de etwc_generate_woocommerce_csv_data
        "STL_file_download", "3D_print_sculpture", "digital_3D_model",
        "model_for_painting", "3D_print_figurine", "digital_sculpture",
        "diy_project", "free_stl"
    ];
    $woocommerce_columns = etwc_get_woocommerce_columns_definition(); // Usar una función para obtener las columnas

    $processed_products = [];

    foreach ($api_listings as $listing) {
        $wc_product_row = array_fill_keys( $woocommerce_columns, '' );

        $wc_product_row['Name'] = isset($listing['title']) ? trim($listing['title']) : '';
        $wc_product_row['Description'] = isset($listing['description']) ? trim($listing['description']) : '';
        $wc_product_row['Short description'] = $wc_product_row['Description']; // Simplificación

        if (isset($listing['price']['amount']) && isset($listing['price']['divisor'])) {
            $wc_product_row['Regular price'] = $listing['price']['amount'] / $listing['price']['divisor'];
        }
        // SKU en API V3 es un array, tomamos el primero si existe.
        $wc_product_row['SKU'] = isset($listing['sku'][0]) ? trim($listing['sku'][0]) : ('API-' . $listing['listing_id']);


        $image_urls = [];
        if (!empty($listing['images'])) {
            foreach ($listing['images'] as $img_obj) {
                if (!empty($img_obj['url_fullxfull'])) {
                    $image_urls[] = $img_obj['url_fullxfull'];
                }
            }
        }
        $wc_product_row['Images'] = implode(',', $image_urls);

        $wc_product_row['Tags'] = isset($listing['tags']) ? implode(',', $listing['tags']) : '';
        
        // Detección de producto digital (simulada)
        $is_digital_product = !empty($listing['is_digital']);
        if (!$is_digital_product && !empty($listing['tags'])) { // Comprobar también por tags
            foreach ($digital_product_tags_definition as $digital_tag) {
                if (in_array(strtolower($digital_tag), array_map('strtolower', $listing['tags']))) {
                    $is_digital_product = true;
                    break;
                }
            }
        }

        // Lógica de stock (simplificada para el placeholder)
        $etsy_quantity = isset($listing['quantity']) ? intval($listing['quantity']) : 0;
        $etsy_quantity_str = isset($listing['quantity']) ? strval($listing['quantity']) : '';

        if ($is_digital_product) {
            $wc_product_row['Downloadable'] = '1';
            $wc_product_row['Virtual'] = '1';
        } else {
            $wc_product_row['Downloadable'] = '0';
            $wc_product_row['Virtual'] = '0';
        }

        if ($ignore_stock) {
            $wc_product_row['Stock'] = '';
            $wc_product_row['In stock?'] = '1';
        } else {
            if ($is_digital_product) {
                $wc_product_row['Stock'] = '';
                $wc_product_row['In stock?'] = '1';
            } else {
                $wc_product_row['Stock'] = $etsy_quantity_str;
                $wc_product_row['In stock?'] = ($etsy_quantity > 0) ? '1' : '0';
            }
        }
        
        // Valores por defecto (simplificado)
        $wc_product_row['Type'] = 'simple'; // La gestión de variaciones API es compleja, omitida aquí
        $wc_product_row['Published'] = '1';
        $wc_product_row['Is featured?'] = '0';
        $wc_product_row['Visibility in catalog'] = 'visible';
        $wc_product_row['Tax status'] = 'taxable';
        // ... otros campos por defecto ...

        $processed_products[] = $wc_product_row;
    }

    return ['headers' => $woocommerce_columns, 'products' => $processed_products];
}


/**
 * Función genérica para descargar un array de datos como CSV.
 * @param array $data_to_download ['headers' => [], 'products' => []]
 * @param string $filename Nombre del archivo para la descarga.
 */
function etwc_download_data_as_csv($data_to_download, $filename = 'downloaded_data.csv') {
    if (empty($data_to_download['products'])) {
        set_transient('etwc_api_admin_error_message', 'No hay datos procesados para descargar como CSV.', 60);
        // La redirección se manejará en la función que llama a esta.
        return; 
    }

    if (ob_get_length() > 0) {
        ob_end_clean();
    }

    if (headers_sent($file_sent, $line_sent)) {
        error_log("ETWC Plugin (API Download): Headers already sent in $file_sent on line $line_sent.");
        set_transient('etwc_api_admin_error_message', 'Error interno al descargar CSV (headers sent).', 60);
        return;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output_handle = fopen('php://output', 'w');
    if ($output_handle === false) {
        error_log("ETWC Plugin (API Download): No se pudo abrir php://output.");
        exit;
    }

    fputcsv($output_handle, $data_to_download['headers']);
    foreach ($data_to_download['products'] as $row) {
        $ordered_row = [];
        foreach($data_to_download['headers'] as $col_name) { 
            $ordered_row[] = isset($row[$col_name]) ? $row[$col_name] : '';
        }
        fputcsv($output_handle, $ordered_row);
    }
    fclose($output_handle);
    exit;
}

/**
 * Devuelve la definición de las columnas de WooCommerce.
 * Centralizado para ser usado por múltiples funciones.
 * @return array
 */
function etwc_get_woocommerce_columns_definition() {
    return [
        'ID', 'Type', 'SKU', 'GTIN, UPC, EAN, or ISBN', 'Name', 'Published',
        'Is featured?', 'Visibility in catalog', 'Short description', 'Description',
        'Date sale price starts', 'Date sale price ends', 'Tax status', 'Tax class',
        'In stock?', 'Stock', 'Low stock amount', 'Backorders allowed?',
        'Sold individually?', 'Weight (kg)', 'Length (cm)', 'Width (cm)',
        'Height (cm)', 'Allow customer reviews?', 'Purchase note', 'Sale price',
        'Regular price', 'Categories', 'Tags', 'Shipping class', 'Images',
        'Download limit', 'Download expiry days', 'Parent', 'Grouped products',
        'Upsells', 'Cross-sells', 'External URL', 'Button text', 'Position', 'Brands',
        'Attribute 1 name', 'Attribute 1 value(s)', 'Attribute 1 visible', 'Attribute 1 global',
        'Attribute 2 name', 'Attribute 2 value(s)', 'Attribute 2 visible', 'Attribute 2 global',
        'Meta: _ppcp_button_position', 'Meta: rank_math_internal_links_processed',
        'Meta: site-sidebar-layout', 'Meta: ast-site-content-layout',
        'Meta: site-content-style', 'Meta: site-sidebar-style',
        'Meta: theme-transparent-header-meta', 'Meta: rank_math_analytic_object_id',
        'Meta: rank_math_seo_score', 'Meta: rank_math_primary_product_cat',
        'Meta: rank_math_title', 'Meta: rank_math_focus_keyword',
        'Meta: rank_math_pillar_content', 'Meta: _wc_gla_visibility',
        'Meta: _wc_gla_synced_at', 'Meta: _wc_gla_sync_status',
        'Meta: _wc_gla_mc_status', 'Meta: astra-migrate-meta-layouts',
        'Meta: _wcml_custom_prices_status', 'Meta: _aioseo_og_article_section',
        'Downloadable', 'Virtual',
        'Download 1 ID', 'Download 1 name', 'Download 1 URL'
    ];
}


// --- FUNCIONES EXISTENTES PARA MANEJO DE CSV (SE MANTIENEN) ---

/**
 * Lógica principal para generar los datos del CSV de WooCommerce desde el archivo de Etsy.
 */
function etwc_generate_woocommerce_csv_data( $etsy_csv_filepath, $ignore_stock = false ) {
    $digital_product_tags_definition = [ // Nombre de variable más específico
        "STL_file_download", "3D_print_sculpture", "digital_3D_model",
        "model_for_painting", "3D_print_figurine", "digital_sculpture",
        "diy_project", "free_stl"
    ];

    $woocommerce_columns = etwc_get_woocommerce_columns_definition(); // Usar la función centralizada

    $processed_products_data = [];
    $etsy_headers = [];

    if ( ( $handle = fopen( $etsy_csv_filepath, 'r' ) ) !== false ) {
        if ( ( $header_row = fgetcsv( $handle ) ) !== false ) {
            $etsy_headers = array_map('trim', $header_row);
        } else {
            fclose( $handle );
            return ['headers' => $woocommerce_columns, 'products' => []]; 
        }

        while ( ( $etsy_row_raw = fgetcsv( $handle ) ) !== false ) {
             if (empty(array_filter($etsy_row_raw)) || count($etsy_row_raw) !== count($etsy_headers)) { 
                 continue; 
             }
             $etsy_row = array_combine($etsy_headers, $etsy_row_raw);
             if (empty($etsy_row)) continue;

            $wc_product_row = array_fill_keys( $woocommerce_columns, '' );

            $wc_product_row['Name'] = isset($etsy_row['TITLE']) ? trim($etsy_row['TITLE']) : '';
            $description_content = isset($etsy_row['DESCRIPTION']) ? trim($etsy_row['DESCRIPTION']) : '';
            $wc_product_row['Description'] = $description_content;
            $wc_product_row['Short description'] = $description_content;
            $wc_product_row['Regular price'] = isset($etsy_row['PRICE']) ? trim($etsy_row['PRICE']) : '';
            $wc_product_row['SKU'] = isset($etsy_row['SKU']) ? trim($etsy_row['SKU']) : '';

            $image_urls = [];
            for ( $i = 1; $i <= 10; $i++ ) {
                $img_col = "IMAGE{$i}";
                if ( isset( $etsy_row[$img_col] ) && !empty( trim($etsy_row[$img_col]) ) ) {
                    $image_urls[] = trim( $etsy_row[$img_col] );
                }
            }
            $wc_product_row['Images'] = implode( ',', $image_urls );

            $etsy_tags_str = isset($etsy_row['TAGS']) ? trim($etsy_row['TAGS']) : '';
            $wc_product_row['Tags'] = $etsy_tags_str;
            
            $is_digital_product = false;
            if ( !empty($etsy_tags_str) ) {
                $product_tags_list = array_map( 'trim', explode( ',', strtolower( $etsy_tags_str ) ) );
                foreach ( $digital_product_tags_definition as $digital_tag ) { // Usar la variable con nombre específico
                    if ( in_array( strtolower( $digital_tag ), $product_tags_list ) ) {
                        $is_digital_product = true;
                        break;
                    }
                }
            }
            
            $var1_name = isset($etsy_row['VARIATION 1 NAME']) ? trim($etsy_row['VARIATION 1 NAME']) : '';
            $var1_values = isset($etsy_row['VARIATION 1 VALUES']) ? trim($etsy_row['VARIATION 1 VALUES']) : '';
            $var2_name = isset($etsy_row['VARIATION 2 NAME']) ? trim($etsy_row['VARIATION 2 NAME']) : '';
            $var2_values = isset($etsy_row['VARIATION 2 VALUES']) ? trim($etsy_row['VARIATION 2 VALUES']) : '';

            if ( !empty($var1_name) ) {
                $wc_product_row['Type'] = 'variable';
                $wc_product_row['Attribute 1 name'] = $var1_name;
                $wc_product_row['Attribute 1 value(s)'] = $var1_values;
                $wc_product_row['Attribute 1 visible'] = '1';
                $wc_product_row['Attribute 1 global'] = '1';
                if ( !empty($var2_name) ) {
                    $wc_product_row['Attribute 2 name'] = $var2_name;
                    $wc_product_row['Attribute 2 value(s)'] = $var2_values;
                    $wc_product_row['Attribute 2 visible'] = '1';
                    $wc_product_row['Attribute 2 global'] = '1';
                }
            } else {
                $wc_product_row['Type'] = 'simple';
            }

            $etsy_quantity_str = isset($etsy_row['QUANTITY']) ? trim($etsy_row['QUANTITY']) : '';
            $etsy_quantity = 0;
            if (ctype_digit($etsy_quantity_str)) { $etsy_quantity = intval($etsy_quantity_str); }

            if ( $is_digital_product ) {
                $wc_product_row['Downloadable'] = '1';
                $wc_product_row['Virtual'] = '1';
            } else {
                $wc_product_row['Downloadable'] = '0';
                $wc_product_row['Virtual'] = '0';
            }

            if ( $ignore_stock ) {
                $wc_product_row['Stock'] = '';
                $wc_product_row['In stock?'] = '1';
            } else { 
                if ( $is_digital_product ) {
                    $wc_product_row['Stock'] = ''; 
                    $wc_product_row['In stock?'] = '1'; 
                } else {
                    $wc_product_row['Stock'] = $etsy_quantity_str;
                    $wc_product_row['In stock?'] = ( $etsy_quantity > 0 ) ? '1' : '0';
                }
            }
            
            $wc_product_row['ID'] = ''; $wc_product_row['Published'] = '1'; $wc_product_row['Is featured?'] = '0';
            $wc_product_row['Visibility in catalog'] = 'visible'; $wc_product_row['Tax status'] = 'taxable';
            $wc_product_row['Backorders allowed?'] = '0'; $wc_product_row['Sold individually?'] = '0';
            $wc_product_row['Allow customer reviews?'] = '1'; $wc_product_row['Position'] = '0';

            $processed_products_data[] = $wc_product_row;
        }
        fclose( $handle );
    }
    return ['headers' => $woocommerce_columns, 'products' => $processed_products_data];
}

/**
 * Procesa el CSV de Etsy y genera la descarga del CSV de WooCommerce.
 */
function etwc_process_and_download_csv( $etsy_csv_filepath, $ignore_stock = false ) {
    $processed_data = etwc_generate_woocommerce_csv_data( $etsy_csv_filepath, $ignore_stock );

    if ( empty( $processed_data['products'] ) ) {
         set_transient('etwc_admin_error_message', 'No se pudieron procesar productos del archivo CSV de Etsy o el archivo estaba vacío. No se generó el CSV para descarga.', 60);
         return; 
    }
    
    // Usar la función genérica de descarga
    etwc_download_data_as_csv($processed_data, 'wc-product_output_from_plugin.csv');
}

?>