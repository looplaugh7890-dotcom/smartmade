<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/seo.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$totals = cart_totals();
$items = $totals['items'];

include __DIR__ . '/includes/cart_drawer_partial.php';
