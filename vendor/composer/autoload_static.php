<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit3cbdbec82e685384f861fe9791536e05
{
    public static $prefixLengthsPsr4 = array (
        'B' => 
        array (
            'Bread\\WooCommerceGateway\\' => 25,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Bread\\WooCommerceGateway\\' => 
        array (
            0 => __DIR__ . '/../..' . '/classes',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit3cbdbec82e685384f861fe9791536e05::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit3cbdbec82e685384f861fe9791536e05::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
