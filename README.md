# WooCommerce to Elastic indexer
CLI plugin for indexing WooCommerce products to ElasticSearch. This is not a UI-interface plugin. You need a minimal PHP and ElasticSearch skills to use it.

---

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
  - [Testing connection](#testing-connection)  
  - [Managing indexes](#managing-indexes)  
  - [Indexing products](#indexing-products)  
- [Actions and Filters](#actions-and-filters)
  - [Actions](#actions)  
  - [Filters](#filters)  
- [Examples](#examples)

---

## Requirements

- [WP-CLI](https://github.com/wp-cli/wp-cli)
- [ElasticSearch](https://www.elastic.co/guide/en/elasticsearch/reference/current/install-elasticsearch.html)
- [WooCommerce](https://wordpress.org/plugins/woocommerce/)

---

## Installation

Install and configure ElasticSearch. Clone this repo or upload and unzip archive to `wp-content/plugins` directory. Define ElasticSearch host credentials in `wp-config.php`

```php
define( 'WC2EL_HOST', 'https://127.0.0.1:9200/' );
define( 'WC2EL_USER', '*******' );
define( 'WC2EL_PASS', '*******' );
define( 'WC2EL_BULK_AMOUNT', 10 );
```

or with `sha_wc2el_elastic_settings` action in your theme's `functions.php`

---

## Usage

This plugin support 3 types of actions: test connection, managing indexes and indexing products. Run `wp wc2el help` to see all arguments.

### Testing connection
To check credentials and client connectivity:

```bash
wp wc2el test
```

Output on success:
```
Success: Connected to Elasticsearch: [CLUSTER-NAME]
```

---

### Managing indexes
`wp wc2el index` supports 4 actions: **stat, create, delete, index**.

- `wp wc2el index stat` → Show current index status.  
- `wp wc2el index create` → Create a new index (name auto-generated from hostname).  
- `wp wc2el index delete` → Delete an index.  
- `wp wc2el index index` → Batch reindex all WooCommerce products (with progress bar).  

Combined example:

```bash
wp wc2el index delete && wp wc2el index create && wp wc2el index index && wp wc2el index stat
```

`wp wc2el index stat` example output:

```bash
+----------------------------------------------+---------------------------------------------+
| Index Name                                   |                                   localhost |
+----------------------------------------------+---------------------------------------------+
| Products in index                            |                                         197 |
+----------------------------------------------+---------------------------------------------+
| Index Size                                   |                                       218Kb |
+----------------------------------------------+---------------------------------------------+
| Last Full Reindex                            |                 September 26, 2024 12:58 pm |
+----------------------------------------------+---------------------------------------------+
```

---


### Indexing products

For single product actions you can use `wp wc2el product`. It supports 3 types of action: `add`, `delete` and `stat`.

- `wp wc2el product add ID` add a product with ID to ElasticSearch index.

- `wp wc2el product delete ID` delete a product with ID from ElasticSearch index.

- `wp wc2el product stat ID` show product data with ID in ElasticSearch index.


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

---

## Actions and Filters

### Actions

#### `sha_wc2el_create_index`
- Create an index.

```php
do_action( 'sha_wc2el_create_index' );
```

#### `sha_wc2el_delete_index`
- Delete index.

```php
do_action( 'sha_wc2el_delete_index' );
```

#### `sha_wc2el_index_product`
- Reindex single product in your plugin or theme (see example section).

```php
do_action( 'sha_wc2el_index_product', $product_id, $product );
```
- **$product_id** *(int)* – Product ID  
- **$product** *(WC_Product|false)* – Product object (optional)  

#### `sha_wc2el_delete_product`
- Delete single product in your plugin or theme (see example section).

```php
do_action( 'sha_wc2el_delete_product', $product_id, $product );
```

#### `sha_wc2el_after_single_index`
Triggered after a product is indexed. Useful for logging or custom actions.  
```php
add_action( 'sha_wc2el_after_single_index', function( $product_id, $data ) {
    error_log( "Product $product_id indexed." );
}, 10, 2 );
```

- **$product_id** *(int)* – Product ID  
- **$data** *(array)* – Data sent to Elasticsearch  

#### `sha_wc2el_after_single_delete`
Triggered after a product is deleted from the index.  
```php
add_action( 'sha_wc2el_after_single_delete', function( $product_id ) {
    error_log( "Product $product_id deleted from index." );
});
```

---

### Filters
#### `sha_wc2el_table_key_length`
- Size of the left column in product stat table. If you want to make stat table wider or your key "breaking" table, change this value.

```php
add_filter( 'sha_wc2el_table_key_length', fn() => 20 );
```

#### `sha_wc2el_table_val_length`
- Size of the right column in product stat table. If you want to make stat table wider or your value "breaking" table, change this value.

```php
add_filter( 'sha_wc2el_table_val_length', fn() => 100 );
```

#### `sha_wc2el_elastic_settings`
- Override ElasticSearch settings.
```php
add_filter( 'sha_wc2el_elastic_settings', function( $elastic_settings ) {
    $elastic_settings['host'][0] = 'https://elastic.myhost.com';

    return $elastic_settings;
} );
```

#### `sha_wc2el_request_args`
Modify `wp_remote_request()` arguments for standard requests.  

```php
add_filter( 'sha_wc2el_request_args', function( $args ) {
    $args['timeout'] = 60;
    return $args;
});
```
#### `sha_wc2el_bulk_request_args`
Modify `wp_remote_request()` arguments for bulk requests.  

```php
add_filter( 'sha_wc2el_bulk_request_args', function( $args ) {
    $args['headers']['X-Debug'] = '1';
    return $args;
});
```

#### `sha_wc2el_allowed_product_types`
- Filter products with only selected types on full indexing or single product indexing. Default: `array('simple');`.

```php
add_filter( 'sha_wc2el_allowed_product_types', function( $allowed_product_types ) {

	$allowed_product_types[] = 'variable';
	
	return $allowed_product_types;
} );
```

#### `sha_wc2el_allowed_product_statuses`
- Filter products with only selected statuses on full indexing or single product indexing. Default: `array('publish');`.

```php
add_filter( 'sha_wc2el_allowed_product_statuses', function( $allowed_product_statuses ) {

	$allowed_product_statuses[] = 'draft';
	
	return $allowed_product_statuses;
} );
```

#### `sha_wc2el_elastic_index_structure`
- Add an extra data to index settings (analysers, filters, etc.). See Example section.

#### `sha_wc2el_product_fields`
- Add an extra field to default product fields array. See Example section.

---

## Examples
### Add custom attribute to index

By default, this plugin supports only default product fields (see `get_product_fields` method). If you want to add custom attributes or custom data, you can use filters to do this. For example, your products has attribute 'Color', which you want to add to index.

First, we need to add this field to mappings. Use filter `sha_wc2el_elastic_index_structure` to do this. This filter calls before index creation. You can use it to set custom analyzers, formatters, etc. (see [ElasticSearch docs](https://www.elastic.co/guide/en/elasticsearch/reference/current/index.html) for more info) In our case, the code is:

```php
add_filter( 'sha_wc2el_elastic_index_structure', function( $params ) {
  $params['settings']['analysis']	= array(
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

  $params['mappings']['properties']['color'] = array(
    'type'      => 'text',
    'analyzer'  => 'format',
  );

  return $params;
} );
```

Second, we need to get this field from product and add to default fields list. Use filter `sha_wc2el_product_fields` to do this. In our case a code is:
```php
add_filter( 'sha_wc2el_product_fields', function( $product_extra_fields, $product ) {
  foreach ( $product->get_attributes() as $key => $attribute ) {

    if ( $key == 'color' ) {
      $product_extra_fields['color'] = array_values( $attribute->get_options() );
    }
  }

  return $product_extra_fields;
}, 10, 2);
```
Now, after indexing or single product reindex `Color` data will appear in a fields list.

### Add product to index on update
When you add a new product, to add this product to ElasticSearch index, in cli you need to run single product or full reindex. It's inconvenient to do this. To do this automatically, use plugin actions. For example, when we updating a product, we can automatically add new data to index:

```php
add_action( 'woocommerce_update_product', function( $product_id, $product ) {
  do_action( 'sha_wc2el_index_product', $product_id, $product );
}, 10, 2 );

```

Same way we can delete data from ElasticSearchon product delete:

```php
add_action( 'woocommerce_before_delete_product', function( $product_id ) {
    do_action( 'sha_wc2el_delete_product', $product_id );
} );
```

For single variable/grouped products, you should get all variation/linked ID's and index/delete them too.

### Important
If you indexing variable/grouped product, make sure you add all linked subtypes. For `variable` product you also should add `variation` type. For `grouped` make sure you also add `simple` product_type.

Try to keep the batch size below 500 products. For variable products, you need to find the appropriate batch size. For example, if you set it to 500, but each product in your store has up to 5 variations (i.e., additional products), the total number of items becomes 500 * 5 = 2500. This amount of data may exceed the ElasticSearch batch size limit and throw an error. If this happens, try reducing the batch size to around 100–150.
