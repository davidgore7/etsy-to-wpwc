<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Procesa el CSV de Etsy subido y genera la descarga del CSV de WooCommerce.
 * Llamada desde etwc_handle_csv_upload_form_actions.
 */
function etwc_process_and_download_csv( $etsy_csv_filepath, $ignore_stock = false ) {
    $processed_data = etwc_generate_woocommerce_csv_data( $etsy_csv_filepath, $ignore_stock ); // Esta función está en etwc-data-helpers.php

    if ( empty( $processed_data['products'] ) ) {
         set_transient('etwc_admin_error_message', 'No se pudieron procesar productos del archivo CSV de Etsy o el archivo estaba vacío. No se generó el CSV para descarga.', 60);
         return; // El manejador principal POST (etwc_handle_csv_upload_form_actions) se encargará de la redirección.
    }
    
    // Usar la función genérica de descarga
    etwc_download_data_as_csv($processed_data, 'wc-product_output_from_plugin.csv');
    // etwc_download_data_as_csv llama a exit()
}


/**
 * Función genérica para descargar un array de datos como CSV.
 * @param array $data_to_download ['headers' => [], 'products' => []]
 * @param string $filename Nombre del archivo para la descarga.
 */
function etwc_download_data_as_csv($data_to_download, $filename = 'downloaded_data.csv') {
    if (empty($data_to_download['products'])) {
        // Este transient podría ser específico para la página API si se llama desde allí
        if (isset($_GET['page']) && $_GET['page'] === 'etsy-api-importer') {
            set_transient('etwc_api_admin_error_message', 'No hay datos procesados para descargar como CSV.', 60);
        } else {
            set_transient('etwc_admin_error_message', 'No hay datos procesados para descargar como CSV.', 60);
        }
        return; // El manejador que llamó a esta función se encargará de la redirección.
    }

    // Limpiar cualquier búfer de salida.
    if (ob_get_length() > 0) { // Verifica si hay contenido en el buffer
        ob_end_clean(); // Descarta el contenido del buffer y lo desactiva.
    }

    // Verificar si las cabeceras ya han sido enviadas.
    if (headers_sent($file_sent, $line_sent)) { 
        error_log("ETWC Plugin (Download Function): Headers already sent in $file_sent on line $line_sent. Cannot initiate download for $filename.");
        if (isset($_GET['page']) && $_GET['page'] === 'etsy-api-importer') {
            set_transient('etwc_api_admin_error_message', 'Error interno al descargar CSV (headers sent).', 60);
        } else {
            set_transient('etwc_admin_error_message', 'Error interno al descargar CSV (headers sent).', 60);
        }
        return; // El manejador que llamó a esta función se encargará de la redirección.
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output_handle = fopen('php://output', 'w');
    if ($output_handle === false) {
        error_log("ETWC Plugin (Download Function): No se pudo abrir php://output para la descarga del CSV: $filename.");
        exit; // Salir si no se puede escribir en el flujo de salida.
    }

    fputcsv($output_handle, $data_to_download['headers']);
    foreach ($data_to_download['products'] as $row) {
        $ordered_row = [];
        // Asegurar que las columnas se escriben en el orden de los encabezados
        foreach($data_to_download['headers'] as $col_name) { 
            $ordered_row[] = isset($row[$col_name]) ? $row[$col_name] : '';
        }
        fputcsv($output_handle, $ordered_row);
    }
    fclose($output_handle);
    exit; // IMPORTANTE: Terminar la ejecución para asegurar que solo se envíe el CSV.
}
?>