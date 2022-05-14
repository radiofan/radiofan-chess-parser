<?php
namespace Radiofan\ChessParser;

use WP_Error;

trait Parser{

	/**
	 * @param string $url - адрес скачиваемого файла
	 * @param string $file_to - абсолютный путь к месту расположения скачиваемого файла
	 * @param null|string $etag - @see https://developer.mozilla.org/ru/docs/Web/HTTP/Headers/ETag
	 * @return WP_Error
	 */
	public function download_file($url, $file_to, $etag = null){

		//проверка etag
		$new_etag = null;
		if(!is_null($etag)){
			$etag = preg_replace('#[^0-9a-z_\\-"\']#iu', '', (string)$etag);

			$ret = wp_remote_head($url, ['redirection' => 0, 'user-agent' => 'RADIOFAN Chess Parser', 'sslverify' => 0, 'headers' => ['If-None-Match' => $etag]]);

			if((int) $ret['response']['code'] === 304){
				return new WP_Error('download_not_need_update', 'Файл не требует обновления');
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

		if((int) $ret['response']['code'] !== 200){
			return new WP_Error('download_undefined_error', 'Не удалось скачать файл', $ret);
		}

		$ret = new WP_Error('download_success', 'Файл скачан', $ret);
		if($new_etag){
			$ret->add('new_etag', '', $new_etag);
		}

		return $ret;
	}


	/**
	 * @param string $zip - абсолютный путь к архиву
	 * @param string $dir_to - аюсолютный путь до папки куда распакуются файлы
	 * @return true|WP_Error
	 */
	protected function unzip_file($zip, $dir_to){
		if(!file_exists($zip) || !is_file($zip))
			return new WP_Error('archive_not_exist', 'Архив не найден!');

		//инициализация функции распаковки
		if(!function_exists('unzip_file')){
			require_once(ABSPATH.'wp-admin/includes/file.php');
		}
		global $wp_filesystem;
		if(empty($wp_filesystem)){
			WP_Filesystem();
		}

		$dir_to = str_replace(ABSPATH, $wp_filesystem->abspath(), $dir_to);

		return unzip_file($zip, $dir_to);
	}

	/**
	 * @param string $csv - абсолютный путь к csv файлу
	 * @return WP_Error
	 */
	protected function parse_csv($csv){
		if(!file_exists($csv) || !is_file($csv))
			return new WP_Error('csv_not_exist', 'CSV файл не найден!');

		$csv_stream = fopen($csv, 'r');
		if($csv_stream === false)
			return new WP_Error('csv_not_open', 'CSV файл не открыт!');

		//проверка заголовка
		$str = (string)fgets($csv_stream);
		$data = str_getcsv($str, ';');
		if($data !== ['ID_No','Name','Sex','Fed','Clubnumber','ClubName','Birthday','Rtg_Nat','Fide_No','Rtg_Int']){
			fclose($csv_stream);
			return new WP_Error('csv_bad_head_format', 'CSV файл имеет не правильный заголовок');
		}

		$parse_error = new WP_Error();

		for($i=2;;$i++){
			$str = fgets($csv_stream);
			if($str === false)
				break;

			$data = str_getcsv($str, ';');
			$data = $this->parse_csv_str($data, $parse_error, $i);
			//todo
		}
		fclose($csv_stream);
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
	 * 		'sex'			=> bool
	 * 		'country'		=> string
	 * 		'birth_year'	=> null|int
	 * 		'region_number'	=> int
	 * 		'region_name'	=> string
	 * 	],
	 * 	'rating' => [
	 * 		'ruchess'		=> null|int
	 * 		'fide'			=> null|int
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
			'rating' => [
				'ruchess'		=> $data[7],//Rtg_Nat
				'fide'			=> $data[9] //Rtg_Int
			]
		];

		$ret['player']['id_ruchess'] = absint($ret['player']['id_ruchess']);
		if($ret['player']['id_ruchess'] === 0){
			$parse_error->add('csv_str_parse_error', 'Строка '.$str_number.': id_ruchess (ID_No) не задано');
			return false;
		}

		$ret['player']['id_fide'] = absint($ret['player']['id_fide']);
		if($ret['player']['id_fide'] === 0){
			$parse_error->add('csv_str_parse_warning', 'Строка '.$str_number.': id_fide (Fide_No) не задано');
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

		if((string)$ret['rating']['ruchess'] === ''){
			$ret['rating']['ruchess'] = null;
		}else{
			$ret['rating']['ruchess'] = absint($ret['rating']['ruchess']);
		}

		if((string)$ret['rating']['fide'] === ''){
			$ret['rating']['fide'] = null;
		}else{
			$ret['rating']['fide'] = absint($ret['rating']['fide']);
		}

		return $ret;
	}
}