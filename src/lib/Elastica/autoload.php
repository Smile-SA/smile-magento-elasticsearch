<?php

function __autoload_elastica ($class) {
    if (strpos($class, 'Elastica\\') === 0) {
        $path = str_replace('\\', '/', $class);
        require_once($path . '.php');
    }
}
spl_autoload_register('__autoload_elastica', false, true);
