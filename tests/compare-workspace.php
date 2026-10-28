<?php
/** Read-only production integration checks for comparison fragments. */
if(!defined('WP_CLI')||!WP_CLI)exit(1);
$request=new WP_REST_Request('GET','/chidemoon-core/v1/compare-table');$request->set_param('ids','443');
$response=rest_do_request($request);$html=$response->get_data()['html']??'';
$checks=[
 'single-product request keeps a real table'=>$response->get_status()===200&&str_contains($html,'<table ')&&str_contains($html,'data-product-count="1"'),
 'single-product table retains purchase destination'=>str_contains($html,'/go/443/'),
 'search remains available independent of table contents'=>str_contains(Chidemoon_Core_Compare::render_picker_shortcode(),'data-comparison-search-input'),
];
$request=new WP_REST_Request('GET','/chidemoon-core/v1/compare-table');$request->set_param('ids','999999');
$data=rest_do_request($request)->get_data();
$checks['unknown products cannot create a comparison column']=!str_contains($data['html']??'','<table ');
$private=get_posts(['post_type'=>'product','post_status'=>['draft','pending','private'],'posts_per_page'=>1,'fields'=>'ids']);
if($private){$request=new WP_REST_Request('GET','/chidemoon-core/v1/compare-table');$request->set_param('ids',(string)$private[0]);$data=rest_do_request($request)->get_data();$checks['unpublished products remain unavailable through public fragments']=!str_contains($data['html']??'','<table ');}
foreach($checks as $label=>$pass){if(!$pass)WP_CLI::error($label);WP_CLI::log('PASS: '.$label);}
