<?php
/*
 * Plugin Name:       WooCommerce To Elastic Product Indexer
 * Description:       WooCommerce Products to Elastic Search Indexer (WC2EL)
 * Version:           0.1.0
 * Author:            Andrew Sh
 * Text Domain:       sha-wc2el
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class SHA_WC_To_Elastic {

    private static $_instance;

    protected $_plugin_slug = 'wc2el';

    protected $_prefix = 'wc2el_';

    protected $_elastic_settings = array();

    protected $_allowed_index_actions = array( 'stat', 'create', 'delete', 'index' );

    protected $_allowed_product_actions = array( 'stat', 'add', 'delete' );

    protected $_wp_allowed_product_types = array();

    protected $_wp_allowed_product_statuses = array();

    public static function get_instance() {

        if ( ! isset( self::$_instance ) ) {
            self::$_instance = new SHA_WC_To_Elastic;
            self::$_instance->init();
        }

        return self::$_instance;
    }

    public function init() {
        $this->init_variables();
        $this->init_hooks();
    }

    // Initing all variables of class
    private function init_variables() {

        $blog_url = get_bloginfo( 'url' );
        $blog_url = wp_parse_url( $blog_url );

        $this->_elastic_settings = array(
            'index'         => str_replace( '.', '-', $blog_url['host'] ),
            'bulk_amount'   => 100
        );

        // Set credentials from wp-config.php
        if ( defined( 'WC2EL_HOST' ) && defined( 'WC2EL_USER' ) && defined( 'WC2EL_PASS' ) ) {
            $this->_elastic_settings['host'] = array( WC2EL_HOST );
            $this->_elastic_settings['username'] = WC2EL_USER;
            $this->_elastic_settings['password'] = WC2EL_PASS;
            $this->_elastic_settings['bulk_amount'] = (int) defined( 'WC2EL_BULK_AMOUNT' ) ? WC2EL_BULK_AMOUNT : $this->_elastic_settings['bulk_amount'];
        }
    }

    // Initing all actions
    private function init_hooks() {

        // Actions for create/delete index, index/delete single product

        // usage do_action( 'sha_wc2el_index_product', $product_id, $product );
        add_action( 'sha_wc2el_index_product', array( $this, 'index_single_item' ), 10, 2 );

        // usage do_action( 'sha_wc2el_delete_product', $product_id );
        add_action( 'sha_wc2el_delete_product', array( $this, 'delete_single_item' ), 10, 2 );

        // usage do_action( 'sha_wc2el_create_index' );
        add_action( 'sha_wc2el_create_index', array( $this, 'create_index' ) );

        // usage do_action( 'wc2el_delete_index' );
        add_action( 'wc2el_delete_index', array( $this, 'delete_index' ) );

        // Load textdomain
        add_action( 'init', array( $this, 'init_textdomain' ) );

        // Initing cli support
        add_action( 'init', array( $this, 'init_cli_support' ) );

        // Apply filters
        add_action( 'init', array( $this, 'apply_filters' ) );

    }

    // Load textdomain
    public function init_textdomain() {
        load_plugin_textdomain( 'sha-wc2el', false, basename( dirname( __FILE__, 1 ) ) . '/languages' );
    }

    // Apply filters
    public function apply_filters() {
        // Override ElasticSearch credentials
        $this->_elastic_settings = apply_filters( 'sha_wc2el_elastic_settings', $this->_elastic_settings );

        // Select only products with this types
        $this->_wp_allowed_product_types = apply_filters( 'sha_wc2el_allowed_product_types', array( 'simple' ) );

        // Select only products with this status
        $this->_wp_allowed_product_statuses = apply_filters( 'sha_wc2el_allowed_product_statuses', array( 'publish' ) );
    }

    // Add WP_CLI support
    public function init_cli_support() {

        // Add suport only if cli and woocommerce active
        if ( defined ( 'WP_CLI' ) && WP_CLI && defined( 'WC_VERSION' ) ) {

            // Test connection
            WP_CLI::add_command(
                'wc2el test',
                array( $this, 'cli_test' ),
                array(
                    'shortdesc' => __( 'Test Elastic connection. No extra args', 'sha-wc2el' ),
                    'when'      => 'after_wp_load',
                    'longdesc' 	=> __( '## EXAMPLES' . "\n\n" . 'wp wc2el test', 'sha-wc2el' )
                )
            );

            // Index operations
            WP_CLI::add_command(
                'wc2el index',
                array( $this, 'cli_index' ),
                array(
                    'shortdesc' => __( 'Index actions: stat, create, delete, update', 'sha-wc2el' ),
                    'synopsis'  => array(
                        array(
                            'type'          => 'positional',
                            'name'          => implode( ',', $this->_allowed_index_actions ),
                            'description'   => sprintf(
                                __( 'Type of action with index. Allowed values: %s', 'sha-wc2el' ),
                                implode( ', ', $this->_allowed_index_actions )
                            ),
                            'optional'      => true,
                            'repeating'     => false
                        ),
                    ),
                    'when'      => 'after_wp_load',
                    'longdesc'  => __( '## EXAMPLES' . "\n\n" . 'wp wc2el index create', 'sha-wc2el' )
                )
            );

            // Product operations
            WP_CLI::add_command(
                'wc2el product',
                array( $this, 'cli_product' ),
                array(
                    'shortdesc' => __( 'CLI for WooCommerce To Elastic plugin', 'sha-wc2el' ),
                    'synopsis'  => array(
                        array(
                            'type'			=> 'positional',
                            'name'			=> implode( ',', $this->_allowed_product_actions ),
                            'description'	=> sprintf(
                                __( 'Type of action with product. Allowed values: %s', 'sha-wc2el' ),
                                implode( ', ', $this->_allowed_product_actions ),
                            ),
                            'optional'		=> true,
                            'repeating'		=> false
                        ),
                        array(
                            'type'			=> 'positional',
                            'name'			=> 'productID',
                            'description'   => __( 'Product ID', 'sha-wc2el' ),
                            'optional'		=> true,
                            'repeating'		=> false
                        )
                    ),
                    'when'      => 'after_wp_load',
                    'longdesc'  => __( '## EXAMPLES' . "\n\n" . 'wp wc2el product add 1012', 'sha-wc2el' )
                )
            );
        }
    }

    // WP_CLI proccessor for test action
    public function cli_test( $args, $assoc_args ) {
        try {
            $res = $this->test_connection();
            WP_CLI::success( __( 'Connected to Elasticsearch: ', 'sha-wc2el' ) . $res['cluster_name'] );                        
        } catch ( Exception $e ) {
            WP_CLI::error(
                sprintf(
                    __( 'Can\'t connect to Elastic. Elastic Error: %s', 'sha-wc2el' ),
                    $e->getMessage()
                )
            );
        }
    }

    // WP_CLI proccessor for index action
    public function cli_index( $args, $assoc_args ) {

        if ( ! isset( $args[0] ) || ! in_array( $args[0], $this->_allowed_index_actions ) ) {

            WP_CLI::error(
                sprintf(
                    __( 'You should provide an extra [%s] argument', 'sha-wc2el' ),
                    implode( ' | ', $this->_allowed_index_actions ),
                )
            );
        }

        switch ( $args[0] ) {

            // Index stat
            case 'stat':
                if ( ! $this->is_index_exists() ) {
                    WP_CLI::error(
                        sprintf(
                            __( 'Can\'t get stat for index [%s]. Index not exists.', 'sha-wc2el' ),
                            $this->_elastic_settings['index']
                        )
                    );
                    break;
                }

                $index_stat = $this->get_index_stat_data();

                if ( ! empty( $index_stat ) ) {

                    $stat_data = array(
                        array(
                            __( 'Index Name', 'sha-wc2el' ),
                            $index_stat['index_name']
                        ),
                        array(
                            __( 'Products in index', 'sha-wc2el' ),
                            $index_stat['records_count']
                        ),
                        array(
                            __( 'Index Size', 'sha-wc2el' ),
                            $index_stat['index_size']
                        ),
                        array(
                            __( 'Last Full Index', 'sha-wc2el' ),
                            $index_stat['last_reindex']
                        )
                    );

                    $this->output_data_as_table( $stat_data );
                } else {
                    WP_CLI::error(
                        sprintf(
                            __( 'Can\'t get stat for index [%s]', 'sha-wc2el' ),
                            $this->_elastic_settings['index']
                        )
                    );
                }

            break;

            // Create index
            case 'create':
                try {
                    if ( ! $this->is_index_exists() ) {
                        $this->create_index();

                        WP_CLI::success( __( 'Index created. Start indexing [ wp wc2el index index ] to add products to index', 'sha-wc2el' ) );
                    } else {
                        WP_CLI::error(
                            sprintf(
                                __( 'Can\'t create index [%s]. Index already exists', 'sha-wc2el' ),
                                $this->_elastic_settings['index']
                            )
                        );

                    }
                } catch ( Exception $e ) {
                    WP_CLI::error(
                        sprintf(
                            __( 'Can\'t build index [%s]. Elastic Error: %s', 'sha-wc2el' ),
                            $this->_elastic_settings['index'],
                            $e->getMessage()
                        )
                    );
                }
            break;

            // Delete index
            case 'delete':
                try {
                    if ( $this->is_index_exists() ) {
                        $this->delete_index();

                        delete_option( $this->_prefix . 'last_reindex_date' );

                        WP_CLI::success(
                            __(	'Index deleted successfully.', 'sha-wc2el' )
                        );
                    } else {
                        WP_CLI::error(
                            sprintf(
                                __( 'Can\'t delete index [%s]. Index not exists', 'sha-wc2el' ),
                                $this->_elastic_settings['index']
                            )
                        );
                    }
                } catch ( Exception $e ) {
                    WP_CLI::error(
                        sprintf(
                            __( 'Can\'t delete index [%s]. Elastic Error: %s', 'sha-wc2el' ),
                            $this->_elastic_settings['index'],
                            $e->getMessage()
                        )
                    );
                }
            break;

            case 'index':
                try {
                    $this->index_products_in_cli();
                } catch ( Exception $e ) {
                    WP_CLI::error(
                        sprintf(
                            __( 'Can\'t indexing products. Elastic Error: %s', 'sha-wc2el' ),
                            $e->getMessage()
                        )
                    );
                }
            break;
        }
    }

    // WP_CLI proccessor for product
    public function cli_product( $args, $assoc_args ) {
        if ( ! isset( $args[0] ) || ! in_array( $args[0], $this->_allowed_product_actions ) ) {

            WP_CLI::error(
                sprintf(
                    __( 'You should provide an extra [%s] argument with productID', 'sha-wc2el' ),
                    implode( ' | ', $this->_allowed_product_actions ),
                )
            );
        }

        if ( ! isset( $args[1] )  ) {
            WP_CLI::error(
                __( 'You should pass productID as second positional argument.', 'sha-wc2el' )
            );
        }

        $product_id = (int)$args[1]; 

        switch ( $args[0] ) {

            // Show product data in index
            case 'stat':

                try {
                    $response = $this->elastic_request( 'GET', $this->_elastic_settings['index'] . '/_doc/' . $product_id );

                    if ( isset( $response['_source'] ) ) {
                        $product_data = array();

                        foreach ( $response['_source'] as $k => $v ) {
                            $v = empty( $v ) ? '-' : $v;
                            $product_data[] = array( $k, $v );
                        }

                        $key_length = apply_filters( 'sha_wc2el_table_key_length', 20 );
                        $val_length = apply_filters( 'sha_wc2el_table_val_length', 100 );

                        $this->output_data_as_table( $product_data, $key_length, $val_length );
                    }
                } catch ( Exception $e ) {
                    WP_CLI::error(
                        sprintf(
                            __( 'Can\'t get stat for product [%d]. Elastic Error: %s', 'sha-wc2el' ),
                            $product_id,
                            $e->getMessage()
                        )
                    );
                }

            break;

            // Add product to index
            case 'add':
                $product = wc_get_product( $product_id );

                if ( ! $product ) {
                    WP_CLI::error( __( 'Product with given ID not found', 'sha-wc2el' ) );
                }

                try {

                    $data = $this->get_product_fields( $product );

                    $this->index_document( $product_id, $data );

                    WP_CLI::success(
                        sprintf(
                            __( 'Product [%s] indexed successfully.', 'sha-wc2el' ),
                            $product->get_name()
                        )
                    );
                } catch ( Exception $e ) {
                    WP_CLI::error(
                        sprintf(
                            __( 'Can\'t add product [%s]. Elastic Error: %s', 'sha-wc2el' ),
                            $product->get_name(),
                            $e->getMessage()
                        )
                    );
                }
            break;

            // Delete product from index
            case 'delete':
                try {

                    $this->delete_document( $product_id );

                    WP_CLI::success(
                        sprintf(
                            __( 'Product with ID [%d] deleted successfully.', 'sha-wc2el' ),
                            $product_id
                        )
                    );
                } catch ( Exception $e ) {
                    WP_CLI::error(
                        sprintf(
                            __( 'Can\'t delete product with ID [%d]. Elastic Error: %s', 'sha-wc2el' ),
                            $product_id,
                            $e->getMessage()
                        )
                    );
                }
            break;
        }
    }

    // Check, if index exists in Elastic
    private function is_index_exists() {

        $indices = $this->elastic_request( 'GET', '_cat/indices?format=json' );

        foreach ( $indices as $item ) {
            if ( isset( $item['index'] ) && $item['index'] === $this->_elastic_settings['index'] ) {
                return true;
            }
        }

        return false;
    }

    // Test Elastic connection
    private function test_connection() {
        return $this->elastic_request( 'GET', '' );
    }

    // Create index
    private function create_index() {

        $index_structure = $this->get_elastic_index_structure();

        return $this->elastic_request( 'PUT', $this->_elastic_settings['index'], $index_structure );
    }

    // Delete index
    private function delete_index() {
        return $this->elastic_request( 'DELETE', $this->_elastic_settings['index'] );
    }

    // Get index stat data
    private function get_index_stat_data() {

        $index = $this->_elastic_settings['index'];

        $index_stat = array(
            'index_name'        => $index,
            'index_size'        => '-',
            'records_count'     => '-',
            'last_reindex'      => '-'
        );

        $stats = $this->elastic_request( 'GET', $index . '/_stats' );
        $stats = $stats['indices'][ $index ]['total'];

        if ( isset( $stats['docs']['count'] ) ) {
            $index_stat['records_count'] = $stats['docs']['count'];
        }

        if ( isset( $stats['store']['size_in_bytes'] ) ) {

            $size_bytes = $stats['store']['size_in_bytes'];

            if ( $size_bytes > 1048576 ) {
                $size = round( $size_bytes / 1048576, 2 ) . ' Mb';
            } elseif ( $size_bytes > 1024 ) {
                $size = round( $size_bytes / 1024, 2 ) . ' Kb';
            } else {
                $size = $size_bytes . ' Bytes';
            }

            $index_stat['index_size'] = $size;
        }

        // Set last reindex date, if exists
        $last_reindex = get_option( $this->_prefix . 'last_reindex_date' );

        if ( $last_reindex ) {
            $index_stat['last_reindex'] = date_i18n(
                get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
                get_option( $this->_prefix . 'last_reindex_date' )
            );
        }

        return $index_stat;
    }

    // Add document to index
    private function index_document( $id, $data ) {
        return $this->elastic_request( 'PUT', $this->_elastic_settings['index'] . '/_doc/' . $id, $data );
    }

    // Delete document from index
    private function delete_document( $id ) {
        return $this->elastic_request( 'DELETE', $this->_elastic_settings['index'] . '/_doc/' . $id );
    }

    // Index single elastic index item
    public function index_single_item( $product_id, $product = false ) {

        if ( ! $product ) {
            $product = wc_get_product( $product_id );
        }

        if ( ! $product ) {
            throw New Exception( __( 'Product load failed', 'sha-wc2el' ) );
        }

        // If product type isn't allowed, stopping indexing
        if ( ! in_array( $product->get_type(), $this->_wp_allowed_product_types ) ) {
            throw New Exception( __( 'Product type is not allowed', 'sha-wc2el' ) );
        }

        // If product status isn't allowed, stopping indexing
        if ( ! in_array( $product->get_status(), $this->_wp_allowed_product_statuses ) ) {
            throw New Exception( __( 'Product status is not allowed', 'sha-wc2el' ) );
        }

        try {

            $data = $this->get_product_fields( $product );

            $this->index_document( $product_id, $data );

            // In case of post-index actions (logging etc.)
            do_action( 'sha_wc2el_after_single_index', $product_id, $data );
        } catch ( Exception $e ) {
            throw New Exception( $e->getMessage() );
        }
    }

    // Delete single elastic index item
    public function delete_single_item( $product_id ) {

        try {
            $this->delete_document( $product_id );
            // In case of post-delete actions (logging etc.)
            do_action( 'sha_wc2el_after_single_delete', $product_id );
        } catch ( Exception $e ) {
            throw New Exception( $e->getMessage() );
        }
    }

    // Index products
    private function index_products_in_cli() {

        $bulk_size  = $this->_elastic_settings['bulk_amount'];
        $statuses   = $this->_wp_allowed_product_statuses;
        $types      = $this->_wp_allowed_product_types;

        // wc_get_products() will not include variations unless 'type' is set properly.
        // See https://stackoverflow.com/a/79355974
        $product_types = $types;

        $page = 1;
        $created = $updated = $errors = 0;

        // Count parent products to determine total number of batches.
        $args = array(
            'status' => $statuses,
            'limit'  => -1,
            'type'   => $types,
            'return' => 'ids',
        );

        $parent_ids_total = wc_get_products( $args );
        $total_batches    = ceil( count( $parent_ids_total ) / $bulk_size );

        // Initialize progress bar (per batch, not per document).
        $progress = \WP_CLI\Utils\make_progress_bar(
            __( 'Indexing products', 'sha-wc2el' ),
            $total_batches
        );

        while ( true ) {
            // Fetch one page of parent products.
            $parents = wc_get_products(
                array(
                    'limit'   => $bulk_size,
                    'status'  => $statuses,
                    'orderby' => 'ID',
                    'order'   => 'ASC',
                    'page'    => $page,
                    'type'    => $types,
                    'return'  => 'objects',
                )
            );

            if ( empty( $parents ) ) {
                break; // All products processed.
            }

            $page++;
            $all_child_ids = array();
            $payload       = '';

            // Build payload for parent products.
            foreach ( $parents as $product ) {
                $doc = $this->get_product_fields( $product, true );

                $payload .= wp_json_encode(
                    array(
                        'index' => array(
                            '_index' => $this->_elastic_settings['index'],
                            '_id'    => $product->get_id(),
                        ),
                    )
                ) . "\n";

                $payload .= wp_json_encode( $doc ) . "\n";

                // Collect child IDs for variable or grouped products.
                if ( $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) ) {
                    $children = $product->get_children();
                    if ( ! empty( $children ) ) {
                        $all_child_ids = array_merge( $all_child_ids, $children );
                    }
                }

                $all_child_ids = array_unique( $all_child_ids );
            }

            // Fetch children in one query.
            if ( ! empty( $all_child_ids ) ) {
                $children = wc_get_products(
                    array(
                        'include' => $all_child_ids,
                        'limit'   => -1,
                        'type'    => $product_types,
                        'return'  => 'objects',
                    )
                );

                foreach ( $children as $product ) {
                    $doc = $this->get_product_fields( $product, true );

                    $payload .= wp_json_encode(
                        array(
                            'index' => array(
                                '_index' => $this->_elastic_settings['index'],
                                '_id'    => $product->get_id(),
                            ),
                        )
                    ) . "\n";

                    $payload .= wp_json_encode( $doc ) . "\n";
                }
            }

            // Final newline is required by Elasticsearch bulk API.
            $payload .= "\n";

            // Send batch to Elasticsearch.
            if ( ! empty( $payload ) ) {
                try {
                    $response = $this->elastic_request( 'POST', '_bulk', $payload, true );

                    // Parse ES bulk response.
                    foreach ( $response['items'] as $item ) {
                        $result = reset( $item )['result'];
                        $status = reset( $item )['status'];

                        if ( 'created' === $result ) {
                            $created++;
                        }
                        if ( 'updated' === $result ) {
                            $updated++;
                        }
                        if ( $status >= 400 ) {
                            $errors++;
                        }
                    }

                    $progress->tick();
                } catch ( Exception $e ) {
                    WP_CLI::warning(
                        sprintf(
                            __( 'Error while sending batch (page %1$d): %2$s', 'sha-wc2el' ),
                            $page - 1,
                            $e->getMessage()
                        )
                    );
                }
            }
        }

        // Finish progress bar and update last reindex date.
        $progress->finish();
        update_option( $this->_prefix . 'last_reindex_date', time() );

        WP_CLI::success(
            sprintf(
                __( 'Indexing completed. Total: %1$d (created: %2$d, updated: %3$d, errors: %4$d)', 'sha-wc2el' ),
                $created + $updated,
                $created,
                $updated,
                $errors
            )
        );
    }

    // Create Elastic Index Structure
    private function get_elastic_index_structure() {

        $params = array(
            'settings' => array(
                'number_of_shards'   => 1,
                'number_of_replicas' => 0,
            )
        );

        // Types of every product field in Elastic index
        $basic_properties = array(
            'id'                => 'integer',
            'parent_id'         => 'integer',
            'link'              => 'keyword',
            'add_to_cart_link'	=> 'keyword',
            'name'              => 'text',
            'product_type'      => 'keyword',
            'desc'              => 'text',
            'short_desc'        => 'text',
            'image'             => 'keyword',
            'category'          => 'integer',
            'current_price'     => 'float',
            'price'             => 'float',
            'sale_price'        => 'float',
            'rating'            => 'float',
            'stock'             => 'boolean',
            'sku'               => 'keyword',
            'qty'               => 'integer',
            'created_at'        => array( 'type' => 'date', 'format' => 'strict_date_time' ),
            'updated_at'        => array( 'type' => 'date', 'format' => 'strict_date_time' )
        );

        foreach ( $basic_properties as $prop => $type ) {
            if ( ! is_array( $type ) ) {
                $params['mappings']['properties'][ $prop ] = array(
                    'type'	=> $type
                );
            } else {
                $params['mappings']['properties'][ $prop ] = $type;
            }
        }

        $params = apply_filters( 'sha_wc2el_elastic_index_structure', $params );

        return $params;
    }

    // Get product extra fields and prepare for put in Elastic
    private function get_product_fields( $product, $is_batch = false ) {

        $product_id = $product->get_id();

        $product_fields = array(
            'id'                => $product_id,
            'parent_id'         => $product->get_parent_id(),
            'link'              => get_the_permalink( $product_id ),
            'add_to_cart_link'  => $product->add_to_cart_url(),
            'name'              => $product->get_name(),
            'product_type'      => $product->get_type(),
            'desc'              => $product->get_description(),
            'short_desc'        => $product->get_short_description(),
            'image'             => $product->get_image(),
            'category'          => wc_get_product_cat_ids( $product_id ),
            'price'             => (float)$product->get_price(),
            'sale_price'        => (float)$product->get_sale_price(),
            'rating'            => (float)$product->get_average_rating(),
            'stock'             => ( $product->get_stock_status() == 'instock' ) ? true : false,
            'sku'               => $product->get_sku(),
            'qty'               => $product->get_stock_quantity(),
            'created_at'        => $product->get_date_created() ? $product->get_date_created()->date_i18n( 'c' ) : null,
            'updated_at'        => $product->get_date_modified() ? $product->get_date_modified()->date_i18n( 'c' ) : null,
        );

        $product_fields = apply_filters( 'sha_wc2el_product_fields', $product_fields, $product, $is_batch );

        return $product_fields;
    }

    // Output associative data as ASCII table.
    private function output_data_as_table( $data, $k_size = 45, $v_size = 80 ) {

        if ( empty( $data ) ) {
            return;
        }

        // Helper: draw border line.
        $print_border = function() use ( $k_size, $v_size ) {
            WP_CLI::line(
                sprintf(
                    '+%s+%s+',
                    str_repeat( '-', $k_size + 1 ),
                    str_repeat( '-', $v_size + 2 )
                )
            );
        };

        // Helper: draw content row.
        $print_row = function( $key, $value ) use ( $k_size, $v_size ) {
            WP_CLI::line(
                sprintf(
                    '| %s| %s |',
                    $this->mb_str_pad( $key, $k_size ),
                    $this->mb_str_pad( $value, $v_size, ' ', STR_PAD_LEFT )
                )
            );
        };

        // Print top border.
        $print_border();

        foreach ( $data as $row ) {
            list( $k, $v ) = $row;

            if ( is_array( $v ) ) {
                $v = '(array) ' . implode( ', ', $v );
            }

            $v = preg_replace( '#\s+#u', ' ', $v );

            // Split into chunks.
            $chunks = ( mb_strlen( $v ) > $v_size )
                ? $this->mb_wordwrap( $v, $v_size )
                : array( $v );

            foreach ( $chunks as $i => $line ) {
                $print_row( $i === 0 ? $k : ' ', $line );
            }

            // Draw separator under each row group.
            $print_border();
        }
    }

    // Multi-byte str_pad for cyrillic strings support
    private function mb_str_pad( $input, $length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT ) {
        $input_length = mb_strlen( $input );
        $pad_length   = $length - $input_length;

        if ( $pad_length <= 0 ) {
            return $input;
        }

        $pad_string_length = mb_strlen( $pad_string );

        switch ( $pad_type ) {
            case STR_PAD_LEFT:
                $repeat_count = ceil( $pad_length / $pad_string_length );
                return mb_substr( str_repeat( $pad_string, $repeat_count ), 0, $pad_length ) . $input;

            case STR_PAD_RIGHT:
            default:
                $repeat_count = ceil( $pad_length / $pad_string_length );
                return $input . mb_substr( str_repeat( $pad_string, $repeat_count ), 0, $pad_length );
        }
    }

    // Multi-byte wordwrap for cyrillic strings support
    private function mb_wordwrap($text, $width = 20, $break = "\n") {
        $lines = array();
        foreach ( explode( "\n", $text ) as $line ) {
            while ( mb_strlen( $line ) > $width ) {
                $pos = mb_strrpos( mb_substr( $line, 0, $width+1 ), ' ' );
                if ( $pos === false ) {
                    $pos = $width;
                }
                $lines[] = rtrim( mb_substr( $line, 0, $pos ) );
                $line = ltrim( mb_substr( $line, $pos ) );
            }

            $lines[] = $line;
        }
        return $lines;
    }

    //HTTP-request to Elasticsearch
    private function elastic_request( $method, $endpoint, $body = null, $is_bulk = false ) {
        $url = trailingslashit( $this->_elastic_settings['host'][0] ) . ltrim( $endpoint, '/' );


        // Basic args
        $args = array(
            'method'    => strtoupper( $method ),
            'headers'   => array(
                'Authorization' => 'Basic ' . base64_encode( $this->_elastic_settings['username'] . ':' . $this->_elastic_settings['password'] ),
                'Content-Type'  => $is_bulk ? 'application/x-ndjson' : 'application/json',
            ),
            'timeout'   => 30,
            'sslverify' => false,
        );

        // Body
        if ( ! empty( $body ) ) {
            $args['body'] = $is_bulk ? $body : wp_json_encode( $body );
        }

        // Apply filters
        if ( $is_bulk ) {
            $args = apply_filters( 'sha_wc2el_bulk_request_args', $args );
        } else {
            $args = apply_filters( 'sha_wc2el_request_args', $args );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            throw new Exception(
                ( $is_bulk ? 'Elasticsearch Bulk HTTP error: ' : 'Elasticsearch HTTP error: ' ) . $response->get_error_message()
            );

        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            throw new Exception( "Elasticsearch API error ($code): " . print_r( $data, true ) );
        }

        // For bulk output errors inside response
        if ( $is_bulk && isset( $data['errors'] ) && $data['errors'] ) {
            throw new Exception( 'Elasticsearch Bulk response contains errors: ' . print_r( $data, true ) );
        }

        return $data;
    }
}

// Init module instance
function init_wc2el_module() {

    return SHA_WC_To_Elastic::get_instance();
}

add_action( 'plugins_loaded', 'init_wc2el_module', 100 );
