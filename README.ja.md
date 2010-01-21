# MyQueue

このパッケージは MySQL を使ったメッセージキューのサンプル実装です。

## 必要なもの

PHP5 と、MySQL へのアクセスのために PDO 拡張が必要です。

## ライセンス

The MIT License.

## 使い方

はじめに、MySQL サーバー上にメッセージキューを保存するテーブルを作成する必要があります。

    CREATE TABLE myqueue (
      `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `locked_until` TIMESTAMP NOT NULL DEFAULT "0000-00-00 00:00:00",
      `data`         BLOB NOT NULL,
      PRIMARY KEY  (`id`)
    ) ENGINE=InnoDB;

テーブル名は自由に変更する事ができます。（上記の例では myqueue となっています）

下記が MyQueue クラスを利用したコード例です。

    require_once 'MyQueue.php';
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=test');
    $queue = new MyQueue($pdo, 'myqueue');
    $queue->push('message1');
    $queue->push('message2');
    $queue->push('message3');
    $queue->pop();  // => 'message1'
    $queue->pop();  // => 'message2'
    $queue->pop();  // => 'message3'

このコードを実行するには localhost 上に MySQL サーバーが動いており、かつ test データベースを作っておく必要があります。

もしテーブル名を変更した場合は、コード中の 'myqueue' の部分を変更する必要があります。
