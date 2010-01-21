<?php

require_once 'MyQueue.php';

define('BENCHMARK_DSN',      'mysql:host=127.0.0.1;dbname=test');
define('BENCHMARK_USER',     'root');
define('BENCHMARK_PASS',     '');
define('BENCHMARK_QNAME',    'test_queue');
define('BENCHMARK_MSG_NUM',  1000);

start_benchmark();

function start_benchmark() {
  $pdo = new PDO(BENCHMARK_DSN, BENCHMARK_USER, BENCHMARK_PASS);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $queue = new MyQueue($pdo, BENCHMARK_QNAME);

  setup_table($pdo, BENCHMARK_QNAME);
  $results = array();
  try {
    $results['push'] = benchmark_push($queue, BENCHMARK_MSG_NUM);
    $results['pop']  = benchmark_pop($queue, BENCHMARK_MSG_NUM);
  } catch (Exception $e) {
    drop_table($pdo, BENCHMARK_QNAME);
    throw $e;
  }
  show_results($results, BENCHMARK_MSG_NUM);
  drop_table($pdo, BENCHMARK_QNAME);
}

function setup_table($pdo, $qname) {
  drop_table($pdo, $qname);
  $pdo->exec("
    CREATE TABLE `{$qname}` (
      `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `locked_until` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
      `data`         BLOB NOT NULL,
      PRIMARY KEY  (`id`)
    ) ENGINE=InnoDB;
  ");
}

function drop_table($pdo, $qname) {
  $pdo->exec("
    DROP TABLE IF EXISTS `{$qname}`;
  ");
}

function benchmark_push($queue, $num) {
  $start_time = microtime(true);
  for ($i = 0; $i < $num; $i++) {
    $queue->push("message{$i}");
  }
  $end_time = microtime(true);
  return $end_time - $start_time;
}

function benchmark_pop($queue, $num) {
  $start_time = microtime(true);
  for ($i = 0; $i < $num; $i++) {
    $data = $queue->pop();
  }
  $end_time = microtime(true);
  return $end_time - $start_time;
}

function show_results($results, $num) {
  print(str_repeat('=', 40) . "\n");
  printf("Benchmark result by %d messages\n", $num);
  foreach ($results as $name => $time) {
    print(str_repeat('-', 40) . "\n");
    print("$name\n");
    printf("  Total: %f sec.\n", $time);
    printf("  QPS:   %f query/sec.\n", $num / $time);
    printf("  SPQ:   %f sec/query.\n", $time / $num);
  }
  print(str_repeat('=', 40) . "\n");
}

?>