<?php
/**
 * SIAX SMSS - Helper Functions
 */

if (!function_exists('numberToWords')) {
    function numberToWords($number) {
        $hyphen      = '-';
        $conjunction = ' and ';
        $separator   = ', ';
        $negative    = 'negative ';
        $decimal     = ' point ';
        $dictionary  = array(
            0                   => 'Zero',
            1                   => 'One',
            2                   => 'Two',
            3                   => 'Three',
            4                   => 'Four',
            5                   => 'Five',
            6                   => 'Six',
            7                   => 'Seven',
            8                   => 'Eight',
            9                   => 'Nine',
            10                  => 'Ten',
            11                  => 'Eleven',
            12                  => 'Twelve',
            13                  => 'Thirteen',
            14                  => 'Fourteen',
            15                  => 'Fifteen',
            16                  => 'Sixteen',
            17                  => 'Seventeen',
            18                  => 'Eighteen',
            19                  => 'Nineteen',
            20                  => 'Twenty',
            30                  => 'Thirty',
            40                  => 'Forty',
            50                  => 'Fifty',
            60                  => 'Sixty',
            70                  => 'Seventy',
            80                  => 'Eighty',
            90                  => 'Ninety',
            100                 => 'Hundred',
            1000                => 'Thousand',
            1000000             => 'Million',
            1000000000          => 'Billion',
            1000000000000       => 'Trillion',
            1000000000000000    => 'Quadrillion',
            1000000000000000000 => 'Quintillion'
        );

        if (!is_numeric($number)) return false;
        if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) return false;

        if ($number < 0) return $negative . numberToWords(abs($number));

        $string = $fraction = null;
        if (strpos($number, '.') !== false) {
            list($number, $fraction) = explode('.', $number);
        }

        switch (true) {
            case $number < 21:
                $string = $dictionary[$number];
                break;
            case $number < 100:
                $tens   = ((int) ($number / 10)) * 10;
                $units  = $number % 10;
                $string = $dictionary[$tens];
                if ($units) {
                    $string .= $hyphen . $dictionary[$units];
                }
                break;
            case $number < 1000:
                $hundreds  = $number / 100;
                $remainder = $number % 100;
                $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
                if ($remainder) {
                    $string .= $conjunction . numberToWords($remainder);
                }
                break;
            default:
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int) ($number / $baseUnit);
                $remainder = $number % $baseUnit;
                $string = numberToWords($numBaseUnits) . ' ' . $dictionary[$baseUnit];
                if ($remainder) {
                    $string .= $remainder < 100 ? $conjunction : $separator;
                    $string .= numberToWords($remainder);
                }
                break;
        }

        if (null !== $fraction && is_numeric($fraction)) {
            $string .= $decimal;
            $words = array();
            foreach (str_split((string) $fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $string;
    }
}

/**
 * Calculate natural sorting rank for a school class name
 * Examples: PG, Nursery, Prep, KG, 1st, 2nd, 3rd, 4th, 5th, 10th, etc.
 */
if (!function_exists('class_sort_rank')) {
    function class_sort_rank($name) {
        $n = strtolower(trim($name));
        
        // Early childhood / preschool
        if (preg_match('/^(play\s*group|pg|day\s*care)/i', $n)) return 0.1;
        if (preg_match('/^(nur|nursery)/i', $n)) return 0.2;
        if (preg_match('/^prep/i', $n)) return 0.3;
        if (preg_match('/^(kg\s*(ii|2)|kg-ii)/i', $n)) return 0.5;
        if (preg_match('/^(kg|kindergarten)/i', $n)) return 0.4;
        
        // Word numbers (One, Two, Three...)
        $words = [
            'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
            'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
            'eleven' => 11, 'twelve' => 12
        ];
        foreach ($words as $w => $num) {
            if (preg_match('/\b' . $w . '\b/i', $n)) {
                return (float)$num;
            }
        }

        // Numeric extraction (e.g. '1st', '2nd', '3rd', 'Class 1', 'Grade 2', '10')
        if (preg_match('/(\d+)/', $n, $matches)) {
            return (float)$matches[1];
        }
        
        // Secondary / Higher levels
        if (preg_match('/matric|metric/i', $n)) return 10.0;
        if (preg_match('/1st\s*year/i', $n)) return 11.0;
        if (preg_match('/2nd\s*year/i', $n)) return 12.0;
        
        return 999.0;
    }
}

/**
 * Natural comparator for class array items
 */
if (!function_exists('compare_classes_naturally')) {
    function compare_classes_naturally($a, $b) {
        $nameA = is_array($a) ? ($a['name'] ?? '') : (string)$a;
        $nameB = is_array($b) ? ($b['name'] ?? '') : (string)$b;
        
        $rankA = class_sort_rank($nameA);
        $rankB = class_sort_rank($nameB);
        
        if ($rankA != $rankB) {
            return ($rankA < $rankB) ? -1 : 1;
        }
        
        $cmp = strnatcasecmp($nameA, $nameB);
        if ($cmp !== 0) return $cmp;
        
        $secA = is_array($a) ? ($a['section'] ?? '') : '';
        $secB = is_array($b) ? ($b['section'] ?? '') : '';
        return strnatcasecmp($secA, $secB);
    }
}

/**
 * Sort classes array in natural sequential order (1st, 2nd, 3rd, ...)
 */
if (!function_exists('sort_classes_naturally')) {
    function sort_classes_naturally(&$classes) {
        if (!is_array($classes)) return;
        usort($classes, 'compare_classes_naturally');
    }
}

/**
 * Fetch all classes from database ordered in natural school order
 */
if (!function_exists('get_all_classes')) {
    function get_all_classes($conn) {
        $classes = [];
        $res = $conn->query("SELECT * FROM classes");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $classes[] = $row;
            }
            sort_classes_naturally($classes);
        }
        return $classes;
    }
}

