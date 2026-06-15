<?php

declare(strict_types=1);

// Dynamisch aus info.xml laden
$xml = simplexml_load_file(__DIR__ . '/info.xml');
return (string) $xml->version;
