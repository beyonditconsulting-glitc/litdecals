<?php
$root=sys_get_temp_dir().'/litdecals-header-test/sandbox-redesign';
if(!is_dir($root)){mkdir($root,0700,true);}
define('ABSPATH',$root.'/');
$GLOBALS['site_path']='/sandbox-redesign/';
function home_url($path){return 'https://litdecals.com'.rtrim($GLOBALS['site_path'],'/').'/'.ltrim($path,'/');}
function site_url($path){return home_url($path);}
function wp_parse_url($url,$component){return parse_url($url,$component);}
function add_action(){}
function add_filter(){}
function esc_url($value){return $value;}
function esc_html($value){return htmlspecialchars($value,ENT_QUOTES,'UTF-8');}
require __DIR__.'/../storefront/litdecals-storefront.php';
function check($condition,$message){if(!$condition){throw new Exception($message);}echo 'PASS: '.$message.PHP_EOL;}
$header='<header><h1 class="site-title title-font"><a href="/sandbox-redesign/">Lit Decals &mdash; Sandbox</a></h1><p>Sandbox description</p></header>';
$expected='<header><h1 class="site-title title-font"><a href="/sandbox-redesign/">Lit Decals</a></h1><p>Sandbox description</p></header>';
check(litrd_header_without_sandbox($header)===$expected,'Only the visible site title changes; markup and other content remain intact');
check(litrd_header_without_sandbox(str_replace('&mdash;','—',$header))===$expected,'Literal em dash is handled');
check(litrd_header_without_sandbox(str_replace('&mdash;','&#8212;',$header))===$expected,'Numeric dash entity is handled');
check(litrd_header_without_sandbox($expected)===$expected,'An already-correct title is preserved');
check(litrd_catalog_url()==='https://litdecals.com/sandbox-redesign/?post_type=product&s=','The full-catalog link remains in the sandbox root authentication path');
$branded='<header><div class="site-branding"><h1 class="site-title">Lit Decals</h1><h2 class="site-description">Old description</h2></div></header>';
$ad_header=litrd_logo_beside_header_text($branded);
check(strpos($ad_header,'class="site-title"')===false && strpos($ad_header,'Old description')===false,'The old header title and description are removed');
check(strpos($ad_header,'litrd-blue-header-logo')!==false && strpos($ad_header,'litrd-header-ad')!==false,'The original logo and linked banner share the header row');
check(strpos($ad_header,'href="https://findrc.parts/"')!==false,'The header advertisement links to FINDRC.PARTS');
check(hash('sha256',base64_decode(litrd_header_banner_data(),true))===hash_file('sha256',__DIR__.'/../design/findrc-parts-basher.webp'),'The embedded banner matches the exact selected artwork');
$GLOBALS['site_path']='/';
check(litrd_header_without_sandbox($header)===$header,'The production-site URL is excluded');
