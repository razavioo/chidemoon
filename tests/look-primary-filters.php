<?php
define('ABSPATH', __DIR__);
function apply_filters($hook, $value) { return $value; }
function wp_unslash($value) { return $value; }
function sanitize_title($value) { return strtolower(trim($value)); }
require (getenv('CHIDEMOON_TEST_CORE') ?: dirname(__DIR__).'/plugins/chidemoon-core').'/includes/class-chidemoon-core-looks.php';
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
$query=Chidemoon_Core_Looks::filter_query(array('room'=>'bedroom','style'=>'boho','color'=>'blue','look_type'=>'concept'));
check(count($query['tax_query'])===4,'Room, style and color must combine with AND.');
check($query['tax_query']['relation']==='AND','Facets must intersect.');
check($query['tax_query'][2]['taxonomy']==='chidemoon_look_color' && $query['tax_query'][2]['terms']==='blue','Color must use the scene palette taxonomy.');
check(Chidemoon_Core_Looks::filter_query(array('color'=>array('blue')) )===array(),'Array input must be ignored safely.');
check(Chidemoon_Core_Looks::filter_query(array('color'=>'pastel-green'))===array(),'Detailed color variants must not become a facet.');
check(Chidemoon_Core_Looks::filter_query(array('look_type'=>'concept'))===array(),'Retired type filter must not affect results.');
check(Chidemoon_Core_Looks::filter_query(array('color'=>''))===array(),'All colors must clear the constraint.');
check(Chidemoon_Core_Looks::filter_query(array('style'=>'modern-boho'))===array(),'Retired secondary styles must not silently constrain results.');
echo "Look primary filter contracts passed.\n";
