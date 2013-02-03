<?php

ini_set('display_errors', 'Off');

/**
 * dateFinder
 *
 * A class to locate and parse dates, in a variety of formats, from a string.
 *
 *
 * Usage
 * =====
 *
 * Instantiate the class with a string (preferably one containing a date)
 * Example: $df = new dateFinder('December 25th, 2012');
 *          var_dump($df->parsedDate);
 *
 * Unit Tests:  $df = new dateFinder();
 *              $df->unitTests();
 *
 * @return array 'year', 'month' & 'day' key/value pairs. Array will also have a
 * 'range' key if the found date lacks day granularity
 *
 *
 * @author Jonathan M. Hollin <darkblue@sdf.lonestar.org>
 */

class dateFinder {

  public $parsedDate = array();
  private $dateString, $days, $months, $years;
  private $monthNames = array();
  private $longMonthNames = array('january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december');

  public function __construct($dateString = false) {
    foreach ($this->longMonthNames as $month) $this->monthNames[] = substr($month, 0, 3);
    if (isset($dateString) && $dateString) {
      $this->dateString = filter_var($dateString, FILTER_SANITIZE_STRING);
      $this->findDate();
    } else {
      return false;
    }
  }

  public function findDate() {
    $this->parsedDate = array();

    if ( $this->simpleDate() === true ) return $this->parsedDate;

    $this->maybeYears();
    $this->maybeMonths();
    $this->maybeDays();

    if ( ($this->years === true) && ($this->months === false) && ($this->days === false) && ($this->checkUSFQD() === false) && ($this->checkUKFQD() === false) ) {
      $this->parsedDate['day'] = $this->parsedDate['month'] = 1;
      $this->parsedDate['range'] = true;
      return $this->parsedDate;
    }

    if ( ($this->years === true) && ($this->months === true) && ($this->days === false) && ($this->checkUSFQD() === false) && ($this->checkUKFQD() === false) ) {
      $this->parsedDate['day'] = 1;
      $this->parsedDate['range'] = true;
      return $this->parsedDate;
    }

    if ( ($this->years === false) && ($this->months === true) && ($this->days === false) && ($this->checkUSFQD() === false) && ($this->checkUKFQD() === false) ) {
      $this->parsedDate['year'] = (int)date('Y');
      $this->parsedDate['day'] = 1;
      $this->parsedDate['range'] = true;
      return $this->parsedDate;
    }

    if ( ($this->years === false) && ($this->months === false) && ($this->days === true) && ($this->checkUSFQD() === false) && ($this->checkUKFQD() === false) && (strtotime($this->dateString) === false) && isset($this->parsedDate['day']) ) {
      $this->parsedDate['year'] = (int)date('Y');
      $this->parsedDate['month'] = (int)date('n');
      return $this->parsedDate;
    }

    if ( $this->checkUSFQD() === true ) return $this->parsedDate;
    if ( $this->checkUKFQD() === true ) return $this->parsedDate;
    if ( $this->fuzzyLogic() === true ) return $this->parsedDate;
    return false;
  }

  private function simpleDate() {
    $this->parsedDate = array();
    preg_match('/([0-9]{1,2})(st|nd|rd|th)?([^A-Za-z0-9]+)(' . implode( '|', $this->longMonthNames ) . ')([^A-Za-z0-9]+)([0-9]{4})/i', $this->dateString, $matches);
    if (isset($matches[0])) {
      $month = array_search(strtolower($matches[4]), $this->longMonthNames) + 1;
      if ( checkdate($month, $matches[1], $matches[6]) ) {
        $this->parsedDate['year'] = (int)$matches[6];
        $this->parsedDate['month'] = (int)$month;
        $this->parsedDate['day'] = (int)$matches[1];
        return true;
      }
    }
  }

  private function maybeYears() {
    $year = false;
    preg_match_all( '/\b(\d{4})\b/', $this->dateString, $fqYears );
    $testString = html_entity_decode($this->dateString, ENT_QUOTES);
    preg_match_all( "/\s['’](\d{2})\b/", $testString, $abbrYears );
    if (count($fqYears[1])) foreach ( $fqYears[1] as $year ) break;
    if (count($abbrYears[1])) {
      foreach ( $abbrYears[1] as $year ) {
        $year = ( ((int)$year >= 0) && ((int)$year <= 9) ) ? '190' . $year : '19' . $year;
        break;
      }
    }
    if ($year) {
      $this->parsedDate['year'] = (int)$year;
      $this->years = true;
    } else {
      $this->years = false;
    }
  }

  private function maybeMonths() {
    $this->months = false;
    if ( preg_match_all( '/\b(' . implode( '|', $this->longMonthNames ) . ')\b/i', $this->dateString, $fqMonths ) ) {
      $monthNum = array_search(strtolower($fqMonths[1][0]), $this->longMonthNames);
      if (is_int($monthNum) && ($monthNum >= 0) && ($monthNum <= 11) ) {
        $this->parsedDate['month'] = $monthNum + 1;
        $this->months = true;
      }
    }
    if ( preg_match_all( '/\b(' . implode( '|', $this->monthNames ) . ')\b/i', $this->dateString, $abbrMonths ) ) {
      $monthNum = array_search(strtolower($abbrMonths[1][0]), $this->monthNames);
      if (is_int($monthNum) && ($monthNum >= 0) && ($monthNum <= 11) ) {
        $this->parsedDate['month'] = $monthNum + 1;
        $this->months = true;
      }
    }
  }

