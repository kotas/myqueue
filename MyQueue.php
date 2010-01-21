<?php
/**
 * Sample implementation of message queue using MySQL.
 *
 * Requirement: PHP5 and PDO extension for MySQL.
 *
 * The MIT License
 *
 * Copyright (c) 2010 kotas <kotas at kotas dot jp>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Message queue class.
 *
 * You need have a table for the message queue like below:
 * <code>
 * CREATE TABLE myqueue (
 *   `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 *   `locked_until` TIMESTAMP NOT NULL DEFAULT "0000-00-00 00:00:00",
 *   `data`         BLOB NOT NULL,
 *   PRIMARY KEY  (`id`)
 * ) ENGINE=InnoDB;
 * </code>
 *
 * Usage example:
 * <code>
 *   $pdo = new PDO('mysql:...');
 *   $queue = new MyQueue($pdo, 'myqueue');
 *   $queue->push('message1');
 *   $queue->push('message2');
 *   $queue->push('message3');
 *   $queue->pop();  // => 'message1'
 *   $queue->pop();  // => 'message2'
 *   $queue->pop();  // => 'message3'
 * </code>
 */
class MyQueue
{

  /**
   * Seconds a message lock will expire in.
   */
  const LOCK_TIMEOUT = 10;

  /**
   * @var PDO The PDO instance for accessing MySQL.
   */
  protected $pdo;

  /**
   * @var string The name of the queue table on the database.
   */
  protected $qname;

  /**
   * Constructor.
   *
   * @param  PDO     The PDO instance for accessing MySQL.
   * @param  string  The name of the queue table on the database.
   */
  public function __construct($pdo, $qname) {
    $this->pdo = $pdo;
    $this->qname = $qname;
  }

  /**
   * Push a message to the end of the queue.
   *
   * @param  string  The data of the message to be pushed.
   * @return bool    true on success, false on failure.
   */
  public function push($data) {
    $sth = $this->pdo->prepare("
      INSERT INTO `{$this->qname}` SET data = :data;
    ");
    $sth->bindParam(':data', $data);
    return $sth->execute();
  }

  /**
   * Pop a message from the top of the queue.
   *
   * By calling this method, the message in the top of the queue will be
   * removed from the queue.
   *
   * @return string|bool
   *    The message popped out from the queue on success.
   *    false on failure to pop a message or empty queue.
   */
  public function pop() {
    // 1. Lock the top message in the queue.
    //    Note that the lock will expire in self::LOCK_TIMEOUT.
    $affected = $this->pdo->exec("
      UPDATE `{$this->qname}`
        SET id = LAST_INSERT_ID(id),
            locked_until = NOW() + INTERVAL ".self::LOCK_TIMEOUT." SECOND
        WHERE locked_until < NOW() ORDER BY id LIMIT 1;
    ");
    if ($affected == 0) {
      // No message in the queue.
      return false;
    }

    // 2. Get the ID of the locked message.
    $msg_id = $this->pdo->lastInsertId();
    if (!$msg_id) {
      // Oops, no message in the queue, or failed to lock a message maybe.
      return false;
    }

    // 3. Get the data of the locked message.
    $sth_select = $this->pdo->prepare("
      SELECT data FROM `{$this->qname}` WHERE id = :msg_id;
    ");
    $sth_select->bindParam(':msg_id', $msg_id, PDO::PARAM_INT);
    if (!$sth_select->execute()) {
      // In this case, we locked the message but have failed to get the data.
      // The message will stay in the queue for 10 secs, and anothe client will process it.
      return false;
    }
    $msg_data = $sth_select->fetchColumn();

    // 4. Delete the locked message from the queue.
    $sth_delete = $this->pdo->prepare("
      DELETE FROM `{$this->qname}` WHERE id = :msg_id;
    ");
    $sth_delete->bindParam(':msg_id', $msg_id, PDO::PARAM_INT);
    if (!$sth_delete->execute()) {
      // In this case, we have the message data but the message stays in the queue,
      // so here we ignore the message data so that another client will receive it.
      return false;
    }

    // And we're done!
    return $msg_data;
  }

}
