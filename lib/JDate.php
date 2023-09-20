<?php

class JDate
{
    protected static $instance;

    public $persian_month_names = array(
        '',
        'فروردین',
        'اردیبهشت',
        'خرداد',
        'تیر',
        'مرداد',
        'شهریور',
        'مهر',
        'آبان',
        'آذر',
        'دی',
        'بهمن',
        'اسفند'
    );

    public $persian_short_month_names = array(
        '',
        'فروردین',
        'اردیبهشت',
        'خرداد',
        'تیر',
        'مرداد',
        'شهریور',
        'مهر',
        'آبان',
        'آذر',
        'دی',
        'بهمن',
        'اسفند'
    );

    public $sesson = array( 'بهار', 'تابستان', 'پاییز', 'زمستان' );

    public $persian_day_names = array( 'یکشنبه', 'دوشنبه', 'سه شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' );
    public $persian_day_small = array( 'ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش' );

    public $j_days_in_month = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 );
    private $j_days_sum_month = array( 0, 0, 31, 62, 93, 124, 155, 186, 216, 246, 276, 306, 336 );

    private $g_days_sum_month = array( 0, 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );

    function __construct()
    {
    }

    public function IsPerLeapYear($year)
    {
        $mod = $year % 33;

        if ($mod == 1 or $mod == 5 or $mod == 9 or $mod == 13 or $mod == 17 or $mod == 22 or $mod == 26 or $mod == 30) {
            return true;
        }

        return false;
    }

    private function IsLeapYear($year)
    {
        if (( ( $year % 4 ) == 0 && ( $year % 100 ) != 0 ) || ( ( $year % 400 ) == 0 ) && ( $year % 100 ) == 0) {
            return true;
        }

        return false;
    }

    public function persian_date($format, $date = 'now', $lang = 'per')
    {
        $j_days_in_month = array( 31, 62, 93, 124, 155, 186, 216, 246, 276, 306, 336, 365 );
        $timestamp       = is_numeric($date) && (int) $date == $date ? $date : strtotime($date);
        $date            = getdate($timestamp);

        list( $date['year'], $date['mon'], $date['mday'] ) = JDate::gregorian_to_persian($date['year'], $date['mon'], $date['mday']);

        $date['mon']  = (int) $date['mon'];
        $date['mday'] = (int) $date['mday'];
        $out          = '';
        $len          = strlen($format);

        for ($i = 0; $i < $len; $i ++) {
            switch ($format[ $i ]) {
                case 'd':
                    $out .= ( $date['mday'] < 10 ) ? '0' . $date['mday'] : $date['mday'];
                    break;
                case 'D':
                    $out .= $this->persian_day_small[ $date['wday'] ];
                    break;
                case 'l':
                    $out .= $this->persian_day_names[ $date['wday'] ];
                    break;
                case 'j':
                    $out .= $date['mday'];
                    break;
                case 'N':
                    $out .= $this->week_day($date['wday']) + 1;
                    break;
                case 'w':
                    $out .= $this->week_day($date['wday']);
                    break;
                case 'z':
                    if ($date['mon'] == 12 && JDate::IsPerLeapYear($date['year'])) {
                        $out .= 30 + $date['mday'];
                    } else {
                        $out .= $this->j_days_in_month[ $date['mon'] ] + $date['mday'];
                    }
                    break;
                case 'W':
                    $yday = $this->j_days_sum_month[ $date['mon'] - 1 ] + $date['mday'];
                    $out  .= intval($yday / 7);
                    break;
                case 'f':
                    $mon = $date['mon'];
                    switch ($mon) {
                        case ( $mon < 4 ):
                            $out .= $this->sesson[0];
                            break;
                        case ( $mon < 7 ):
                            $out .= $this->sesson[1];
                            break;
                        case ( $mon < 10 ):
                            $out .= $this->sesson[2];
                            break;
                        case ( $mon > 9 ):
                            $out .= $this->sesson[3];
                            break;
                    }
                    break;
                case 'F':
                    $out .= $this->persian_month_names[ $date['mon'] ];
                    break;
                case 'm':
                    $out .= ( $date['mon'] < 10 ) ? '0' . $date['mon'] : $date['mon'];
                    break;
                case 'M':
                    $out .= $this->persian_short_month_names[ $date['mon'] ];
                    break;
                case 'n':
                    $out .= $date['mon'];
                    break;
                case 'S':
                    $out .= 'ام';
                    break;
                case 't':
                    if ($date['mon'] == 12 && JDate::IsPerLeapYear($date['year'])) {
                        $out .= 30;
                    } else {
                        $out .= $this->j_days_in_month[ $date['mon'] - 1 ];
                    }
                    break;
                case 'L':
                    $out .= ( ( $date['year'] % 4 ) == 0 ) ? 1 : 0;
                    break;
                case 'o':
                case 'Y':
                    $out .= $date['year'];
                    break;
                case 'y':
                    $out .= substr($date['year'], 2, 2);
                    break;
                case 'a':
                    $out .= ( $date['hours'] < 12 ) ? 'ق.ظ' : 'ب.ظ';
                    break;
                case 'A':
                    $out .= ( $date['hours'] < 12 ) ? 'قبل از ظهر' : 'بعد از ظهر';
                    break;
                case 'B':
                    $out .= (int) ( 1 + ( $date['mon'] / 3 ) );
                    break;
                case 'g':
                    $out .= ( $date['hours'] > 12 ) ? $date['hours'] - 12 : $date['hours'];
                    break;
                case 'G':
                    $out .= $date['hours'];
                    break;
                case 'h':
                    $hour = ( $date['hours'] > 12 ) ? $date['hours'] - 12 : $date['hours'];
                    $out  .= ( $hour < 10 ) ? '0' . $hour : $hour;
                    break;
                case 'H':
                    $out .= ( $date['hours'] < 10 ) ? '0' . $date['hours'] : $date['hours'];
                    break;
                case 'i':
                    $out .= ( $date['minutes'] < 10 ) ? '0' . $date['minutes'] : $date['minutes'];
                    break;
                case 's':
                    $out .= ( $date['seconds'] < 10 ) ? '0' . $date['seconds'] : $date['seconds'];
                    break;
                case 'c':
                    $out = $date['year'] . '/' . $date['mon'] . '/' . $date['mday'] . ' ' . $date['hours'] . ':' . ( ( $date['minutes'] < 10 ) ? '0' . $date['minutes'] : $date['minutes'] ) . ':' . ( ( $date['seconds'] < 10 ) ? '0' . $date['seconds'] : $date['seconds'] );//2004-02-12T15:19:21+00:00
                    break;
                case 'r':
                    $out = $this->persian_day_names[ $date['wday'] ] . ',' . $date['mday'] . ' ' . $this->persian_month_names[ $date['mon'] ] . ' ' . $date['year'] . ' ' . $date['hours'] . ':' . ( ( $date['minutes'] < 10 ) ? '0' . $date['minutes'] : $date['minutes'] ) . ':' . ( ( $date['seconds'] < 10 ) ? '0' . $date['seconds'] : $date['seconds'] );//Thu, 21 Dec 2000 16:01:07
                    break;
                case 'U':
                    $out = $timestamp;
                    break;
                case 'e':
                case 'I':
                case 'O':
                case 'P':
                case 'T':
                case 'Z':
                case 'u':
                    break;
                default:
                    $out .= $format[ $i ];
            }
        }

        if (!in_array(strtolower($format), [ 'u', 'timestamp' ]) && $lang == 'per') {
            return JDate::trim_number($out);
        } else {
            return $out;
        }
    }

    function gregorian_to_persian($gy, $gm, $gd)
    {
        $dayOfYear = $this->g_days_sum_month[ (int) $gm ] + $gd;

        if (JDate::IsLeapYear($gy) and $gm > 2) {
            $dayOfYear ++;
        }

        $d_33 = (int) ( ( ( $gy - 16 ) % 132 ) * 0.0305 );
        $leap = $gy % 4;
        $a    = ( ( $d_33 == 1 or $d_33 == 2 ) and ( $d_33 == $leap or $leap == 1 ) ) ? 78 : ( ( $d_33 == 3 and $leap == 0 ) ? 80 : 79 );
        $b    = ( $d_33 == 3 or $d_33 < ( $leap - 1 ) or $leap == 0 ) ? 286 : 287;

        if ((int) ( ( $gy - 10 ) / 63 ) == 30) {
            $b --;
            $a ++;
        }

        if ($dayOfYear > $a) {
            $jy = $gy - 621;
            $jd = $dayOfYear - $a;
        } else {
            $jy = $gy - 622;
            $jd = $dayOfYear + $b;
        }

        for ($i = 0; $i < 11 and $jd > $this->j_days_in_month[ $i ]; $i ++) {
            $jd -= $this->j_days_in_month[ $i ];
        }

        $jm = ++ $i;

        return array( $jy, strlen($jm) == 1 ? '0' . $jm : $jm, strlen($jd) == 1 ? '0' . $jd : $jd );
    }

    private function week_day($wday)
    {
        return $wday == 6 ? 0 : ++ $wday;
    }

    public function trim_number($num, $sp = '٫')
    {
        $eng    = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.' );
        $per    = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', $sp );
        $number = filter_var($num, FILTER_SANITIZE_NUMBER_INT);

        return empty($number) ? str_replace($per, $eng, $num) : str_replace($eng, $per, $num);
    }

    public static function getInstance()
    {
        if (! isset(JDate::$instance)) {
	        JDate::$instance = new JDate();
        }

        return JDate::$instance;
    }

    public function gregorian_date($format, $persiandate)
    {
        preg_match_all('!\d+!', $persiandate, $matches);

        $matches = $matches[0];

        list( $year, $mon, $day ) = JDate::persian_to_gregorian($matches[0], $matches[1], $matches[2]);

        return date($format, mktime(( isset($matches[3]) ? $matches[3] : 0 ), ( isset($matches[4]) ? $matches[4] : 0 ), ( isset($matches[5]) ? $matches[5] : 0 ), $mon, $day, $year));
    }

    public function persian_to_gregorian($jy, $jm, $jd)
    {
        $doyj = ( $jm - 2 > - 1 ? $this->j_days_sum_month[ (int) $jm ] + $jd : $jd );
        $d4   = ( $jy + 1 ) % 4;
        $d33  = (int) ( ( ( $jy - 55 ) % 132 ) * .0305 );
        $a    = ( $d33 != 3 and $d4 <= $d33 ) ? 287 : 286;
        $b    = ( ( $d33 == 1 or $d33 == 2 ) and ( $d33 == $d4 or $d4 == 1 ) ) ? 78 : ( ( $d33 == 3 and $d4 == 0 ) ? 80 : 79 );

        if ((int) ( ( $jy - 19 ) / 63 ) == 20) {
            $a --;
            $b ++;
        }

        if ($doyj <= $a) {
            $gy = $jy + 621;
            $gd = $doyj + $b;
        } else {
            $gy = $jy + 622;
            $gd = $doyj - $a;
        }

        foreach (array( 0, 31, ( $gy % 4 == 0 ) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ) as $gm => $days) {
            if ($gd <= $days) {
                break;
            }

            $gd -= $days;
        }

        return array( $gy, $gm, $gd );
    }
}

function parsidate($input, $datetime = 'now', $lang = 'per')
{
    $bndate = bn_parsidate::getInstance();
    return $bndate->persian_date($input, $datetime, $lang);
}

function gregdate($input, $datetime)
{
    $bndate = bn_parsidate::getInstance();
    return $bndate->gregorian_date($input, $datetime);
}
