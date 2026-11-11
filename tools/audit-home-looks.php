<?php
require '/var/www/html/wp-load.php';
$hero=Chidemoon_Core_Looks::home_looks(array('mode'=>'hero'));$strip=Chidemoon_Core_Looks::home_looks(array());
preg_match_all('/class="ch-home-look-card(?:\s|"|>)/',$hero,$h);
preg_match_all('/class="ch-home-look-card(?:\s|"|>)/',$strip,$s);
echo json_encode(array('fixed'=>get_option('chidemoon_home_hero_look_ids',array()),'heroCards'=>count($h[0]),'heroHotspots'=>substr_count($hero,'class="ch-scene-point"'),'stripCards'=>count($s[0]),'stripHotspots'=>substr_count($strip,'class="ch-scene-point"')),JSON_UNESCAPED_UNICODE)."\n";
