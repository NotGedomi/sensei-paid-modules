<?php
/**
 * Clase para manejar las validaciones de compra
 *
 * @package Sensei_Module_Products
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sensei_Module_Products_Validator {
    /**
     * @var self Instancia de la clase
     */
    private static $instance = null;

    /**
     * @var string Nombre de la taxonomía
     */
    private $taxonomy = 'module';

    /**
     * Constructor privado para singleton
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Inicializa los hooks necesarios
     */
    private function init_hooks() {
        // Validación de compra
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_module_product_purchase'), 10, 2);
        
        // Verificación después de la compra
        add_action('woocommerce_order_status_completed', array($this, 'process_module_purchase'));
        add_action('woocommerce_subscription_status_active', array($this, 'process_module_subscription'));
    }

    /**
     * Valida si un usuario puede comprar un producto de módulo
     */
    public function validate_module_product_purchase($valid, $product_id) {
        // Buscar si este producto está asociado a algún módulo
        $module = $this->get_module_by_product($product_id);
        
        if (!$module) {
            return $valid; // No es un producto de módulo
        }

        if (!is_user_logged_in()) {
            wc_add_notice(
                __('Debes iniciar sesión para comprar este módulo.', 'sensei-module-products'),
                'error'
            );
            return false;
        }

        // Obtener el curso al que pertenece el módulo
        $course_id = $this->get_module_course($module->term_id);
        
        if (!$course_id) {
            return $valid; // El módulo no está asignado a ningún curso
        }

        $user_id = get_current_user_id();

        // Verificar si el usuario tiene acceso al curso
        if (!Sensei_Utils::user_started_course($course_id, $user_id)) {
            wc_add_notice(
                sprintf(
                    __('Debes comprar y estar inscrito en el curso "%s" antes de poder comprar este módulo.', 'sensei-module-products'),
                    get_the_title($course_id)
                ),
                'error'
            );
            return false;
        }

        // Verificar si ya compró el módulo
        if ($this->user_has_module_access($user_id, $module->term_id)) {
            wc_add_notice(
                __('Ya tienes acceso a este módulo.', 'sensei-module-products'),
                'error'
            );
            return false;
        }

        return $valid;
    }

    /**
     * Procesa la compra de un módulo cuando se completa una orden
     */
    public function process_module_purchase($order_id) {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $module = $this->get_module_by_product($product_id);

            if ($module) {
                $this->grant_module_access($user_id, $module->term_id);
            }
        }
    }

    /**
     * Procesa la suscripción de un módulo cuando se activa
     */
    public function process_module_subscription($subscription) {
        $user_id = $subscription->get_user_id();

        foreach ($subscription->get_items() as $item) {
            $product_id = $item->get_product_id();
            $module = $this->get_module_by_product($product_id);

            if ($module) {
                $this->grant_module_access($user_id, $module->term_id);
            }
        }
    }

    /**
     * Obtiene el módulo asociado a un producto
     */
    private function get_module_by_product($product_id) {
        $args = array(
            'taxonomy' => $this->taxonomy,
            'meta_query' => array(
                array(
                    'key' => '_module_product',
                    'value' => $product_id,
                    'compare' => '='
                )
            ),
            'hide_empty' => false
        );

        $modules = get_terms($args);

        return !empty($modules) ? $modules[0] : null;
    }

    /**
     * Obtiene el curso al que pertenece un módulo
     */
    private function get_module_course($module_id) {
        $args = array(
            'post_type' => 'course',
            'tax_query' => array(
                array(
                    'taxonomy' => $this->taxonomy,
                    'field'    => 'id',
                    'terms'    => $module_id
                )
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        );
        
        $courses = get_posts($args);
        return !empty($courses) ? $courses[0] : 0;
    }

    /**
     * Verifica si un usuario tiene acceso a un módulo
     */
    private function user_has_module_access($user_id, $module_id) {
        // Primero verificar si hay un registro de compra
        $access_records = get_user_meta($user_id, '_sensei_module_purchases', true);
        if (!empty($access_records) && in_array($module_id, $access_records)) {
            return true;
        }

        // Verificar si tiene acceso por producto
        $product_id = get_term_meta($module_id, '_module_product', true);
        if ($product_id && wc_customer_bought_product($user_id, $user_id, $product_id)) {
            return true;
        }

        return false;
    }

    /**
     * Otorga acceso a un módulo para un usuario
     */
    private function grant_module_access($user_id, $module_id) {
        $access_records = get_user_meta($user_id, '_sensei_module_purchases', true);
        
        if (!is_array($access_records)) {
            $access_records = array();
        }

        if (!in_array($module_id, $access_records)) {
            $access_records[] = $module_id;
            update_user_meta($user_id, '_sensei_module_purchases', $access_records);

            do_action('sensei_module_purchase_complete', $user_id, $module_id);
        }
    }

    /**
     * Obtiene la instancia de la clase
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}