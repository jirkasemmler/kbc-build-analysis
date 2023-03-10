<?php

// batchedCI

$list = json_decode(shell_exec("az pipelines runs list --project=26bf9dc4-aef5-4354-84fe-c52ee81d4cfa --pipeline-ids=44 --reason=batchedCI --branch=master"), true);

$result = [];
$resultSuiteName = [];
$resultMapping = ['0' => 'success', '1' => 'failed', '2' => 'failed', '-1' => 'na'];
$files = [];

$longRunnnig = [];
foreach ($list as $key => $build) {
    $startTime = (new DateTime($build['startTime']));
    $limit = (new DateTime('2023-03-01'));
    if ($startTime < $limit) {
        // skip builds before 2023-03-01
        continue;
    }
    if ($build['result'] === null) {
        echo "skipping build {$build['id']} because it didn't finish yet\n";
        continue;
    }
    $logs = getFile($build['logs']['url']);
    $decodedLogs = json_decode($logs, true)['value'];
    usort($decodedLogs, function ($a, $b) {
        if ($a['lineCount'] > $b['lineCount']) {
            return -1;
        } elseif ($a['lineCount'] < $b['lineCount']) {
            return 1;
        } else {
            return 0;
        }
    });

    if (count($decodedLogs) === 0) {
        echo "skipping build {$build['id']} because it has no logs\n";
        continue;
    }

    $buildLogsFound = false;
    $buildResultsFound = false;
    foreach ($decodedLogs as $log) {
//        // download results
        if (!$buildLogsFound && $log['lineCount'] > 4000) {
            $file = getFile($log['url']);
            if (str_contains($file, 'Starting: Run tests in parallel')) {
                $fileName = 'build_' . $build['buildNumber'] . '_' . $build['id'];
                $files[] = $fileName;
                file_put_contents('./data/' . $fileName, $file);
                $buildLogsFound = true;
            }
        }

        // suites result overview
        if (!$buildResultsFound && $log['lineCount'] > 30 && $log['lineCount'] < 60) {
            $file = getFile($log['url']);
            if (str_contains($file, 'Starting: Output test results')) {
                $buildResultsFound = true;
                $data = array_slice(explode("\n", $file), 12);
                foreach ($data as $singleSuite) {
                    $singleSuiteData = explode("\t", $singleSuite);
                    if (count($singleSuiteData) < 2) {
                        // skip last rows
                        continue;
                    }

                    $matches = [];
                    $found = preg_match('/SUITE_NAME.+\"(?<suiteName>.*)\\\"/', $singleSuiteData[8], $matches);
                    if (!$found) {
                        echo "Suite not found: " . $singleSuite . "\n\n";
                        continue;
                    }
                    $suiteName = $matches['suiteName'];

                    $buildResult = $singleSuiteData[6];
                    $time = (float)$singleSuiteData[3];
                    if ($time < 4000) {
                        if (!array_key_exists($suiteName, $longRunnnig)) {
                            $longRunnnig[$suiteName] = [$time];
                        }
                        $longRunnnig[$suiteName][] = $time;
                    }

                    if (!array_key_exists($suiteName, $result)) {
                        $result[$suiteName] = ['success' => 0, 'failed' => 0, 'na' => 0];
                    }
                    $result[$suiteName][$resultMapping[$buildResult]]++;

                    if (!array_key_exists($suiteName, $resultSuiteName)) {
                        $resultSuiteName[$suiteName] = ['success' => [], 'failed' => [], 'na' => []];
                    }
                    $resultSuiteName[$suiteName][$resultMapping[$buildResult]][] = $build['id'];
                }
            }
        }
    }
}
ksort($result);
ksort($resultSuiteName);


//$failedOnly = array_filter($result, fn($item) => ($item['failed']  $item['na']) >= $item['success']);

print_r($result);
//print_r($resultSuiteName);
file_put_contents('result.json', json_encode($result, JSON_PRETTY_PRINT));
file_put_contents('timing.json', json_encode($longRunnnig, JSON_PRETTY_PRINT));
$averages = [];
foreach ($longRunnnig as $suiteName => $times){
    $averages[$suiteName] = array_sum($times) / count($times);
}
file_put_contents('averages.json', json_encode($averages, JSON_PRETTY_PRINT));

function getFile($url): string
{
    $token = ''; // TODO


    $ci = curl_init();

    curl_setopt($ci, CURLOPT_URL, $url);
    curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ci, CURLOPT_TIMEOUT, 30);
    curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ci, CURLOPT_HTTPHEADER, array(
            "Authorization: Basic " . base64_encode(":" . $token)
        )
    );

    $buffer = curl_exec($ci);
    curl_close($ci);
    return $buffer;

}

