<?php

if (count($argv) < 2) {
    echo "No suite";
    exit;
}
$suite = $argv[1];
$dir = './data';
$files1 = scandir($dir);
$files1 = array_filter($files1, fn($item) => $item != '.' && $item != '..');
$files1 = ['build_20230306.12_31440'];

foreach ($files1 as $file) {
    $data = file_get_contents('./data/' . $file);
    $suiteResult = preg_grep('/.*' . $suite . '/', explode("\n", $data));
    $take = false;
    $result = [];
    foreach ($suiteResult as $row) {
        if ($take
            || str_contains($row, 'errors')
            || str_contains($row, 'There was 1 error')
            || str_contains($row, 'There was 1 failure') || str_contains($row, 'failures')
        ) {
            $take = true;
        }
        if ($take) {
            $result[] = $row;

        }

        if ($take && str_ends_with($row, "---\r")) {
            $take = false;
        }
    }
    echo "\n\n================ RESULTS of " . $file . "\n\n";
    echo implode("\n", $result);

}
