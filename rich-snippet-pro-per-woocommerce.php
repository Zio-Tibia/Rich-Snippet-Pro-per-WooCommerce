<?php
/**
 * Plugin Name: Rich Snippet Pro per WooCommerce
 * Description: Genera automaticamente rich snippet avanzati per archivi e prodotti singoli, con supporto WPML e campi personalizzati.
 * Version: 2.0.0
 * Author: Dave (con suggerimenti da Gemini)
 * Text Domain: rich-snippet-archives
 * Domain Path: /languages
 */

// Prevenire accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class RichSnippetArchives {

    private $plugin_path;
    private $plugin_url;

    public function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);

        add_action('init', array($this, 'init'));
        add_action('wp_head', array($this, 'output_schema'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));

        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    public function init() {
        load_plugin_textdomain('rich-snippet-archives', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function activate() {
        // ==> SUGGERIMENTO 1: Controllo di sicurezza per WooCommerce
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('Questo plugin richiede WooCommerce per funzionare. Per favore, installa e attiva WooCommerce prima.', 'rich-snippet-archives'),
                __('Plugin non attivato', 'rich-snippet-archives'),
                array('back_link' => true)
            );
        }

        $default_settings = array(
            'enabled_categories' => array(),
            'brand_name' => get_bloginfo('name'),
            'organization_logo' => 'https://www.orodelsalento.com/immagini/logo-orodelsalento-1.png',
            'organization_url' => home_url(),
            'currency' => get_woocommerce_currency(),
            'price_valid_until' => date('Y-m-d', strtotime('+1 year')),
            'default_shipping' => array(
                'cost' => '4.90',
                'handling_days' => array('1', '2'),
                'transit_days' => array('2', '5')
            )
        );

        add_option('rich_snippet_settings', $default_settings);
    }

    public function add_admin_menu() {
        add_options_page(
            __('Rich Snippet Pro', 'rich-snippet-archives'),
            __('Rich Snippet Pro', 'rich-snippet-archives'),
            'manage_options',
            'rich-snippet-archives',
            array($this, 'admin_page')
        );
    }

    public function register_settings() {
        register_setting('rich_snippet_settings', 'rich_snippet_settings');
    }

    public function admin_page() {
        $settings = get_option('rich_snippet_settings', array());
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false
        ));
        ?>
        <div class="wrap">
            <h1><?php _e('Configurazione Rich Snippet Pro', 'rich-snippet-archives'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('rich_snippet_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Nome Brand/Azienda', 'rich-snippet-archives'); ?></th>
                        <td>
                            <input type="text" name="rich_snippet_settings[brand_name]"
                                   value="<?php echo esc_attr($settings['brand_name'] ?? get_bloginfo('name')); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('URL Logo Organizzazione', 'rich-snippet-archives'); ?></th>
                        <td>
                            <input type="url" name="rich_snippet_settings[organization_logo]"
                                   value="<?php echo esc_url($settings['organization_logo'] ?? ''); ?>"
                                   class="regular-text" />
                            <p class="description"><?php _e('URL completo del logo dell\'azienda', 'rich-snippet-archives'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('URL Organizzazione', 'rich-snippet-archives'); ?></th>
                        <td>
                            <input type="url" name="rich_snippet_settings[organization_url]"
                                   value="<?php echo esc_url($settings['organization_url'] ?? home_url()); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Valuta Predefinita', 'rich-snippet-archives'); ?></th>
                        <td>
                            <input type="text" name="rich_snippet_settings[currency]"
                                   value="<?php echo esc_attr($settings['currency'] ?? get_woocommerce_currency()); ?>"
                                   class="small-text" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Validità Prezzi Fino al', 'rich-snippet-archives'); ?></th>
                        <td>
                            <input type="date" name="rich_snippet_settings[price_valid_until]"
                                   value="<?php echo esc_attr($settings['price_valid_until'] ?? date('Y-m-d', strtotime('+1 year'))); ?>" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Spedizione Predefinita', 'rich-snippet-archives'); ?></th>
                        <td>
                            <label>
                                <?php _e('Costo Spedizione (€):', 'rich-snippet-archives'); ?>
                                <input type="number" step="0.01" name="rich_snippet_settings[default_shipping][cost]"
                                       value="<?php echo esc_attr($settings['default_shipping']['cost'] ?? '4.90'); ?>"
                                       class="small-text" />
                            </label><br/>
                            <label>
                                <?php _e('Giorni Preparazione (min-max):', 'rich-snippet-archives'); ?>
                                <input type="number" name="rich_snippet_settings[default_shipping][handling_days][0]"
                                       value="<?php echo esc_attr($settings['default_shipping']['handling_days'][0] ?? '1'); ?>"
                                       class="small-text" /> -
                                <input type="number" name="rich_snippet_settings[default_shipping][handling_days][1]"
                                       value="<?php echo esc_attr($settings['default_shipping']['handling_days'][1] ?? '2'); ?>"
                                       class="small-text" />
                            </label><br/>
                            <label>
                                <?php _e('Giorni Consegna (min-max):', 'rich-snippet-archives'); ?>
                                <input type="number" name="rich_snippet_settings[default_shipping][transit_days][0]"
                                       value="<?php echo esc_attr($settings['default_shipping']['transit_days'][0] ?? '2'); ?>"
                                       class="small-text" /> -
                                <input type="number" name="rich_snippet_settings[default_shipping][transit_days][1]"
                                       value="<?php echo esc_attr($settings['default_shipping']['transit_days'][1] ?? '5'); ?>"
                                       class="small-text" />
                            </label>
                            <p class="description"><?php _e('Informazioni predefinite per la spedizione nei rich snippet', 'rich-snippet-archives'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Categorie Abilitate', 'rich-snippet-archives'); ?></th>
                        <td>
                            <?php if ($categories): ?>
                                <?php foreach ($categories as $category): ?>
                                    <label>
                                        <input type="checkbox"
                                               name="rich_snippet_settings[enabled_categories][]"
                                               value="<?php echo esc_attr($category->slug); ?>"
                                               <?php checked(in_array($category->slug, $settings['enabled_categories'] ?? array())); ?> />
                                        <?php echo esc_html($category->name); ?> (<?php echo esc_html($category->slug); ?>)
                                    </label><br/>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <p class="description"><?php _e('Seleziona le categorie per cui abilitare i rich snippet', 'rich-snippet-archives'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr/>

            <h2><?php _e('Test Rich Snippet', 'rich-snippet-archives'); ?></h2>
            <p><?php _e('Usa lo strumento di test di Google per verificare i rich snippet:', 'rich-snippet-archives'); ?></p>
            <a href="https://search.google.com/test/rich-results" target="_blank" class="button">
                <?php _e('Test Rich Results Google', 'rich-snippet-archives'); ?>
            </a>

            <h3><?php _e('Campi Personalizzati Supportati per Prodotti', 'rich-snippet-archives'); ?></h3>
            <p><?php _e('Il plugin supporta i seguenti campi personalizzati per arricchire i rich snippet:', 'rich-snippet-archives'); ?></p>
            <ul>
                <li><strong>_gtin:</strong> <?php _e('Codice GTIN/EAN del prodotto', 'rich-snippet-archives'); ?></li>
                <li><strong>_mpn:</strong> <?php _e('Codice MPN (Manufacturer Part Number)', 'rich-snippet-archives'); ?></li>
                <li><strong>_nutrition_info:</strong> <?php _e('Array con informazioni nutrizionali (calories, fat, saturated_fat, carbohydrates, protein, serving_size)', 'rich-snippet-archives'); ?></li>
                <li><strong>_product_video_url:</strong> <?php _e('URL del video del prodotto', 'rich-snippet-archives'); ?></li>
                <li><strong>_product_video_thumbnail:</strong> <?php _e('URL della miniatura del video', 'rich-snippet-archives'); ?></li>
                <li><strong>_product_video_duration:</strong> <?php _e('Durata del video (formato ISO 8601, es: PT2M30S)', 'rich-snippet-archives'); ?></li>
                <li><strong>_product_faqs:</strong> <?php _e('Array di FAQ con campi question e answer', 'rich-snippet-archives'); ?></li>
                <li><strong>_shipping_info:</strong> <?php _e('Array con informazioni spedizione (cost, handling_days, transit_days)', 'rich-snippet-archives'); ?></li>
            </ul>
        </div>
        <?php
    }

    // ==> BUG 1 & 2 CORRETTI: Logica di output riscritta
    public function output_schema() {
        $schema = false;

        // Se è una pagina di prodotto singolo, genera lo schema per il prodotto
        if (is_product()) {
            global $post;
            $schema = $this->generate_single_product_schema($post->ID);
        }
        // Altrimenti, se è una pagina archivio (shop o categoria), genera lo schema per l'archivio
        elseif (is_shop() || is_product_category()) {
            $settings = get_option('rich_snippet_settings', array());

            // Se siamo in una categoria, verifica se è abilitata
            if (is_product_category()) {
                $current_category = get_queried_object();
                if (!in_array($current_category->slug, $settings['enabled_categories'] ?? array())) {
                    return; // Non fare nulla se la categoria non è abilitata
                }
            }

            $schema = $this->generate_archive_schema();
        }

        if ($schema) {
            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        }
    }

    private function generate_archive_schema() {
        global $wp_query;

        $settings = get_option('rich_snippet_settings', array());
        $current_url = $this->get_current_url();
        $current_lang = $this->get_current_language();

        $schema = array(
            "@context" => "https://schema.org",
            "@graph" => array()
        );

        $page_info = $this->get_page_info();

        $itemList = array(
            "@type" => "ItemList",
            "@id" => $current_url . "#itemlist",
            "name" => $page_info['title'],
            "description" => $page_info['description'],
            "numberOfItems" => $wp_query->found_posts,
            "itemListElement" => array()
        );

        if ($current_lang) {
            $itemList["inLanguage"] = $current_lang;
        }

        $products = $this->get_archive_products();
        $position = 1;

        foreach ($products as $product_data) {
            $product_schema = $this->generate_product_schema_for_archive($product_data, $settings);
            if ($product_schema) {
                $schema["@graph"][] = $product_schema;

                // ==> SUGGERIMENTO 3 CORRETTO: ItemList usa @id
                $itemList["itemListElement"][] = array(
                    "@type" => "ListItem",
                    "position" => $position++,
                    "item" => array(
                        "@id" => $product_data['url'] . "#product"
                    )
                );
            }
        }

        $schema["@graph"][] = $itemList;

        $breadcrumb_schema = $this->generate_breadcrumb_schema();
        if ($breadcrumb_schema) {
            $schema["@graph"][] = $breadcrumb_schema;
        }

        $organization_schema = $this->generate_organization_schema($settings);
        if ($organization_schema) {
            $schema["@graph"][] = $organization_schema;
        }

        return $schema;
    }

    private function get_archive_products() {
        global $wp_query;
        $products = array();

        if ($wp_query->posts) {
            foreach ($wp_query->posts as $post) {
                $product = wc_get_product($post->ID);
                if ($product) {
                    $products[] = array(
                        'id' => $post->ID,
                        'product' => $product,
                        'title' => get_the_title($post->ID),
                        'url' => get_permalink($post->ID),
                        'image' => wp_get_attachment_image_url(get_post_thumbnail_id($post->ID), 'full'),
                        'excerpt' => get_the_excerpt($post->ID)
                    );
                }
            }
        }

        return $products;
    }

    private function generate_product_schema_for_archive($product_data, $settings) {
        $product = $product_data['product'];

        $schema = array(
            "@type" => "Product",
            "@id" => $product_data['url'] . "#product",
            "name" => $product_data['title'],
            "description" => wp_strip_all_tags($product_data['excerpt']),
            "url" => $product_data['url']
        );

        if ($product_data['image']) {
            $schema["image"] = $product_data['image'];
        }

        if ($product->get_sku()) {
            $schema["sku"] = $product->get_sku();
        }

        $schema["brand"] = array(
            "@type" => "Brand",
            "name" => $settings['brand_name'] ?? get_bloginfo('name')
        );

        // ==> BUG 3 CORRETTO: Gestione Prodotti Variabili negli Archivi
        $common_offer_properties = array(
            "availability" => $product->is_in_stock() ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
            "url" => $product_data['url'],
            "priceValidUntil" => $settings['price_valid_until'] ?? date('Y-m-d', strtotime('+1 year')),
            "seller" => array(
                "@type" => "Organization",
                "name" => $settings['brand_name'] ?? get_bloginfo('name')
            )
        );

        if ($product->is_type('variable')) {
            $schema["offers"] = array_merge(array(
                "@type" => "AggregateOffer",
                "lowPrice" => $product->get_variation_price('min', true),
                "highPrice" => $product->get_variation_price('max', true),
                "priceCurrency" => $settings['currency'] ?? get_woocommerce_currency(),
                "offerCount" => count($product->get_children())
            ), $common_offer_properties);
        } else {
            $schema["offers"] = array_merge(array(
                "@type" => "Offer",
                "price" => $product->get_price(),
                "priceCurrency" => $settings['currency'] ?? get_woocommerce_currency(),
            ), $common_offer_properties);

            if ($product->is_on_sale() && $product->get_sale_price()) {
                $schema["offers"]["priceSpecification"] = array(
                    "@type" => "PriceSpecification",
                    "price" => $product->get_sale_price(),
                    "priceCurrency" => $settings['currency'] ?? get_woocommerce_currency()
                );
            }
        }

        if ($product->get_average_rating()) {
            $schema["aggregateRating"] = array(
                "@type" => "AggregateRating",
                "ratingValue" => $product->get_average_rating(),
                "reviewCount" => $product->get_review_count(),
                "bestRating" => "5",
                "worstRating" => "1"
            );
        }

        $attributes = $product->get_attributes();
        if (!empty($attributes)) {
            $schema["additionalProperty"] = array();
            foreach ($attributes as $attribute) {
                if ($attribute->is_taxonomy()) {
                    $terms = wp_get_post_terms($product->get_id(), $attribute->get_name());
                    if (!empty($terms)) {
                        $schema["additionalProperty"][] = array(
                            "@type" => "PropertyValue",
                            "name" => wc_attribute_label($attribute->get_name()),
                            "value" => implode(", ", wp_list_pluck($terms, 'name'))
                        );
                    }
                }
            }
        }

        return $schema;
    }

    private function generate_breadcrumb_schema() {
        $breadcrumbs = array(
            "@type" => "BreadcrumbList",
            "@id" => $this->get_current_url() . "#breadcrumb",
            "itemListElement" => array()
        );

        $position = 1;

        $breadcrumbs["itemListElement"][] = array(
            "@type" => "ListItem",
            "position" => $position++,
            "name" => __('Home', 'rich-snippet-archives'),
            "item" => home_url()
        );

        if (!is_shop()) {
            $shop_page_id = wc_get_page_id('shop');
            if ($shop_page_id > 0) {
                $breadcrumbs["itemListElement"][] = array(
                    "@type" => "ListItem",
                    "position" => $position++,
                    "name" => get_the_title($shop_page_id),
                    "item" => get_permalink($shop_page_id)
                );
            }
        }

        if (is_product_category()) {
            $current_category = get_queried_object();

            if ($current_category->parent) {
                $parent_categories = get_ancestors($current_category->term_id, 'product_cat');
                $parent_categories = array_reverse($parent_categories);

                foreach ($parent_categories as $parent_id) {
                    $parent = get_term($parent_id, 'product_cat');
                    $breadcrumbs["itemListElement"][] = array(
                        "@type" => "ListItem",
                        "position" => $position++,
                        "name" => $parent->name,
                        "item" => get_term_link($parent)
                    );
                }
            }

            $breadcrumbs["itemListElement"][] = array(
                "@type" => "ListItem",
                "position" => $position++,
                "name" => $current_category->name,
                "item" => get_term_link($current_category)
            );
        }

        return $breadcrumbs;
    }

    private function generate_organization_schema($settings) {
        $schema = array(
            "@type" => "Organization",
            "@id" => home_url() . "#organization",
            "name" => $settings['brand_name'] ?? get_bloginfo('name'),
            "url" => $settings['organization_url'] ?? home_url()
        );

        if (!empty($settings['organization_logo'])) {
            $schema["logo"] = array(
                "@type" => "ImageObject",
                "@id" => $settings['organization_logo'] . '#logo',
                "url" => $settings['organization_logo'],
            );
        }

        return $schema;
    }

    private function get_page_info() {
        $info = array(
            'title' => '',
            'description' => ''
        );

        if (is_shop()) {
            $shop_page_id = wc_get_page_id('shop');
            $info['title'] = get_the_title($shop_page_id);
            $info['description'] = get_the_excerpt($shop_page_id) ?: get_bloginfo('description');
        } elseif (is_product_category()) {
            $category = get_queried_object();
            $info['title'] = $category->name;
            $info['description'] = $category->description ?: sprintf(__('Prodotti nella categoria %s', 'rich-snippet-archives'), $category->name);
        }

        return $info;
    }

    private function get_current_url() {
        global $wp;
        return home_url(add_query_arg(array(), $wp->request));
    }

    private function get_current_language() {
        if (function_exists('icl_get_current_language')) {
            return icl_get_current_language();
        }

        if (function_exists('pll_current_language')) {
            return pll_current_language();
        }

        return get_bloginfo('language');
    }

    /**
     * Genera schema per pagina prodotto singola
     */
    private function generate_single_product_schema($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) return false;

        $settings = get_option('rich_snippet_settings', array());
        $current_lang = $this->get_current_language();

        $schema = array(
            "@context" => "https://schema.org",
            "@graph" => array()
        );

        // Product Schema principale
        $product_schema = array(
            "@type" => "Product",
            "@id" => get_permalink($product_id) . "#product",
            "name" => get_the_title($product_id),
            "description" => wp_strip_all_tags(get_post_field('post_content', $product_id)),
            "url" => get_permalink($product_id)
        );

        // Lingua se multilingua
        if ($current_lang) {
            $product_schema["inLanguage"] = $current_lang;
        }

        // Immagini multiple
        $images = array();
        $thumbnail_id = get_post_thumbnail_id($product_id);
        if ($thumbnail_id) {
            $images[] = wp_get_attachment_image_url($thumbnail_id, 'full');
        }

        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $gallery_id) {
            $gallery_url = wp_get_attachment_image_url($gallery_id, 'full');
            if ($gallery_url) {
                $images[] = $gallery_url;
            }
        }

        if (!empty($images)) {
            $product_schema["image"] = count($images) === 1 ? $images[0] : $images;
        }

        // SKU e identificatori
        if ($product->get_sku()) {
            $product_schema["sku"] = $product->get_sku();
        }

        // GTIN se presente (campo personalizzato)
        $gtin = get_post_meta($product_id, '_gtin', true);
        if ($gtin) {
            $product_schema["gtin13"] = $gtin;
        }

        // MPN se presente (campo personalizzato)
        $mpn = get_post_meta($product_id, '_mpn', true);
        if ($mpn) {
            $product_schema["mpn"] = $mpn;
        }

        // Brand
        $product_schema["brand"] = array(
            "@type" => "Brand",
            "name" => $settings['brand_name'] ?? get_bloginfo('name')
        );

        // Manufacturer
        $product_schema["manufacturer"] = array(
            "@type" => "Organization",
            "name" => $settings['brand_name'] ?? get_bloginfo('name'),
            "url" => $settings['organization_url'] ?? home_url()
        );

        // Categoria
        $categories = wp_get_post_terms($product_id, 'product_cat');
        if (!empty($categories)) {
            $main_category = $categories[0];
            $product_schema["category"] = $main_category->name;
        }

        // Offers - Gestione prodotti semplici e variabili
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            $offers = array();

            foreach ($variations as $variation_data) {
                $variation = wc_get_product($variation_data['variation_id']);
                if ($variation && $variation->exists()) {
                    $offer = array(
                        "@type" => "Offer",
                        "price" => $variation->get_price(),
                        "priceCurrency" => $settings['currency'] ?? get_woocommerce_currency(),
                        "availability" => $variation->is_in_stock() ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
                        "itemCondition" => "https://schema.org/NewCondition",
                        "url" => get_permalink($product_id),
                        "priceValidUntil" => $settings['price_valid_until'] ?? date('Y-m-d', strtotime('+1 year')),
                        "seller" => array(
                            "@type" => "Organization",
                            "name" => $settings['brand_name'] ?? get_bloginfo('name')
                        )
                    );

                    if ($variation->get_sku()) {
                        $offer["sku"] = $variation->get_sku();
                    }

                    $offers[] = $offer;
                }
            }

            if (!empty($offers)) {
                $product_schema["offers"] = $offers;
            }
        } else {
            $offer = array(
                "@type" => "Offer",
                "price" => $product->get_price(),
                "priceCurrency" => $settings['currency'] ?? get_woocommerce_currency(),
                "availability" => $product->is_in_stock() ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
                "itemCondition" => "https://schema.org/NewCondition",
                "url" => get_permalink($product_id),
                "priceValidUntil" => $settings['price_valid_until'] ?? date('Y-m-d', strtotime('+1 year')),
                "seller" => array(
                    "@type" => "Organization",
                    "name" => $settings['brand_name'] ?? get_bloginfo('name')
                )
            );

            // Prezzo scontato
            if ($product->is_on_sale() && $product->get_sale_price()) {
                $offer["priceSpecification"] = array(
                    "@type" => "PriceSpecification",
                    "price" => $product->get_sale_price(),
                    "priceCurrency" => $settings['currency'] ?? get_woocommerce_currency()
                );
            }

            // Informazioni spedizione
            $shipping_info = get_post_meta($product_id, '_shipping_info', true);
            if ($shipping_info || !empty($settings['default_shipping'])) {
                $shipping_cost = $shipping_info['cost'] ?? $settings['default_shipping']['cost'] ?? '4.90';
                $handling_days = $shipping_info['handling_days'] ?? $settings['default_shipping']['handling_days'] ?? array(1, 2);
                $transit_days = $shipping_info['transit_days'] ?? $settings['default_shipping']['transit_days'] ?? array(2, 5);

                $offer["shippingDetails"] = array(
                    "@type" => "OfferShippingDetails",
                    "shippingRate" => array(
                        "@type" => "MonetaryAmount",
                        "value" => $shipping_cost,
                        "currency" => $settings['currency'] ?? get_woocommerce_currency()
                    ),
                    "deliveryTime" => array(
                        "@type" => "ShippingDeliveryTime",
                        "handlingTime" => array(
                            "@type" => "QuantitativeValue",
                            "minValue" => is_array($handling_days) ? $handling_days[0] : $handling_days,
                            "maxValue" => is_array($handling_days) ? $handling_days[1] : $handling_days,
                            "unitCode" => "DAY"
                        ),
                        "transitTime" => array(
                            "@type" => "QuantitativeValue",
                            "minValue" => is_array($transit_days) ? $transit_days[0] : $transit_days,
                            "maxValue" => is_array($transit_days) ? $transit_days[1] : $transit_days,
                            "unitCode" => "DAY"
                        )
                    )
                );
            }

            $product_schema["offers"] = $offer;
        }

        // Rating e recensioni
        if ($product->get_average_rating()) {
            $product_schema["aggregateRating"] = array(
                "@type" => "AggregateRating",
                "ratingValue" => $product->get_average_rating(),
                "reviewCount" => $product->get_review_count(),
                "bestRating" => "5",
                "worstRating" => "1"
            );

            // Recensioni individuali (prime 5)
            $reviews = get_comments(array(
                'post_id' => $product_id,
                'comment_type' => 'review',
                'comment_approved' => 1,
                'number' => 5,
                'meta_query' => array(
                    array(
                        'key' => 'rating',
                        'compare' => 'EXISTS'
                    )
                )
            ));

            if ($reviews) {
                $review_schemas = array();
                foreach ($reviews as $review) {
                    $rating = get_comment_meta($review->comment_ID, 'rating', true);
                    if ($rating) {
                        $review_schemas[] = array(
                            "@type" => "Review",
                            "reviewRating" => array(
                                "@type" => "Rating",
                                "ratingValue" => $rating,
                                "bestRating" => "5",
                                "worstRating" => "1"
                            ),
                            "author" => array(
                                "@type" => "Person",
                                "name" => $review->comment_author
                            ),
                            "datePublished" => date('Y-m-d', strtotime($review->comment_date)),
                            "reviewBody" => wp_strip_all_tags($review->comment_content),
                            "publisher" => array(
                                "@type" => "Organization",
                                "name" => $settings['brand_name'] ?? get_bloginfo('name')
                            )
                        );
                    }
                }
                if (!empty($review_schemas)) {
                    $product_schema["review"] = $review_schemas;
                }
            }
        }

        // Attributi prodotto
        $attributes = $product->get_attributes();
        if (!empty($attributes)) {
            $additional_properties = array();
            foreach ($attributes as $attribute) {
                if ($attribute->is_taxonomy()) {
                    $terms = wp_get_post_terms($product->get_id(), $attribute->get_name());
                    if (!empty($terms)) {
                        $additional_properties[] = array(
                            "@type" => "PropertyValue",
                            "name" => wc_attribute_label($attribute->get_name()),
                            "value" => implode(", ", wp_list_pluck($terms, 'name'))
                        );
                    }
                } else {
                    // Attributi custom
                    $values = $attribute->get_options();
                    if (!empty($values)) {
                        $additional_properties[] = array(
                            "@type" => "PropertyValue",
                            "name" => $attribute->get_name(),
                            "value" => implode(", ", $values)
                        );
                    }
                }
            }
            if (!empty($additional_properties)) {
                $product_schema["additionalProperty"] = $additional_properties;
            }
        }

        // Informazioni nutrizionali se presenti
        $nutrition = get_post_meta($product_id, '_nutrition_info', true);
        if (is_array($nutrition) && !empty($nutrition)) {
            $nutrition_schema = array(
                "@type" => "NutritionInformation"
            );

            if (!empty($nutrition['calories'])) {
                $nutrition_schema["calories"] = $nutrition['calories'];
            }
            if (!empty($nutrition['fat'])) {
                $nutrition_schema["fatContent"] = $nutrition['fat'];
            }
            if (!empty($nutrition['saturated_fat'])) {
                $nutrition_schema["saturatedFatContent"] = $nutrition['saturated_fat'];
            }
            if (!empty($nutrition['carbohydrates'])) {
                $nutrition_schema["carbohydrateContent"] = $nutrition['carbohydrates'];
            }
            if (!empty($nutrition['protein'])) {
                $nutrition_schema["proteinContent"] = $nutrition['protein'];
            }
            if (!empty($nutrition['serving_size'])) {
                $nutrition_schema["servingSize"] = $nutrition['serving_size'];
            }

            if (count($nutrition_schema) > 1) {
                $product_schema["nutrition"] = $nutrition_schema;
            }
        }

        // Video se presente
        $video_url = get_post_meta($product_id, '_product_video_url', true);
        if ($video_url) {
            $video_schema = array(
                "@type" => "VideoObject",
                "name" => "Video di " . get_the_title($product_id),
                "description" => "Video dimostrativo del prodotto",
                "contentUrl" => $video_url,
                "embedUrl" => $video_url,
                "uploadDate" => get_the_date('Y-m-d', $product_id)
            );

            $video_thumbnail = get_post_meta($product_id, '_product_video_thumbnail', true);
            if ($video_thumbnail) {
                $video_schema["thumbnailUrl"] = $video_thumbnail;
            }

            $video_duration = get_post_meta($product_id, '_product_video_duration', true);
            if ($video_duration) {
                $video_schema["duration"] = $video_duration; // Formato ISO 8601 es: PT2M30S
            }

            $schema["@graph"][] = $video_schema;
        }

        $schema["@graph"][] = $product_schema;

        // Breadcrumb per prodotto
        $breadcrumb_schema = $this->generate_product_breadcrumb_schema($product_id);
        if ($breadcrumb_schema) {
            $schema["@graph"][] = $breadcrumb_schema;
        }

        // Organization Schema
        $organization_schema = $this->generate_organization_schema($settings);
        if ($organization_schema) {
            $schema["@graph"][] = $organization_schema;
        }

        // FAQ Schema se presente
        $faqs = get_post_meta($product_id, '_product_faqs', true);
        if (is_array($faqs) && !empty($faqs)) {
            $faq_schema = array(
                "@type" => "FAQPage",
                "@id" => get_permalink($product_id) . "#faq",
                "mainEntity" => array()
            );

            foreach ($faqs as $faq) {
                if (!empty($faq['question']) && !empty($faq['answer'])) {
                    $faq_schema["mainEntity"][] = array(
                        "@type" => "Question",
                        "name" => $faq['question'],
                        "acceptedAnswer" => array(
                            "@type" => "Answer",
                            "text" => $faq['answer']
                        )
                    );
                }
            }

            if (!empty($faq_schema["mainEntity"])) {
                $schema["@graph"][] = $faq_schema;
            }
        }

        return $schema;
    }

    /**
     * Genera breadcrumb per pagina prodotto
     */
    private function generate_product_breadcrumb_schema($product_id) {
        $breadcrumbs = array(
            "@type" => "BreadcrumbList",
            "@id" => get_permalink($product_id) . "#breadcrumb",
            "itemListElement" => array()
        );

        $position = 1;

        // Home
        $breadcrumbs["itemListElement"][] = array(
            "@type" => "ListItem",
            "position" => $position++,
            "name" => __('Home', 'rich-snippet-archives'),
            "item" => home_url()
        );

        // Shop
        $shop_page_id = wc_get_page_id('shop');
        if ($shop_page_id > 0) {
            $breadcrumbs["itemListElement"][] = array(
                "@type" => "ListItem",
                "position" => $position++,
                "name" => get_the_title($shop_page_id),
                "item" => get_permalink($shop_page_id)
            );
        }

        // Categorie prodotto
        $categories = wp_get_post_terms($product_id, 'product_cat');
        if (!empty($categories)) {
            // Trova la categoria principale (quella con il path più lungo)
            $main_category = null;
            $max_depth = 0;

            foreach ($categories as $category) {
                $ancestors = get_ancestors($category->term_id, 'product_cat');
                $depth = count($ancestors);
                if ($depth >= $max_depth) {
                    $max_depth = $depth;
                    $main_category = $category;
                }
            }

            if ($main_category) {
                // Aggiungi categorie padre
                if ($main_category->parent) {
                    $parent_categories = get_ancestors($main_category->term_id, 'product_cat');
                    $parent_categories = array_reverse($parent_categories);

                    foreach ($parent_categories as $parent_id) {
                        $parent = get_term($parent_id, 'product_cat');
                        if ($parent && !is_wp_error($parent)) {
                            $breadcrumbs["itemListElement"][] = array(
                                "@type" => "ListItem",
                                "position" => $position++,
                                "name" => $parent->name,
                                "item" => get_term_link($parent)
                            );
                        }
                    }
                }

                // Categoria corrente
                $breadcrumbs["itemListElement"][] = array(
                    "@type" => "ListItem",
                    "position" => $position++,
                    "name" => $main_category->name,
                    "item" => get_term_link($main_category)
                );
            }
        }

        // Prodotto corrente
        $breadcrumbs["itemListElement"][] = array(
            "@type" => "ListItem",
            "position" => $position++,
            "name" => get_the_title($product_id),
            "item" => get_permalink($product_id)
        );

        return $breadcrumbs;
    }
}

// Inizializza il plugin
new RichSnippetArchives();
