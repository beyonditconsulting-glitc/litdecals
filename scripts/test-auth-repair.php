<?php
require __DIR__.'/test-header.php';
$GLOBALS['site_path']='/sandbox-redesign/';
$GLOBALS['repair_options']=array();
function current_user_can($capability){return true;}
function is_wp_error($value){return false;}
function get_option($name,$default=false){return isset($GLOBALS['repair_options'][$name])?$GLOBALS['repair_options'][$name]:$default;}
function add_option($name,$value,$deprecated='',$autoload=null){$GLOBALS['repair_options'][$name]=$value;return true;}
function wp_generate_uuid4(){return 'test-check';}
function add_query_arg($key,$value,$url){return $url.'?'.$key.'='.$value;}
function wp_remote_get($url,$options){return array_shift($GLOBALS['repair_responses']);}
function wp_remote_retrieve_response_code($response){return $response['status'];}
function wp_remote_retrieve_header($response,$header){return isset($response['headers'][$header])?$response['headers'][$header]:'';}
function insert_with_markers($file,$marker,$rules){return file_put_contents($file,file_get_contents($file)."\n# BEGIN ".$marker."\n".implode("\n",$rules)."\n# END ".$marker."\n")!==false;}
class LitrdRepairRequest{
 public $route='/litdecals-sandbox/v1/preview';public $method='POST';public $authorization='Basic test-only';
 function get_route(){return $this->route;}
 function get_method(){return $this->method;}
 function get_param($key){return $key==='repair_auth_header';}
 function get_header($key){return $this->authorization;}
}
class LitrdRepairResponse{
 public $data=array('enabled'=>true);
 function get_data(){return $this->data;}
 function set_data($data){$this->data=$data;}
 function get_status(){return 200;}
}
$file=ABSPATH.'.htaccess';
$original="AuthType Basic\nAuthName \"Sandbox\"\nRequire valid-user\n";
function run_repair($authenticated,$anonymous){
 global $file,$original;
 file_put_contents($file,$original);
 $GLOBALS['repair_responses']=array($authenticated,$anonymous);
 $response=new LitrdRepairResponse();
 return litrd_repair_browser_auth_header($response,null,new LitrdRepairRequest());
}
$protected=array('status'=>401,'headers'=>array('www-authenticate'=>'Basic realm="Sandbox"'));
$ok=array('status'=>200,'headers'=>array());
$result=run_repair($ok,$protected);
check($result->data['auth_header_repair']==='verified','Successful-response cleanup requires authenticated 200 and anonymous 401');
check(strpos(file_get_contents($file),'Require valid-user')!==false,'Existing password protection is retained');
check($GLOBALS['repair_options']['litrd_auth_header_original_htaccess']===$original,'The original configuration is retained privately');
$result=run_repair(array('status'=>500,'headers'=>array()),$protected);
check($result->data['auth_header_repair']==='rolled_back' && file_get_contents($file)===$original,'A server error restores the original configuration');
$result=run_repair($ok,$ok);
check($result->data['auth_header_repair']==='rolled_back' && file_get_contents($file)===$original,'Anonymous access becoming public causes an automatic rollback');
$result=run_repair(array('status'=>200,'headers'=>array('www-authenticate'=>'Basic realm="Sandbox"')),$protected);
check($result->data['auth_header_repair']==='rolled_back','An ineffective cleanup is rolled back');
file_put_contents($file,$original);
$request=new LitrdRepairRequest();$request->authorization='';
$response=litrd_repair_browser_auth_header(new LitrdRepairResponse(),null,$request);
check($response->data['auth_header_repair']==='hosting_auth_unavailable' && file_get_contents($file)===$original,'Missing hosting credentials do not modify configuration');
$request=new LitrdRepairRequest();$request->method='GET';
$response=litrd_repair_browser_auth_header(new LitrdRepairResponse(),null,$request);
check(!isset($response->data['auth_header_repair']) && file_get_contents($file)===$original,'Read-only requests never modify configuration');
unlink($file);
