<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Maneja las acciones del formulario de subida de CSV.
 */
function etwc_handle_csv_upload_form_actions() {
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'etsy-to-wc-converter' && // Página CSV
         'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['etwc_nonce_field'] ) ) { // Nonce de la página CSV

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


/**
 * Maneja las acciones de la página de API, incluyendo el inicio del flujo OAuth y el callback.
 */
function etwc_handle_api_page_actions_and_oauth() {
    if ( !isset( $_GET['page'] ) || $_GET['page'] !== 'etsy-api-importer' ) {
        return; // Solo actuar en nuestra página de API
    }

    // --- Manejo del Callback de OAuth (petición GET) ---
    if ( isset( $_GET['etwc_oauth_action'] ) && $_GET['etwc_oauth_action'] === 'callback' ) {
        $stored_state = get_transient('etwc_oauth_state');
        if ( !$stored_state || !isset($_GET['state']) || !hash_equals($stored_state, $_GET['state']) ) { // Usar hash_equals para comparar
            set_transient('etwc_api_admin_error_message', 'Error de OAuth: El estado no coincide (posible CSRF) o ha expirado.', 60);
            wp_safe_redirect( admin_url( 'tools.php?page=etsy-api-importer' ) );
            exit;
        }
        delete_transient('etwc_oauth_state');

        if ( isset( $_GET['code'] ) ) {
            $auth_code = sanitize_text_field( $_GET['code'] );
            $api_credentials = get_option('etwc_api_credentials', ['client_id' => '', 'client_secret' => '']);
            $code_verifier = get_transient('etwc_oauth_pkce_verifier');
            delete_transient('etwc_oauth_pkce_verifier');

            if (empty($api_credentials['client_id'])) {
                 set_transient('etwc_api_admin_error_message', 'Error de OAuth: Client ID no configurado.', 60);
                 wp_safe_redirect( admin_url( 'tools.php?page=etsy-api-importer' ) );
                 exit;
            }
            if (!$code_verifier) {
                set_transient('etwc_api_admin_error_message', 'Error de OAuth: No se encontró el verificador PKCE (sesión expirada probablemente). Intenta autorizar de nuevo.', 60);
                wp_safe_redirect( admin_url( 'tools.php?page=etsy-api-importer' ) );
                exit;
            }

            $token_url = 'https://api.etsy.com/v3/public/oauth/token';
            $redirect_uri = admin_url('tools.php?page=etsy-api-importer&etwc_oauth_action=callback');
            
            $body_params = [
                'grant_type'    => 'authorization_code',
                'client_id'     => $api_credentials['client_id'],
                'redirect_uri'  => $redirect_uri,
                'code'          => $auth_code,
                'code_verifier' => $code_verifier,
            ];
            // Para apps confidenciales, se necesita el client_secret. La API V3 de Etsy usa PKCE para apps públicas.
            // Si tu app está registrada como "confidencial", el client_secret podría ser necesario, a menudo como autenticación Basic HTTP o en el cuerpo.
            // Revisa la documentación de Etsy para tu tipo de app. Por defecto, PKCE para apps públicas no usa client_secret en este paso.
            // Si tu app ES confidencial y Etsy requiere client_secret aquí, deberías añadirlo:
            // $body_params['client_secret'] = $api_credentials['client_secret'];


            /* --- EJEMPLO DE LLAMADA HTTP REAL (COMENTADA) ---
            $response = wp_remote_post( $token_url, [ 'body' => $body_params, 'timeout' => 45 ] );
            if ( is_wp_error( $response ) ) {
                set_transient('etwc_api_admin_error_message', 'Error de OAuth al obtener token (WP Error): ' . $response->get_error_message(), 60);
            } else {
                $body = wp_remote_retrieve_body( $response );
                $token_data = json_decode( $body, true );
                if ( isset( $token_data['access_token'] ) ) {
                    $api_credentials['access_token'] = $token_data['access_token'];
                    $api_credentials['refresh_token'] = isset($token_data['refresh_token']) ? $token_data['refresh_token'] : '';
                    $api_credentials['token_expires_at'] = time() + (isset($token_data['expires_in']) ? intval($token_data['expires_in']) : 3600);
                    update_option( 'etwc_api_credentials', $api_credentials );
                    set_transient('etwc_api_admin_notice_message', '¡Conectado a Etsy con éxito!', 60);
                } else {
                    $error_detail = isset($token_data['error_description']) ? $token_data['error_description'] : (isset($token_data['error']) ? $token_data['error'] : 'Respuesta inválida.');
                    set_transient('etwc_api_admin_error_message', 'Error de OAuth al obtener token: ' . esc_html($error_detail) . ' Raw: ' . esc_html(substr($body,0,200)), 60);
                }
            }
            --- FIN EJEMPLO LLAMADA HTTP REAL --- */
            
            // --- SIMULACIÓN DE ÉXITO (BORRAR CUANDO IMPLEMENTES LA LLAMADA REAL) ---
            $api_credentials['access_token'] = 'TOKEN_DE_ACCESO_SIMULADO_' . time();
            $api_credentials['refresh_token'] = 'TOKEN_DE_REFRESCO_SIMULADO';
            $api_credentials['token_expires_at'] = time() + 3600; 
            update_option( 'etwc_api_credentials', $api_credentials );
            set_transient('etwc_api_admin_notice_message', '¡Conexión con Etsy simulada con éxito! (Auth Code: ' . esc_html(substr($auth_code,0,20)).'...)' , 60);
            // --- FIN SIMULACIÓN ---
        } else if (isset($_GET['error'])) {
            set_transient('etwc_api_admin_error_message', 'Error de OAuth de Etsy: ' . esc_html(isset($_GET['error_description']) ? $_GET['error_description'] : $_GET['error']), 60);
        }
        wp_safe_redirect( admin_url( 'tools.php?page=etsy-api-importer' ) );
        exit;
    }


    // --- Manejo de Acciones POST de la página API (guardar credenciales, iniciar OAuth, etc.) ---
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['etwc_api_nonce_field'] ) ) {
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

        if ( isset( $_POST['etwc_save_api_credentials'] ) ) {
            $client_id = isset($_POST['etwc_api_client_id']) ? sanitize_text_field($_POST['etwc_api_client_id']) : '';
            $client_secret_new = isset($_POST['etwc_api_client_secret']) ? sanitize_text_field($_POST['etwc_api_client_secret']) : '';
            $current_settings = get_option('etwc_api_credentials', ['client_id' => '', 'client_secret' => '', 'access_token' => '', 'refresh_token' => '', 'token_expires_at' => 0]);
            $current_settings['client_id'] = $client_id;
            if (!empty($client_secret_new)) { 
                $current_settings['client_secret'] = $client_secret_new;
            }
            update_option('etwc_api_credentials', $current_settings);
            set_transient('etwc_api_admin_notice_message', 'Credenciales de la aplicación de Etsy guardadas.', 60);
        }

        if (isset($_POST['etwc_oauth_action']) && $_POST['etwc_oauth_action'] === 'authorize') {
            $api_credentials = get_option('etwc_api_credentials', ['client_id' => '']);
            if (empty($api_credentials['client_id'])) {
                set_transient('etwc_api_admin_error_message', 'Por favor, guarda tu Client ID de Etsy primero.', 60);
                wp_safe_redirect(admin_url('tools.php?page=etsy-api-importer'));
                exit;
            }
            $state = wp_create_nonce('etsy_oauth_state_'.get_current_user_id()); // Hacer el state más único
            set_transient('etwc_oauth_state', $state, HOUR_IN_SECONDS);
            
            try {
                $code_verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                set_transient('etwc_oauth_pkce_verifier', $code_verifier, HOUR_IN_SECONDS);
                $code_challenge = rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');
            } catch (Exception $e) {
                set_transient('etwc_api_admin_error_message', 'Error generando PKCE: ' . $e->getMessage(), 60);
                wp_safe_redirect(admin_url('tools.php?page=etsy-api-importer'));
                exit;
            }

            $redirect_uri = admin_url('tools.php?page=etsy-api-importer&etwc_oauth_action=callback');
            // Scopes importantes: listings_r (leer listados), shops_r (leer info de tienda), email_r (leer email del usuario).
            // Ajusta los scopes según los permisos que necesite tu aplicación.
            $scopes = 'listings_r shops_r email_r'; 
            
            $auth_url = 'https://www.etsy.com/oauth/connect?' . http_build_query([
                'response_type'         => 'code',
                'client_id'             => $api_credentials['client_id'],
                'redirect_uri'          => $redirect_uri,
                'scope'                 => $scopes,
                'state'                 => $state,
                'code_challenge'        => $code_challenge,
                'code_challenge_method' => 'S256',
            ]);
            wp_redirect($auth_url);
            exit;
        }
        
        if (isset($_POST['etwc_oauth_action']) && $_POST['etwc_oauth_action'] === 'disconnect') { // Cambiado a POST para el botón de desconectar
            $current_settings = get_option('etwc_api_credentials', []);
            $current_settings['access_token'] = ''; $current_settings['refresh_token'] = '';
            $current_settings['token_expires_at'] = 0;
            update_option('etwc_api_credentials', $current_settings);
            set_transient('etwc_api_admin_notice_message', 'Desconectado de Etsy. Necesitarás autorizar de nuevo para usar la API.', 60);
        }

        if ( isset( $_POST['etwc_fetch_and_generate_csv_from_api'] ) ) {
            $api_credentials = get_option('etwc_api_credentials', []);
            $ignore_stock_api = isset( $_POST['api_ignore_stock_setting'] ) && $_POST['api_ignore_stock_setting'] === '1';
            update_option('etwc_api_ignore_stock', $ignore_stock_api); // Guardar preferencia

            if (empty($api_credentials['access_token']) || time() >= $api_credentials['token_expires_at']) {
                set_transient('etwc_api_admin_error_message', 'No estás conectado a Etsy o el token (simulado) ha expirado. Por favor, conecta/autoriza primero.', 60);
            } else {
                $mock_api_listings = etwc_fetch_etsy_listings_via_api_placeholder($api_credentials); 
                if (empty($mock_api_listings)) {
                    set_transient('etwc_api_admin_error_message', 'No se obtuvieron datos (simulados) de la API de Etsy.', 60);
                } else {
                    $processed_api_data = etwc_transform_api_data_to_wc_format_placeholder($mock_api_listings, $ignore_stock_api);
                    etwc_download_data_as_csv($processed_api_data, 'wc_products_from_api_simulated.csv');
                    // exit() está en etwc_download_data_as_csv
                }
            }
        }
        
        wp_safe_redirect( admin_url( 'tools.php?page=etsy-api-importer' ) );
        exit;
    }
}
?>