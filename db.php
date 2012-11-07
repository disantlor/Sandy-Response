<?php

define('SEC_IN_DAY', 60*60*24);

$conn = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// clear any aider messages that we've received that are older than an hour
$clear_query = 'DELETE FROM aider_messages WHERE timestamp < date_sub(now(), interval 1 hour)';
$stmt = $conn->query($clear_query);

/**
 * Creates a request in the database for an aidee.
 *
 * This should enforce the logic that no new request should be created if the
 * following is already found in the database (essentially a composite key
 * of these fields):
 * - normalized address
 * - request type
 * - phone number
 *
 * If a request comes in that matches a request in the database that has been
 * flagged as HELPED, but that HELPED flag was set >24 hours ago, update that
 * help request and set helped back to NULL, and set the timestamp to now.
 *
 * @param Request $request a request object to try to insert
 *
 * @return int response code
 */
function create_request($request) {
  if ($existing_request = request_exists($request)) {
    // a record exists already!

    if (!is_null($existing_request->helped)) {
      // this person has been flagged as helped!

      $helped_ago = time() - strtotime($existing_request->helped);
      if ($helped_ago > SEC_IN_DAY) {
        // but they were flagged as helped >24 hours ago, so we're going to assume
        // that help never arrived

        reset_helped($existing_request);
        return FLAG_NEW_REQUEST;
      }
      else {
        // and they were flagged as help-is-on-the-way in the past 24 hours, so we
        // don't want to re-record the help request

        return FLAG_RECENTLY_HELPED;
      }
    }
    else {
      // they haven't been flagged as helped yet! be patient, help is on the way.

      return FLAG_HELP_PENDING;
    }
  }
  else {
    // this is a new help request!
    insert_request($request);
    return FLAG_NEW_REQUEST;
  }
}

/**
 * Reset the helped flag on a help request and set the timestamp to now.
 *
 * @param Request $request a request WITH AN ID SET (must have been returned from the DB)
 */
function reset_helped($request) {
  global $conn;
  $sql = 'UPDATE requests SET timestamp=CURRENT_TIMESTAMP, helped=NULL WHERE id=:id';
  $stmt = $conn->prepare($sql);
  $stmt->execute(array(':id' => $request->id));
}

/**
 * Check if a record exists in the database matching this request.
 *
 * @return Request|bool Return the matching request object from the DB. FALSE if no record exists.
 */
function request_exists($request) {
  global $conn;
  $sql = 'SELECT * FROM requests WHERE address=:address AND type=:type AND phone=:phone';
  $stmt = $conn->prepare($sql);
  $stmt->execute(array(
    ':address' => $request->address,
    ':type' => $request->type,
    ':phone' => $request->phone,
  ));

  $stmt->setFetchMode(PDO::FETCH_CLASS, 'Request');
  return $stmt->fetch();
}

/**
 * Insert a request into the database
 *
 * @param Request $request
 *
 * @return int request ID from DB
 */
