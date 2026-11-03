<?php
/**
 * Plugin Name: Lit Decals Storefront
 * Description: Product-focused sandbox homepage using the original theme header, branding, navigation and footer.
 * Version: 2.6.0
 * Requires PHP: 7.4
 */
if(!defined('ABSPATH')){exit;}
function litrd_sandbox(){
 $root=realpath(ABSPATH);
 return $root && basename($root)==='sandbox-redesign'
 && rtrim((string)wp_parse_url(home_url('/'),PHP_URL_PATH),'/')==='/sandbox-redesign'
 && rtrim((string)wp_parse_url(site_url('/'),PHP_URL_PATH),'/')==='/sandbox-redesign';
}
function litrd_active(){return litrd_sandbox() && is_front_page() && function_exists('wc_get_products') && get_option('litrd_enabled',true);}
add_action('wp_enqueue_scripts',function(){
 if(!litrd_sandbox() || !function_exists('wc_get_products')){return;}
 wp_enqueue_style('litrd',plugins_url('storefront.css',__FILE__),array(),'2.6.0');
 wp_enqueue_script('litrd-interactions',plugins_url('storefront.js',__FILE__),array(),'2.6.0',true);

 $accent=sanitize_hex_color(get_theme_mod('storefront_accent_color','#b52b25'));
 if($accent){wp_add_inline_style('litrd','.litrd-store{--litrd-accent:'.$accent.';}');}
});
add_filter('template_include',function($template){return litrd_active()?__DIR__.'/home.php':$template;},99);
add_action('admin_notices',function(){if(current_user_can('manage_options')){echo '<div class="notice notice-info"><p>Lit Decals Storefront 2.0: '.(litrd_sandbox()?'sandbox homepage enabled. Original theme branding and navigation retained. Deactivate to restore the original homepage.':'disabled outside the sandbox.').'</p></div>';}});
function litrd_collection($label,$path,$slugs){
 $page=get_page_by_path($path,OBJECT,'page');$term=null;
 foreach($slugs as $slug){$candidate=get_term_by('slug',$slug,'product_cat');if($candidate && !is_wp_error($candidate)){$term=$candidate;break;}}
 $url=$page && $page->post_status==='publish'?get_permalink($page):false;
 if(!$url && $term){$url=get_term_link($term);if(is_wp_error($url)){$url=false;}}
 if($label==='Stickers'){$url=home_url('/product-category/stickers-2/');}
 if(!$url){return null;}
 $image='';
 if($page && has_post_thumbnail($page)){$image=get_the_post_thumbnail($page,'medium',array('alt'=>'','loading'=>'lazy'));}
 if(!$image && $term){
 $image=wp_get_attachment_image((int)get_term_meta($term->term_id,'thumbnail_id',true),'medium',false,array('alt'=>'','loading'=>'lazy'));
 if(!$image){$samples=wc_get_products(array('status'=>'publish','visibility'=>'catalog','category'=>array($term->slug),'limit'=>1));if($samples){$image=$samples[0]->get_image('woocommerce_thumbnail',array('alt'=>'','loading'=>'lazy'));}}
 }
 if($label==='Decals'){$image='<img src="'.esc_url('https://litdecals.com/sandbox-redesign/wp-content/uploads/2022/08/OuttaRCParts_Black.png').'" alt="Outta RC Parts decal design" loading="lazy">';}
 if($label==='Stickers'){$image='<img src="'.esc_url('https://litdecals.com/sandbox-redesign/wp-content/uploads/2025/03/Sticker-Sheets-5.jpg').'" alt="Lit Decals sticker sheets" loading="lazy">';}
 if($label==='Arrma Mafia Shop'){
  $hoodie_post=get_page_by_path('arrma-mafia-hoodie',OBJECT,'product');
  $hoodie=$hoodie_post?wc_get_product($hoodie_post->ID):false;
  if($hoodie && $hoodie->get_image_id()){$image=$hoodie->get_image('medium_large',array('alt'=>'ARRMA Mafia hoodie','loading'=>'lazy','data-litrd-image-source'=>'arrma-mafia-hoodie'));}
 }
 if($label==='RC Accessories'){$image='<img src="'.esc_url('https://litdecals.com/wp-content/uploads/2023/05/GRAY-SKULLS.jpg').'" alt="Gray Skulls RC accessory design" loading="lazy">';}
 return array('label'=>$label,'url'=>$url,'image'=>$image,'term'=>$term);
}

