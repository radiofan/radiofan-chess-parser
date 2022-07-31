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
 * Возвращает даты первого и последнего дня предшествующего месяца
 * Если сегодня последний день текущего месяца, то данный месяц считается предшествующим
 * @return array - ['first_day' => DateTime, 'end_day' => DateTime]
 */
function get_start_end_prev_month_days(){
	$first_day = null;
	$end_day = new \DateTime();
	$end_day->setTime(0, 0);
	if((int)$end_day->format('t') == (int)$end_day->format('j')){
		$first_day = clone $end_day;
		$first_day->setDate($end_day->format('Y'), $end_day->format('n'), 1);
	}else{
		$end_day->setDate($end_day->format('Y'), $end_day->format('n'), 1);

		$first_day = clone $end_day;

		$first_day->sub(new \DateInterval('P1M'));
		$end_day->sub(new \DateInterval('P1D'));
	}
	return ['first_day' => $first_day, 'end_day' => $end_day];
}