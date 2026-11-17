<?php
foreach(['chidemoon_style','chidemoon_room','pa_color','chidemoon_look_color'] as $tax){$terms=Chidemoon_Core_Primary_Facets::terms($tax);WP_CLI::log($tax.': '.count($terms));foreach($terms as $term)if(in_array($tax,['chidemoon_room','chidemoon_style'])){ $page=get_page_by_path('shop-the-look/'.($tax==='chidemoon_room'?'room':'style').'-'.$term->slug);if(!$page)WP_CLI::error('Missing page '.$term->slug);}}
WP_CLI::success('All primary room/style pages exist.');
