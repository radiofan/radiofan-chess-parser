<?php
namespace Radiofan\ChessParser;

use WP_Error;

trait Parser{

	/**
	 * @param string $url - адрес скачиваемого файла
	 * @param string $file_to - абсолютный путь к месту расположения скачиваемого файла
	 * @param null|string $etag - @see https://developer.mozilla.org/ru/docs/Web/HTTP/Headers/ETag
	 * @return WP_Error (download_not_need_update, download_undefined_error, download_success, new_etag)
	 */
	protected function download_file($url, $file_to, $etag = null){
		$time_statistic = ['download_file_start' => microtime(1)];
		//проверка etag
		$new_etag = null;
		if(!is_null($etag)){
			$etag = preg_replace('#[^0-9a-z_\\-"\']#iu', '', (string)$etag);

			$ret = wp_remote_head($url, ['redirection' => 0, 'user-agent' => 'RADIOFAN Chess Parser', 'sslverify' => 0, 'headers' => ['If-None-Match' => $etag]]);

			if((int) $ret['response']['code'] === 304){
				return new WP_Error('download_not_need_update', 'Файл не требует обновления', $ret);
			}
			if((int) $ret['response']['code'] !== 200){
				return new WP_Error('download_undefined_error', 'Не удалось получить данные о файле', $ret);
			}

			$headers = $ret['headers'];
			if(is_array($headers)){
				if(isset($headers['etag'][0]))
					$new_etag = preg_replace('#[^0-9a-z_\\-"\']#iu', '', $headers['etag'][0]);
			}else{
				/** @var \Requests_Response_Headers $headers */
				$new_etag = preg_replace('#[^0-9a-z_\\-"\']#iu', '', $headers->offsetGet('etag'));
			}
		}

		//скачивание файла
		$ret = wp_remote_get($url, ['redirection' => 0, 'user-agent' => 'RADIOFAN Chess Parser', 'sslverify' => 0, 'timeout' => 300, 'stream' => 1, 'filename' => $file_to]);
		
		$time_statistic['download_file_end'] = microtime(1);
		$time_statistic['download_file_time'] = $time_statistic['download_file_end'] - $time_statistic['download_file_start'];

		if((int) $ret['response']['code'] !== 200){
			return new WP_Error('download_undefined_error', 'Не удалось скачать файл', [$ret, $time_statistic]);
		}

		$ret = new WP_Error('download_success', 'Файл скачан', [$ret, $time_statistic]);
		if($new_etag){
			$ret->add('new_etag', '', $new_etag);
		}

		return $ret;
	}


	/**
	 * @param string $zip - абсолютный путь к архиву
	 * @param string $dir_to - аюсолютный путь до папки куда распакуются файлы
	 * @return WP_Error (unzip_file_success, данные возвращаемые unzip_file)
	 * @see unzip_file
	 */
	protected function unzip_file($zip, $dir_to){
		$time_statistic = ['unzip_file_start' => microtime(1)];
		//инициализация файловой системы
		if(!function_exists('unzip_file')){
			require_once(ABSPATH.'wp-admin/includes/file.php');
		}
		/** @var \WP_Filesystem_Base $wp_filesystem */
		global $wp_filesystem;
		if(empty($wp_filesystem)){
			WP_Filesystem();
		}

		$dir_to = str_replace(ABSPATH, $wp_filesystem->abspath(), $dir_to);
		
		$wp_filesystem->delete($dir_to, true);

		$ret = unzip_file($zip, $dir_to);
		
		$time_statistic['unzip_file_end'] = microtime(1);
		$time_statistic['unzip_file_time'] = $time_statistic['unzip_file_end'] - $time_statistic['unzip_file_start'];
		
		if($ret === true){
			return new WP_Error('unzip_file_success', 'Файл успешно распакован', [$time_statistic, $dir_to]);
		}else{
			return $ret;
		}
	}

