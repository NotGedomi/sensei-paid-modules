<?php
/**
 * Clase para manejar la interfaz de administración
 *
 * @package Sensei_Module_Products
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sensei_Module_Products_Admin {
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
     * Inicializa los hooks de administración
     */
    private function init_hooks() {
        // Campos del módulo
        add_action($this->taxonomy . '_add_form_fields', array($this, 'add_module_product_field'));
        add_action($this->taxonomy . '_edit_form_fields', array($this, 'edit_module_product_field'), 10, 2);
        
        // Guardar campos
        add_action('created_' . $this->taxonomy, array($this, 'save_module_product'));
        add_action('edited_' . $this->taxonomy, array($this, 'save_module_product'));

        // Columnas en la lista de módulos
        add_filter('manage_edit-' . $this->taxonomy . '_columns', array($this, 'add_module_columns'));
        add_filter('manage_' . $this->taxonomy . '_custom_column', array($this, 'add_module_column_content'), 10, 3);

        // Scripts y estilos
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Agrega el campo de producto en el formulario de crear módulo
     */
    public function add_module_product_field() {
        $products = $this->get_available_products();
        ?>
        <div class="form-field module-product-wrap">
            <label for="module_product"><?php _e('Producto WooCommerce', 'sensei-module-products'); ?></label>
            <select name="module_product" id="module_product" class="widefat">
                <option value=""><?php _e('Ninguno', 'sensei-module-products'); ?></option>
                <?php foreach ($products as $product) : ?>
                    <option value="<?php echo esc_attr($product->ID); ?>">
                        <?php 
                        echo esc_html($product->post_title); 
                        echo ' (' . get_woocommerce_currency_symbol() . get_post_meta($product->ID, '_price', true) . ')';
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                <?php _e('Selecciona un producto de WooCommerce para este módulo. Los estudiantes deberán comprar el curso antes de poder comprar este módulo.', 'sensei-module-products'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Agrega el campo de producto en el formulario de editar módulo
     */
    public function edit_module_product_field($term, $taxonomy) {
        $product_id = get_term_meta($term->term_id, '_module_product', true);
        $products = $this->get_available_products();
        ?>
        <tr class="form-field module-product-wrap">
            <th scope="row">
                <label for="module_product"><?php _e('Producto WooCommerce', 'sensei-module-products'); ?></label>
            </th>
            <td>
                <select name="module_product" id="module_product" class="widefat">
                    <option value=""><?php _e('Ninguno', 'sensei-module-products'); ?></option>
                    <?php foreach ($products as $product) : ?>
                        <option value="<?php echo esc_attr($product->ID); ?>" 
                                <?php selected($product_id, $product->ID); ?>>
                            <?php 
                            echo esc_html($product->post_title);
                            echo ' (' . get_woocommerce_currency_symbol() . get_post_meta($product->ID, '_price', true) . ')';
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php _e('Selecciona un producto de WooCommerce para este módulo. Los estudiantes deberán comprar el curso antes de poder comprar este módulo.', 'sensei-module-products'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Obtiene los productos disponibles de WooCommerce
     */
    private function get_available_products() {
        return get_posts(array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish'
        ));
    }

    /**
     * Guarda el producto asociado al módulo
     */
    public function save_module_product($term_id) {
        if (isset($_POST['module_product'])) {
            $product_id = absint($_POST['module_product']);
            if ($product_id > 0) {
                update_term_meta($term_id, '_module_product', $product_id);
            } else {
                delete_term_meta($term_id, '_module_product');
            }
        }
    }

    /**
     * Agrega columna de producto en la lista de módulos
     */
    public function add_module_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'description') {
                $new_columns['module_product'] = __('Producto', 'sensei-module-products');
            }
        }
        return $new_columns;
    }

    /**
     * Agrega el contenido de la columna de producto
     */
    public function add_module_column_content($content, $column_name, $term_id) {
        if ('module_product' === $column_name) {
            $product_id = get_term_meta($term_id, '_module_product', true);
            if ($product_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    return sprintf(
                        '<a href="%s">%s</a> <span class="price">%s</span>',
                        get_edit_post_link($product_id),
                        $product->get_name(),
                        $product->get_price_html()
                    );
                }
            }
            return '—';
        }
        return $content;
    }

    /**
     * Carga scripts y estilos en el admin
     */
    public function enqueue_admin_scripts($hook) {
        $screen = get_current_screen();
        
        if ($screen && 'edit-' . $this->taxonomy === $screen->id) {
            // Estilos
            wp_enqueue_style(
                'smp-admin',
                SMP_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                SMP_VERSION
            );

            // Select2
            wp_enqueue_style('select2');
            wp_enqueue_script('select2');

            // Scripts del plugin
            wp_enqueue_script(
                'smp-admin',
                SMP_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'select2'),
                SMP_VERSION,
                true
            );

            // Localización
            wp_localize_script(
                'smp-admin',
                'SenseiModuleProductsL10n',
                array(
                    'selectPlaceholder' => __('Selecciona un producto...', 'sensei-module-products')
                )
            );
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