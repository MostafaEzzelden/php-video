<?php

require __DIR__ . '/vendor/autoload.php';

$videoStream = new App\VideoStreaming;

isset($_GET['file']) ?  $videoStream->display(__DIR__ . '/data/' . $_GET['file']) : null;
