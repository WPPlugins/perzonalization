<?php
/**
 * Plugin Name: Perzonalization  - Recommendations in Real Time
 * Plugin URI: perzonalization.com
 * Version:  1.3.8
 * Description: With the help of more than a billion pages we have personalized so far, we have learned that understanding the visitor is the key in personalizing an online store. That is why we not only analyse behaviours around products but also the visitor's individual preferences and similar users' preferences. Works with activated wooCommerce API.
 * Author: Perzonalization
 * Author URI: http://www.perzonalization.com/woocommerce-plugin/
 *
 * @package woocommerce-perzonalization.
 */

 


if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    global $description, $store_name;
    $description = 'Perzonalization plugin';

    //store name
    $blog_info = get_bloginfo('wpurl');
    if (substr($blog_info, 0, 7) == "http://") {
        $store_name = str_replace('http://', '', $blog_info);
    } elseif (substr($blog_info, 0, 8) == "https://") {
        $store_name = str_replace('https://', '', $blog_info);
    }

    // ACTIVATING
    register_activation_hook(__FILE__, function () {
        global $wpdb, $description, $store_name;
        $table = $wpdb->prefix . 'woocommerce_api_keys';
        $result = $wpdb->get_var("SELECT key_id FROM $table WHERE description = '" . $description . "'");
        if (!$result) {
            $consumer_key = 'ck_' . wc_rand_hash();
            $consumer_secret = 'cs_' . wc_rand_hash();

            $data = array(
                'user_id' => get_current_user_id(),
                'description' => $description,
                'permissions' => 'read',
                'consumer_key' => wc_api_hash($consumer_key),
                'consumer_secret' => $consumer_secret,
                'truncated_key' => substr($consumer_key, -7)
            );

            $result = $wpdb->insert($table, $data);

            if ($result) {
                // add guid in options table
                $guid = get_option('perzonalization_guid');
                if ($guid === false) {
                    $guid = strtolower(guid_woocommerce());
                    add_option('perzonalization_guid', $guid);
                }
                if ($guid == '') {
                    $guid = strtolower(guid_woocommerce());
                    update_option('perzonalization_guid', $guid);
                }

                if (!function_exists('get_plugin_data')) {
                    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
                }
                $plugin_version = get_plugin_data(__FILE__);


                //woocommerce version
                $woo_version = get_option('woocommerce_version');

                //country
                $country = wc_get_base_location();
                $country = $country['country'];
				$admin_url = admin_url();
                $data_post = array();
                //accessToken - not applicable
                $data_post['apiConsumerKey'] = $consumer_key;
                $data_post['apiConsumerSecret'] = $data['consumer_secret'];
                //city - not applicable
                $data_post['country'] = $country;
                $data_post['currency'] = get_woocommerce_currency();
                $data_post['displayName'] = get_bloginfo('name');
                $data_post['nameAPI'] = get_bloginfo('wpurl');
                $data_post['email'] = get_option('admin_email');
                $data_post['language'] = get_bloginfo('language');
                //owner - not applicable
                //phone - not applicable
                $data_post['platformVersion'] = $woo_version;
                $data_post['pluginVersion'] = $plugin_version['Version'];
                $data_post['url'] = $store_name;
                $data_post['adminUrl'] = $admin_url;
                $data_post_send = '';
                foreach ($data_post as $key => $value) {
                    $data_post_send .= $key . '=' . $value . '&';
                }
                $headers = array(
                    "Accept-Encoding: gzip, deflate",
                    "Accept: */*",
                    "Content-Type: application/x-www-form-urlencoded"
                );
                //curl to create the Perzonalization API page
                $ch = curl_init();
                $url = "http://api.perzonalization.com/stores/woocommerce." . $guid;
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data_post_send);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $test = curl_exec($ch);
                if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 201) {
                    // all is good
                } else {
                    // error
                }
                curl_close($ch);
            }
        }
    });

    // DEACTIVATING
    register_deactivation_hook(__FILE__, function () {
        global $wpdb, $description, $store_name;
        $table = $wpdb->prefix . 'woocommerce_api_keys';
        $result = $wpdb->get_results("SELECT consumer_secret FROM $table WHERE description = '" . $description . "'");
        $guid = get_option('perzonalization_guid');
        if ($result) {
            $wpdb->delete($table, array('description' => $description));
            // curl to delete Perzonalization API page
            $url = 'http://api.perzonalization.com/stores/woocommerce.' . $guid;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }
    });

    // Submenu into a wooCommerce
    add_action('admin_menu', function () {
        add_submenu_page('woocommerce', __('Perzonalization', 'woocommerce-perzonalization'), __('Perzonalization', 'woocommerce-perzonalization'), 'manage_options', 'perzonalization', 'display_options_page');
    });
    
	function display_options_page() {
        $guid = get_option('perzonalization_guid');
        ?>
        <iframe src="//my.perzonalization.com/stores/woocommerce.<?php echo $guid; ?>/config" width="100%"
                height="813px" align="left"></iframe>
    <?php
    }

    add_action('init', 'manufacturer_taxonomy', 0);

