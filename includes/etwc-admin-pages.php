<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Añade los submenús del plugin al menú de Herramientas de WordPress.
 */
function etwc_add_admin_menu() {
    // Submenú para la subida de CSV
    add_submenu_page(
        'tools.php',
        'Etsy CSV Tool', 
        'Etsy CSV Tool', 
        'manage_options',
        'etsy-to-wc-converter', 
        'etwc_csv_upload_page_html' 
    );

    // Submenú para la importación vía API
    add_submenu_page(
        'tools.php',
        'Etsy API Import (Beta)',    
        'Etsy API Import',         
        'manage_options',
        'etsy-api-importer',       
        'etwc_api_import_page_html'  
    );
}

/**
 * Renderiza la página de administración para la subida de CSV.
 */
function etwc_csv_upload_page_html() {
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
            <?php wp_nonce_field( 'etwc_action_nonce', 'etwc_nonce_field' ); // Nonce para la página CSV ?>
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
    $api_credentials = get_option('etwc_api_credentials', ['client_id' => '', 'client_secret' => '', 'access_token' => '', 'refresh_token' => '', 'token_expires_at' => 0]);
    $is_connected = !empty($api_credentials['access_token']) && time() < $api_credentials['token_expires_at'];
    ?>
    <div class="wrap">
        <h1>Importar Productos desde Etsy vía API</h1>
        <p>Esta sección te permitirá configurar la conexión con la API de Etsy e importar productos.</p>
        
        <p><strong>Paso 1: Configuración de la Aplicación de Etsy.</strong> Necesitarás registrar una aplicación en el <a href="https://www.etsy.com/developers/your-apps" target="_blank">Portal de Desarrolladores de Etsy</a> para obtener tu "Keystring" (Client ID) y "Shared Secret" (Client Secret). Asegúrate de que una de tus "Redirect URIs" sea: <br>
        <code><?php echo esc_url(admin_url('tools.php?page=etsy-api-importer&etwc_oauth_action=callback')); ?></code></p>
        
        <?php 
        if ( $message = get_transient( 'etwc_api_admin_notice_message' ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
            delete_transient( 'etwc_api_admin_notice_message' );
        }
        if ( $error_message = get_transient( 'etwc_api_admin_error_message' ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_message ) . '</p></div>';
            delete_transient( 'etwc_api_admin_error_message' );
        }
        ?>

        <form method="POST" action="<?php echo esc_url( admin_url( 'tools.php?page=etsy-api-importer' ) ); ?>">
            <?php wp_nonce_field( 'etwc_api_action_nonce', 'etwc_api_nonce_field' ); // Nonce para la página API ?>
            
            <h2>Configuración de la Aplicación de Etsy</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="etwc_api_client_id">Etsy Keystring (Client ID):</label></th>
                    <td><input type="text" id="etwc_api_client_id" name="etwc_api_client_id" value="<?php echo esc_attr($api_credentials['client_id']); ?>" class="regular-text" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="etwc_api_client_secret">Etsy Shared Secret (Client Secret):</label></th>
                    <td><input type="password" id="etwc_api_client_secret" name="etwc_api_client_secret" placeholder="Introduce solo si quieres cambiarlo" class="regular-text" />
                    <p class="description">Tu Client Secret se guarda pero no se muestra aquí por seguridad después de guardarlo.</p></td>
                </tr>
                <?php if (!empty($api_credentials['client_id'])): // Mostrar estado de conexión solo si hay client_id ?>
                <tr valign="top">
                    <th scope="row">Estado de Conexión:</th>
                    <td>
                        <?php if ($is_connected): ?>
                            <strong style="color:green;">Conectado a Etsy.</strong> El token simulado es válido hasta <?php echo esc_html(wp_date('Y-m-d H:i:s', $api_credentials['token_expires_at'])); ?>.
                            <input type="hidden" name="etwc_oauth_action" value="disconnect">
                            <button type="submit" name="etwc_disconnect_etsy" class="button button-link" style="margin-left:10px; color:red; text-decoration:underline;">Desconectar</button>
                        <?php else: ?>
                            <strong style="color:red;">No conectado o token expirado.</strong>
                            <input type="hidden" name="etwc_oauth_action" value="authorize">
                            <button type="submit" name="etwc_authorize_etsy" class="button button-secondary" style="margin-left:10px;">Conectar con Etsy (Autorizar)</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            <p><button type="submit" name="etwc_save_api_credentials" class="button">Guardar Credenciales de Aplicación</button></p>
        </form>
        <hr>
        <h2>Proceso de Importación desde API (Simulado con datos de ejemplo)</h2>
         <form method="POST" action="<?php echo esc_url( admin_url( 'tools.php?page=etsy-api-importer' ) ); ?>">
            <?php wp_nonce_field( 'etwc_api_action_nonce', 'etwc_api_nonce_field' ); ?>
            <p>
                 <label for="api_ignore_stock_setting"> <input type="checkbox" id="api_ignore_stock_setting" name="api_ignore_stock_setting" value="1" <?php checked(true, get_option('etwc_api_ignore_stock', false)); ?>>
                    Ignorar gestión de stock para productos importados (afecta al CSV generado)
                </label>
            </p>
            <p>
                <button type="submit" name="etwc_fetch_and_generate_csv_from_api" class="button button-primary" <?php if (!$is_connected) echo 'disabled'; ?>>
                    Obtener de API y Generar CSV (Simulado)
                </button>
                 <?php if (!$is_connected) echo '<p class="description">Debes conectar con Etsy y tener un token válido primero.</p>'; ?>
            </p>
        </form>
    </div>
    <?php
    $html_output = ob_get_clean();
    echo $html_output;
}
?>