/**
 * Automatically detect server's local network (LAN) IP address (e.g. 192.168.100.15)
 */
if (!function_exists('get_server_lan_ip')) {
    function get_server_lan_ip() {
        $ip = $_SERVER['SERVER_ADDR'] ?? '';
        
        if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') {
            $hostname = gethostname();
            if ($hostname) {
                $host_ip = gethostbyname($hostname);
                if (!empty($host_ip) && $host_ip !== '127.0.0.1' && strpos($host_ip, '127.') !== 0) {
                    $ip = $host_ip;
                }
            }
        }
        
        if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                @exec('ipconfig', $output);
                if (is_array($output)) {
                    foreach ($output as $line) {
                        if (preg_match('/IPv4 Address[^\:]*:\s*([0-9\.]+)/i', $line, $matches)) {
                            if ($matches[1] !== '127.0.0.1') {
                                $ip = trim($matches[1]);
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') {
            $host_header = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $parts = explode(':', $host_header);
            $ip = $parts[0];
        }
        
        return $ip;
    }
}

/**
 * Get full Campus LAN Base URL (e.g. http://192.168.100.15:8080/siax-smss/)
 */
if (!function_exists('get_campus_base_url')) {
    function get_campus_base_url() {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $ip = get_server_lan_ip();
        
        $port = $_SERVER['SERVER_PORT'] ?? 80;
        $port_str = ($port != 80 && $port != 443 && strpos($ip, ':') === false) ? (':' . $port) : '';
        
        $proj_root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        $doc_root  = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
        $relative_path = (!empty($doc_root)) ? str_ireplace($doc_root, '', $proj_root) : '';
        if (empty($relative_path) || $relative_path === $proj_root) {
            $base_path = '/siax-smss/';
        } else {
            $base_path = '/' . ltrim($relative_path, '/') . '/';
            $base_path = str_replace('//', '/', $base_path);
        }
        
        return $scheme . '://' . $ip . $port_str . $base_path;
    }
}





