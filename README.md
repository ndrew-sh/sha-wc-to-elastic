# sha-wc-to-elastic
CLI plugin for indexing WooCommerce products to ElasticSearch. This is not a UI-interface plugin. You need a minimal PHP and ElasticSearch skills to use it.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [Actions and Filters](#actions-and-filters)
- [Examples](#examples)

## Requirements

- [WP-CLI](https://github.com/wp-cli/wp-cli)
- [ElasticSearch](https://www.elastic.co/guide/en/elasticsearch/reference/current/install-elasticsearch.html)
- [ElasticSearch-PHP](https://github.com/elastic/elasticsearch-php/)
- [WooCommerce](https://wordpress.org/plugins/woocommerce/)

## Installation

Install and configure ElasticSearch. Import ElasticSearch-PHP client with composer `composer require elasticsearch/elasticsearch`. Clone this repo or upload and unzip archive to `wp-content/plugins` directory. Define ElasticSearch host credentials in `wp-config.php`

```php
define( 'WC2EL_CREDENTIALS', 'http[s]://USER:PASSWORD@HOST:PORT' );
```

or with `sha_wc2el_elastic_settings` action in your theme's `functions.php`

```php
add_filter( 'sha_wc2el_elastic_settings', function( $default_elastic_settings ) {

	// ElasticSearch connection credentials
	$default_elastic_settings['host'] = 'http[s]://USER:PASSWORD@HOST:PORT';

	// Index name. Use it, if you want to set specific name, otherwise skip this setting
	$default_elastic_settings['index'] = 'INDEX-NAME';

	// Products for reindex per one iteration 
	$default_elastic_settings['bulk_amount'] = 10;
	
	return $default_elastic_settings;
} );
```

## Usage

This plugin support 2 types of actions: managing indexes and indexing products. Run `wp wc2el help` to see all arguments.

### Managing indexes

`wp wc2el index` supports 3 actions: `rebuild`, `reindex` and `stat`.

`wp wc2el index stat` shows current state of index:
```bash
+----------------------------------------------+---------------------------------------------+
| Elastic Version                              |                                       8.6.2 |
+----------------------------------------------+---------------------------------------------+
| Index Name                                   |                                   localhost |
+----------------------------------------------+---------------------------------------------+
| Index Ping                                   |                                         Yes |
+----------------------------------------------+---------------------------------------------+
| Products in index                            |                                         197 |
+----------------------------------------------+---------------------------------------------+
| Index Size                                   |                                       218Kb |
+----------------------------------------------+---------------------------------------------+
| Last Full Reindex                            |                 September 26, 2024 12:58 pm |
+----------------------------------------------+---------------------------------------------+
```

`wp wc2el index rebuild` creating a new index (if index exists, it will be deleted and recreated). Index name generating from hostname with dash instead of dots (sitename-com for example)

`wp wc2el index reindex` batch fetching all woocommerce products and add them to ElasticSearch index. Progress bar show you all proccess.

### Indexing products

For single product actions you can use `wp wc2el product`. It supports 3 actions: `add`, `delete` and `stat`.

`wp wc2el product add ID` add a product with ID to ElasticSearch index.

`wp wc2el product delete ID` delete a product with ID from ElasticSearch index.

`wp wc2el product stat ID` show product data with ID in ElasticSearch index.

```bash
+---------------------+----------------------------------------------------------------------------------------------------+
| id                  |                                                                                               8073 |
+---------------------+----------------------------------------------------------------------------------------------------+
| parent_id           |                                                                                                  0 |
+---------------------+----------------------------------------------------------------------------------------------------+
| link                |                                                http://localhost/product/endeavor-daytrip-backpack/ |
+---------------------+----------------------------------------------------------------------------------------------------+
| add_to_cart_link    |                                                                                  ?add-to-cart=8073 |
+---------------------+----------------------------------------------------------------------------------------------------+
| name                |                                                                          Endeavor Daytrip Backpack |
+---------------------+----------------------------------------------------------------------------------------------------+
| product_type        |                                                                                             simple |
+---------------------+----------------------------------------------------------------------------------------------------+
| desc                |     With more room than it appears, the Endeavor Daytrip Backpack will hold a whole day's worth of |
|                     |  books, binders and gym clothes. The spacious main compartment includes a dedicated laptop sleeve. |
|                     |         Two other compartments offer extra storage space. <ul> <li>Foam-padded adjustable shoulder |
|                     |      straps.</li> <li>900D polyester.</li> <li>Oversized zippers.</li> <li>Locker loop.</li> </ul> |
+---------------------+----------------------------------------------------------------------------------------------------+
| short_desc          |                                          This is a simple product called Endeavor Daytrip Backpack |
+---------------------+----------------------------------------------------------------------------------------------------+
| image               |                                                                      <img width="300" height="300" |
|                     |                      src="http://localhost/wp-content/uploads/woocommerce-placeholder-300x300.png" |
|                     |    class="woocommerce-placeholder wp-post-image" alt="Placeholder" decoding="async" loading="lazy" |
|                     |              srcset="http://localhost/wp-content/uploads/woocommerce-placeholder-300x300.png 300w, |
|                     |                      http://localhost/wp-content/uploads/woocommerce-placeholder-450x450.png 450w, |
|                     |                      http://localhost/wp-content/uploads/woocommerce-placeholder-100x100.png 100w, |
|                     |                      http://localhost/wp-content/uploads/woocommerce-placeholder-600x600.png 600w, |
|                     |                   http://localhost/wp-content/uploads/woocommerce-placeholder-1024x1024.png 1024w, |
|                     |                      http://localhost/wp-content/uploads/woocommerce-placeholder-150x150.png 150w, |
|                     |                      http://localhost/wp-content/uploads/woocommerce-placeholder-768x768.png 768w, |
|                     |          http://localhost/wp-content/uploads/woocommerce-placeholder.png 1200w" sizes="(max-width: |
|                     |                                                                            300px) 100vw, 300px" /> |
+---------------------+----------------------------------------------------------------------------------------------------+
| category            |                                                                         (array) 256, 255, 250, 252 |
+---------------------+----------------------------------------------------------------------------------------------------+
| current_price       |                                                                                                 33 |
+---------------------+----------------------------------------------------------------------------------------------------+
| price               |                                                                                                 33 |
+---------------------+----------------------------------------------------------------------------------------------------+
| sale_price          |                                                                                                  0 |
+---------------------+----------------------------------------------------------------------------------------------------+
| rating              |                                                                                                  0 |
+---------------------+----------------------------------------------------------------------------------------------------+
| stock               |                                                                                                  1 |
+---------------------+----------------------------------------------------------------------------------------------------+
| sku                 |                                                                                            24-WB06 |
+---------------------+----------------------------------------------------------------------------------------------------+
| qty                 |                                                                                                 10 |
+---------------------+----------------------------------------------------------------------------------------------------+
| created_at          |                                                                                         1675173726 |
+---------------------+----------------------------------------------------------------------------------------------------+
| updated_at          |                                                                                         1727298394 |
+---------------------+----------------------------------------------------------------------------------------------------+
| size                |                                                                                         (array) OS |
+---------------------+----------------------------------------------------------------------------------------------------+
```

## Actions and Filters

### Actions

**wc2el_reindex_single_product**
- Reindex single product in your plugin or theme (see example section).

```php
do_action( 'wc2el_reindex_single_product', $product_id, $product );
```

**wc2el_rebuild**
- Rebuilding (delete and create) index in your plugin or theme (for example, when you adding a new attribute in your plugin or themes).

```php
do_action( 'wc2el_rebuild' );
```

**wc2el_reindex**
- Reindexing products in your plugin or theme (for example, when you changing you permalink structure, you should update links in ElasticSearch).

```php
do_action( 'wc2el_reindex' );
```

### Filters
**sha_wc2el_elastic_settings**
- Override ElasticSearch settings (host, index name and bulk amount). See Installation section.

**sha_wc2el_allowed_product_types**
- Filter products with only with selected types on reindex or single product indexing. Default value is `simple`.

```php
add_filter( 'sha_wc2el_allowed_product_types', function( $allowed_product_types ) {
	$allowed_product_types[] = 'variable';
	
	return $allowed_product_types;
} );
```

**sha_wc2el_allowed_product_statuses**
- Filter products with only with selected statuses on reindex or single product indexing. Default value is `publish`.

```php
add_filter( 'sha_wc2el_allowed_product_statuses', function( $allowed_product_statuses ) {
	$allowed_product_statuses[] = 'draft';
	
	return $allowed_product_statuses;
} );
```

**sha_wc2el_elastic_index_structure**
- Add an extra data to index settings (analysers, filters, etc.). See Example section.

**sha_wc2el_product_image_size_type**
- Filter product image size to store in index. Default value is `simple`.

```php
add_filter( 'sha_wc2el_product_image_size_type', function( $sha_wc2el_product_image_size_type ) {	
	return 'thumbnail';
} );
```

**sha_wc2el_after_product_fields**
- If you want to add an extra specific data to bulk product only (`sha_wc2el_product_extra_fields` runs before this action for every product).

**sha_wc2el_product_extra_fields**
Add an extra field to default product fields array. See Example section.

## Examples
### Add custom attribute to index

By default, this plugin supports only default product fields (see `get_product_fields` method). If you want to add custom attributes or custom data, you can use filters to do this. For example, your products has attribute 'Color', which you want to add in index. First, we need to add this field to mappings. Use filter `sha_wc2el_elastic_index_structure` to do this. This filter calls before index creation. You can use it to set custom analyzers, formatters, etc. (see [ElasticSearch docs](https://www.elastic.co/guide/en/elasticsearch/reference/current/index.html) for more info) In our case a code is:

```php
add_filter( 'sha_wc2el_elastic_index_structure', function( $params ) {
  $params['body']['settings']['analysis']	= array(
    'filter'	=> array(
      'convert_spaces_to_single_space'	=> array(
      	'type'         => 'pattern_replace',
        'pattern'      => '\\s+',
        'replacement'  => ' '
      ),
    ),
    'analyzer'	=> array(
      'format'	=> array(
        'tokenizer'  => 'keyword',
        'filter'     => array( 'trim', 'convert_spaces_to_single_space' )
      )
    )
  );

  $params['body']['mappings']['properties']['color'] = array(
    'type'      => 'text',
    'analyzer'  => 'format',
  );

  return $params;
} );
```
Do this before rebuild, otherwise on rebuild your previous data will be lost.

Second, we need to get this field from product and add to default fields list. Use filter `sha_wc2el_product_extra_fields` to do this. In our case a code is:
```php
add_filter( 'sha_wc2el_product_extra_fields', function( $product_extra_fields, $product ) {
  foreach ( $product->get_attributes() as $key => $attribute ) {

    if ( $key == 'color' ) {
      $product_extra_fields['color'] = array_values( $attribute->get_options() );
    }
  }

  return $product_extra_fields;
}, 10, 2);
```
Now, after full reindex or single product reindex `Color` data will appear in a fields list.

### Add product to index on update
When you add a new product, to add this product to ELasticSearch index, in cli you need to run single product or full reindex. It's inconvenient to do this. To do this automatically, use plugin actions. For example, when we updating a product, we can automatically add new data to index:

```php
add_action( 'woocommerce_update_product', function( $product_id, $product ) {
  do_action( 'wc2el_reindex_single_product', $product_id, $product );
}, 10, 2 );

```
See [woocommerce actions](https://woocommerce.github.io/code-reference/hooks/hooks.html) for a full list and choose an action you need.
