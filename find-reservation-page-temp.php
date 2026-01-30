<?php
require_once __DIR__ . '/../../../../wp-load.php';

$pages = get_pages(['number' => 50]);
foreach($pages as $p) {
    $content = $p->post_content;
    if (strpos($content, '[fp_reservations') !== false || strpos($content, 'fp-resv') !== false) {
        echo $p->ID . '|' . $p->post_title . '|' . get_permalink($p->ID) . PHP_EOL;
        exit;
    }
}
echo "Nessuna pagina trovata" . PHP_EOL;





