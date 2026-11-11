<?php
require '/var/www/html/wp-load.php';
$ids=array_map('intval',(array)get_option('chidemoon_home_hero_look_ids',[]));
if($ids!==[8608,8607,3979]&&$ids!==[3976,8607,3979])throw new RuntimeException('Hero selection changed; refusing to overwrite');
if(get_post_status(3976)!=='publish'||get_post_type(3976)!=='reycob_look')throw new RuntimeException('Bedroom unavailable');
if(!get_option('chidemoon_home_hero_before_bedroom_20261109'))add_option('chidemoon_home_hero_before_bedroom_20261109',$ids,'',false);
update_option('chidemoon_home_hero_look_ids',[3976,8607,3979],false);
Chidemoon_Core_Page_Cache::purge();
$html=Chidemoon_Core_Looks::home_looks(['mode'=>'hero']);
if(substr_count($html,'class="ch-home-look-card"')!==3||str_contains($html,'ch-home-hero-8608-')||!str_contains($html,'ch-home-hero-3976-'))throw new RuntimeException('Hero verification failed');
echo json_encode(['fixed'=>get_option('chidemoon_home_hero_look_ids'),'cards'=>3,'bedroom'=>true,'coffee'=>false])."\n";
