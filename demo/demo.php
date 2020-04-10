<?php
require_once dirname(__DIR__) . '/src/vendor/autoload.php';

use Qosen\Ch2py;

$chpy = new Ch2py();
print_r($chpy->getPinYins('熊'));
print_r($chpy->getPinYins('快'));
print_r($chpy->getPinYins('若'));
echo $chpy->toString('生命诚可贵，爱情价更高。若为自由顾，两者皆可抛！'), PHP_EOL;
