<?php
/**
 * Integración con WooCommerce Product Data
 */
class Sensei_Module_Products_WooCommerce {
    private static $instance = null;
    private $taxonomy = 'module';

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Agregar pestaña de módulos en Product Data
        add_filter('woocommerce_product_data_tabs', array($this, 'add_module_product_data_tab'));
        
        // Agregar campos en la pestaña de módulos
        add_action('woocommerce_product_data_panels', array($this, 'add_module_product_data_fields'));
        
        // Guardar los datos del módulo
        add_action('woocommerce_process_product_meta', array($this, 'save_module_product_data'));
    }

    /**
     * Agrega la pestaña de módulos en Product Data
     */
    public function add_module_product_data_tab($tabs) {
        $tabs['sensei_module'] = array(
            'label'    => __('Módulos', 'sensei-module-products'),
            'target'   => 'sensei_module_product_data',
            'class'    => array('show_if_simple', 'show_if_subscription'),
            'priority' => 85, // Después de la pestaña de cursos
        );
        return $tabs;
    }

    /**
     * Agrega los campos en la pestaña de módulos
     */
    public function add_module_product_data_fields() {
        global $post;
        
        echo '<div id="sensei_module_product_data" class="panel woocommerce_options_panel">';
        
        // Campo de búsqueda de módulos
        $modules = get_terms(array(
            'taxonomy' => $this->taxonomy,
            'hide_empty' => false
        ));

        $selected_module = get_post_meta($post->ID, '_module_id', true);

        // Título de la sección
        echo '<div class="options_group">';
        echo '<p class="form-field">';
        echo '<label for="module_id">' . __('Módulo', 'sensei-module-products') . '</label>';
        
        // Select para módulos
        echo '<select id="module_id" name="module_id" class="select2">';
        echo '<option value="">' . __('Seleccionar un módulo...', 'sensei-module-products') . '</option>';
        
        foreach ($modules as $module) {
            echo '<option value="' . esc_attr($module->term_id) . '" ' . selected($selected_module, $module->term_id, false) . '>';
            echo esc_html($module->name);
            // Obtener y mostrar el curso asociado si existe
            $course = $this->get_module_course($module->term_id);
            if ($course) {
                echo ' (' . __('Curso:', 'sensei-module-products') . ' ' . get_the_title($course) . ')';
            }
            echo '</option>';
        }
        echo '</select>';

        // Descripción del campo
        echo '<span class="description">' . __('Selecciona el módulo al que este producto dará acceso.', 'sensei-module-products') . '</span>';
        echo '</p>';

        // Notas informativas
        echo '<div class="notice notice-info inline"><p>';
        echo __('Nota: Los estudiantes necesitarán tener acceso al curso principal antes de poder comprar este módulo.', 'sensei-module-products');
        echo '</p></div>';

        echo '</div>'; // Cierre options_group
        echo '</div>'; // Cierre panel
    }

    /**
     * Guarda los datos del módulo
     */
    public function save_module_product_data($post_id) {
        $module_id = isset($_POST['module_id']) ? absint($_POST['module_id']) : '';
        update_post_meta($post_id, '_module_id', $module_id);

        // Si se seleccionó un módulo, actualizar también el meta del módulo
        if ($module_id) {
            update_term_meta($module_id, '_module_product', $post_id);
        }
    }

    /**
     * Obtiene el curso asociado a un módulo
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

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}