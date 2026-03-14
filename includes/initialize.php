<?php
    defined('DS') ? null : define('DS', DIRECTORY_SEPARATOR); // Fixed typo

    // defined('SITE_ROOT') ? null : define('SITE_ROOT', DS . 'xampp' . DS . 'htdocs' . DS . 'sitIn');
    defined('SITE_ROOT') ? null : define('SITE_ROOT', $_SERVER['DOCUMENT_ROOT'] . DS . 'sitIn');

    defined('INC_PATH')   ? null : define('INC_PATH',   SITE_ROOT . DS . 'includes');
    defined('SRC_PATH')   ? null : define('SRC_PATH',   SITE_ROOT . DS . 'src');
    defined('MODEL_PATH') ? null : define('MODEL_PATH', SRC_PATH  . DS . 'models');
    defined('CONT_PATH')  ? null : define('CONT_PATH',  SRC_PATH  . DS . 'controllers');
    defined('DB_PATH')    ? null : define('DB_PATH',    SRC_PATH  . DS . 'database');
    defined('UTIL_PATH')  ? null : define('UTIL_PATH',  SRC_PATH  . DS . 'utils');

    require_once(INC_PATH . DS . 'config.php');
    require_once(MODEL_PATH . DS . 'student.php');


?>