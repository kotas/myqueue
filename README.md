# MyQueue

This package is a sample PHP implementation of message queue using MySQL.

## Requirement

PHP5 and PDO extension for MySQL access.

## License

The MIT License.

## Usage

First, you need to create a database for storing a message queue on MySQL server.

    CREATE TABLE myqueue (
      `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `locked_until` TIMESTAMP NOT NULL DEFAULT "0000-00-00 00:00:00",
      `data`         BLOB NOT NULL,
      PRIMARY KEY  (`id`)
    ) ENGINE=InnoDB;

You can change the name of the table (`myqueue`) as you like.

Then, here is an example code of using MyQueue class.

    require_once 'MyQueue.php';
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=test');
    $queue = new MyQueue($pdo, 'myqueue');
    $queue->push('message1');
    $queue->push('message2');
    $queue->push('message3');
    $queue->pop();  // => 'message1'
    $queue->pop();  // => 'message2'
    $queue->pop();  // => 'message3'

To run this code, you need MySQL server running on localhost, and it must have `test` database.

If you changed the name of the table, you have to change 'myqueue' in the source code to your table name.
