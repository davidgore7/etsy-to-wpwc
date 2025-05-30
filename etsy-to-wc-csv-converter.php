<?php
/**
 * Plugin Name:       Etsy to WooCommerce CSV Converter & Importer Helper
 * Description:       Convierte un CSV de Etsy al formato de WooCommerce y facilita el acceso al importador.
 * Version:           1.2.4
 * Author:            Tu Nombre / Gemini AI
 * Author URI:        https://yourwebsite.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       etsy-to-wc-csv-converter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

add_action( 'admin_menu', 'etwc_add_admin_menu' );
add_action( 'admin_init', 'etwc_handle_form_actions' );

function etwc_handle_form_actions() {
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'etsy-to-wc-converter' &&
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

        // Acción para el botón "Convertir y Descargar CSV de WooCommerce"
        if ( isset( $_POST['submit_download_csv'] ) ) {
            if ( isset( $_FILES['etsy_csv_file'] ) && $_FILES['etsy_csv_file']['error'] == UPLOAD_ERR_OK ) {
                $file_tmp_path = $_FILES['etsy_csv_file']['tmp_name'];
                $file_name = sanitize_file_name( $_FILES['etsy_csv_file']['name'] );
                $file_ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

                if ( $file_ext === 'csv' ) {
                    etwc_process_and_download_csv( $file_tmp_path, $ignore_stock ); // Esta función llama a exit()
                    // Si la función retorna (error), la redirección de abajo se encargará.
                } else {
                     set_transient('etwc_admin_error_message', 'Error: Por favor, sube un archivo CSV válido para convertir.', 60);
                }
            } else {
                // Manejar errores de subida de archivo si se intentó esta acción
                $error_message = 'Error: Debes seleccionar un archivo CSV para convertir y descargar.';
                 if (isset($_FILES['etsy_csv_file']['error']) && $_FILES['etsy_csv_file']['error'] !== UPLOAD_ERR_OK && $_FILES['etsy_csv_file']['error'] !== UPLOAD_ERR_NO_FILE) { // UPLOAD_ERR_NO_FILE es "normal" si no se seleccionó.
                    switch ($_FILES['etsy_csv_file']['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE: 
                            $error_message = 'El archivo subido excede el tamaño máximo permitido.'; 
                            break;
                        default: 
                            $error_message = 'Error desconocido al subir el archivo. Código: ' . $_FILES['etsy_csv_file']['error'];
                    }
                }
                set_transient('etwc_admin_error_message', $error_message, 60);
            }
        } 
        // Acción para el botón "Ir al Importador de WooCommerce"
        elseif ( isset( $_POST['submit_go_to_wc_importer'] ) ) {
            $importer_url = admin_url( 'edit.php?post_type=product&page=product_importer' );
            // Alternativa para versiones más nuevas con WC Admin:
            // $importer_url = admin_url( 'admin.php?page=wc-admin&path=%2Fproduct-import' );
            wp_safe_redirect( $importer_url );
            exit;
        }
        
        // Si llegamos aquí (y no se hizo exit), redirigir para mostrar mensajes.
        wp_safe_redirect( admin_url( 'tools.php?page=etsy-to-wc-converter' ) );
        exit;
    }
}

function etwc_add_admin_menu() {
    add_submenu_page(
        'tools.php',
        'Etsy to WooCommerce Tool',
        'Etsy to WC Tool',
        'manage_options',
        'etsy-to-wc-converter',
        'etwc_admin_page_html'
    );
}

function etwc_admin_page_html() {
    ob_start(); 
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
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

// La función etwc_prepare_for_wc_native_import() ha sido eliminada.

function etwc_generate_woocommerce_csv_data( $etsy_csv_filepath, $ignore_stock = false ) {
    $digital_product_tags = [
        "STL_file_download", "3D_print_sculpture", "digital_3D_model",
        "model_for_painting", "3D_print_figurine", "digital_sculpture",
        "diy_project", "free_stl"
    ];

    $woocommerce_columns = [ 
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
                foreach ( $digital_product_tags as $digital_tag ) {
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

function etwc_process_and_download_csv( $etsy_csv_filepath, $ignore_stock = false ) {
    $processed_data = etwc_generate_woocommerce_csv_data( $etsy_csv_filepath, $ignore_stock );

    if ( empty( $processed_data['products'] ) ) {
         set_transient('etwc_admin_error_message', 'No se pudieron procesar productos del archivo CSV de Etsy o el archivo estaba vacío. No se generó el CSV para descarga.', 60);
         return; 
    }
    
    if (ob_get_length() > 0) { 
        ob_end_clean(); 
    }

    if (headers_sent($file_sent, $line_sent)) { 
        error_log("ETWC Plugin: Headers already sent in $file_sent on line $line_sent when trying to download CSV.");
        set_transient('etwc_admin_error_message', 'Error interno: No se pudo iniciar la descarga (headers already sent). Por favor, revisa los logs del servidor.', 60);
        return; 
    }
    
    $output_filename = 'wc-product_output_from_plugin.csv';
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $output_filename . '"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    $output_handle = fopen( 'php://output', 'w' );
    if ($output_handle === false) {
        error_log("ETWC Plugin: No se pudo abrir php://output para la descarga del CSV.");
        exit; 
    }

    fputcsv( $output_handle, $processed_data['headers'] );
    foreach ( $processed_data['products'] as $product_row ) {
        $ordered_row = [];
        foreach($processed_data['headers'] as $col_name) { 
            $ordered_row[] = isset($product_row[$col_name]) ? $product_row[$col_name] : '';
        }
        fputcsv( $output_handle, $ordered_row );
    }
    fclose( $output_handle );
    exit; 
}
?>