  private function maybeDays() {
    preg_match_all( '/\b([0-9]{1,2})(st|nd|rd|th)?\b/', $this->dateString, $days );
    $this->days = false;
    if ( count($days[1]) ) {
      foreach ($days[1] as $test) {
        if ( ((int)$test >= 1) && ((int)$test <= 31) ) {
          $this->parsedDate['day'] = (int)$test;
          $this->days = true;
          break;
        }
      }
    }
  }

  private function checkUSFQD() {
    $USFQD = explode('-', $this->dateString);
    $errors = 0;
    if ( count($USFQD) === 3 ) {
      $this->parsedDate = array();
      if ( ((int)$USFQD[0] < 1) && ((int)$USFQD[0] > 12) ) $errors ++;
      if ( ((int)$USFQD[1] < 1) && ((int)$USFQD[1] > 31) ) $errors ++;
      if ( !is_numeric($USFQD[2]) ) $errors ++;
      if ($errors === 0) {
        if ( checkdate((int)$USFQD[0], (int)$USFQD[1], (int)$USFQD[2]) ) {
          $this->parsedDate['year'] = (int)$USFQD[2];
          $this->parsedDate['month'] = (int)$USFQD[0];
          $this->parsedDate['day'] = (int)$USFQD[1];
          return true;
        } else {
          return false;
        }
      } else {
        return false;
      }
    } else {
      return false;
    }
  }

  private function checkUKFQD() {
    $UKFQD = explode('/', $this->dateString);
    $errors = 0;
    if ( count($UKFQD) === 3 ) {
      $this->parsedDate = array();
      if ( ((int)$UKFQD[0] < 1) && ((int)$UKFQD[0] > 31) ) $errors ++;
      if ( ((int)$UKFQD[1] < 1) && ((int)$UKFQD[1] > 12) ) $errors ++;
      if ( !is_numeric($UKFQD[2]) ) $errors ++;
      if ($errors === 0) {
        if ( checkdate((int)$UKFQD[1], (int)$UKFQD[0], (int)$UKFQD[2]) ) {
          $this->parsedDate['year'] = (int)$UKFQD[2];
          $this->parsedDate['month'] = (int)$UKFQD[1];
          $this->parsedDate['day'] = (int)$UKFQD[0];
          return true;
        } else {
          return false;
        }
      } else {
        return false;
      }
    } else {
      return false;
    }
  }

  private function fuzzyLogic() {
    $fuzzy = date_parse($this->dateString);
    if ( ($fuzzy['error_count'] === 0) && ($fuzzy['warning_count'] === 0) && $fuzzy['month'] && $fuzzy['day'] ) {
      $this->parsedDate['year'] = ($fuzzy['year']) ? (int)$fuzzy['year'] : (int)date('Y');
      $this->parsedDate['month'] = (int)$fuzzy['month'];
      $this->parsedDate['day'] = (int)$fuzzy['day'];
      if ( ($this->days === false) && ($this->parsedDate['day'] === 1) ) $this->parsedDate['range'] = true;
      return true;
    } else {
      $timestamp = getdate(strtotime($this->dateString));
      if ($timestamp[0]) {
        if ( checkdate($timestamp['mon'], $timestamp['mday'], $timestamp['year']) ) {
          $this->parsedDate['year'] = (int)$timestamp['year'];
          $this->parsedDate['month'] = (int)$timestamp['mon'];
          $this->parsedDate['day'] = (int)$timestamp['mday'];
          if (
                ( ($this->days === false) && ($this->parsedDate['day'] === 1) ) ||
                ( ($this->months === true) && empty($this->days) && ($this->years === false) )
          ) {
            $this->parsedDate['day'] = 1;
          }
          return true;
        } else {
          return false;
        }
      } else {
        $year = $month = $day = false;
        preg_match_all( '/(' . implode( '|', $this->monthNames ) . ')/i', $this->dateString, $monthMatch );
        if ( count($monthMatch) ) {
          foreach ($monthMatch as $foundMonth) {
            if ( isset($foundMonth[0]) && $foundMonth[0] ) $month = array_search( strtolower( $foundMonth[0] ),  $this->monthNames ) + 1;
            preg_match( '/([0-9]?[0-9])(st|nd|rd|th)/', $this->dateString, $dayMatch );
            if ( count($dayMatch) ) if ( $dayMatch[1] ) $day = $dayMatch[1];
            preg_match( '/[0-9]{4}/', $this->dateString, $yearMatch );
            if ( count($yearMatch) && $yearMatch[0] ) $year = $yearMatch[0];
            if ( $year && $month && $day ) {
              if ( checkdate($month, $day, $year) ) {
                $this->parsedDate['year'] = (int)$year;
                $this->parsedDate['month'] = (int)$month;
                $this->parsedDate['day'] = (int)$day;
                return true;
              } else {
                return false;
              }
            } else {
              return false;
            }
          }
        }
        return false;
      }
    }
    return false;
  }

