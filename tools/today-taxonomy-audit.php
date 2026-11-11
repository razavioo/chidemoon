<?php
foreach(['chidemoon_style','chidemoon_room','pa_color','pa_material'] as $taxonomy){echo $taxonomy.' '.wp_json_encode(array_map(static fn($t)=>['id'=>$t->term_id,'name'=>$t->name,'slug'=>$t->slug],get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false])),JSON_UNESCAPED_UNICODE).PHP_EOL;}
