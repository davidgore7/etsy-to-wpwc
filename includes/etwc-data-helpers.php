<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Devuelve la definición de las columnas de WooCommerce.
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

/**
 * Lógica principal para generar los datos del CSV de WooCommerce desde el archivo de Etsy.
 */
function etwc_generate_woocommerce_csv_data( $etsy_csv_filepath, $ignore_stock = false ) {
    $digital_product_tags_definition = [ 
        "STL_file_download", "3D_print_sculpture", "digital_3D_model",
        "model_for_painting", "3D_print_figurine", "digital_sculpture",
        "diy_project", "free_stl"
    ];
    $woocommerce_columns = etwc_get_woocommerce_columns_definition();
    $processed_products_data = [];
    $etsy_headers = [];

    if ( !file_exists($etsy_csv_filepath) || !is_readable($etsy_csv_filepath)) {
        error_log("ETWC Plugin: No se puede leer el archivo CSV de Etsy en: " . $etsy_csv_filepath);
        return ['headers' => $woocommerce_columns, 'products' => []];
    }

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
             // Asegurar que no haya problemas si una fila tiene más columnas de las esperadas, aunque fgetcsv debería manejarlo.
             // Si hay menos, array_combine fallará. La comprobación de count() ya lo maneja.
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
                if ( isset( $etsy_row[$img_col] ) && !empty( trim($etsy_row[$img_col]) ) ) { $image_urls[] = trim( $etsy_row[$img_col] ); }
            }
            $wc_product_row['Images'] = implode( ',', $image_urls );

            $etsy_tags_str = isset($etsy_row['TAGS']) ? trim($etsy_row['TAGS']) : '';
            $wc_product_row['Tags'] = $etsy_tags_str;
            
            $is_digital_product = false;
            if ( !empty($etsy_tags_str) ) {
                $product_tags_list = array_map( 'trim', explode( ',', strtolower( $etsy_tags_str ) ) );
                foreach ( $digital_product_tags_definition as $digital_tag ) {
                    if ( in_array( strtolower( $digital_tag ), $product_tags_list ) ) { $is_digital_product = true; break; }
                }
            }
            
            $var1_name = isset($etsy_row['VARIATION 1 NAME']) ? trim($etsy_row['VARIATION 1 NAME']) : '';
            $var1_values = isset($etsy_row['VARIATION 1 VALUES']) ? trim($etsy_row['VARIATION 1 VALUES']) : '';
            $var2_name = isset($etsy_row['VARIATION 2 NAME']) ? trim($etsy_row['VARIATION 2 NAME']) : '';
            $var2_values = isset($etsy_row['VARIATION 2 VALUES']) ? trim($etsy_row['VARIATION 2 VALUES']) : '';

            if ( !empty($var1_name) ) {
                $wc_product_row['Type'] = 'variable';
                $wc_product_row['Attribute 1 name'] = $var1_name; $wc_product_row['Attribute 1 value(s)'] = $var1_values;
                $wc_product_row['Attribute 1 visible'] = '1'; $wc_product_row['Attribute 1 global'] = '1';
                if ( !empty($var2_name) ) {
                    $wc_product_row['Attribute 2 name'] = $var2_name; $wc_product_row['Attribute 2 value(s)'] = $var2_values;
                    $wc_product_row['Attribute 2 visible'] = '1'; $wc_product_row['Attribute 2 global'] = '1';
                }
            } else { $wc_product_row['Type'] = 'simple'; }

            $etsy_quantity_str = isset($etsy_row['QUANTITY']) ? trim($etsy_row['QUANTITY']) : '';
            $etsy_quantity = 0;
            if (ctype_digit($etsy_quantity_str)) { $etsy_quantity = intval($etsy_quantity_str); }

            if ( $is_digital_product ) {
                $wc_product_row['Downloadable'] = '1'; $wc_product_row['Virtual'] = '1';
            } else {
                $wc_product_row['Downloadable'] = '0'; $wc_product_row['Virtual'] = '0';
            }

            if ( $ignore_stock ) {
                $wc_product_row['Stock'] = ''; $wc_product_row['In stock?'] = '1';
            } else { 
                if ( $is_digital_product ) {
                    $wc_product_row['Stock'] = ''; $wc_product_row['In stock?'] = '1'; 
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
    } else {
        error_log("ETWC Plugin: No se pudo abrir el archivo CSV de Etsy en: " . $etsy_csv_filepath);
    }
    return ['headers' => $woocommerce_columns, 'products' => $processed_products_data];
}


/**
 * Placeholder: Simula la obtención de listados de la API de Etsy.
 */