// Reversible sandbox exclusions. No catalog records are deleted.
function litrd_retired_brand($value){
 $plain=html_entity_decode(wp_strip_all_tags((string)$value),ENT_QUOTES,'UTF-8');
 return (bool)preg_match('/rc[\s_\-]*4[\s_\-]*us|black[\s_\-]*sheep[\s_\-]*rc/i',$plain);
}
function litrd_brand_filtering(){return litrd_sandbox() && !is_admin();}
function litrd_retired_terms(){
 static $ids=null;if($ids!==null){return $ids;}$ids=array();
 $taxonomies=array_values(array_intersect(array('product_cat','product_brand','pa_brand'),get_object_taxonomies('product')));if(!$taxonomies){return $ids;}
 $terms=get_terms(array('taxonomy'=>$taxonomies,'hide_empty'=>false,'suppress_filter'=>true));
 if(is_wp_error($terms)){return $ids;}
 foreach($terms as $term){if(litrd_retired_brand($term->name.' '.$term->slug)){$ids[]=(int)$term->term_id;}}
 return $ids;
}
add_filter('posts_where',function($where,$query){
 if(!litrd_brand_filtering() || $query->get('litrd_allow_retired')){return $where;}
 global $wpdb;
 $patterns=array('%rc4us%','%rc4-us%','%rc4 us%','%rc 4 us%','%rc-4-us%','%blacksheeprc%','%blacksheep rc%','%black sheep rc%','%black-sheep-rc%','%blacksheep-rc%');
 foreach($patterns as $pattern){$where.=$wpdb->prepare(" AND {$wpdb->posts}.post_title NOT LIKE %s",$pattern);}
 $ids=litrd_retired_terms();
 if($ids){$list=implode(',',array_map('absint',$ids));$where.=" AND {$wpdb->posts}.ID NOT IN (SELECT tr.object_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE tt.taxonomy IN ('product_cat','product_brand','pa_brand') AND tt.term_id IN ($list) AND NOT EXISTS (SELECT 1 FROM {$wpdb->term_relationships} neutral_tr INNER JOIN {$wpdb->term_taxonomy} neutral_tt ON neutral_tt.term_taxonomy_id=neutral_tr.term_taxonomy_id WHERE neutral_tr.object_id=tr.object_id AND neutral_tt.taxonomy=tt.taxonomy AND neutral_tt.term_id NOT IN ($list)))";}
 return $where;
},20,2);
function litrd_clean_menu($items){
 if(!litrd_brand_filtering()){return $items;}$removed=array();
 foreach($items as $item){if(litrd_retired_brand($item->title.' '.urldecode($item->url))){$removed[(int)$item->ID]=true;}}
 do{$changed=false;foreach($items as $item){if(!isset($removed[(int)$item->ID]) && isset($removed[(int)$item->menu_item_parent])){$removed[(int)$item->ID]=true;$changed=true;}}}while($changed);
 return array_values(array_filter($items,function($item)use($removed){return !isset($removed[(int)$item->ID]);}));
}
add_filter('wp_nav_menu_objects','litrd_clean_menu',20);
add_filter('get_pages',function($pages){
 if(!litrd_brand_filtering()){return $pages;}$removed=array();
 foreach($pages as $page){if(litrd_retired_brand($page->post_title.' '.$page->post_name)){$removed[(int)$page->ID]=true;}}
 do{$changed=false;foreach($pages as $page){if(!isset($removed[(int)$page->ID]) && isset($removed[(int)$page->post_parent])){$removed[(int)$page->ID]=true;$changed=true;}}}while($changed);
 return array_values(array_filter($pages,function($page)use($removed){return !isset($removed[(int)$page->ID]);}));
},20);
add_filter('get_terms',function($terms,$taxonomies,$args){
 if(!litrd_brand_filtering() || !empty($args['suppress_filter'])){return $terms;}
 return array_values(array_filter($terms,function($term){return !is_object($term) || !isset($term->name) || !litrd_retired_brand($term->name.' '.$term->slug);}));
},20,3);
add_action('template_redirect',function(){
 if(!litrd_brand_filtering()){return;}$object=get_queried_object();$blocked=false;
 if($object instanceof WP_Post){$blocked=litrd_retired_brand($object->post_title.' '.$object->post_name);if(!$blocked && $object->post_type==='product'){$blocked=litrd_is_exclusively_retired_product($object->ID);}}
 elseif($object instanceof WP_Term){$blocked=litrd_retired_brand($object->name.' '.$object->slug);}
 if($blocked){global $wp_query;$wp_query->set_404();status_header(404);nocache_headers();include get_404_template();exit;}
},1);
function litrd_clean_retired_mentions($html){
 if(!litrd_brand_filtering() || !is_string($html) || !litrd_retired_brand($html)){return $html;}
 // Remove complete branded links and brand image elements; retain unrelated shared product copy.
 $pattern='(?:rc[\s_\-]*4[\s_\-]*us|black[\s_\-]*sheep[\s_\-]*rc)';
 $html=preg_replace_callback('~<a\b[^>]*>.*?</a>~is',function($m){return litrd_retired_brand(urldecode($m[0]))?'':$m[0];},$html);
 $html=preg_replace_callback('~<img\b[^>]*>~is',function($m){return litrd_retired_brand(urldecode($m[0]))?'':$m[0];},$html);
 $html=preg_replace_callback('~(<[^>]+>)|([^<]+)~s',function($m)use($pattern){return !empty($m[1])?$m[1]:preg_replace('~'.$pattern.'~i','',$m[2]);},$html);
 return $html;
}
add_filter('the_content','litrd_clean_retired_mentions',30);
add_filter('woocommerce_short_description','litrd_clean_retired_mentions',30);
add_filter('widget_text_content','litrd_clean_retired_mentions',30);
add_filter('widget_block_content','litrd_clean_retired_mentions',30);

