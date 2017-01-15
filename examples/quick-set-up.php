<?php

require __DIR__ . '/../src/Err.php';

Err::initialise([
	'log_directory' => __DIR__ . '/log',
	'mode' =>Err::MODE_SILENT
]);

Err::triggerMinor('Nothing serious');

Err::triggerMajor('A little worrying');

Err::triggerFatal('I can\'t go on!');

echo 'Example file complete';