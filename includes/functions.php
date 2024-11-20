<?php

use AWP\IO\Admin\ImageOptimizerOptions;

function get_optimizer_settings($name = null)
{
    $optimizer = ImageOptimizerOptions::get_instance();
    return $optimizer->get_optimizer_settings($name);
}

function get_default_optimizer_settings()
{
    $optimizer = ImageOptimizerOptions::get_instance();
    return $optimizer->get_default_optimizer_settings();
}

function dd($mix, $log = 0) {

    if($log == false) {
        echo "<pre>";
        print_r($mix);
        echo "</pre>";
    }

    if($log == true) {
        error_log('WriteLog:');
        error_log(print_r($mix, 1));
    }
}
    