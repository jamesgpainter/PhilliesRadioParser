<?php

/* This script is used to convert note-taking from a radio
 * broadcast or conversation into a WordPress MySQL database.
 * Pass a valid text file as a parameter to the script.
 *
 * Notes must have the following format in a text file:
 * 
 * == FILE START ==
 *
 * 04-01-2012
 *
 * Speaker1: Today, the Phillies will win.
 * Speaker2: I agree.
 * Speaker1: I'm glad you agree.
 *
 * 04-02-2012
 *
 * Speaker1: ...
 *
 * == FILE END ==
 *
 *  The script looks for valid dates to begin and end the transcript.
 *  A colon in a line is the indicator for a valid quote.
 *  See one of the note-taking text files in this directory for
 *  samples of input files.
 */

$filename = $argv[1];
$textfile = file_get_contents($filename);
$textfile = "\r\n" . $textfile;
$rows = explode("\r", $textfile);
array_shift($rows);

$timeparts = array();

// iterate through each line in the file
foreach($rows as $row => $data) {
  if(substr($data,0,1) == "\n") {
    $data = substr($data, 1);
  }

  // check for beginning of game (line with a date)
  if(strpos($data,":")==false && (is_numeric(substr($data,0,2)) ||
    is_numeric(substr($data,1,2))) ) {
		// date line: "x00-00-00" or "00-00-00"

    if(substr($data,0,1) == "x") {
      $startpos = 1;
    } else {
      $startpos = 0;
    }
    $month = intval(substr($data, $startpos, 2));
		$day = intval(substr($data, $startpos+3, 2));
    $year = intval(substr($data, $startpos+6, 4));
		if($year < 100) {
			$year += 2000;
    }
		$nexthour = 1; $nextminute = 0; $nextsecond = 0;
		$atleastone = false;
    $conversation = "";
    continue;
	}

  // check for time of conversation
  if(strpos($data, ":") == true) {
    $teststr = str_replace(":", "", $data);
    if(is_numeric($teststr)) {
      // found a valid time (00:00:00), so parse it
      $timeparts = explode(":", $data);
      $hour = $timeparts[0];
      $minute = $timeparts[1];
      $second = $timeparts[2];
      $timeprovided = true;
      $nextsecond = $second+1;
      $nextminute = $minute;
      $nexthour = $hour;
      if($nextsecond>59) { $nextminute++; $nextsecond = 0; }
      if($nextminute>59) { $nexthour++; $nextminute = 0; }
    }
  }

  // check for blank line
	if(strlen($data) < 1) {
    if($atleastone) {
      // reached end of conversation, so write to database
			if($quotenum < 10) {
				$quotenum = "0" . strval($quotenum);
			} else {
				$quotenum = strval($quotenum);
      }

      // remove final endline
      $conversation = substr($conversation, 0, -1);

      // insert into database
      // either use exact time if provided, or use next available time so that
      // the quotes are ordered properly
      if($timeprovided == false) {
        createRecord($month, $day, $year, $nexthour, $nextminute, $nextsecond, $conversation);
        $nextsecond = $nextsecond+1;
        if($nextsecond>59) { $nextminute++; $nextsecond = 0; }
        if($nextminute>59) { $nexthour++; $nextminute = 0; }
      } else {
        createRecord($month, $day, $year, $hour, $minute, $second, $conversation);
      }
      $conversation = "";
      $timeprovided = false;
      $atleastone = false;
		}
		continue;
	}

	// check for garbage line
	if(strpos($data, ":") == false) {
		continue;
	}

  // weed out lines that don't start with letters
  if(!ctype_alpha(substr($data, 0, strpos($data,":")-1))) {
    continue;
  }

  // we have a good line now

	// append line to current converstaion
	$conversation .= $data . "\n";
	$atleastone = true;

}

function createRecord($m, $d, $y, $h, $min, $s, $content) {
	error_reporting(E_ALL);
	ini_set('display_errors', True);

	mysql_connect('localhost',$user,$password);
	@mysql_select_db($database) or die( "Unable to select database");


  // be sure to convert current time zone to GMT when inserting record
  $datestr = sprintf("'%d-%02d-%02d %02d:%02d:%02d'", $y, $m, $d, $h, $min, $s);
  $urlstr = sprintf("'http://funnyphillies.com/%d/%02d/%02d/%02d/%02d/%02d'", $y, $m, $d, $h, $min, $s);
	$sql = "
	  INSERT INTO wp_posts (
	    post_author,
	    post_date,
	    post_date_gmt,
	    post_content,
	    post_status,
	    comment_status,
	    ping_status,
	    post_modified,
	    post_modified_gmt,
	    post_parent,
	    guid,
	    menu_order,
	    post_type,
	    comment_count)
	  VALUES (
	    1,
	    ".$datestr.",
	    CONVERT_TZ(".$datestr.",'-08:00','+00:00'),
	    '".addslashes($content)."',
	    'publish',
	    'open',
	    'open',
	    ".$datestr.",
	    CONVERT_TZ(".$datestr.",'-08:00','+00:00'),
	    0,
	    ".$urlstr.",
	    0,
	    'post',
	    0);";

  echo $sql;
  $qry = mysql_query($sql);
}

?>
