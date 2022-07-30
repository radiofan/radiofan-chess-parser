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


/**
 * todo описание
 * @param \DateTime|null $date_start
 * @param \DateTime|null $date_end
 * @throws \Exception 'date_start more or equal date_end'
 * @return array
 */
function get_players_with_rating_dynamics($date_start = null, $date_end = null){
	global $wpdb;
	
	if(!is_null($date_start)){
		$date_start->setTime(0, 0);
	}

	if(!is_null($date_end)){
		$date_end->setTime(0, 0)->add(new \DateInterval('P1D'));
	}
	
	if($date_start >= $date_end)
		throw new \Exception('date_start more or equal date_end');

	$time_start = !is_null($date_start) ? $date_start->getTimestamp() : null;
	$time_end = !is_null($date_end) ? $date_end->getTimestamp() : null;
	
	$ratings = [];

	//todo можно произвести оптимизацию
	$ret = $wpdb->get_results('SELECT `id_ruchess`, `rating_type`, `rating`, UNIX_TIMESTAMP(`update_date`) AS `update_time` FROM `'.$wpdb->prefix.'rad_chess_players_ratings` ORDER BY `id_ruchess` DESC, `update_date` DESC', ARRAY_A);
	$len = sizeof($ret);
	for($i=$len-1; $i>=0; $i--){
		$id = (int)$ret[$i]['id_ruchess'];
		$r_t = $ret[$i]['rating_type'];
		$time_update = (int)$ret[$i]['update_time'];
		
		if(!isset($ratings[$id]))
			$ratings[$id] = [];

		if(!isset($ratings[$id][$r_t]))
			$ratings[$id][$r_t] = ['rating_start' => null, 'rating_end' => null];
		

		//проверяем попадает ли рейтинг в правую границу если она имеется
		if(is_null($time_end) || $time_update < $time_end){
			//стартовый рейтинг может установиться:
			//только первый при отсутствии левой границы, при условии что он находится до правой границы (если она есть)
			//устанавливается до тех пор пока меньше левой границы
			if(
				(is_null($time_start) && is_null($ratings[$id][$r_t]['rating_start'])) ||
				(!is_null($time_start) && $time_update < $time_start)
			){
				$ratings[$id][$r_t]['rating_start'] = ['rating' => (int)$ret[$i]['rating'], 'update_time' => $time_update];
			}
			
			//конечный рейтинг устанавливается, при условии что он находится до правой границы (если она есть)
			$ratings[$id][$r_t]['rating_end'] = ['rating' => (int)$ret[$i]['rating'], 'update_time' => $time_update];
		
		}
	}
	
	return $ratings;
}