	/**
	 * @param string $csv - абсолютный путь к csv файлу
	 * @param WP_Error $parse_error - хранит ошибки парсинга (csv_open_error, csv_error_head_format, csv_str_parse_error, csv_str_parse_warning, csv_parsing_success)
	 * @return false|array
	 * [
	 * 	'player' => массив результатов parse_csv_str player
	 * 	'rating' => массив результатов parse_csv_str rating
	 * ]
	 * @see Parser::parse_csv_str
	 */
	protected function parse_csv($csv, $parse_error){
		$time_statistic = ['csv_parsing_start' => microtime(1)];

		$csv_stream = fopen($csv, 'r');
		if($csv_stream === false){
			$parse_error->add('csv_open_error', 'CSV файл не открыт!', $csv);
			return false;
		}

		//проверка заголовка
		$str = (string)fgets($csv_stream);
		$data = str_getcsv($str, ',');
		if($data !== ['ID_No','Name','Sex','Fed','Clubnumber','ClubName','Birthday','Rtg_Nat','Fide_No','Rtg_Int']){
			fclose($csv_stream);
			$parse_error->add('csv_error_head_format', 'CSV файл имеет не правильный заголовок', [$csv, $data]);
			return false;
		}
				
		$player_data_to_import = [];
		$rating_data_to_import = [];
		
		$statistic = ['str_corrupted' => 0, 'str_all' => 0, 'str_filtered' => 0];
		
		for($i=2;;$i++){
			$str = fgets($csv_stream);
			if($str === false)
				break;

			$statistic['str_all']++;
			
			$data = str_getcsv($str, ',');
			$data = $this->parse_csv_str($data, $parse_error, $i);
			if($data === false){
				$statistic['str_corrupted']++;
				continue;
			}
			
			if(!$this->user_import_filter($data)){
				continue;
			}
			$statistic['str_filtered']++;

			$player_data_to_import[] = $data['player'];
			$rating_data_to_import = array_merge($rating_data_to_import, $data['rating']);
		}
		fclose($csv_stream);
		$time_statistic['csv_parsing_end'] = microtime(1);
		$time_statistic['csv_parsing_time'] = $time_statistic['csv_parsing_end'] - $time_statistic['csv_parsing_start'];
		
		$parse_error->add('csv_parsing_success', 'Парсинг завершен', array_merge([$csv], $time_statistic, $statistic));
		
		return ['player' => $player_data_to_import, 'rating' => $rating_data_to_import];
		
	}