  private function unitTestHelper($ds = false) {
    $d = getdate(strtotime($ds));
    return array('year' => (int)$d['year'], 'month' => (int)$d['mon'], 'day' => (int)$d['mday']);
  }

  public function unitTests() {
    $unitTests = array(
                        '02-31-1972' => false, // invalid
                        '12-08-1980' => array('year' => (int)1980, 'month' => (int)12, 'day' => (int)8), // US date
                        '7th December, 1969' => array('year' => (int)1969, 'month' => (int)12, 'day' => (int)7),
                        '7/12/69' => array('year' => (int)69, 'month' => (int)12, 'day' => (int)7), // UK date
                        '64/08/1960' => false, // invalid
                        'aug 2012' => array('year' => (int)2012, 'month' => (int)8, 'day' => (int)1, 'range' => true),
                        '1969 december' => array('year' => (int)1969, 'month' => (int)12, 'day' => (int)1, 'range' => true),
                        '12th April' => array('year' => (int)date('Y'), 'month' => (int)4, 'day' => (int)12),
                        '4th July' => array('year' => (int)date('Y'), 'month' => (int)7, 'day' => (int)4),
                        'next week' => $this->unitTestHelper('next week'),
                        'last thursday' => $this->unitTestHelper('last thursday'),
                        'next year' => $this->unitTestHelper('next year'),
                        '+ 14 days' => $this->unitTestHelper('+ 14 days'),
                        '+ 3 months' => $this->unitTestHelper('+ 3 months'),
                        'week last friday' => false,
                        'day before sunday' => false,
                        'day before next wednesday' => false,
                        '^(*^&*(EQ&TUQGEH^*QE^^*^*' => false, // garbage
                        'x" AND 1=(SELECT COUNT(*) FROM users); --' => false, // SQL injection
                        'I like this code because it <script>alert("Injected!");</script> teaches me a lot' => false, // JavaScript injection
                        '1st week in june' => false,
                        'next april 12' => false,
                        '2012-10-11' => array('year' => (int)2012, 'month' => (int)10, 'day' => (int)11),
                        'November 19th, 2012' => array('year' => (int)2012, 'month' => (int)11, 'day' => (int)19),
                        'Nov 3rd, 2010' => array('year' => (int)2010, 'month' => (int)11, 'day' => (int)3),
                        '2 years ago' => $this->unitTestHelper('2 years ago'),
                        'today' => $this->unitTestHelper('today'),
                        'tomorrow' => $this->unitTestHelper('tomorrow'),
                        'John Lennon was murdered on the 8th December, 1980.' => array('year' => (int)1980, 'month' => (int)12, 'day' => (int)8),
                        'John Lennon was born on the 9th October, 1940. He was murdered on the 8th December, 1980.' => array('year' => (int)1940, 'month' => (int)10, 'day' => (int)9),
                        '-90 days' => $this->unitTestHelper('-90 days'),
                        'This august body met on the 15th April, 1982.' => array('year' => (int)1982, 'month' => (int)4, 'day' => (int)15),
                        'The soldiers marched for days.' => false,
                        'November' => array('year' => (int)date('Y'), 'month' => (int)11, 'day' => (int)1, 'range' => true),
                        '1914' => array('year' => (int)1914, 'month' => (int)1, 'day' => (int)1, 'range' => true),
                        "June '63 - '64" => array('year' => (int)1963, 'month' => (int)6, 'day' => (int)1, 'range' => true),
                        '16th' => array('year' => (int)date('Y'), 'month' => (int)date('n'), 'day' => 16),
                        '1st January 1900' => array('year' => (int)1900, 'month' => (int)1, 'day' => (int)1),
                        '31st December 1800' => array('year' => (int)1800, 'month' => (int)12, 'day' => (int)31),
                        'December \'61' => array('year' => (int)1961, 'month' => (int)12, 'day' => (int)1, 'range' => true),
                      );

    echo '<h1>Starting &ldquo;dateFinder&rdquo; Unit Tests</h1>';
    foreach ($unitTests as $k => $v) {
      $this->dateString = filter_var($k, FILTER_SANITIZE_STRING);
      echo '<hr />';
      //echo date('d/m/Y', strtotime($k)) . '<br />';
      if ($this->findDate() == $v) {
        echo $this->dateString;
        echo '<p style="color: #00bb00">Passed</p>';
      } else {
        echo $k;
        echo '<p style="color: #bb0000">Failed</p>';
        echo 'Expected:';
        var_dump($v);
        $this->dateString = filter_var($k, FILTER_SANITIZE_STRING);
        echo 'Got:';
        var_dump($this->findDate());
      }
    }
    echo '<hr />';
  }

}

// Run the unit tests if script is called directly
if (__FILE__ == $_SERVER['DOCUMENT_ROOT'] . $_SERVER['PHP_SELF']) {
  $df = new dateFinder();
  $df->unitTests();
}