// Capture actual theme menu output so links move into its existing HOME bar.
function litrd_capture_menu_markup($markup){
 if(!empty($GLOBALS['litrd_capture_header']) && is_string($markup)){
 $GLOBALS['litrd_header_menus'][]=$markup;
 }
 return $markup;
}
add_filter('wp_nav_menu','litrd_capture_menu_markup',90);
add_filter('wp_page_menu','litrd_capture_menu_markup',90);
function litrd_place_page_links($header){
 $header=preg_replace_callback('~<header\b([^>]*)>~i',function($m){
  $attributes=$m[1];
  if(preg_match('~\bclass\s*=\s*(["\'])(.*?)\1~is',$attributes)){
   $attributes=preg_replace_callback('~\bclass\s*=\s*(["\'])(.*?)\1~is',function($c){return 'class='.$c[1].$c[2].' litrd-spaced-header'.$c[1];},$attributes,1);
  }else{$attributes.=' class="litrd-spaced-header"';}
  return '<header'.$attributes.'>';
 },$header,1);

 $header=litrd_remove_header_search_cart($header);
 $header=preg_replace('~<img\b[^>]*FreeShipping50-1[^>]*>~i','',$header);
 $menus=isset($GLOBALS['litrd_header_menus'])?array_unique($GLOBALS['litrd_header_menus']):array();
 $items=litrd_ordered_navigation();$target=null;
 foreach($menus as $menu){
 preg_match_all('~<a\b[^>]*>(.*?)</a>~is',$menu,$anchors);
 if(count($anchors[1])===1 && strtolower(trim(wp_strip_all_tags($anchors[1][0])))==='home'){$target=$menu;break;}
 }
 if($target!==null){
 $new=preg_replace_callback('~(<ul\b[^>]*>).*?(</ul>)~is',function($m)use($items){return $m[1].$items.$m[2];},$target,1);
 $header=str_replace($target,$new,$header);
 // Remove the old multi-page header menu only after the destination bar exists.
 foreach($menus as $menu){if($menu===$target){continue;}preg_match_all('~<a\b[^>]*>(.*?)</a>~is',$menu,$anchors);if(count($anchors[1])>=3){$header=str_replace($menu,'',$header);}}
 return $header;
 }
 // If the theme did not expose a HOME-only menu, provide a visible page bar.
 foreach($menus as $menu){preg_match_all('~<a\b[^>]*>(.*?)</a>~is',$menu,$anchors);if(count($anchors[1])>=3){$header=str_replace($menu,'',$header);}}
 return $header.'<nav class="litrd-page-bar" aria-label="Website pages"><ul>'.$items.'</ul></nav>';
}

function litrd_logo_source(){return 'https://litdecals.com/sandbox-redesign/wp-content/uploads/2021/11/Lit-Decals-Logo-1-1.png';}
function litrd_add_header_logo($header){
 $header=preg_replace_callback('~<(h[12]|p)\b([^>]*)class=([\"\'])([^\"\']*\bsite-title\b[^\"\']*)\3([^>]*)>~i',function($m){return '<'.$m[1].$m[2].'class='.$m[3].$m[4].' litrd-spaced-site-title'.$m[3].$m[5].'>';},$header,1);
 $logo='<a class="litrd-header-logo" href="'.esc_url(home_url('/')).'" aria-label="Lit Decals home"><img src="'.esc_url(litrd_logo_source()).'" alt="Lit Decals" loading="eager" fetchpriority="high"></a>';
 // Place it inside the existing branding section; retain the theme title and navigation.
 $pattern='~(<(?:div|section)\b[^>]*class=["\'][^"\']*\bsite-branding\b[^"\']*["\'][^>]*>)~i';
 $count=0;
 $out=preg_replace_callback($pattern,function($m)use($logo){return $m[1].$logo;},$header,1,$count);
 if($count){return $out;}
 // Older themes often expose a title heading instead of a branding wrapper.
 $out=preg_replace_callback('~<(?:h1|h2)\b[^>]*>~i',function($m)use($logo){return $logo.$m[0];},$header,1,$count);
 if($count){return $out;}
 return $header.'<div class="litrd-header-logo-fallback">'.$logo.'</div>';
}