	/**
	 * преобразует результат str_getcsv() в массив с данными игрока и его рейтингами
	 * ошибки парсинга добавляются в $parse_error
	 * @param string[] $data - результат str_getcsv()
	 * @param WP_Error $parse_error - хранит ошибки парсинга (csv_str_parse_error, csv_str_parse_warning)
	 * @param int $str_number - номер обрабатываемой строки, нужен для указания ошибок
	 * @return false|array
	 * [
	 * 	'player' => [
	 * 		'id_ruchess'	=> int
	 * 		'id_fide'		=> null|int
	 * 		'name'			=> string
	 * 		'sex'			=> bool			//0-м, 1-ж
	 * 		'country'		=> string
	 * 		'birth_year'	=> null|int
	 * 		'region_number'	=> int
	 * 		'region_name'	=> string
	 * 	],
	 * 	'rating' => [
	 * 		// 0 или 1 массив
	 * 		[
	 * 			'id_ruchess'	=> int,//рейтинг ruchess
	 * 			'rating'		=> int
	 * 		]
	 * 	]
	 * ]
	 */
	protected function parse_csv_str($data, $parse_error, $str_number){
		if(sizeof($data) !== 10){
			$parse_error->add('csv_str_parse_error', 'Строка '.$str_number.': Неверное кол-во элементов');
			return false;
		}

		$ret = [
			'player' => [
				'id_ruchess'	=> $data[0],//ID_No
				'id_fide'		=> $data[8],//Fide_No
				'name'			=> $data[1],//Name
				'sex'			=> $data[2],//Sex
				'country'		=> $data[3],//Fed
				'birth_year'	=> $data[6],//Birthday
				'region_number'	=> $data[4],//Clubnumber
				'region_name'	=> $data[5] //ClubName
			],
			'rating' => []
		];

		$ret['player']['id_ruchess'] = absint($ret['player']['id_ruchess']);
		if($ret['player']['id_ruchess'] === 0){
			$parse_error->add('csv_str_parse_error', 'Строка '.$str_number.': id_ruchess (ID_No) не задано');
			return false;
		}

		$ret['player']['id_fide'] = absint($ret['player']['id_fide']);
		if($ret['player']['id_fide'] === 0){
			//$parse_error->add('csv_str_parse_warning', 'Строка '.$str_number.': id_fide (Fide_No) не задано');//очень частое явление
			$ret['player']['id_fide'] = null;
		}

		$ret['player']['name'] = trim($ret['player']['name']);

		switch(mb_strtolower($ret['player']['sex'])){
			case 'f':
			case 'female':
			case 'woman':
			case 'ж':
			case 'жен':
				$ret['player']['sex'] = 1;
				break;
			default:
				$ret['player']['sex'] = 0;
				break;

		}

		$ret['player']['country'] = trim($ret['player']['country']);

		$ret['player']['birth_year'] = absint($ret['player']['birth_year']);
		if($ret['player']['birth_year'] == 0){
			$ret['player']['birth_year'] = null;
		}else if($ret['player']['birth_year'] < 1901 || $ret['player']['birth_year'] > 2155){
			$parse_error->add('csv_str_parse_error', 'Строка '.$str_number.': birth_year (Birthday) выходит за пределы');
			return false;
		}

		$ret['player']['region_number'] = absint($ret['player']['region_number']);

		$ret['player']['region_name'] = trim($ret['player']['region_name']);
		
		
		$rating_ruchess = $data[7];//Rtg_Nat
		$rating_fide    = $data[9];//Rtg_Int
		
		if($rating_ruchess !== ''){
			$ret['rating'][] = ['id_ruchess' => $ret['player']['id_ruchess'], 'rating' => absint($rating_ruchess)];
		}
		/*
		if($rating_fide !== ''){
			$ret['rating'][] =['id_ruchess' => $ret['player']['id_ruchess'], 'rating_type' => 2, 'rating' => absint($rating_fide)];
		}
		*/

		return $ret;
	}

	/**
	 * Проверяет данные на допуск с помощью функции user_import_filter(), используется пользовательский фильтр
	 * @see user_import_filter()
	 * @param array $data - результат удачной работы Parser::parse_csv_str()
	 * @return bool - true - данные допущены
	 */
	protected function user_import_filter($data){
		return (bool) user_import_filter(
			$data['player']['id_ruchess'],
			$data['player']['id_fide'],
			$data['player']['name'],
			$data['player']['sex'],
			$data['player']['country'],
			$data['player']['birth_year'],
			$data['player']['region_number'],
			$data['player']['region_name']
		);
	}

