<?php

/**
 * PSR-4 Autoloader for SnippenBooking namespace
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'SnippenBooking\\';

    if ( strpos( $class, $prefix ) === 0 ) {
        $relative_class = substr( $class, strlen( $prefix ) );
        $file = __DIR__ . '/inc/' . str_replace( '\\', '/', $relative_class ) . '.php';

        if ( file_exists( $file ) ) {
            require $file;
        }
    }
} );
