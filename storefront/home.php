<?php
if(!defined('ABSPATH')){exit;}
// Wrap the original theme template on inner pages without replacing their content.
if(!empty($GLOBALS['litrd_original_template'])){
 $original=$GLOBALS['litrd_original_template'];unset($GLOBALS['litrd_original_template']);
 $GLOBALS['litrd_header_menus']=array();$GLOBALS['litrd_capture_header']=true;
 ob_start();include $original;$document=ob_get_clean();$GLOBALS['litrd_capture_header']=false;
 echo litrd_consistent_inner_page_header($document);
 return;
}

$shop=litrd_catalog_url();
$collections=array_filter(array(
 litrd_collection('Decals','decals',array('decals','decal')),
 litrd_collection('Stickers','product-category/stickers-2',array('stickers-2','stickers','sticker')),
 litrd_collection('Arrma Mafia Shop','arrma-mafia-shop',array('arrma-mafia','arrma-mafia-shop')),
 litrd_collection('RC Accessories','rc-accessories',array('rc-accessories','accessories'))
));
$products=wc_get_products(array('status'=>'publish','visibility'=>'catalog','limit'=>12,'orderby'=>'date','order'=>'DESC'));
$GLOBALS['litrd_header_menus']=array();
$GLOBALS['litrd_capture_header']=true;
ob_start();get_header();$litrd_header=ob_get_clean();
$GLOBALS['litrd_capture_header']=false;
echo litrd_place_page_links(litrd_logo_beside_header_text($litrd_header));

?>
<main id="litrd-main" class="litrd-store" aria-label="Lit Decals shop homepage">
<?php echo litrd_browse_search_row(); ?>
<div class="litrd-intro"><div><h1>RC decals, stickers<br><span>&amp; accessories.</span></h1><p>Decals for your RC body. Stickers for your toolbox. Chassis skins and accessories for your next build.</p></div><div class="litrd-search-panel"><div class="litrd-shipping"><?php
$litrd_uploads=wp_upload_dir();
$litrd_shipping_url=trailingslashit($litrd_uploads['baseurl']).'2025/02/FreeShipping50-1.png';
$litrd_shipping_id=attachment_url_to_postid($litrd_shipping_url);
if($litrd_shipping_id){echo wp_get_attachment_image($litrd_shipping_id,'medium_large',false,array('alt'=>'Lit Decals shipping information','loading'=>'eager'));}
else{?><img src="<?php echo esc_url(trailingslashit($litrd_uploads['baseurl']).'2025/02/FreeShipping50-1-1024x461.png'); ?>" alt="Lit Decals shipping information" loading="eager" width="1024" height="461"><?php }
?></div></div></div>

<?php if($collections): ?>
<section class="litrd-carousel" role="region" aria-roledescription="carousel" aria-label="Shop collections"><div class="litrd-carousel-heading"><h2>Shop by category</h2><div class="litrd-carousel-controls"><span class="litrd-carousel-status" aria-live="polite" aria-atomic="true"></span><button type="button" class="litrd-carousel-prev" aria-label="Previous categories" aria-controls="litrd-category-track">←</button><button type="button" class="litrd-carousel-next" aria-label="Next categories" aria-controls="litrd-category-track">→</button></div></div><div class="litrd-carousel-viewport"><div class="litrd-collections litrd-carousel-track" id="litrd-category-track" tabindex="0" aria-label="Product categories. Use arrow keys or swipe to browse.">
<?php $number=0;foreach($collections as $collection):$number++; ?><a class="litrd-collection" href="<?php echo esc_url($collection['url']); ?>"><span class="litrd-collection-number" aria-hidden="true"><?php echo esc_html(sprintf('%02d',$number)); ?></span><div class="litrd-collection-image"><?php echo wp_kses_post($collection['image']); ?></div><h2><?php echo esc_html($collection['label']); ?><span aria-hidden="true">↗</span></h2></a><?php endforeach; ?>
</div></div></section><?php endif; ?>
<section class="litrd-products" data-cycle-size="4" aria-labelledby="litrd-product-heading"><div class="litrd-section-title"><div><span>THE LATEST FROM LIT DECALS</span><h2 id="litrd-product-heading">New in the shop.</h2></div><a href="<?php echo esc_url($shop); ?>">View the full catalog <span aria-hidden="true">↗</span></a></div>
<div class="litrd-product-grid" id="litrd-new-products"><?php $litrd_product_index=0;foreach($products as $product):$terms=get_the_terms($product->get_id(),'product_cat');$product_category='';if($terms && !is_wp_error($terms)){$product_category=$terms[0]->name;} ?>
<article class="litrd-product"<?php echo $litrd_product_index++>=4?' hidden':''; ?>><a class="litrd-product-link" href="<?php echo esc_url($product->get_permalink()); ?>"><div class="litrd-product-image"><?php if($product->is_on_sale()): ?><span class="litrd-sale">Sale</span><?php endif; ?><?php echo wp_kses_post($product->get_image('woocommerce_thumbnail',array('loading'=>'lazy'))); ?><span class="litrd-product-view" aria-hidden="true">View product ↗</span></div><?php if($product_category): ?><span class="litrd-product-category"><?php echo esc_html($product_category); ?></span><?php endif; ?><h3><?php echo esc_html($product->get_name()); ?></h3><div class="litrd-product-price"><?php echo wp_kses_post($product->get_price_html()); ?></div></a><a class="litrd-product-button" href="<?php echo esc_url($product->add_to_cart_url()); ?>" aria-label="<?php echo esc_attr(wp_strip_all_tags($product->add_to_cart_description())); ?>"><?php echo esc_html($product->add_to_cart_text()); ?><span aria-hidden="true">↗</span></a></article>
<?php endforeach;if(!$products): ?><p>Browse our <a href="<?php echo esc_url($shop); ?>">full product catalog</a>.</p><?php endif; ?></div><div class="litrd-products-cycle-controls" hidden><span class="litrd-products-cycle-status" aria-live="off" aria-atomic="true"></span><div><button type="button" class="litrd-products-prev" aria-label="Previous new products" aria-controls="litrd-new-products">←</button><button type="button" class="litrd-products-pause" aria-controls="litrd-new-products">Pause</button><button type="button" class="litrd-products-next" aria-label="Next new products" aria-controls="litrd-new-products">→</button></div></div></section>
<?php $skin_collection=litrd_collection('Lit RC Skins','litrcskins',array('lit-rc-skins','litrcskins','chassis-skins','rc-skins'));if($skin_collection): ?><section class="litrd-skins"><div class="litrd-skins-caption"><span>LIT RC SKINS</span><h2>Start with<br>the chassis.</h2></div><div><p>Browse chassis skins and check each product’s model and fit options before ordering.</p><?php $collection=$skin_collection; ?><a href="<?php echo esc_url($collection['url']); ?>">Find your chassis skin <span aria-hidden="true">↗</span></a></div></section><?php endif; ?>
</main>
<?php get_footer(); ?>
