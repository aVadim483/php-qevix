<?php
/**
 * Генератор классов символов для Qevix
 */

use avadim\Qevix\Qevix;

require 'Qevix.php';

function addChClass(&$table, $chars, $class)
{
	foreach($chars as $ch) {
		$ord = Qevix::ord($ch);
		$table[$ord] = (isset($table[$ord]) ? $table[$ord] : 0) | $class;
	}
}

function addChRangeClass(&$table, $from, $to, $class)
{
	for($i = $from; $i <= $to; $i++) {
		$table[$i] = (isset($table[$i]) ? $table[$i] : 0) | $class;
	}
}

$table = [];

addChRangeClass($table, 0, 0x20, Qevix::NO_PRINT);
addChRangeClass($table, Qevix::ord('a'), Qevix::ord('z'), Qevix::ALPHA | Qevix::PRINTABLE | Qevix::TAG_NAME | Qevix::TAG_PARAM_NAME);
addChRangeClass($table, Qevix::ord('A'), Qevix::ord('Z'), Qevix::ALPHA |  Qevix::PRINTABLE | Qevix::TAG_NAME | Qevix::TAG_PARAM_NAME);
addChRangeClass($table, Qevix::ord('0'), Qevix::ord('9'), Qevix::NUMERIC | Qevix::PRINTABLE | Qevix::TAG_NAME | Qevix::TAG_PARAM_NAME);

addChClass($table, ['-'], Qevix::TAG_PARAM_NAME | Qevix::PRINTABLE);

addChClass($table, [' ', "\t"], Qevix::SPACE);
addChClass($table, ["\r", "\n"], Qevix::NL);
addChClass($table, ['"'], Qevix::TAG_QUOTE | Qevix::TEXT_QUOTE | Qevix::PRINTABLE);
addChClass($table, ["'"], Qevix::TAG_QUOTE | Qevix::PRINTABLE);
addChClass($table, ['.', ',', '!', '?', ':', ';'], Qevix::PUNCTUATION | Qevix::PRINTABLE);

addChClass($table, ['<', '>', '[', ']', '{', '}', '(', ')'],  Qevix::TEXT_BRACKET | Qevix::PRINTABLE);

addChClass($table, ['@', '#', '$'],  Qevix::SPECIAL_CHAR | Qevix::PRINTABLE);

ob_start();
var_export($table);
$res = ob_get_clean();
echo str_replace(["\n", ' '], '', $res).';';

// EOF