<?php
/** Read-only WordPress integration checks for responsive image rewriting. */
if(!defined('WP_CLI')||!WP_CLI)exit(1);
$base=trailingslashit(wp_upload_dir()['baseurl']);
$manifest=['test/source.jpg'=>['id'=>123,'width'=>1200,'height'=>800,'variants'=>[['url'=>$base.'test/640.webp','width'=>640],['url'=>$base.'test/1200.webp','width'=>1200]]]];
$html='<a href="'.$base.'test/source.jpg"><img src="'.$base.'test/source.jpg" alt="محصول" sizes="50vw" data-large_image="'.$base.'test/source.jpg"></a><img src="https://example.org/external.jpg">';
$result=Chidemoon_Core_Responsive_Images::rewrite($html,$manifest);
$checks=[
 'compact and legacy manifests produce identical markup'=>$result===Chidemoon_Core_Responsive_Images::rewrite($html,Chidemoon_Core_Responsive_Images::index($manifest)),
 'responsive WebP candidates are emitted'=>str_contains($result,'640.webp 640w')&&str_contains($result,'1200.webp 1200w'),
 'layout sizes remain intact'=>str_contains($result,'sizes="50vw"'),
 'zoom and original-image link remain intact'=>str_contains($result,'href="'.$base.'test/source.jpg"')&&str_contains($result,'data-large_image="'.$base.'test/source.jpg"'),
 'external assets are untouched'=>str_contains($result,'src="https://example.org/external.jpg"'),
 'intrinsic aspect ratio remains intact'=>str_contains($result,'width="1200"')&&str_contains($result,'height="800"'),
];
foreach($checks as $label=>$pass){if(!$pass)WP_CLI::error($label);WP_CLI::log('PASS: '.$label);}