function insert_request($request) {
  global $conn;
  $stmt = $conn->prepare('INSERT INTO requests
    (type, phone, neighborhood, address)
    VALUES(:type, :phone, :neighborhood, :address)');
  $stmt->execute(array(
    ':type' => $request->type,
    ':phone' => $request->phone,
    ':neighborhood' => $request->neighborhood,
    ':address' => $request->address,
  ));

  return $conn->lastInsertId();
}

/**
 * Get the oldest request in a neighborhood.
 *
 * @param string $neighborhood
 * @param string $type
 * @param int $offset optional offset from the oldest
 *
 * @return mixed Result object if a record exists, response code if not
 */
function find_request($neighborhood, $type, $offset = 0) {
  global $conn;
  $stmt = $conn->prepare('SELECT * FROM requests WHERE helped IS NULL AND neighborhood=:neighborhood AND type=:type LIMIT 1 OFFSET :offset');
  $stmt->bindParam(':offset', intval($offset), PDO::PARAM_INT);
  $stmt->bindParam(':neighborhood', $neighborhood);
  $stmt->bindParam(':type', $type);
  $stmt->execute();

  $stmt->setFetchMode(PDO::FETCH_CLASS, 'Request');

  if ($result = $stmt->fetch()) {
    return $result;
  }
  else if ($offset == 0) {
    return FLAG_NO_REQUESTS;
  }
  else {
    return FLAG_NO_MORE_REQUESTS;
  }
}

/**
 * Flag a request as being helped.
 *
 * @param string $phone
 */
function set_helping($phone) {
  global $conn, $type;
  // make sure we're not rate limited
  $stmt = $conn->prepare('SELECT count(*) FROM requests WHERE helping=:phone AND helped >= date_sub(now(), interval '.RATE_LIMIT_INTERVAL.')');
  $stmt->execute(array(
    ':phone' => $phone,
  ));
  $count = $stmt->fetchColumn();
  if ($count >= RATE_LIMIT) {
    return FLAG_RATE_LIMIT;
  }

  // get the request id if there is one
  $stmt = $conn->prepare('SELECT request_id FROM aider_messages WHERE phone=:phone AND type=:type');
  $stmt->execute(array(
    ':phone' => $phone,
    ':type' => $type,
  ));
  $request_id = $stmt->fetchColumn();
  if (!$request_id || is_null($request_id)) {
    return FLAG_NO_REQUEST_FOR_NUMBER;
  }

  // mark the request as helped
  $stmt = $conn->prepare('UPDATE requests SET helping=:phone, helped=CURRENT_TIMESTAMP WHERE id=:id');
  $stmt->execute(array(
    ':phone' => $phone,
    ':id' => $request_id,
  ));
  return FLAG_SUCCESSFULLY_HELPING;
}

/**
 * Get the current offset for a phone number.
 *
 * @param string $phone
 * @param bool $increment whether or not to increment the offset by 1.
 */
function get_offset($phone, $increment = TRUE) {
  global $conn, $type;
  $stmt = $conn->prepare('SELECT offset FROM aider_messages WHERE phone=:phone AND type=:type');
  $stmt->execute(array(
    ':phone' => $phone,
    ':type' => $type,
  ));
  $offset = $stmt->fetchColumn();

  if ($increment !== FALSE) {
    if (!is_int($increment)) {
      $stmt = $conn->prepare('UPDATE aider_messages SET offset=offset+:increment WHERE phone=:phone AND type=:type');
    }
    else {
      $stmt = $conn->prepare('UPDATE aider_messages SET offset=:increment WHERE phone=:phone AND type=:type');
    }
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':type', $type);
    $stmt->bindParam(':increment', $increment, PDO::PARAM_INT);
    $stmt->execute();
  }

  return $offset;
}

/**
 * Initialize an aider.
 *
 * @param string $phone
 */
function initialize_aider($phone) {
  global $conn, $type;
  $stmt = $conn->prepare('INSERT IGNORE INTO aider_messages (phone, type) VALUES (:phone, :type)');
  $stmt->execute(array(
    ':phone' => $phone,
    ':type' => $type,
  ));
}

/**
 * Log the response that was sent to the user.
 *
 * @param string $phone
 * @param int $request_id
 */
function log_response_sent($phone, $request_id) {
  global $conn, $type;
  $stmt = $conn->prepare('UPDATE aider_messages SET request_id=:id WHERE phone=:phone AND type=:type');
  $stmt->bindParam(':id', $request_id, PDO::PARAM_INT);
  $stmt->bindParam(':phone', $phone);
  $stmt->bindParam(':type', $type);
  $stmt->execute();
}

/**
 * Clear the message log for this phone number.
 *
 * @param string $phone
 */
function clear_log($phone) {
  global $conn, $type;
  $stmt = $conn->prepare('DELETE FROM aider_messages WHERE phone=:phone AND type=:type');
  $stmt->execute(array(
    ':phone' => $phone,
    ':type' => $type,
  ));
}

/**
 * Get the last keyword that an aider used.
 *
 * @return string keyword
 */
function get_recent_keyword($phone) {
  global $conn;
  $stmt = $conn->prepare('SELECT type FROM aider_messages WHERE phone=:phone ORDER BY timestamp DESC LIMIT 1');
  $stmt->execute(array(
    ':phone' => $phone,
  ));
  return map_type_reverse($stmt->fetchColumn());
}

/**
 * Check if a phone number is an aidee.
 *
 * @param string $phone
 *
 * @return bool
 */
function is_aidee($phone) {
  global $conn;
  $stmt = $conn->prepare('SELECT UNIX_TIMESTAMP(timestamp) FROM requests WHERE phone=:phone ORDER BY timestamp limit 1');
  $stmt->execute(array(
    ':phone' => $phone,
  ));
  $aidee_time = $stmt->fetchColumn();

  $stmt = $conn->prepare('SELECT UNIX_TIMESTAMP(timestamp) FROM aider_messages WHERE phone=:phone ORDER BY timestamp limit 1');
  $stmt->execute(array(
    ':phone' => $phone,
  ));
  $aider_time = $stmt->fetchColumn();

  return $aidee_time > $aider_time;
}

class Request {
  public $type;
  public $phone;
  public $neighborhood;
  public $address;

  public $helped;
  public $timestamp;

  public $id;
}