function litrd_ordered_navigation(){
 $links=array(
  array('HOME',home_url('/')),
  array('DECALS',home_url('/decals/')),
  array('STICKERS',home_url('/product-category/stickers-2/')),
  array('RC ACCESSORIES',home_url('/rc-accessories/')),
  array('ARRMA MAFIA',home_url('/arrma-mafia-shop/')),
  array('REFUNDS/RETURNS',home_url('/refund_returns/')),
  array('TERMS/CONDITIONS',home_url('/3466-2/')),
  array('MY ACCOUNT',wc_get_page_permalink('myaccount')),
  array('CART',wc_get_cart_url()),
 );
 $items='';foreach($links as $index=>$link){
 $current=$index===0 && is_front_page()?' aria-current="page"':'';
 if($link[0]==='CART'){
 $count=(function_exists('WC') && WC()->cart)?(int)WC()->cart->get_cart_contents_count():0;
 $items.='<li class="menu-item litrd-ordered-menu-item"><a class="litrd-nav-cart-link" href="'.esc_url($link[1]).'" aria-label="'.esc_attr(sprintf('Cart, %d items',$count)).'">CART <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 3h3l2.2 11.5a2 2 0 0 0 2 1.5H18a2 2 0 0 0 2-1.5L22 7H6"/><circle cx="10" cy="21" r="1"/><circle cx="18" cy="21" r="1"/></svg><span class="litrd-cart-count">'.esc_html((string)$count).'</span></a></li>';
 }else{
 $items.='<li class="menu-item litrd-ordered-menu-item"><a href="'.esc_url($link[1]).'"'.$current.'>'.esc_html($link[0]).'</a></li>';
 }
 if($link[0]==='ARRMA MAFIA'){$items.='<li class="menu-item litrd-ordered-menu-item"><a href="https://findrc.parts/" class="notranslate" translate="no">FINDRC.PARTS</a></li>';}
 }
 return $items;
}

