<?php
// Füstteszt minden ügynökségre (= wp kompasz smoke): wp eval-file scripts/wp/smoke.php
// Hiba esetén nem nulla kilépési kóddal áll le (CI és deploy ezen bukik el). A logika: kompasz_smoke() (inc/ops.php).
if (!defined('WP_CLI')) exit(1);
$s = kompasz_smoke();
if ($s['problems']) {
    foreach (array_slice($s['problems'], 0, 50) as $p) WP_CLI::warning($p);
    WP_CLI::error(count($s['problems']) . ' hiba ' . $s['count'] . ' ügynökségnél.');
}
WP_CLI::success($s['count'] . ' ügynökség adatlapja rendben.');
