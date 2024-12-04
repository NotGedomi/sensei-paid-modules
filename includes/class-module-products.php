<?php
/**
 * Clase base para la funcionalidad principal del plugin
 *
 * @package Sensei_Module_Products
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sensei_Module_Products {
    /**
     * @var self Instancia de la clase
     */
    private static $instance = null;

    /**
     * @var string Nombre de la taxonomía de módulos
     */
    protected $taxonomy = 'module';

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
        // Control de acceso a módulos
        add_filter('sensei_can_user_view_module', array($this, 'check_module_access'), 10, 4);
        
        // Filtros para el contenido del módulo
        add_filter('sensei_module_content', array($this, 'maybe_restrict_module_content'), 10, 2);
    }

    /**
     * Verifica si un usuario puede acceder a un módulo
     */
    public function check_module_access($can_view, $module_id, $course_id, $user_id) {
        // Si ya tiene acceso por otras razones, mantenerlo
        if ($can_view) {
            return true;
        }

        // Verificar si el módulo tiene un producto asociado
        $product_id = $this->get_module_product_id($module_id);
        if (!$product_id) {
            return $can_view;
        }

        // Verificar si el usuario tiene acceso al curso padre
        if (!$this->user_has_course_access($user_id, $course_id)) {
            return false;
        }

        // Verificar si el usuario compró el producto del módulo
        return wc_customer_bought_product($user_id, $user_id, $product_id);
    }

    /**
     * Posiblemente restringe el contenido del módulo
     */
    public function maybe_restrict_module_content($content, $module) {
        if (!is_user_logged_in()) {
            return $this->get_login_required_message();
        }

        $module_id = $module->term_id;
        $course_id = $this->get_module_course_id($module_id);
        $user_id = get_current_user_id();

        if (!$this->check_module_access(false, $module_id, $course_id, $user_id)) {
            return $this->get_purchase_required_message($module_id);
        }

        return $content;
    }

    /**
     * Obtiene el ID del producto asociado a un módulo
     */
    public function get_module_product_id($module_id) {
        return get_term_meta($module_id, '_module_product', true);
    }

    /**
     * Obtiene el curso al que pertenece un módulo
     */
    public function get_module_course_id($module_id) {
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
     * Verifica si un usuario tiene acceso a un curso
     */
    public function user_has_course_access($user_id, $course_id) {
        if (!$course_id || !$user_id) {
            return false;
        }

        return Sensei_Utils::user_started_course($course_id, $user_id);
    }

    /**
     * Obtiene el mensaje para usuarios no logueados
     */
    private function get_login_required_message() {
        return sprintf(
            '<div class="module-access-notice">%s</div>',
            __('Por favor inicia sesión para ver este contenido.', 'sensei-module-products')
        );
    }

    /**
     * Obtiene el mensaje para módulos que requieren compra
     */
    private function get_purchase_required_message($module_id) {
        $product_id = $this->get_module_product_id($module_id);
        if (!$product_id) {
            return '';
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return '';
        }

        return sprintf(
            '<div class="module-access-notice">%s<br><a href="%s" class="button">%s</a></div>',
            __('Este módulo requiere compra adicional.', 'sensei-module-products'),
            $product->get_permalink(),
            sprintf(
                __('Comprar por %s', 'sensei-module-products'),
                $product->get_price_html()
            )
        );
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