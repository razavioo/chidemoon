<?php
if(!defined('WP_CLI')||!WP_CLI)exit;
if(!function_exists('imagecreatetruecolor'))WP_CLI::error('GD missing');
$data=json_decode(file_get_contents('/tmp/product-enrichment-before.json'),true);
foreach(array_chunk(array_values(array_filter($data['products'],static fn($p)=>$p['status']==='publish')),60) as $sheet=>$products){
 $im=imagecreatetruecolor(1200,1500);$white=imagecolorallocate($im,255,255,255);$black=imagecolorallocate($im,0,0,0);imagefill($im,0,0,$white);
 foreach($products as $i=>$p){$x=($i%8)*150;$y=intdiv($i,8)*185;$file=get_attached_file(get_post_thumbnail_id($p['id']));$bytes=$file&&is_file($file)?file_get_contents($file):false;$photo=$bytes?@imagecreatefromstring($bytes):false;if($photo){$w=imagesx($photo);$h=imagesy($photo);$r=min(145/$w,155/$h);imagecopyresampled($im,$photo,$x+(int)((150-$w*$r)/2),$y,0,0,(int)($w*$r),(int)($h*$r),$w,$h);imagedestroy($photo);}imagestring($im,5,$x+8,$y+160,(string)$p['id'],$black);}
 imagejpeg($im,'/tmp/enrichment-sheet-'.($sheet+1).'.jpg',85);imagedestroy($im);
}
WP_CLI::success('Contact sheets generated from local original images');