// Apply the full-width layout throughout the sandbox, including WooCommerce pages.
add_filter('body_class',function($classes){
 if(litrd_sandbox()){$classes=array_diff($classes,array('right-sidebar','left-sidebar','has-sidebar','sidebar-left','sidebar-right'));$classes[]='litrd-no-sidebars';$classes[]='no-sidebar';}
 return array_values(array_unique($classes));
},30);
add_action('wp_enqueue_scripts',function(){
 if(!litrd_sandbox()){return;}
 wp_register_style('litrd-full-width',false,array(),'2.6.0');
 wp_enqueue_style('litrd-full-width');
 wp_add_inline_style('litrd-full-width','
 body.litrd-no-sidebars #secondary,
 body.litrd-no-sidebars #tertiary,
 body.litrd-no-sidebars aside.sidebar,
 body.litrd-no-sidebars .sidebar,
 body.litrd-no-sidebars .widget-area:not(footer .widget-area):not(.site-footer .widget-area):not(header .widget-area):not(.site-header .widget-area){display:none!important}
 body.litrd-no-sidebars #primary,
 body.litrd-no-sidebars .content-area,
 body.litrd-no-sidebars .site-main,
 body.litrd-no-sidebars .content-sidebar-wrap{float:none!important;clear:both;width:100%!important;max-width:100%!important;flex:1 1 100%!important;margin-left:0!important;margin-right:0!important}
 body.litrd-no-sidebars .site-content:has(> #primary),
 body.litrd-no-sidebars .site-content:has(> .content-area),
 body.litrd-no-sidebars .row:has(> #secondary),
 body.litrd-no-sidebars .row:has(> .sidebar){grid-template-columns:minmax(0,1fr)!important}
 ');
},99);

function litrd_remove_header_search_cart($header){
 $header=preg_replace('~<(?:i|span)\b[^>]*class=[\"\'][^\"\']*(?:fa-shopping-cart|fa-cart-shopping|cart-icon)[^\"\']*[\"\'][^>]*>\s*</(?:i|span)>~i','',$header);
 // Only inspect the captured theme header; keep the product search below the shipping banner.
 $header=preg_replace_callback('~<form\b[^>]*>.*?</form>~is',function($match){
 return preg_match('~<input\b[^>]*\bname\s*=\s*["\']s["\']~i',$match[0])?'':$match[0];
 },$header);
 $header=preg_replace_callback('~<a\b[^>]*>.*?</a>~is',function($match){
 if(preg_match('~\bclass\s*=\s*["\'][^"\']*(?:cart-contents|cart-content|header-cart|shopping-cart)[^"\']*["\']~i',$match[0])){return '';}
 if(preg_match('~href\s*=\s*["\']([^"\']+)["\']~i',$match[0],$url)){
 $path=untrailingslashit((string)wp_parse_url(html_entity_decode($url[1]),PHP_URL_PATH));
 $cart_path=untrailingslashit((string)wp_parse_url(wc_get_cart_url(),PHP_URL_PATH));
 if($path===$cart_path && preg_match('~(?:\bitems?\b|<svg\b|<i\b|<img\b)~i',$match[0])){return '';}
 }
 return $match[0];
 },$header);
 return $header;
}
add_filter('woocommerce_add_to_cart_fragments',function($fragments){
 if(!litrd_sandbox()){return $fragments;}
 $count=WC()->cart?(int)WC()->cart->get_cart_contents_count():0;
 $fragments['.litrd-cart-count']='<span class="litrd-cart-count">'.esc_html((string)$count).'</span>';
 return $fragments;
});



function litrd_header_without_sandbox($header){
 if(!litrd_sandbox()){return $header;}
 // Change only visible text inside the theme's site-title element.
 return preg_replace_callback('~(<(h[1-6]|p)\b[^>]*\bclass=["\'][^"\']*\bsite-title\b[^"\']*["\'][^>]*>)(.*?)(</\2>)~is',function($match){
  $parts=preg_split('~(<[^>]*>)~s',$match[3],-1,PREG_SPLIT_DELIM_CAPTURE);
  foreach($parts as $index=>$part){
   if($index%2===0){
    $text=html_entity_decode($part,ENT_QUOTES,'UTF-8');
    $clean=preg_replace('~\s*(?:[\x{2014}\x{2013}-]\s*)?Sandbox\s*$~iu','',$text);
    if($clean!==$text){$parts[$index]=esc_html($clean);}
   }
  }
  return $match[1].implode('',$parts).$match[4];
 },$header,1);
}

function litrd_logo_beside_header_text($header){
 $header=litrd_header_without_sandbox($header);
 $logo='<a class="litrd-blue-header-logo" href="'.esc_url(home_url('/')).'" aria-label="Lit Decals home"><img src="'.esc_url(litrd_logo_source()).'" alt="Lit Decals" loading="eager" fetchpriority="high"></a>';
 $count=0;
 $header=preg_replace_callback('~(<(?:div|section)\b[^>]*class=["\'][^"\']*\bsite-branding\b[^"\']*["\'][^>]*>)~i',function($m)use($logo){return $m[1].$logo;},$header,1,$count);
 if(!$count){$header=preg_replace_callback('~<(?:h1|h2)\b[^>]*>~i',function($m)use($logo){return $logo.$m[0];},$header,1);}
 return $header;
}

function litrd_is_exclusively_retired_product($id){
 $retired=litrd_retired_terms();
 foreach(array('product_cat','product_brand','pa_brand') as $taxonomy){
  if(!taxonomy_exists($taxonomy)){continue;}
  $terms=wp_get_object_terms($id,$taxonomy,array('fields'=>'ids','suppress_filter'=>true));
  if(!is_wp_error($terms) && $terms && array_intersect($terms,$retired) && !array_diff($terms,$retired)){return true;}
 }
 return false;
}
add_filter('get_the_terms',function($terms){
 if(!litrd_brand_filtering() || !is_array($terms)){return $terms;}
 return array_values(array_filter($terms,function($term){return !litrd_retired_brand($term->name.' '.$term->slug);}));
},30);
function litrd_retired_variation($variation){
 if(!$variation || !is_a($variation,'WC_Product_Variation')){return false;}
 foreach($variation->get_attributes() as $name=>$value){
  if(litrd_retired_brand($value)){return true;}
  if(taxonomy_exists($name)){$term=get_term_by('slug',$value,$name);if($term && !is_wp_error($term) && litrd_retired_brand($term->name)){return true;}}
 }
 return false;
}
add_filter('woocommerce_dropdown_variation_attribute_options_args',function($args){
 if(!litrd_brand_filtering()){return $args;}
 if(empty($args['options']) && !empty($args['product']) && !empty($args['attribute'])){$attributes=$args['product']->get_variation_attributes();$args['options']=isset($attributes[$args['attribute']])?$attributes[$args['attribute']]:array();}
 if(!empty($args['options'])){$taxonomy=isset($args['attribute'])?$args['attribute']:'';$args['options']=array_values(array_filter($args['options'],function($option)use($taxonomy){if(litrd_retired_brand($option)){return false;}if(taxonomy_exists($taxonomy)){$term=get_term_by('slug',$option,$taxonomy);if($term && !is_wp_error($term) && litrd_retired_brand($term->name)){return false;}}return true;}));}
 return $args;
},30);
add_filter('woocommerce_variation_is_visible',function($visible,$id,$parent,$variation){return litrd_brand_filtering() && litrd_retired_variation($variation)?false:$visible;},30,4);
add_filter('woocommerce_variation_is_active',function($active,$variation){return litrd_brand_filtering() && litrd_retired_variation($variation)?false:$active;},30,2);
add_filter('woocommerce_add_to_cart_validation',function($valid,$product_id,$quantity,$variation_id=0,$variations=array()){
 if(litrd_brand_filtering() && $variation_id && litrd_retired_variation(wc_get_product($variation_id))){wc_add_notice('This design is no longer available. Please choose another design.','error');return false;}
 return $valid;
},30,5);

add_filter('template_include',function($template){
 if(!litrd_sandbox() || !function_exists('wc_get_products') || is_front_page() || is_feed() || is_embed() || !get_option('litrd_enabled',true) || $template===__DIR__.'/home.php'){return $template;}
 $GLOBALS['litrd_original_template']=$template;
 return __DIR__.'/home.php';
},100);
add_action('get_footer',function(){$GLOBALS['litrd_capture_header']=false;},1);
function litrd_catalog_url(){return home_url('/shop/');}
function litrd_browse_search_row(){
 $shop=litrd_catalog_url();
 ob_start();
 ?><div class="litrd-topline"><div class="litrd-browse-links"><span>EXPLORE LIT DECALS</span><a href="<?php echo esc_url($shop); ?>">Browse all products <span aria-hidden="true">↗</span></a></div><form class="litrd-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>"><label for="litrd-search">Find something for your build</label><div><input id="litrd-search" type="search" name="s" placeholder="Search decals, skins, accessories…" required><input type="hidden" name="post_type" value="product"><button type="submit">Search <span aria-hidden="true">↗</span></button></div></form></div><?php
 return ob_get_clean();
}
function litrd_consistent_inner_page_header($document){
 // Remove the theme's old header-menu output, captured before the footer began.
 $menus=isset($GLOBALS['litrd_header_menus'])?array_unique($GLOBALS['litrd_header_menus']):array();
 usort($menus,function($a,$b){return strlen($b)-strlen($a);});
 foreach($menus as $menu){$position=strpos($document,$menu);if($position!==false){$document=substr_replace($document,'',$position,strlen($menu));}}
 $document=preg_replace_callback('~<header\b[^>]*>.*?</header>~is',function($match){
  $header=litrd_remove_header_search_cart($match[0]);
  $header=litrd_logo_beside_header_text($header);
  $header=preg_replace_callback('~<header\b([^>]*)>~i',function($tag){
   $attributes=$tag[1];
   if(preg_match('~\bclass\s*=\s*(["\'])(.*?)\1~is',$attributes)){
    $attributes=preg_replace_callback('~\bclass\s*=\s*(["\'])(.*?)\1~is',function($c){return 'class='.$c[1].$c[2].' litrd-spaced-header'.$c[1];},$attributes,1);
   }else{$attributes.=' class="litrd-spaced-header"';}
   return '<header'.$attributes.'>';
  },$header,1);
  return $header.'<nav class="litrd-page-bar" aria-label="Website pages"><ul>'.litrd_ordered_navigation().'</ul></nav><div class="litrd-store litrd-shared-search">'.litrd_browse_search_row().'</div>';
 },$document,1);
 return $document;
}

 
// Keep the Mafia landing page focused without changing catalog records or categories.
function litrd_mafia_page(){
 return litrd_sandbox() && !is_admin() && is_page('arrma-mafia-shop');
}
function litrd_scope_mafia_query($query){
 if(!litrd_mafia_page() || $query->is_main_query()){return;}
 if(!in_array('product',(array)$query->get('post_type'),true)){return;}
 $query->set('litrd_mafia_only',true);
 $query->set('suppress_filters',false);
}
add_action('pre_get_posts','litrd_scope_mafia_query',20);
function litrd_mafia_products_where($where,$query){
 if(!litrd_mafia_page() || !$query->get('litrd_mafia_only')){return $where;}
 global $wpdb;
 return $where.$wpdb->prepare(" AND {$wpdb->posts}.post_title LIKE %s",'%'.$wpdb->esc_like('mafia').'%');
}
add_filter('posts_where','litrd_mafia_products_where',25,2);

function litrd_mafia_product_block($content,$block){
 if(!litrd_mafia_page() || empty($block['blockName']) || !in_array($block['blockName'],array('woocommerce/all-products','woocommerce/product-collection','woocommerce/product-tag'),true)){return $content;}
 $ids=get_posts(array(
  'post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,
  's'=>'mafia','search_columns'=>array('post_title'),'fields'=>'ids',
  'orderby'=>'title','order'=>'ASC','suppress_filters'=>false
 ));
 if(!$ids){return '<p>No Mafia products are currently available.</p>';}
 return do_shortcode('[products ids="'.implode(',',array_map('absint',$ids)).'" columns="3" limit="-1" orderby="title" order="ASC"]');
}
add_filter('render_block','litrd_mafia_product_block',20,2);

// One authenticated publishing operation can repair redundant hosting challenges.
// The existing directory password stays required; failed checks restore the file.
function litrd_repair_browser_auth_header($response,$server,$request){
 if(!litrd_sandbox() || $request->get_route()!=='/litdecals-sandbox/v1/preview' || $request->get_method()!=='POST' || $request->get_param('repair_auth_header')!==true){return $response;}
 if(!current_user_can('manage_options') || !current_user_can('install_plugins') || !current_user_can('activate_plugins')){return $response;}
 if(is_wp_error($response) || !is_object($response) || !method_exists($response,'get_data') || $response->get_status()>=400){return $response;}
 $data=$response->get_data();
 if(defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS){$data['auth_header_repair']='file_modifications_disabled';$response->set_data($data);return $response;}
 $file=ABSPATH.'.htaccess';
 if(is_link($file) || !is_file($file) || !is_readable($file) || !is_writable($file)){$data['auth_header_repair']='configuration_not_writable';$response->set_data($data);return $response;}
 $authorization=$request->get_header('authorization');
 if(!is_string($authorization) || stripos($authorization,'Basic ')!==0){$data['auth_header_repair']='hosting_auth_unavailable';$response->set_data($data);return $response;}
 $original=file_get_contents($file);
 if($original===false){return $response;}
 if(!function_exists('insert_with_markers')){require_once ABSPATH.'wp-admin/includes/misc.php';}
 $rules=array(
  '<IfModule mod_headers.c>',
  'Header always unset WWW-Authenticate "expr=-n %{REMOTE_USER}"',
  'Header onsuccess unset WWW-Authenticate "expr=-n %{REMOTE_USER}"',
  '</IfModule>'
 );
 if(!insert_with_markers($file,'LitDecals Sandbox Successful Authentication',$rules)){$data['auth_header_repair']='configuration_write_failed';$response->set_data($data);return $response;}
 $url=add_query_arg('litrd_auth_check',wp_generate_uuid4(),home_url('/shop/'));
 $authenticated=wp_remote_get($url,array('timeout'=>12,'redirection'=>0,'sslverify'=>true,'headers'=>array('Authorization'=>$authorization,'Cache-Control'=>'no-cache')));
 $anonymous=wp_remote_get($url,array('timeout'=>12,'redirection'=>0,'sslverify'=>true,'headers'=>array('Cache-Control'=>'no-cache')));
 $data['auth_header_checks']=array(
  'authenticated_status'=>is_wp_error($authenticated)?$authenticated->get_error_code():wp_remote_retrieve_response_code($authenticated),
  'anonymous_status'=>is_wp_error($anonymous)?$anonymous->get_error_code():wp_remote_retrieve_response_code($anonymous),
  'authenticated_challenge'=>!is_wp_error($authenticated) && (bool)wp_remote_retrieve_header($authenticated,'www-authenticate'),
  'anonymous_challenge'=>!is_wp_error($anonymous) && (bool)wp_remote_retrieve_header($anonymous,'www-authenticate')
 );
 $verified=!is_wp_error($authenticated) && !is_wp_error($anonymous)
  && wp_remote_retrieve_response_code($authenticated)===200
  && !wp_remote_retrieve_header($authenticated,'www-authenticate')
  && wp_remote_retrieve_response_code($anonymous)===401
  && wp_remote_retrieve_header($anonymous,'www-authenticate');
 if($verified){
  if(get_option('litrd_auth_header_original_htaccess',null)===null){add_option('litrd_auth_header_original_htaccess',$original,'','no');}
  $data['auth_header_repair']='verified';
 }else{
  $restored=file_put_contents($file,$original,LOCK_EX)===strlen($original);
  $data['auth_header_repair']=$restored?'rolled_back':'restore_failed';
 }
 unset($authorization);
 $response->set_data($data);
 return $response;
}
add_filter('rest_post_dispatch','litrd_repair_browser_auth_header',20,3);


// Explicit owner-authorized change: make only this sandbox storefront public.
function litrd_make_storefront_public($response,$server,$request){
 if(!litrd_sandbox() || $request->get_route()!=='/litdecals-sandbox/v1/preview' || $request->get_method()!=='POST' || $request->get_param('make_storefront_public')!==true){return $response;}
 if(!current_user_can('manage_options') || !current_user_can('install_plugins') || !current_user_can('activate_plugins')){return $response;}
 if(is_wp_error($response) || !is_object($response) || !method_exists($response,'get_data') || $response->get_status()>=400){return $response;}
 $data=$response->get_data();
 if(defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS){$data['public_access']='file_modifications_disabled';$response->set_data($data);return $response;}
 $file=ABSPATH.'.htaccess';
 if(is_link($file) || !is_file($file) || !is_readable($file) || !is_writable($file)){$data['public_access']='configuration_not_writable';$response->set_data($data);return $response;}
 $original=file_get_contents($file);
 if($original===false){return $response;}
 $used_cpanel=false;
 $has_basic=(bool)preg_match('/^\s*AuthType\s+Basic\s*$/mi',$original);
 if($has_basic && !preg_match('~^\s*AuthUserFile\b[^\r\n]*/sandbox-redesign/[^\r\n]*$~mi',$original)){
  $data['public_access']='unrecognized_password_configuration';$response->set_data($data);return $response;
 }
 $updated=$original;
 $removed=0;
 if($has_basic){
  $updated=preg_replace('/^[\t ]*(?:AuthType[\t ]+Basic|AuthName\b[^\r\n]*|AuthUserFile\b[^\r\n]*|AuthGroupFile\b[^\r\n]*|Require[\t ]+valid-user)[\t ]*(?:\r?\n|$)/mi','',$original,-1,$removed);
 }
 if($updated===null || ($has_basic && $removed<3)){$data['public_access']='unrecognized_password_configuration';$response->set_data($data);return $response;}
 // Explicitly override inherited directory authentication only for this sandbox.
 $updated=preg_replace('~\n?# BEGIN LitDecals Public Sandbox\n.*?# END LitDecals Public Sandbox\n?~s','',$updated);
 $updated=rtrim($updated)."\n\n# BEGIN LitDecals Public Sandbox\nAuthType None\nRequire all granted\n# END LitDecals Public Sandbox\n";
 if($request->get_param('use_cpanel_privacy')===true){
  $cpanel=litrd_cpanel_directory_privacy(false,'');
  if(!$cpanel){$data['public_access']='cpanel_api_unavailable_or_failed';$response->set_data($data);return $response;}
  $used_cpanel=true;
 }
 if($updated!==$original && file_put_contents($file,$updated,LOCK_EX)!==strlen($updated)){
  file_put_contents($file,$original,LOCK_EX);
  if($used_cpanel){litrd_cpanel_directory_privacy(true,"Protected 'public_html/sandbox-redesign'");}
  $data['public_access']='configuration_write_failed';$response->set_data($data);return $response;
 }
 $args=array('timeout'=>12,'redirection'=>0,'sslverify'=>true,'headers'=>array('Cache-Control'=>'no-cache'));
 $stamp=wp_generate_uuid4();
 $home=wp_remote_get(add_query_arg('litrd_public_check',$stamp,home_url('/')),$args);
 $shop=wp_remote_get(add_query_arg('litrd_public_check',$stamp,home_url('/shop/')),$args);
 $admin=wp_remote_get(home_url('/wp-admin/'),$args);
 $login=wp_remote_get(home_url('/wp-login.php'),$args);
 $admin_location=is_wp_error($admin)?'':wp_remote_retrieve_header($admin,'location');
 $login_path=(string)wp_parse_url(home_url('/wp-login.php'),PHP_URL_PATH);
 $verified=!is_wp_error($home) && !is_wp_error($shop) && !is_wp_error($admin) && !is_wp_error($login)
  && wp_remote_retrieve_response_code($home)===200 && !wp_remote_retrieve_header($home,'www-authenticate')
  && wp_remote_retrieve_response_code($shop)===200 && !wp_remote_retrieve_header($shop,'www-authenticate')
  && in_array(wp_remote_retrieve_response_code($admin),array(301,302,303),true)
  && (string)wp_parse_url($admin_location,PHP_URL_PATH)===$login_path
  && wp_remote_retrieve_response_code($login)===200 && strpos(wp_remote_retrieve_body($login),'id="loginform"')!==false;
 $data['public_access_checks']=array(
  'home_status'=>is_wp_error($home)?0:wp_remote_retrieve_response_code($home),
  'shop_status'=>is_wp_error($shop)?0:wp_remote_retrieve_response_code($shop),
  'admin_redirects_to_login'=>(string)wp_parse_url($admin_location,PHP_URL_PATH)===$login_path,
  'login_form_present'=>!is_wp_error($login) && strpos(wp_remote_retrieve_body($login),'id="loginform"')!==false
 );
 if($verified){
  if($updated!==$original && get_option('litrd_private_htaccess_before_public',null)===null){add_option('litrd_private_htaccess_before_public',$original,'','no');}
  update_option('litrd_sandbox_storefront_public',true,false);
  $data['public_access']='verified';
 }else{
  if($used_cpanel){
   $authname="Protected 'public_html/sandbox-redesign'";
   if(preg_match('/^\s*AuthName\s+(.+)$/mi',$original,$match)){$authname=trim($match[1]," \t\r\n\"'");}
   litrd_cpanel_directory_privacy(true,$authname);
  }
  $restored=file_put_contents($file,$original,LOCK_EX)===strlen($original);
  $data['public_access']=$restored?'rolled_back':'restore_failed';
 }
 $response->set_data($data);
 return $response;
}
add_filter('rest_post_dispatch','litrd_make_storefront_public',25,3);

function litrd_cpanel_directory_privacy($enabled,$authname){
 if(!litrd_sandbox() || !function_exists('shell_exec') || !is_executable('/usr/local/cpanel/bin/uapi')){return false;}
 $arguments=array(
  '/usr/local/cpanel/bin/uapi','--user=litdwlek','--output=json',
  'DirectoryPrivacy','configure_directory_protection',
  'dir='.untrailingslashit(ABSPATH),'enabled='.($enabled?'1':'0')
 );
 if($enabled){$arguments[]='authname='.$authname;}
 $command=implode(' ',array_map('escapeshellarg',$arguments));
 $result=json_decode((string)shell_exec($command.' 2>/dev/null'),true);
 return is_array($result) && isset($result['result']['status']) && (int)$result['result']['status']===1;
}