	/**
	 * @param string $txt_path - абсолютный путь к текстовому файлу
	 * @param WP_Error $parse_error - хранит ошибки парсинга (fide_txt_open_error, fide_txt_error_head_format, fide_txt_str_parse_error, fide_txt_parsing_success)
	 * @return false|array
	 * [
	 * 	'player' => []
	 * 	'rating' => массив результатов parse_csv_str  rating
	 * ]
	 * @see Parser::parse_txt_str
	 * @see Parser::parse_csv_str
	 */
	protected function parse_fide_txt($txt_path, $parse_error){
		$time_statistic = ['fide_txt_parsing_start' => microtime(1)];

		$file_stream = fopen($txt_path, 'r');
		if($file_stream === false){
			$parse_error->add('fide_txt_open_error', 'Файл с данными не открыт!', $txt_path);
			return false;
		}

		//проверка заголовка
		$str = (string)fgets($file_stream);
		$data = [];
		$header = [];
		preg_match('#^(ID Number +)(Name +)(Fed +)(Sex +)(Titl? +)(WTit +)(OTit +)(FOA +)([a-z0-9]+ +)(Gms +)(K +)(B-day +)(Flag *)#iu', $str, $data);
		
		if(sizeof($data) != 14){
			fclose($file_stream);
			$parse_error->add('fide_txt_error_head_format', 'Файл с данными имеет неправильный заголовок', [$txt_path, $str]);
			return false;
		}
		
		//обработаем заголовок, получим длины полей
		$len = sizeof($data);
		for($i=1; $i<$len; $i++){
			if($i == 9){
				$header['rating'] = mb_strlen($data[$i]);
			}else{
				$header[mb_strtolower(trim($data[$i]))] = mb_strlen($data[$i]);
			}
		}
		
		global $wpdb;
		$data = $wpdb->get_results('SELECT `id_ruchess`, `id_fide` FROM `'.$wpdb->prefix.'rad_chess_players` WHERE NOT `id_fide` IS NULL', ARRAY_A);
		$fide_to_ruchess_id = [];
		$len = sizeof($data);
		for($i=0; $i<$len; $i++){
			$fide_to_ruchess_id[absint($data[$i]['id_fide'])] = absint($data[$i]['id_ruchess']);
			unset($data[$i]);
		}
		

		$rating_data_to_import = [];

		$statistic = ['str_corrupted' => 0, 'str_all' => 0, 'str_filtered' => 0];

		for($i=2;;$i++){
			$str = fgets($file_stream);
			if($str === false)
				break;

			$statistic['str_all']++;
			$data = $this->parse_txt_str($str, $header, $parse_error, $i);
			if($data === false){
				$statistic['str_corrupted']++;
				continue;
			}
			
			//проверка fide_id
			if(!sizeof($data['rating'])){
				continue;
			}
			
			$data = $data['rating'][0];
			if(!isset($fide_to_ruchess_id[$data['id_fide']])){
				continue;
			}
			$statistic['str_filtered']++;

			$data['id_ruchess'] = $fide_to_ruchess_id[$data['id_fide']];
			unset($data['id_fide']);

			$rating_data_to_import[] = $data;
		}
		fclose($file_stream);
		$time_statistic['fide_txt_parsing_end'] = microtime(1);
		$time_statistic['fide_txt_parsing_time'] = $time_statistic['fide_txt_parsing_end'] - $time_statistic['fide_txt_parsing_start'];

		$parse_error->add('fide_txt_parsing_success', 'Парсинг завершен', array_merge([$txt_path], $time_statistic, $statistic));

		return ['player' => [], 'rating' => $rating_data_to_import];

	}

	/**
	 * преобразует строку данных из файла fide в массив с данными игрока и его рейтингами
	 * ошибки парсинга добавляются в $parse_error
	 * @param string $str - строка данных
	 * @param array $header - заголовок файла [{название заголовка} => {длина данных}, ...]
	 * @param WP_Error $parse_error - хранит ошибки парсинга (fide_txt_str_parse_error)
	 * @param int $str_number - номер обрабатываемой строки, нужен для указания ошибок
	 * @return false|array
	 * [
	 * 	'player' => [{массив строк, ключи - ключи из header кроме ключа 'rating'}],
	 * 	'rating' => [
	 * 		// 0 или 1 массив
	 * 		[
	 * 			'id_fide'	=> int,//рейтинг fide
	 * 			'rating'	=> int
	 * 		]
	 * 	]
	 * ]
	 */
	protected function parse_txt_str($str, $header, $parse_error, $str_number){

		if(!isset($header['id number'], $header['rating'])){
			$parse_error->add('fide_txt_str_parse_error', 'Заголовки \'id number\' и \'rating\' не найдены');
			return false;
		}
		
		$data = [];
		foreach($header as $head => $data_len){
			$data[$head] = mb_substr($str, 0, $data_len);
			if(mb_strlen($data[$head]) != $data_len){
				$parse_error->add('fide_txt_str_parse_error', 'Строка '.$str_number.': Имеет не верную длину');
				return false;
			}
			$data[$head] = trim($data[$head]);
			$str = mb_substr($str, $data_len);
		}
		
		
		$rating_fide = $data['rating'];
		$id_fide = absint($data['id number']);
		if($id_fide === 0){
			$parse_error->add('fide_txt_str_parse_error', 'Строка '.$str_number.': \'id number\' не задано');
			return false;
		}
		
		unset($data['rating']);
		$ret = [
			'player' => $data,
			'rating' => []
		];

		if($rating_fide !== ''){
			$ret['rating'][] = ['id_fide' => $id_fide, 'rating' => absint($rating_fide)];
		}

		return $ret;
	}
	
