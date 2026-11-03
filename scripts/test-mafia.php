<?php
$GLOBALS['mafia_page_test']=true;
$GLOBALS['mafia_admin_test']=false;
function is_admin(){return $GLOBALS['mafia_admin_test'];}
function is_page($slug){return $GLOBALS['mafia_page_test'] && $slug==='arrma-mafia-shop';}
require __DIR__.'/test-header.php';
$GLOBALS['site_path']='/sandbox-redesign/';
class LitrdTestQuery{
 public $values;private $main;
 function __construct($type,$main=false){$this->values=array('post_type'=>$type,'tax_query'=>array('existing'));$this->main=$main;}
 function get($key){return isset($this->values[$key])?$this->values[$key]:null;}
 function set($key,$value){$this->values[$key]=$value;}
 function is_main_query(){return $this->main;}
}
class LitrdTestDatabase{
 public $posts='wp_posts';
 function esc_like($value){return addcslashes($value,'_%\\');}
 function prepare($sql,$value){return str_replace('%s',"'".str_replace("'","''",$value)."'",$sql);}
}
$GLOBALS['wpdb']=new LitrdTestDatabase();
$q=new LitrdTestQuery('product');litrd_scope_mafia_query($q);
check($q->get('litrd_mafia_only')===true,'Product queries on the Mafia page are restricted');
check($q->get('tax_query')===array('existing'),'Existing product selection constraints are preserved');
check(strpos(litrd_mafia_products_where('WHERE 1=1',$q),"post_title LIKE '%mafia%'")!==false,'The Mafia restriction checks product titles');
$main=new LitrdTestQuery('product',true);litrd_scope_mafia_query($main);
check(!$main->get('litrd_mafia_only'),'The main page query is preserved');
$GLOBALS['mafia_page_test']=false;
$shop=new LitrdTestQuery('product');litrd_scope_mafia_query($shop);
check(!$shop->get('litrd_mafia_only'),'The general shop is unaffected');
check(litrd_mafia_products_where('WHERE 1=1',$q)==='WHERE 1=1','The restriction does not run outside the Mafia page');
$GLOBALS['mafia_page_test']=true;$GLOBALS['mafia_admin_test']=true;
$admin=new LitrdTestQuery('product');litrd_scope_mafia_query($admin);
check(!$admin->get('litrd_mafia_only'),'Administration and catalog records are unaffected');