//  Manufacturer taxonomy

    function manufacturer_taxonomy() {
        $labels = array(
            'name' => _x('Manufacturer', 'taxonomy general name'),
            'singular_name' => _x('Manufacturer', 'taxonomy singular name'),
            'search_items' => __('Search Manufacturer'),
            'all_items' => __('All Manufacturers'),
            'parent_item' => __('Parent Manufacturer'),
            'parent_item_colon' => __('Parent Manufacturer:'),
            'edit_item' => __('Edit Manufacturer'),
            'update_item' => __('Update Manufacturer'),
            'add_new_item' => __('Add New Manufacturer'),
            'new_item_name' => __('New Genre Manufacturer'),
            'menu_name' => __('Manufacturer'),
        );

        $args = array(
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'manufacturer'),
        );

        register_taxonomy('manufacturer', array('product'), $args);
    }


    // Add a main script on pages

    add_action('woocommerce_thankyou', 'my_custom_tracking');

    function my_custom_tracking($order_id)
    {
        $user_id = get_current_user_id();
        $order = wc_get_order($order_id);
        $line_items = $order->get_items();
        $prod_data = '';
//        $info = array();
        foreach ($line_items as $item) {
            $product = $order->get_product_from_item($item);
            $product_id = $item['product_id'];
            $price = $product->get_price();
            $qty = $item['qty'];
            $prod = wc_get_product($product_id);
            $attrs = $prod->get_attributes();
            if ((array_key_exists('size', $attrs)) and ($prod->product_type == 'variable')) {
                $size = $item['size'];
                $prod_data .= "{id: '$product_id', price: '$price', quantity: '$qty', size: '$size'},";
            }
            elseif ((!($prod->product_type == 'variable')) and (array_key_exists('size', $attrs))) {
                $size = $attrs['size']['value'];
                $prod_data .= "{id: '$product_id', price: '$price', quantity: '$qty', size: '$size'},";
            }
            else {
                $prod_data .= "{id: '$product_id', price: '$price', quantity: '$qty'},";
            }
        }
//        $prod_data = substr($prod_data, 0, -1);
//        $info2 = json_encode($info);
        echo "<script type='text/javascript'>
				 var purchaseDetailsForPrz = {
                       transactionId: '{$order_id}',
                        userId: '{$user_id}',
                          productData: [
                              $prod_data
                          ]};
                        var _przq = _przq || []; _przq.push(purchaseDetailsForPrz);</script>";
    }


    add_action('wp_head', function () {
        $guid = get_option('perzonalization_guid');
		echo "<!--PERZONALIZATION-START Do not modify or delete this comment-->
		";
        if (is_order_received_page()) {
			$user_id = get_current_user_id();
            echo "<script type='text/javascript'>
				  var detailsForPrz = {
					instanceGuid: '{$guid}',
					pageType: 'sales',
					userId: '{$user_id}'
				  };</script>";
        }

        if (is_product()) {

            global $post;
            $product_id = $post->ID;

            $product = wc_get_product($product_id);
            $permalink = get_permalink();
            // get product tags
            $product_tags = get_the_terms($post->ID, 'product_tag');
            if ($product_tags == false) {
                $tags = '';
            } else {
                $tags = '';
                foreach ($product_tags as $product_tag) {
                    $tags .= "{'name': 'tag', 'value': '" . clean_string($product_tag->name) . "'},"; 
                }
                $tags = substr($tags, 0, -1);
				$tags = str_replace('"', "'", $tags);
            }

            // get manufacturer
            $manufacturer_tax = get_the_terms($post->ID, 'manufacturer');
            if (!($manufacturer_tax == false)) {
                $manufacturer = $manufacturer_tax[0]->name;
            } else {
                $manufacturer = '';
            }
            // get product categories
            $product_cats = get_the_terms($post->ID, 'product_cat');
            if (!empty($product_cats))  {
                $cats = '';
                foreach ($product_cats as $product_cat) {
                    $cats .= '"' . clean_string($product_cat->name) . '",';
                }
                $cats = substr($cats, 0, -1);
                $cats = str_replace('"', "'", $cats);
            } else {$cats = '';}
            // product image
            $product_img = wp_get_attachment_url(get_post_thumbnail_id());
            $product_attributes = $product->get_attributes();
			
			$isFirst = true;
            // get product attributes
            if (!empty($product_attributes)) {
                foreach ($product_attributes as $product_attribute => $val) {
					if($isFirst == true && empty($tags) == false){
						$tags .= ',';
						$isFirst = false;
					}
					if (strpos($val['value'], '|') !== false) {
						//multiple values
						$values = explode('|', $val['value']);
						  foreach ($values as $singleValue) {
							  $tags .= '{"name":' . "'" . $product_attribute . "'" . ',"value":' . "'" . clean_string($singleValue) . "'" . '},';						  
						  }
					}
					else {
						$tags .= '{"name":' . "'" . $product_attribute . "'" . ',"value":' . "'" . clean_string($val['value']) . "'" . '},';
					}
                }
                $tags = substr($tags, 0, -1);
                $tags = str_replace('"', "'", $tags);
            }

            $post_title = addslashes($product->get_title());
            $post_content = clean_string($product->post->post_content);
            $in_stock = $product->is_in_stock();

            $regular_price = $product->get_regular_price();
            $sale_price = $product->get_price();
            if ($sale_price == $regular_price ) {
                $regular_price = null;
            }

            if ($product->product_type == 'variable') {

                    $variations = $product->get_available_variations();
                    $regular_price = $variations[0]['display_regular_price'];
                    $var_onsale = '';
                    foreach ($variations as $variation) {
                        foreach ($variation['attributes'] as $key => $value) {
                            $key = substr($key, 10);
                            $var_onsale .= "{'name': '" . $key . "', 'value': '" . $value . "', 'variantId': '" . $variation['variation_id'] . "'},";
                        }
                    }

                    $var_onsale = substr($var_onsale, 0, -1);

            } else {
                $var_onsale = null;
            }

            echo "<script type='text/javascript'>
				  var productDetailsForPrz  = {
                    attributes: [$tags],
				      campaign: null,
					canonicalUrl: '{$permalink}',
                    categories: [$cats],
					description: '{$post_content}',
					id: '{$product_id}',
					manufacturer: '{$manufacturer}',
					name: '{$post_title}',
					onSale: '{$in_stock}',
					regularPrice : '{$regular_price}',
					salesPrice : '{$sale_price}',
					thumbnailUrl : '{$product_img}',
                    variantsOnSale: [
                        {$var_onsale}
                    ]
				  }</script>";

            $user_id = get_current_user_id();
            echo "<script type='text/javascript'>
				  var detailsForPrz = {
					instanceGuid: '{$guid}',
					pageType: 'product',
					userId: '{$user_id}'
				  };</script>";
        }

        if (is_shop()) {

            $user_id = get_current_user_id();

            $taxonomy     = 'product_cat';
            $orderby      = 'name';
            $show_count   = 0;
            $pad_counts   = 0;
            $hierarchical = 1;
            $title        = '';
            $empty        = 0;
            $args = array(
                'taxonomy'     => $taxonomy,
                'orderby'      => $orderby,
                'show_count'   => $show_count,
                'pad_counts'   => $pad_counts,
                'hierarchical' => $hierarchical,
                'title_li'     => $title,
                'hide_empty'   => $empty
            );
            $all_categories = get_categories( $args );
            $every_cat = '';
            foreach ($all_categories as $category) {
                $every_cat .= "'".$category->name."',";
            }
            $every_cat = substr($every_cat, 0, -1);


            echo "<script type='text/javascript'>
				var filterDetailsForPrz = {
                     categories: [$every_cat]
                     }
                     </script>";
            echo "<script type='text/javascript'>
                var detailsForPrz = {
					instanceGuid: '{$guid}',
					pageType: 'filter',
					userId: '{$user_id}'
                };</script>";
        }

        if (is_search()) {
            $user_id = get_current_user_id();

            global $wp_query;
            $search_ids = '';
            foreach ($wp_query->posts as $queried_post) {
                if ($queried_post->post_type == 'product') {
                    $search_ids .= "'".$queried_post->ID."'".",";
                }
                else {
                    $search_ids .= ' ';
                }
            }
            $search_ids = substr($search_ids, 0, -1);

            echo "<script type='text/javascript'>
                      var searchDetailsForPrz = {
                        ids: [$search_ids]
                      };</script>";

            echo "<script type='text/javascript'>
				  var detailsForPrz = {
					instanceGuid: '{$guid}',
					pageType: 'search',
					userId: '{$user_id}'
				  };</script>";
        }

        if (is_product_category()) {

            $user_id = get_current_user_id();
            $cat_id = get_queried_object()->term_id;
            $categories = get_all_parents($cat_id);
            $cat_r = array_reverse($categories, true);
            $cat = array();
            foreach ($cat_r as $category) {
                $cat[] = $category->name;
            }
            $cat = json_encode($cat);
            $cat_s = str_replace('"', "'", $cat);

            $args = array(
                'post_type'             => 'product',
                'post_status'           => 'publish',
                'ignore_sticky_posts'   => 1,
                'posts_per_page'        => '12',
                'meta_query'            => array(
                    array(
                        'key'           => '_visibility',
                        'value'         => array('catalog', 'visible'),
                        'compare'       => 'IN'
                    )
                ),
                'tax_query'             => array(
                    array(
                        'taxonomy'      => 'product_cat',
                        'field' => 'term_id', //This is optional, as it defaults to 'term_id'
                        'terms'         => $cat_id,
                        'operator'      => 'IN' // Possible values are 'IN', 'NOT IN', 'AND'.
                    )
                )
            );
//            $products = new WP_Query($args);
            $products = get_posts( $args );
            $ids_str = '';
            foreach ($products as $product) {
                $ids_str .= "'".$product->ID."'".",";
            }
            $ids_str = substr($ids_str, 0, -1);


            echo "<script type='text/javascript'>
				var filterDetailsForPrz = {
                     categories: $cat_s,
                     ids: [$ids_str]
                     }
                     </script>";
            echo "<script type='text/javascript'>
				  var detailsForPrz = {
					instanceGuid: '{$guid}',
					pageType: 'filter',
					userId: '{$user_id}'
				  };</script>";
        }

        if (is_front_page()) {
            $user_id = get_current_user_id();
            echo "<script type='text/javascript'>
				  var detailsForPrz = {
					instanceGuid: '{$guid}',
					pageType: 'home',
					userId: '{$user_id}'
				  };</script>";
        }
        if (is_cart()) {

            global $woocommerce;
            $items = $woocommerce->cart->get_cart();
            $info = '';
            foreach ($items as $item) {

                $product = wc_get_product($item['product_id']);
                $product_attributes = $product->get_attributes();
                $product_id = $item['product_id'];
                $price = $item['line_total'];
                if ((!empty($product_attributes)) and (!empty($item['variation'])) and (array_key_exists('attribute_size', $item['variation'])) ) {
                    $size = $item['variation']['attribute_size'];
                        $info .= "{id: '$product_id', size: '$size', price: '$price'},";
                }
                elseif ((!empty($product_attributes)) and (empty($item['variation'])) and (array_key_exists('size', $product_attributes))) {
                    $size = $product_attributes['size']['value'];
                        $info .= "{id: '$product_id', size: '$size', price: '$price'},";
                }
                else {
                    $info .= "{id: '$product_id', price: '$price'},";
                }
            }

            $info = substr($info, 0, -1);

            echo "<script type='text/javascript'>

                    var basketDetailsForPrz = {
                    products: [
                      $info
                    ]};
                     </script>";
            $user_id = get_current_user_id();
            echo "<script type='text/javascript'>
				  var detailsForPrz = {
					instanceGuid: '{$guid}',
					pageType: 'basket',
					userId: '{$user_id}'
				  };</script>";
        }

        if (is_woocommerce() || is_search() || is_checkout() || is_cart() || is_front_page() || is_order_received_page()) {
            echo "<script type='text/javascript' async>(function (w, d, n, i, a, l, s, r, c) { r = Math.round(Math.random() * 1e4); c = d.getElementById(i); if (!c) { s = d.createElement(n); s.type = 'text/javascript'; s.id = i; s.src = '//' + a + '?rnd=' + r; s.async = 1; l = d.getElementsByTagName(n)[0]; l.parentNode.insertBefore(s, l); } if (c) { runPRZPlugin(true); } })(window, document, 'script', 'prz_loader', 's.perzonalization.com/js/loader/woocommerce.loader.js'); </script>";
        }
		echo "
		<!--PERZONALIZATION-END Do not modify or delete this comment-->";
    });

    // Add a div.perzonalization on pages
    add_action('woocommerce_after_shop_loop', 'echo_div'); //  home
    add_action('woocommerce_after_cart', 'echo_div'); //  cart
    add_action('woocommerce_cart_is_empty', 'echo_div');// empty cart
    add_action('woocommerce_after_single_product', 'echo_div'); // single product
    add_action('woocommerce_checkout_after_order_review', 'echo_div'); // checkout


    add_action('get_footer', 'echo_div_footer');// search page
    add_action('loop_end', 'stop_loop');// search page
    add_filter('the_post', 'test_my');// search page
    add_action('woocommerce_after_shop_loop', 'echo_div');// search page

    add_action('woocommerce_archive_description', function () {
        if (!is_shop() && !is_product_category() && !is_product_tag()) {
            echo_div();
        }
    });// search page $_GET['post_type=product']

    function stop_loop()
    {
        global $is_loop_stop;
        $is_loop_stop = true;
    }

    function test_my($content)
    {
        if (is_search()) {
            global $have_product, $post;
            $have_product = true;
        }
    }

    function echo_div_footer()
    {
        global $have_product;
        if (is_order_received_page() || is_front_page() || is_search()) {
            echo_div();
        }
    }

    // get all parents of current category
    function get_all_parents($id, &$output = array())
    {
        $id = (int)$id;
        global $wpdb;

        $_term = $wpdb->get_row("
						SELECT t.name, tt.parent
						FROM  $wpdb->terms t
						LEFT JOIN $wpdb->term_taxonomy  tt
						ON tt.term_id = t.term_id
						WHERE t.term_id =$id");
        $output[] = $_term;

        if ((int)$_term->parent != 0) {
            return get_all_parents($_term->parent, $output);
        } else {
            return $output;
        }
    }

    function echo_div()
    {
        $user_id = get_current_user_id();
        echo "<!--PERZONALIZATION-START Do not modify or delete this comment-->
				<div style='clear: both;'></div>
				<div id='perzonalization' class='perzonalization'></div>
				<!--PERZONALIZATION-END Do not modify or delete this comment-->";

        global $post, $store_name;
        
    }

    function clean_string($string)
    {
        return trim(preg_replace('/\s\s+/', ' ', addslashes($string)));
    }

    function guid_woocommerce()
    {
        if (function_exists('com_create_guid') === true) {
            return trim(com_create_guid(), '{}');
        }
        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }
	
	function plugin_settings_link($links) {
	   $settings_link = '<a href="admin.php?page=perzonalization">Settings</a>';
	   array_unshift($links, $settings_link);
	   return $links;
	}
	
	$plugin = plugin_basename(__FILE__);
	add_filter("plugin_action_links_$plugin", 'plugin_settings_link' );
	
} else {
	$blog_info = get_bloginfo('wpurl');
    if (substr($blog_info, 0, 7) == "http://") {
        $store_name = str_replace('http://', '', $blog_info);
    } elseif (substr($blog_info, 0, 8) == "https://") {
        $store_name = str_replace('https://', '', $blog_info);
    }
	$email = get_option('admin_email');
	
	//user doesn't have woocommerce or it's disabled
	$data_post['platformName'] = 'woocommerce';
	$data_post['message'] = "user doesn't have woocommerce or it's disabled, skipping install. store: " .$store_name . " email: " . $email;
	
	$data_post_send = '';
	foreach ($data_post as $key => $value) {
		$data_post_send .= $key . '=' . $value . '&';
	}
	$headers = array(
		"Accept-Encoding: gzip, deflate",
		"Accept: */*",
		"Content-Type: application/x-www-form-urlencoded"
	);
	//curl to create the Perzonalization API page
	$ch = curl_init();
	$url = "http://api.perzonalization.com/v1.0/events/NoWoocommerce";
	
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_post_send);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$test = curl_exec($ch);
	if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 201) {
		// all is good
	} else {
		// error
	}
	curl_close($ch);
	exit(sprintf('<p><strong>Perzonalization</strong> requires WooCommerce plugin in order to function.</p>'));
}