	/**
	 * @param array $players - массив из player'ов see Parser::parse_csv
	 * @see Parser::parse_csv
	 * @param WP_Error $import_error - хранит ошибки импорта (db_import_players_error, db_import_players_success)
	 * @param bool $update - будут ли обновляться уже добавленные игроки
	 * @return true
	 */
	protected function db_import_players($players, $import_error, $update = true){
		global $wpdb;
		$time_statistic = ['players_import_start' => microtime(1)];
		$statistic = [];
		$statistic['players_was'] = $wpdb->get_var('SELECT COUNT(*) FROM `'.$wpdb->prefix.'rad_chess_players`');
		
		$players_len = sizeof($players);
		for($i=0; $i<$players_len; $i += 100){
			$query = 'INSERT '.(!$update ? 'IGNORE ' : '').'INTO `'.$wpdb->prefix.'rad_chess_players` (`id_ruchess`, `id_fide`, `name`, `sex`, `country`, `birth_year`, `region_number`, `region_name`) VALUES ';
			for($x=$i; $x<$i+100 && $x<$players_len; $x++){
				$query .= $wpdb->prepare(
					'(%d, '.(is_null($players[$x]['id_fide']) ? 'NULL' : absint($players[$x]['id_fide'])).', %s, %d, %s, '.(is_null($players[$x]['birth_year']) ? 'NULL' : absint($players[$x]['birth_year'])).', %d, %s), ',
					$players[$x]['id_ruchess'],
					$players[$x]['name'],
					$players[$x]['sex'],
					$players[$x]['country'],
					$players[$x]['region_number'],
					$players[$x]['region_name']
				);
			}
			$query = mb_substr($query, 0, mb_strlen($query)-2);
			if($update){
				//MySQL  >= 8.0.20
				//$query .= ' AS new ON DUPLICATE KEY UPDATE `id_fide` = new.id_fide, `name` = new.name, `sex` = new.sex, `country` = new.country, `birth_year` = new.birth_year, `region_number` = new.region_number, `region_name` = new.region_name';
				$query .= ' ON DUPLICATE KEY UPDATE `id_fide` = VALUES(id_fide), `name` = VALUES(name), `sex` = VALUES(sex), `country` = VALUES(country), `birth_year` = VALUES(birth_year), `region_number` = VALUES(region_number), `region_name` = VALUES(region_name)';
			}

			if($wpdb->query($query) === false){
				$import_error->add('db_import_players_error', 'Не удалось добавить данные пользователей', [$query, $wpdb->last_error]);
			}
		}

		$time_statistic['players_import_end'] = microtime(1);
		$time_statistic['players_import_time'] = $time_statistic['players_import_end'] - $time_statistic['players_import_start'];

		$statistic['players_now'] = $wpdb->get_var('SELECT COUNT(*) FROM `'.$wpdb->prefix.'rad_chess_players`');
		$statistic['players_added'] = $statistic['players_now'] - $statistic['players_was'];

		$import_error->add('db_import_players_success', 'Вставка игроков завершена', array_merge($time_statistic, $statistic));

		return true;
	}