function etwc_fetch_etsy_listings_via_api_placeholder($api_credentials) {
    $client_id = isset($api_credentials['client_id']) ? $api_credentials['client_id'] : null;
    $access_token = isset($api_credentials['access_token']) ? $api_credentials['access_token'] : null;

    if (empty($access_token)) {
        // En una implementación real, aquí intentarías refrescar el token o indicarías al usuario que re-autorice.
        error_log("ETWC API Placeholder: Se intentó obtener listados sin un access_token (simulado).");
        // Para la simulación, podríamos devolver un error o seguir con datos de ejemplo.
        // Devolver un array vacío si no hay token simulado puede ser más realista para el flujo de error.
        // return []; 
    }
     // Devolvemos los mismos datos de ejemplo:
    return [
        [
            'listing_id' => 123, 'title' => 'Producto API de Ejemplo 1 (Taza)', 'description' => 'Descripción API taza.',
            'price' => ['amount' => 1500, 'divisor' => 100, 'currency_code' => 'USD'], 'quantity' => 10,
            'sku' => ['SKU-API-001'], 'tags' => ['ejemplo', 'api', 'taza'], 'materials' => ['ceramica'],
            'images' => [['url_fullxfull' => 'https://via.placeholder.com/150/FF0000/FFFFFF?Text=Taza1.jpg'], ['url_fullxfull' => 'https://via.placeholder.com/150/00FF00/FFFFFF?Text=Taza2.jpg']],
        ],
        [
            'listing_id' => 124, 'title' => 'Producto API de Ejemplo 2 (Camiseta Digital)', 'description' => 'Diseño digital API.',
            'price' => ['amount' => 500, 'divisor' => 100, 'currency_code' => 'USD'], 'quantity' => 999,
            'sku' => ['SKU-API-002'], 'is_digital' => true, 'tags' => ['digital', 'camiseta', 'STL_file_download'],
            'images' => [['url_fullxfull' => 'https://via.placeholder.com/150/0000FF/FFFFFF?Text=Digital1.jpg']],
        ]
    ];
}

/**
 * Placeholder: Transforma los datos "API" de ejemplo al formato WooCommerce CSV.
 */
function etwc_transform_api_data_to_wc_format_placeholder($api_listings, $ignore_stock = false) {
    $digital_product_tags_definition = [
        "STL_file_download", "3D_print_sculpture", "digital_3D_model", "model_for_painting", 
        "3D_print_figurine", "digital_sculpture", "diy_project", "free_stl"
    ];
    $woocommerce_columns = etwc_get_woocommerce_columns_definition();
    $processed_products = [];

    if (!is_array($api_listings)) { // Comprobación por si $api_listings no es un array
        return ['headers' => $woocommerce_columns, 'products' => []];
    }

    foreach ($api_listings as $listing) {
        $wc_product_row = array_fill_keys( $woocommerce_columns, '' );
        $wc_product_row['Name'] = isset($listing['title']) ? trim($listing['title']) : '';
        $wc_product_row['Description'] = isset($listing['description']) ? trim($listing['description']) : '';
        $wc_product_row['Short description'] = $wc_product_row['Description'];
        if (isset($listing['price']['amount']) && isset($listing['price']['divisor']) && is_numeric($listing['price']['amount']) && is_numeric($listing['price']['divisor']) && $listing['price']['divisor'] != 0) {
            $wc_product_row['Regular price'] = number_format($listing['price']['amount'] / $listing['price']['divisor'], 2, '.', '');
        } else {
            $wc_product_row['Regular price'] = '0.00';
        }
        $wc_product_row['SKU'] = isset($listing['sku'][0]) ? trim($listing['sku'][0]) : ('API-' . (isset($listing['listing_id']) ? $listing['listing_id'] : uniqid()));
        
        $image_urls = [];
        if (!empty($listing['images']) && is_array($listing['images'])) {
            foreach ($listing['images'] as $img_obj) {
                if (is_array($img_obj) && !empty($img_obj['url_fullxfull'])) { $image_urls[] = $img_obj['url_fullxfull']; }
            }
        }
        $wc_product_row['Images'] = implode(',', $image_urls);
        $wc_product_row['Tags'] = isset($listing['tags']) && is_array($listing['tags']) ? implode(',', $listing['tags']) : '';
        
        $is_digital_product = !empty($listing['is_digital']);
        if (!$is_digital_product && !empty($listing['tags']) && is_array($listing['tags'])) {
            foreach ($digital_product_tags_definition as $digital_tag) {
                if (in_array(strtolower($digital_tag), array_map('strtolower', $listing['tags']))) {
                    $is_digital_product = true; break;
                }
            }
        }
        $etsy_quantity = isset($listing['quantity']) ? intval($listing['quantity']) : 0;
        $etsy_quantity_str = isset($listing['quantity']) ? strval($listing['quantity']) : '';

        if ($is_digital_product) {
            $wc_product_row['Downloadable'] = '1'; $wc_product_row['Virtual'] = '1';
        } else {
            $wc_product_row['Downloadable'] = '0'; $wc_product_row['Virtual'] = '0';
        }
        if ($ignore_stock) {
            $wc_product_row['Stock'] = ''; $wc_product_row['In stock?'] = '1';
        } else {
            if ($is_digital_product) {
                $wc_product_row['Stock'] = ''; $wc_product_row['In stock?'] = '1';
            } else {
                $wc_product_row['Stock'] = $etsy_quantity_str;
                $wc_product_row['In stock?'] = ($etsy_quantity > 0) ? '1' : '0';
            }
        }
        $wc_product_row['Type'] = 'simple'; $wc_product_row['Published'] = '1';
        $wc_product_row['Is featured?'] = '0'; $wc_product_row['Visibility in catalog'] = 'visible';
        $wc_product_row['Tax status'] = 'taxable';
        $processed_products[] = $wc_product_row;
    }
    return ['headers' => $woocommerce_columns, 'products' => $processed_products];
}
?>