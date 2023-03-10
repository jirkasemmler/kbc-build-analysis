<?php

print_r($argv);
if (count($argv) < 2) {
    echo "No suite";
    exit;
}
$suite = $argv[1];
$dir = './data';
$files1 = scandir($dir);
$files1 = array_filter($files1, fn($item) => $item != '.' && $item != '..');
$files1 = ['build_20230306.12_31440'];
$out = [];
foreach ($files1 as $file) {
    $data = file_get_contents('./data/' . $file);
    $suiteResult = preg_grep('/.* \- ' . $suite . '/', explode("\n", $data));
    $take = false;
    $result = [];
    $previousTest = '';
    $testStarted = null;
    foreach ($suiteResult as $row) {
        $splittedRow = explode(' -', $row);
        $dateTime = explode(" ", $splittedRow[0])[0];
        $test = trim($splittedRow[2]);

        $testParts = explode("::", $test);
        if (count($testParts) >= 2 && str_starts_with($test, 'Test')) {
            $thisTestFile = $testParts[0];

            if ($previousTest == $thisTestFile) {
                $testEnded = $dateTime;
                continue;
            } else {
                if ($previousTest !== '') {

                    $start = new DateTime($testStarted);
                    $end = new DateTime($testEnded);

                    $minutes = $end->diff($start)->format("%i");
                    $time = ((float) $minutes * 60) + (float) $end->diff($start)->format("%s.%u");
                    $out[$previousTest] = $time;
                    echo "Test $previousTest took " . $time . "\n\n";
                }

                $testStarted = $dateTime;
            }
            $previousTest = $thisTestFile;
        }
    }
    $start = new DateTime($testStarted);
    $end = new DateTime($testEnded);

    $minutes = $end->diff($start)->format("%i");
    $time = ((float) $minutes * 60) + (float) $end->diff($start)->format("%s.%u");
    $out[$previousTest] = $time;
    echo "Test $previousTest took " . $time . "\n\n";

    arsort($out);
    foreach ($out as $testName => $time){
        $mins = floor($time / 60);
        $s = $time % 60;
        echo "$mins min $s s - " . $testName . " \n";
    }
    echo "Checksum : " . array_reduce($out, function($carry, $item){ return $carry + $item;});

}