	/**
	 * @param array $ratings - массив из rating'ов see Parser::parse_csv
	 * @see Parser::parse_csv
	 * @param WP_Error $import_error - хранит ошибки импорта (db_import_ratings_error, db_import_ratings_success)
	 * @param string $site - 'fide' или 'ruchess'
	 * @param int $rating_type - 0 - standard, 1 - rapid, 2 - blitz
	 */
	protected function db_import_ratings($ratings, $import_error, $site, $rating_type=0){
		if($site !== 'fide' && $site !== 'ruchess')
			throw new \Exception('Undefined site');
		global $wpdb;
		$time_statistic = ['ratings_import_start' => microtime(1)];
		$statistic = [];
		$statistic['ratings_was'] = $wpdb->get_var('SELECT COUNT(*) FROM `'.$wpdb->prefix.'rad_chess_players_ratings`');
		
		$type_converter = [
			1 => 'rating_ru_s',
			2 => 'rating_fi_s',
			3 => 'rating_ru_r',
			4 => 'rating_fi_r',
			5 => 'rating_ru_b',
			6 => 'rating_fi_b',
		];
		
		$ratings_len = sizeof($ratings);
		for($i=0; $i<$ratings_len; $i++){
			$id = absint($ratings[$i]['id_ruchess']);
			$cur_rating_type = $rating_type*2 + ($site === 'fide' ? 2 : 1);
			$rating = absint($ratings[$i]['rating']);
			
			$query = '
				INSERT INTO '.$wpdb->prefix.'rad_chess_players_ratings (`id_ruchess`, `rating_type`, `rating`)
				SELECT '.$id.', '.$cur_rating_type.', '.$rating.' FROM dual
				WHERE '.$rating.' != IFNULL((
					SELECT `rating` FROM wp_rad_chess_players_ratings
					WHERE `id_ruchess` = '.$id.' AND `rating_type` = '.$cur_rating_type.'
					ORDER BY `update_date` DESC LIMIT 1
				), -1)';
			
			$insert_c = $wpdb->query($query);
			if($insert_c === false){
				$import_error->add('db_import_ratings_error', 'Не удалось добавить рейтинги', [$query, $wpdb->last_error]);
				continue;
			}
			
			if($insert_c){
				$insert_query = 'INSERT INTO '.$wpdb->prefix.'rad_chess_current_ratings (`id_ruchess`, `'.$type_converter[$cur_rating_type].'`) VALUES ('.$id.', '.$rating.')';
				//MySQL  >= 8.0.20
				//$insert_query .= ' AS new ON DUPLICATE KEY UPDATE `'.$type_converter[$cur_rating_type].'` = new.'.$type_converter[$cur_rating_type];
				$insert_query .= ' ON DUPLICATE KEY UPDATE `'.$type_converter[$cur_rating_type].'` = VALUES('.$type_converter[$cur_rating_type].')';

				if($wpdb->query($insert_query) === false){
					$import_error->add('db_import_ratings_error', 'Не удалось обновить текущий рейтинг', [$insert_query, $wpdb->last_error]);
				}
			}
		}
		

		$time_statistic['ratings_import_end'] = microtime(1);
		$time_statistic['ratings_import_time'] = $time_statistic['ratings_import_end'] - $time_statistic['ratings_import_start'];

		$statistic['ratings_now'] = $wpdb->get_var('SELECT COUNT(*) FROM `'.$wpdb->prefix.'rad_chess_players_ratings`');
		$statistic['ratings_added'] = $statistic['ratings_now'] - $statistic['ratings_was'];

		$import_error->add('db_import_ratings_success', 'Вставка рейтингов завершена', array_merge($time_statistic, $statistic));

		return true;
	}
}