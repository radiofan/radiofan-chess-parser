<?php
namespace Radiofan\ChessParser;

function user_import_filter(
	int $id_ruchess,
	?int $id_fide,
	string $name,
	bool $sex,
	string $country,
	?int $birth_year,
	int $region_number,
	string $region_name
){
	$accept = true;
	eval(get_option('radiofan_chess_parser__import_filter', ''));
	return $accept;
}

/**
 * Склонение слова после числа.
 *
 * Примеры вызова:
 * num_decline($num, ['книга','книги','книг'])
 * num_decline($num, 'книга', 'книги', 'книг')
 * num_decline($num, 'книга', 'книг')
 *
 * @param  int    $number  Число после которого будет слово. Можно указать число в HTML тегах.
 * @param  string|array  $titles  Варианты склонения или первое слово для кратного 1.
 * @param  string        $param2  Второе слово, если не указано в параметре $titles.
 * @param  string        $param3  Третье слово, если не указано в параметре $titles.
 *
 * @return string 1 книга, 2 книги, 10 книг.
 */
function num_decline($number, $titles, $param2 = '', $param3 = ''){
	if($param2)
		$titles = array($titles, $param2, $param3);

	if(empty($titles[2]))
		$titles[2] = $titles[1]; // когда указано 2 элемента

	$cases = array(2, 0, 1, 1, 1, 2);

	$number = absint($number);

	return $number.' '. $titles[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
}

/**
 * @param string $sql_time_interval - возраст, старше которого записи лога будут удалены; параметр не проверяется и используется на прмую в запросе вида `log_time` + INTERVAL '.$sql_time_interval.'
 */
function delete_old_logs($sql_time_interval = '1 MONTH'){
	global $wpdb;
	$wpdb->query('DELETE FROM '.$wpdb->prefix.'rad_chess_logs WHERE `log_time` + INTERVAL '.$sql_time_interval.' <= NOW()');
}