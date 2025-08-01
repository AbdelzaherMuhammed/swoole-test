<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInita0f05a1660c6239936d6c1cceff99380
{
    public static $prefixLengthsPsr4 = array (
        'F' => 
        array (
            'Framework\\' => 10,
        ),
        'A' => 
        array (
            'App\\' => 4,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Framework\\' => 
        array (
            0 => __DIR__ . '/../..' . '/framework',
        ),
        'App\\' => 
        array (
            0 => __DIR__ . '/../..' . '/app',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInita0f05a1660c6239936d6c1cceff99380::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInita0f05a1660c6239936d6c1cceff99380::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInita0f05a1660c6239936d6c1cceff99380::$classMap;

        }, null, ClassLoader::class);
    }
}
