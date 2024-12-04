<?php
/**
 * Plugin Name: Sensei Paid Modules
 * Plugin URI: https://github.com/NotGedomi/sensei-paid-modules
 * Description: Permite vincular productos de WooCommerce a módulos de Sensei con validación de curso padre
 * Version: 1.0.0
 * Author: Gedomi
 * Author URI: https://github.com/NotGedomi/
 * License: GPL v2 or later
 * Text Domain: sensei-module-products
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

 if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase principal del plugin
 */
final class Sensei_Module_Products_Plugin {
    const VERSION = '1.0.0';
    private static $instance = null;
    
    /**
     * Constructor privado para singleton
     */
    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define las constantes del plugin
     */
    private function define_constants() {
        define('SMP_VERSION', self::VERSION);
        define('SMP_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('SMP_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('SMP_PLUGIN_BASENAME', plugin_basename(__FILE__));
    }

    /**
     * Incluye los archivos necesarios
     */
    private function includes() {
        require_once SMP_PLUGIN_DIR . 'includes/class-module-products.php';
        require_once SMP_PLUGIN_DIR . 'includes/class-module-products-admin.php';
        require_once SMP_PLUGIN_DIR . 'includes/class-module-products-validator.php';
        require_once SMP_PLUGIN_DIR . 'includes/class-module-products-woocommerce.php';
    }

    /**
     * Inicializa los hooks principales
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'on_plugins_loaded'));
        add_action('init', array($this, 'load_textdomain'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Carga el dominio de texto para traducciones
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'sensei-module-products',
            false,
            dirname(SMP_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Callback cuando todos los plugins están cargados
     */
    public function on_plugins_loaded() {
        if ($this->check_dependencies()) {
            $this->init_plugin();
        }
    }

    /**
     * Verifica las dependencias del plugin
     */
    private function check_dependencies() {
        if (!class_exists('Sensei_Main') || !class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                ?>
                <div class="error">
                    <p><?php _e('Sensei Module Products requiere que Sensei LMS y WooCommerce estén instalados y activados.', 'sensei-module-products'); ?></p>
                </div>
                <?php
            });
            return false;
        }
        return true;
    }

    /**
     * Inicializa las clases principales del plugin
     */
    private function init_plugin() {
        Sensei_Module_Products::instance();
        Sensei_Module_Products_Admin::instance();
        Sensei_Module_Products_Validator::instance();
        Sensei_Module_Products_WooCommerce::instance();
    }

    /**
     * Acciones al activar el plugin
     */
    public function activate() {
        // Crear opciones por defecto si es necesario
        add_option('smp_version', self::VERSION);
    }

    /**
     * Acciones al desactivar el plugin
     */
    public function deactivate() {
        // Limpieza si es necesario
    }

    /**
     * Retorna la instancia única del plugin
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

// Inicializar el plugin
function Sensei_Module_Products_Plugin() {
    return Sensei_Module_Products_Plugin::instance();
}

Sensei_Module_Products_Plugin();