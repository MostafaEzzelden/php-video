<?php

require __DIR__ . '/vendor/autoload.php';

$videoStream = new App\VideoStreaming;

if (isset($_GET['file']) && file_exists(__DIR__ . '/data/' . $_GET['file'])) {
    $videoStream->display(__DIR__ . '/data/' . $_GET['file']);
} else {
    echo "<a href='/?file=001.mp4'>Wath now</a>";
}
