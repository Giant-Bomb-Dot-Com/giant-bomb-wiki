<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config-library.php';

$cfg['directory_list'][] = 'tests';

return $cfg;
