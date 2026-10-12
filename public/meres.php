<?php
// IDEIGLENES meroLAP a probatelepiteshez — a meres utan torlendo (es a .cpanel.yml-bol a hivatkozas).
// Titkot nem ir ki, csak igen/nem ertekeket.
header('Content-Type: text/plain; charset=utf-8');
$tiltott = array_map('trim', explode(',', (string) ini_get('disable_functions')));
echo 'PHP verzio: ', PHP_VERSION, "\n";
echo 'Futtato (SAPI): ', PHP_SAPI, "\n";
echo 'ext-intl: ', extension_loaded('intl') ? 'IGEN' : 'NEM', "\n";
echo 'proc_open: ', (function_exists('proc_open') && ! in_array('proc_open', $tiltott, true)) ? 'IGEN' : 'NEM (tiltva)', "\n";
echo 'pdo_mysql: ', extension_loaded('pdo_mysql') ? 'IGEN' : 'NEM', "\n";
