<?php
/** Execute in WordPress CLI context after loading wp-load.php. */
$dir=wp_upload_dir()['basedir'].'/ch-style-test';wp_mkdir_p($dir);
$file=$dir.'/fixture.css';file_put_contents($file,'.fixture{background:url("../photo.png");mask:url(#icon);color:red}');
$url=wp_upload_dir()['baseurl'].'/ch-style-test/fixture.css';
$html='<link rel="stylesheet" id="fixture" media="screen" href="'.$url.'?ver=1"><style>.fixture{color:blue}</style><link rel="stylesheet" href="https://example.org/external.css">';
$out=Chidemoon_Core_Critical_Styles::rewrite($html);
$checks=[str_contains($out,'<style id="fixture" media="screen" data-ch-inline="1">'),str_contains($out,'/ch-style-test/../photo.png'),str_contains($out,'url(#icon)'),strpos($out,'color:red')<strpos($out,'color:blue'),str_contains($out,'href="https://example.org/external.css"')];
unlink($file);rmdir($dir);
if(in_array(false,$checks,true))throw new RuntimeException('Critical CSS URL/cascade/media preservation failed');
echo "Critical styles: 5 assertions passed\n";
