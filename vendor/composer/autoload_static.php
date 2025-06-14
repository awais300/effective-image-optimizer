<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit1fdb8d7d94b731b5574bbe1da7241b4d
{
    public static $files = array (
        '256558b1ddf2fa4366ea7d7602798dd1' => __DIR__ . '/..' . '/yahnis-elsts/plugin-update-checker/load-v5p5.php',
        'd3659cb612a2ff51539fedf1ff2201e0' => __DIR__ . '/../..' . '/includes/functions.php',
    );

    public static $prefixLengthsPsr4 = array (
        'A' => 
        array (
            'AWP\\IO\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'AWP\\IO\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit1fdb8d7d94b731b5574bbe1da7241b4d::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit1fdb8d7d94b731b5574bbe1da7241b4d::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit1fdb8d7d94b731b5574bbe1da7241b4d::$classMap;

        }, null, ClassLoader::class);
    }
}
