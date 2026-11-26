<?php
define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);
class WP_Error {
    public $code; public $message; public $data;
    public function __construct($code, $message, $data) { $this->code=$code; $this->message=$message; $this->data=$data; }
}
class FakeRequest {
    private $data; private $body;
    public function __construct($data, $body = null) { $this->data=$data; $this->body=$body ?? json_encode($data); }
    public function get_body() { return $this->body; }
    public function get_json_params() { return $this->data; }
}
function add_action($hook, $cb) {}
function wp_parse_url($url) { return parse_url($url); }
function get_option($name, $fallback = []) { return $GLOBALS['test_options']; }
function sanitize_text_field($s) { return trim(strip_tags($s)); }
function wp_salt($scheme) { return 'fictional-test-salt'; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key]=$value; }
function wp_json_encode($x) { return json_encode($x); }
function wp_safe_remote_post($url, $args) { $GLOBALS['last_request'] = [$url, $args]; return $GLOBALS['fake_response']; }
function is_wp_error($x) { return $x instanceof WP_Error; }
function wp_remote_retrieve_response_code($x) { return $x['code']; }
function rest_ensure_response($x) { return $x; }
function check_test($ok, $message) { if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } echo "PASS: $message\n"; }
require __DIR__ . '/interactive-tool-bridge.php';
$test_hook='https://hooks.zapier.com/hooks/catch/123456/abcdef/';
$GLOBALS['test_options']=['webhook'=>$test_hook, 'enabled'=>true];
$GLOBALS['transients']=[];
$GLOBALS['fake_response']=['code'=>200];
$_SERVER['REMOTE_ADDR']='203.0.113.21';
$sample=['tool_name'=>'Starter quiz', 'displayed_result'=>'Try a quiz.', 'answers'=>['reader_goal'=>'Choose between options'], 'send_confirmed'=>true, 'website'=>''];
check_test(sks_bridge_valid_url($test_hook), 'accepts expected hook URL');
foreach (['http://hooks.zapier.com/hooks/catch/123/a/', 'https://hooks.zapier.com.evil.test/hooks/catch/123/a/', 'https://hooks.zapier.com/hooks/catch/123/a/?x=1', 'https://evil.test/hooks/catch/123/a/'] as $url) {
    check_test(!sks_bridge_valid_url($url), 'rejects unrelated or unsafe URL');
}
$result=sks_bridge_submit(new FakeRequest($sample));
$sent=json_decode($GLOBALS['last_request'][1]['body'],true);
check_test($result['received']===true && $sent['displayed_result']==='Try a quiz.' && $sent['answers']['reader_goal']==='Choose between options', 'forwards answer and actual displayed result');
check_test($GLOBALS['last_request'][0]===$test_hook && $GLOBALS['last_request'][1]['redirection']===0, 'sends only to saved hook without redirects');
$bad=$sample; unset($bad['send_confirmed']);
check_test(sks_bridge_submit(new FakeRequest($bad))->data['status']===400, 'requires explicit send');
$bad=$sample; $bad['website']='spam';
check_test(sks_bridge_submit(new FakeRequest($bad))->data['status']===400, 'rejects honeypot submissions');
$bad=$sample; $bad['displayed_result']='';
check_test(sks_bridge_submit(new FakeRequest($bad))->data['status']===400, 'rejects missing result');
check_test(sks_bridge_submit(new FakeRequest($sample,str_repeat('x',8193)))->data['status']===413, 'rejects oversized request');
$GLOBALS['fake_response']=['code'=>500];
check_test(sks_bridge_submit(new FakeRequest($sample))->data['status']===502, 'reports upstream failure without exposing hook');
$GLOBALS['fake_response']=['code'=>200];
$GLOBALS['transients']=[];
for ($i=0; $i<20; $i++) { sks_bridge_submit(new FakeRequest($sample)); }
check_test(sks_bridge_submit(new FakeRequest($sample))->data['status']===429, 'limits repeated requests from one address');
$GLOBALS['test_options']['enabled']=false;
check_test(sks_bridge_submit(new FakeRequest($sample))->data['status']===503, 'disables receiver until enabled');
