<?php
/*
 * Plugin Name:       WooCommerce To Elastic Product Indexer
 * Description:       WooCommerce Products to Elastic Search Indexer (WC2EL)
 * Version:           0.1.0
 * Author:            Andrew Sh
 * Text Domain:       sha-wc2el
 * Domain Path:       /languages
 */
use Elasticsearch\ClientBuilder;

if ( ! defined( 'ABSPATH' ) )  {
  exit;
}

class SHA_WC_To_Elastic {

	private static $_instance;

	protected $_elastic_client;

	protected $_plugin_name;

	protected $_plugin_file;

	protected $_plugin_slug;

	protected $_prefix;

	protected $_elastic_settings = array();

	protected $_elastic_settings_index = array();

	protected $_allowed_index_actions = array( 'stat', 'create', 'update', 'delete', 'reindex' );

	protected $_allowed_product_actions = array( 'stat', 'add', 'delete' );

	protected $_wp_allowed_product_types = array();

	protected $_wp_allowed_product_statuses = array();


	// Instance of this class
  public static function getInstance() {

		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new SHA_WC_To_Elastic;
			self::$_instance->init();
		}

		return self::$_instance;
	}

	// Base initing function
  public function init() {

    $this->init_variables();
    $this->init_hooks();
    $this->init_cli_support();
	}

  // Initing all variables of class
  private function init_variables() {

    $this->_plugin_name = basename( dirname( __FILE__ , 1 ) );
    $this->_plugin_file = __FILE__;
    $this->_plugin_slug = 'wc2el';
    $this->_prefix = 'sha_wc2el_';

    $blog_url = get_bloginfo( 'url' );
    $blog_url = wp_parse_url( $blog_url );
    
		// Default elastic settings. Set with WC2EL_CREDENTIALS constant in wp-config.php
		// or with add_filter() in functions.php (see below)
    $default_elastic_settings = array(
      'host'        => defined( 'WC2EL_CREDENTIALS' ) ? array( WC2EL_CREDENTIALS ) : array(),
      'index'       => str_replace( '.', '-', $blog_url['host'] ),
      'bulk_amount' => 100
    );

		// Override default elastic settings
    $this->_elastic_settings = apply_filters( 'sha_wc2el_elastic_settings', $default_elastic_settings );

		// Shortlink for elastic index, which used many times in code below
    $this->_elastic_settings_index = array( 'index' => $this->_elastic_settings['index'] );

		// Select only products with this types
    $this->_wp_allowed_product_types = apply_filters( 'sha_wc2el_allowed_product_types', array( 'simple' ) );

		// Select only products with this status
    $this->_wp_allowed_product_statuses = apply_filters( 'sha_wc2el_allowed_product_statuses', array( 'publish' ) );
  }

	// Initing all actions
	private function init_hooks() {

		// Actions for reindex, rebuild and single product indexing

		// usage do_action( 'wc2el_reindex_single_product', $product_id, $product );
    add_action( 'wc2el_reindex_single_product', array( $this, 'reindex_single_item' ), 10, 2 );

    // usage do_action( 'wc2el_create' );
    add_action( 'wc2el_create', array( $this, 'build_index' ) );

    // usage do_action( 'wc2el_update' );
    add_action( 'wc2el_update', array( $this, 'update_index' ) );

    // usage do_action( 'wc2el_delete' );
    add_action( 'wc2el_delete', array( $this, 'remove_index' ) );

    // usage do_action( 'wc2el_reindex' );
    add_action( 'wc2el_reindex', array( $this, 'reindex_all_elastic_items' ) );

    // Load textdomain
    load_plugin_textdomain( 'sha-wc2el', false, $this->_plugin_name . '/languages' );
	}
  
  // Add WP_CLI support
  private function init_cli_support() {

		// Add suport only if cli and woocommerce active
    if ( defined ( 'WP_CLI' ) && WP_CLI && defined( 'WC_VERSION' ) ) {
      WP_CLI::add_command(
      	'wc2el',
      	array( $this, 'wp_cli' ),
      	array(
					'shortdesc' => __( 'CLI for WooCommerce To Elastic plugin', 'sha-wc2el' ),
					'synopsis'  => array(
						array(
							'type'				=> 'positional',
							'name'				=> 'product|index',
							'description'	=> __(
								'Type of action. Allowed values are product and index with an extra args',
								'sha-wc2el'
							),
							'optional'		=> false,
							'repeating'		=> false,
						),
						array(
							'type'				=> 'positional',
							'name'				=> implode( ',', $this->_allowed_product_actions ) . '|' . implode( ',', $this->_allowed_index_actions ),
							'description'	=> sprintf(
																__( 'Type of action with index or product. Allowed values %s (with ProductID as extra ergument) for product and %s for index', 'sha-wc2el' ),
																implode( ', ', $this->_allowed_product_actions ),
																implode( ', ', $this->_allowed_index_actions )
															),
							'optional'		=> false,
							'repeating'		=> false
						),
						array(
							'type'				=> 'positional',
							'name'				=> 'productID',
							'description' => __( 'Product ID', 'sha-wc2el' ),
							'optional'		=> true,
							'repeating'		=> false
						)
					),
					'when'			=> 'after_wp_load',
					'longdesc' 	=> __( '## EXAMPLES' . "\n\n" . 'wp wc2el product add 1012', 'sha-wc2el' ),
				)
    	);
    }
  }

  // Create Elastic Index Structure
  private function get_elastic_index_structure() {

		// Types of every product field in ElasticSearch index
		$basic_properties = array(
			'id'								=> 'integer',
			'parent_id'					=> 'integer',
			'link'							=> 'text',
			'add_to_cart_link'	=> 'text',
			'name'							=> 'text',
			'product_type'			=> 'text',
			'desc'							=> 'text',
			'short_desc'				=> 'text',
			'image'							=> 'text',
			'category'					=> 'integer',
			'current_price'			=> 'float',
			'price'							=> 'float',
			'sale_price'				=> 'float',
			'rating'						=> 'float',
			'stock'							=> 'boolean',
			'sku'								=> 'text',
			'qty'								=> 'integer',
			'created_at'				=> 'integer',
			'updated_at'				=> 'integer'
		);

    $params = $this->_elastic_settings_index;

    foreach ( $basic_properties as $prop => $type ) {
			$params['body']['mappings']['properties'][ $prop ] = array(
				'type'	=> $type
			);
		}

    $params = apply_filters( 'sha_wc2el_elastic_index_structure', $params );

    return $params;
  }

  // Create index
  private function create_index() {
    
    $client = $this->get_elastic_connection();

    try {
      $index_structure = $this->get_elastic_index_structure();

			// If index exists, put mapping and settings
			if ( $client->indices()->exists( $this->_elastic_settings_index ) ) {
				throw New Exception(
					sprintf(
						__( 'Index with a name [%s] already exists. Try to rebuild index.', 'sha-wc2el' ),
						$this->_elastic_settings['index']
					)
				);
			} else {
      	$client->indices()->create( $index_structure );
			}
    } catch ( Exception $e ) {
      throw New Exception( $e->getMessage() );
    }
  }

  // Update index
  private function update_index() {
    
    $client = $this->get_elastic_connection();

    try {
      $index_structure = $this->get_elastic_index_structure();

			// If index exists, put mapping and settings
			if ( $client->indices()->exists( $this->_elastic_settings_index ) ) {
				// If structure contain settings, put settings
				if ( $this->akne( $index_structure, array( 'body', 'settings' ) ) ) {

					// Non dynamic settings like anlysis needs closing index and open it after putting
					$reopen_index = apply_filters( 'sha_wc2el_reopen_index', false );

					if ( $reopen_index ) {
						$client->indices()->close( $this->_elastic_settings_index );
					}

					$settings_data = $this->_elastic_settings_index;
					$settings_data['body']['settings'] = $index_structure['body']['settings'];
					$client->indices()->putSettings( $settings_data );

					if ( $reopen_index ) {
						$client->indices()->open( $this->_elastic_settings_index );
					}
				}


				// If structure contain mappings, put mappings
				if ( $this->akne( $index_structure, array( 'body', 'mappings' ) ) ) {
					$mapping_data = $this->_elastic_settings_index;
					$mapping_data['body']['_source'] = array( 'enabled' => true );
					$mapping_data['body']['properties'] = $index_structure['body']['mappings']['properties'];
					$client->indices()->putMapping( $mapping_data );
				}
			} else {
      	$client->indices()->create( $index_structure );
			}
    } catch ( Exception $e ) {
      throw New Exception( $e->getMessage() );
    }
  }

  // Delete index
  private function remove_index() {
    
    $client = $this->get_elastic_connection();

		try {
			if ( $client->indices()->exists( $this->_elastic_settings_index ) ) {
				$client->indices()->delete( $this->_elastic_settings_index );
			}
		} catch ( Exception $e ) {
			throw New Exception( $e->getMessage() );
		}
  }


  // Build index (delete and create)
  public function build_index() {
		$this->create_index();
	}

  // Update index (change settings of existing index)
  public function modify_index() {
		$this->update_index();
	}

  // Delete index
  public function delete_index() {
		$this->remove_index();
	}

  // Reindex single elastic index item
  public function reindex_single_item( $product_id, $product = false ) {

		// If product type isn't allowed, stopping indexing
    if ( ! in_array( $product->get_type(), $this->_wp_allowed_product_types ) ) {
      throw New Exception( __( 'Product type is not allowed', 'sha-wc2el' ) );
		}

		// If product status isn't allowed, stopping indexing
    if ( ! in_array( $product->get_status(), $this->_wp_allowed_product_statuses ) ) {
			throw New Exception( __( 'Product status is not allowed', 'sha-wc2el' ) );

		}

    $params = $this->_elastic_settings_index;
    $product_fields = $this->get_product_fields( $product );

    $params['id'] = $product_id;
    $params['body'] = $product_fields;

    $client = $this->get_elastic_connection();
		try {
    	$response = $client->index( $params );
		} catch ( Exception $e ) {
      throw New Exception( $e->getMessage() );
    }
  }
  
  // Reindex all products
  public function reindex_all_elastic_items() {

    try {
			$total_products_for_reindex = $this->total_products_for_reindex();
      if ( $total_products_for_reindex ) {

        $client = $this->get_elastic_connection();

        for ( $i = 0; $i < $total_products_for_reindex['pages']; $i++ ) {
      
          $bulk_data = $this->get_products_and_prepare_bulk_data( $i );

          //Bulk add data to Elastic Search index
          if ( ! empty( $bulk_data['data']['body'] ) ) {
            $responses = $client->bulk( $bulk_data['data'] );
            foreach ( $response['items'] as $item ) {
              if ( isset( $item['index']['error'] ) ) {
                throw New Exception(
                  sprintf(
                  	__( 'Can\'t bulk add data to Elastic Search. Error on item with id %d. Error type: %s. Error reason: %s', 'sha-wc2el' ),
                    $item['index']['_id'],
                    $item['index']['error']['type'],
                    $item['index']['error']['reason']
                  )
                );
              }
            }
          }
        }
        update_option( $this->_prefix . 'last_reindex_date', time() );
      }
    } catch ( Exception $e ) {
      throw new Exception( $e->getMessage() );
    }  
  }

  // Load products and prepare data for bulk indexing
  public function get_products_and_prepare_bulk_data( $page ) {

    $params = array();

    $product_size_type = apply_filters( 'sha_wc2el_product_size_type', 'single' );

    $args = array(
      'status'        => $this->_wp_allowed_product_statuses,
      'product_type'  => $this->_wp_allowed_product_types,
      'limit'         => $this->_elastic_settings['bulk_amount'],
      'offset'        => $page * $this->_elastic_settings['bulk_amount'],
      'paginate'      => true
    );

    $products = wc_get_products( $args );
    foreach ( $products->products as $product ) {

      // Add only instock products, if checkbox checked
      if ( ( $product->get_manage_stock() == 1 ) && ( $product->get_stock_status() == 'outofstock' ) ) {
        continue;
      }

      $product_image = ( $product->get_image_id() ) ? wp_get_attachment_image_url( $product->get_image_id(), $product_size_type ) : '';

      $params['data']['body'][] = array(
        'index' => array(
          '_index' => $this->_elastic_settings['index'],
          '_id' => $product->get_id()
        )
      );

      $product_fields = $this->get_product_fields( $product );

      $params['max_pages'] = $products->max_num_pages;
      $params['total'] = $products->total;
      $params['data']['body'][] = $product_fields;
      
      $params = apply_filters( 'sha_wc2el_after_product_fields', $params, $product );
    }

    return $params;
  }

  // Elastic Search Connector
  public function get_elastic_connection() {

		if ( ! $this->akne( $this->_elastic_settings, array( 'host' ) ) ) {
			WP_CLI::error( __( 'Can\'t connect to Elastic. Host is not defined. Add WC2EL_CREDENTIALS to wp-config.php or use [sha_wc2el_elastic_settings] filter', 'sha-wc2el' ) );
		}

		if ( ! isset( $this->_client ) ) {
	    require dirname( __FILE__ ) . '/vendor/autoload.php';
			try {
				$client = ClientBuilder::create()->setHosts( $this->_elastic_settings['host'] )->build();
				$this->_elastic_client = $client;
			} catch ( Exception $e ) {
				throw New Exception( $e->getMessage() );
			}
		}

		return $this->_elastic_client;
  }

  // WP_CLI processor
  public function wp_cli( $args, $assoc_args ) {

    if ( ! in_array( $args[0], array( 'product', 'index' ) ) ) {
      WP_CLI::error( __( 'Unknown argument. See \'wp help wc2el\' for supported arguments', 'sha-wc2el' ) );
    }

    $client = $this->get_elastic_connection();

    switch ( $args[0] ) {

      case 'index':
                  
        if ( ! isset( $args[1] ) || ! in_array( $args[1], $this->_allowed_index_actions ) ) {

					$index_actions = $this->get_actions_with_brackets( $this->_allowed_index_actions );

          WP_CLI::error(
          	sprintf(
          		__( 'You should provide an extra %s argument', 'sha-wc2el' ),
          		$index_actions,
          	)
          );
        }

        switch ( $args[1] ) {
          
          case 'stat':

            $stat = $this->get_index_stat_data();

            if ( ! empty( $stat ) ) {

              $this->print_stat_line();

              $this->print_stat_line(
                __( 'Elastic Version', 'sha-wc2el' ),
                $stat['version']
              );

              $this->print_stat_line(
                __( 'Index Name', 'sha-wc2el' ),
                $stat['name']
              );

              $this->print_stat_line(
                __( 'Index Ping', 'sha-wc2el' ),
                $stat['ping']
              );

              $this->print_stat_line(
                __( 'Products in index', 'sha-wc2el' ),
                $stat['records_count']
              );

              $this->print_stat_line(
                __( 'Index Size', 'sha-wc2el' ),
                $stat['index_size']
              );

              $this->print_stat_line(
                __( 'Last Full Reindex', 'sha-wc2el' ),
                $stat['last_reindex']
              );
          
            } else {
              WP_CLI::error(
              	sprintf(
              		__( 'Can\'t get stat for index [%s]', 'sha-wc2el' ),
              		$this->_elastic_settings['index']
              	)
              );
            }
          
          	break;
          
          case 'reindex':

          	$total_products_for_reindex = $this->total_products_for_reindex();

            if ( $total_products_for_reindex ) {

              $progress = WP_CLI\Utils\make_progress_bar( __( 'Reindex Progress', 'sha-wc2el' ), $total_products_for_reindex['pages'] );
          
              for ( $i = 0; $i < $total_products_for_reindex['pages']; $i++ ) {

                $bulk_data = $this->get_products_and_prepare_bulk_data( $i );

                //Bulk add data to Elastic Search index
                if ( ! empty( $bulk_data['data']['body'] ) ) {

                  $response = $client->bulk( $bulk_data['data'] );

                  foreach ( $response['items'] as $item ) {

                    if ( isset( $item['index']['error'] ) ) {
                      throw New Exception(
                        sprintf(
                          __( 'Can\'t bulk add data to Elastic Search. Error on item with id %d. Error type: %s. Error reason: %s', 'sha-wc2el' ),
                          $item['index']['_id'],
                          $item['index']['error']['type'],
                          $item['index']['error']['reason']
                        )
                      );
                    }
                  }
                  $progress->tick();
                }
              }
              $progress->finish();

              update_option( $this->_prefix . 'last_reindex_date', time() );

              WP_CLI::success( __( 'Reindex completed', 'sha-wc2el' ) );
            }          
          	break;
          
          case 'create':

            try {

              $this->create_index();

              WP_CLI::success( __(	'Build completed. Start reindex [ wp wc2el index reindex ] to add products to index', 'sha-wc2el' ) );
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

          case 'delete':

            try {

              $this->delete_index();

              WP_CLI::success(
              	__(	'Index deleted successfully.', 'sha-wc2el' )
              );
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

          case 'update':
            try {

              $this->modify_index();

              WP_CLI::success( __(	'Update completed. Start reindex [ wp wc2el index reindex ] to add products to index', 'sha-wc2el' ) );
            } catch ( Exception $e ) {
              WP_CLI::error(
              	sprintf(
              		__( 'Can\'t update index [%s]. Elastic Error: %s', 'sha-wc2el' ),
              		$this->_elastic_settings['index'],
              		$e->getMessage()
              	)
              );
            }
          	break;
        }

      	break;

      case 'product':

        if ( ! in_array( $args[1], $this->_allowed_product_actions ) ) {

					$product_actions = $this->get_actions_with_brackets( $this->_allowed_product_actions );					

          WP_CLI::error(
          	sprintf(
          		__( 'You should provide an extra %s argument with productID', 'sha-wc2el' ),
          		$product_actions,
          	)
          );
          break;
        }

        if ( ! isset( $args[2] )  ) {
          WP_CLI::error(
          	__( 'You should pass productID as third argument.', 'sha-wc2el' )
          );
          break;
        }

				$product = wc_get_product( $args[2] );

        if ( ! $product ) {
          WP_CLI::error( __( 'Product with given ID not found', 'sha-wc2el' ) );
          break;
        }

        switch ( $args[1] ) {
          
          case 'stat':

            $params = $this->_elastic_settings_index;
            $params['id'] = $args[2];

            try {

              $response = $client->get( $params );

							// Size of label cell in output table
              $label_size = 20;

							// Size of value cell in output table
							$value_size = 100;

							// Empty line
              $this->print_stat_line( '', '', $label_size, $value_size );

              if ( ! $this->akne( $response, array( '_source' ) ) ) {
								WP_CLI::error(
									sprintf(
										__( 'Can\'t get stat for product [%s]. Empty result returned', 'sha-wc2el' ),
										$product->get_name()
									)
								);
							}

              foreach ( $response['_source'] as $k => $v ) {

								// If value is array, convert to string and add (array) prefix
								if ( is_array( $v ) ) {
									$v = '(array) ' . implode( ', ', $v );
								}

								$v = preg_replace( '#\s+#i', ' ', $v );

								if ( mb_strlen( $v ) > ( $value_size - 2 ) ) {
									$v = explode( '|', wordwrap( $v, ( $value_size - 2 ), '|' ) );

									// If value is exceed value_size, split it to multiline. In this
									// case label must contain value only on first line
									$first = true;
									$last = count( $v );
									$i = 0;

									foreach ( $v as $line ) {
										$i++;
										$label = $first ? $k : ' ';
										// If value is multline, don't output separation line until
										// the end of all value
										$close_line = ( $i == $last ) ? 1 : 0;
										$this->print_stat_line( $label, $line, $label_size, $value_size, $close_line );
										$first = false;
									}
								} else {
									$this->print_stat_line( $k, $v, $label_size, $value_size );
								}
							}
            } catch ( Exception $e ) {
              WP_CLI::error(
              	sprintf(
              		__( 'Can\'t get stat for product [%s]. Elastic Error: %s', 'sha-wc2el' ),
              		$product->get_name(),
              		$e->getMessage()
              	)
              );
            }

          	break;

          case 'delete':

            $params = $this->_elastic_settings_index;
            $params['id'] = $args[2];

            try {

              $client->delete( $params );

              WP_CLI::success(
              	sprintf(
              		__( 'Product [%s] deleted from index [%s]', 'sha-wc2el' ),
              		$product->get_name(),
              		$this->_elastic_settings['index']
              	)
              );
            } catch ( Exception $e ) {
              WP_CLI::error(
              	sprintf(
              		__( 'Can\'t delete product [%s]. Elastic Error: %s', 'sha-wc2el' ),
              		$product->get_name(),
              		$e->getMessage()
              	)
              );
            }
          	break;
          
          case 'add':
            
            try {

              $this->reindex_single_item( $args[2], $product );

              WP_CLI::success(
              	sprintf(
              		__( 'Product [%s] added/updated to index [%s]', 'sha-wc2el' ),
              		$product->get_name(),
              		$this->_elastic_settings['index']
              	)
              );
            } catch( Exception $e ) {
              WP_CLI::error(
              	sprintf(
              		__( 'Can\'t add product [%s]. Error: %s', 'sha-wc2el' ),
              		$product->get_name(),
              		$e->getMessage()
              	)
              );
            }

          	break;
        }
      	break;
    }
  }

  // Return elastic settings
  public function get_elastic_settings() {

    return $this->_elastic_settings;
  }

  // Return stat index data array
  public function get_index_stat_data() {
    
    $index_stat = array(
    	'version'					=> '-',
      'name'						=> $this->_elastic_settings['index'],
      'ping'						=> __( 'No', 'sha-wc2el' ),
			'index_size'			=> 0,
      'records_count'		=> 0,
      'last_reindex'		=> '-'
    );

    $client = $this->get_elastic_connection();

    try {
			// Get client info for ElasticSearch version data
      $info = $client->info();

      // Get index stat for other data
      $stat = $client->indices()->stats( $this->_elastic_settings_index );

			// Ping ElasticSearch
			if ( $client->ping() ) {
				$index_stat['ping'] = __( 'Yes', 'sha-wc2el' );
			}

			// If index not exists, return empty data
			if ( ! $this->akne( $stat, array( 'indices', $this->_elastic_settings['index'] ) ) ) {
				return $index_stat;
			}

      $index_data = $stat['indices'][ $this->_elastic_settings['index'] ]['total'];

			// Set ElasticSearch version
      if ( $this->akne( $info, array( 'version', 'number' ) ) ) {
				$index_stat['version'] = $info['version']['number'];
			}

			// Set last reindex date, if exists
      $last_reindex = get_option( $this->_prefix . 'last_reindex_date' );

			if ( $last_reindex ) {
				$index_stat['last_reindex'] = date(
					get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
					get_option( $this->_prefix . 'last_reindex_date' )
				);
			}

			// Set total products in index
      if ( $this->akne( $index_data, array( 'docs', 'count' ) ) ) {
      	$index_stat['records_count'] = $index_data['docs']['count'];
			}

			// Set index size in bytes/Kb/Mb
			if ( $this->akne( $index_data, array( 'store', 'size_in_bytes' ) ) ) {
      	$size_in_bytes = $index_data['store']['size_in_bytes'];

				if ( $size_in_bytes > 1048576 ) {
					$index_stat['index_size'] = round( $size_in_bytes / 1048576, 2 ) . 'Mb';
				}
				
				if ( ( $size_in_bytes > 1024 ) && ( $size_in_bytes < 1048576 ) ) {
					$index_stat['index_size'] = round( $size_in_bytes/1024 ) . 'Kb';
				}
				
				if ( ( $size_in_bytes > 0 ) && ( $size_in_bytes < 1024 ) ) {
					$index_stat['index_size'] = $size_in_bytes . ' Bytes';
				}
			}
    } catch ( Exception $e ) {
			$message = json_decode( $e->getMessage() );
			printf(
				__( 'Elastic Exception: "%s", "%s"' . "\n" ),
				$message->error->type,
				$message->error->reason
			);
    }

    return $index_stat;
  }

  // Get product extra fields and prepare for put in Elastic Search
  private function get_product_fields( $product ) {

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
      'created_at'        => strtotime( $product->get_date_created()->__toString() ),
      'updated_at'        => strtotime( $product->get_date_modified()->__toString() )
    );

    $product_fields = apply_filters( 'sha_wc2el_product_extra_fields', $product_fields, $product );

    return $product_fields;
  }

  // Return total amount of products and iteration amount to reindex
  private function total_products_for_reindex() {

    $args = array(
      'status'        => $this->_wp_allowed_product_statuses,
      'product_type'  => $this->_wp_allowed_product_types,
      'limit'         => $this->_elastic_settings['bulk_amount'],
      'offset'        => 0,
      'return'        => 'ids',
      'paginate'      => true
    );

		$products = wc_get_products( $args );

    if ( $products ) {
      return array(
        'total' => $products->total,
        'pages' => $products->max_num_pages
      );
    }
    
    return;
  }

  // Print cli stat line
  // Line contain key and value in ElasticSearch index
  // By default, key cell fit 45 chars, value cell fit 45 chars

  // @type string		$k				Key name in ElasticSearch index
  // @type string		$v				Value in ElasticSearch index
  // @type int			$k_size		Key cell output length in chars
  // @type int			$v_size		Value cell output length in chars
  // @type int			$close		Print closing line below row or not
  private function print_stat_line( $k = '', $v = '', $k_size = 45, $v_size = 45, $close = 1 ) {

    if ( ! empty( $k ) ) {
      WP_CLI::line(
        sprintf(
          '| %s|%s |',
          $k . str_repeat( ' ', $k_size - mb_strlen( $k ) ),
          str_repeat( ' ', $v_size - 1 - mb_strlen( $v ) ) . $v
        )
      );
    }

    if ( $close == 1 ) {
			WP_CLI::line( 
				sprintf(
					'+%s+%s+',
					str_repeat( '-', $k_size + 1 ),
					str_repeat( '-', $v_size )
				)
			);
		}
  }

  // Check, if Array Key exists and Not Empty
  public function akne( $array, $keys = '' ) {

    if ( ! is_array( $array ) ) {
      return false;
    }

    if ( empty( $keys ) ) {
      return false;
    }

    if ( is_array( $keys ) ) {
      $arr = $array;
      foreach ( $keys as $key ) {
        if ( ! isset( $arr[ $key ] ) || empty( $arr[ $key ] ) ) {
          return false;
        } else {
          $arr = $arr[ $key ];
        }
      }
    } else {
      if ( ! isset( $array[ $keys ] ) || empty( $array[ $keys ] ) ) {
        return false;
      }
    }
        
    return true;
  }

  // Add brackets around actions values and or before last element
  private function get_actions_with_brackets( $actions ) {

		if ( ! is_array( $actions ) || empty( $actions ) ) {
			return;
		}

		if ( count( $actions ) == 1 ) {
			return '[' . $actions[0] . ']';
		}

		array_walk( $actions, function ( &$v ) { $v = '[' . $v . ']'; } );
		$last_action = array_pop( $actions );

		return sprintf( __( '%s or %s', 'sha-wc2el' ), implode( ', ', $actions ), $last_action );
	}
}

// Init module instance
function init_wc2el_module() {

  return SHA_WC_To_Elastic::getInstance();
}

add_action( 'init', 'init_wc2el_module', 100 );
