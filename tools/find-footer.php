<?php
$m = get_option( 'theme_mods_chidemoon-blocksy-child' );
foreach ( $m as $k => $v ) {
echo $k . ' => ' . ( is_array( $v ) ? json_encode( array_keys( $v ) ) : substr( var_export( $v, true ), 0, 80 ) ) . "\n";
}