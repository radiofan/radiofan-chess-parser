<?php

class RadiofanChessParser{
	
	/**
	 * @var bool будет ли проводиться проверка etag перед скачиванием файлов
	 * @see https://developer.mozilla.org/ru/docs/Web/HTTP/Headers/ETag
	 */
	const CHECK_ETAG = true;
	
	/** @var string $plugin_path - путь к главному файлу */
	protected $plugin_path;
	/** @var string $plugin_dir - путь к папке плагина */
	protected $plugin_dir;
	/** @var string $plugin_dir_name - название папки плагина */
	protected $plugin_dir_name;
	/** @var string $plugin_url - url к папке плагина */
	protected $plugin_url;

	public function __construct(string $plugin_path = __FILE__){
		
		$this->plugin_path = $plugin_path;
		$this->plugin_dir = plugin_dir_path($this->plugin_path);
		$this->plugin_dir_name = basename($this->plugin_dir);
		$this->plugin_url = plugin_dir_url($this->plugin_path);
		
		
		register_activation_hook($this->plugin_path, [$this, 'activate']);
		register_deactivation_hook($this->plugin_path, [$this, 'deactivate']);

		add_action('current_screen', [$this, 'test_download']);
	}

	/**
	 * @param WP_Screen $screen
	 */
	public function test_download($screen){
		error_log('test_download');//todo delme
		
		/*
		 * empty_memory_peak(1): 2 МБ (2097152), empty_memory_peak(0): 40 МБ (41225784)
		 * unzip_memory_peak(1): 34 МБ (35651584), unzip_memory_peak(0): 71 МБ (74926456)
		 * wordpress_download_memory_peak(1): 42 МБ (44040192), wordpress_download_memory_peak(0): 40 МБ (41676872)
		 * download_memory_peak(1): 6 МБ (6291456), download_memory_peak(0): 39 МБ (41265256)
		 */
		
		//$ret = download_url('https://ratings.ruchess.ru/api/smanager_standard.csv.zip');
		//$this->unzip_file($this->plugin_dir.'smanager_standard.zip', 'files/unzip/');
		$ret = $this->download_file('https://ratings.ruchess.ru/api/smanager_standard.csv.zip', $this->plugin_dir.'files/file.zip', '"627f456a-5c2ee8"');
		error_log('download_memory_peak(1): '.size_format(memory_get_peak_usage(1)).' ('.memory_get_peak_usage(1).'), download_memory_peak(0): '.size_format(memory_get_peak_usage()).' ('.memory_get_peak_usage().')');
	}

	public function download_file($url, $file_to, $etag = null){
		
		//проверка etag
		$new_etag = null;
		if(!is_null($etag)){
			$etag = preg_replace('#[^0-9a-z_\\-"\']#iu', '', (string)$etag);
			$option = [
				CURLOPT_URL 			=> $url,
				CURLOPT_RETURNTRANSFER	=> 1,	//вернем тело ответа в строку
				CURLOPT_NOBODY			=> 1,	//не загружать тело страницы
				CURLOPT_HEADER			=> 1,	//заголовки респонса
				CURLINFO_HEADER_OUT		=> 1,	//поместить в curl_getinfo данные запроса
				CURLOPT_CONNECTTIMEOUT	=> 30,	//таймаут соединения
				CURLOPT_TIMEOUT			=> 30,	//таймаут ответа
				CURLOPT_FAILONERROR		=> 0,	//подробный отчет о неудаче (не нужен)
				CURLOPT_SSL_VERIFYPEER	=> 0,	//Не проверяем ССЛ
				CURLOPT_HTTPHEADER		=> ['If-None-Match: '.$etag]
			];

			$ch = curl_init();
			curl_setopt_array($ch, $option);
			$output = array('content' => curl_exec($ch), 'info' => curl_getinfo($ch), 'status' => curl_errno($ch), 'status_text' => curl_error($ch));
			curl_close($ch);
			
			if((int) $output['info']['http_code'] === 304){
				return new WP_Error('download_not_need_update', 'Файл не требует обновления');
			}
			if((int) $output['info']['http_code'] !== 200){
				return new WP_Error('download_undefined_error', 'Не удалось получить данные о файле', $output);
			}
			
			$matches = [];
			preg_match('#etag: ([0-9a-z_\\-"]+)#iu', $output['content'], $matches);
			if(isset($matches[1]))
				$new_etag = $matches[1];
		}
		
		//скачивание файла
		$file_to_desc = fopen($file_to, 'w');
		if(!$file_to_desc)
			return new WP_Error('download_desc_not_open', 'Не удалось открыть дескриптор для скачиваемого файла');
		
		$option = [
			CURLOPT_URL => $url,
			CURLOPT_NOBODY			=> 0,	//не загружать тело страницы
			CURLOPT_HEADER			=> 0,	//заголовки респонса
			CURLOPT_CONNECTTIMEOUT	=> 30,	//таймаут соединения
			CURLOPT_TIMEOUT			=> 300,	//таймаут ответа
			CURLOPT_FAILONERROR		=> 0,	//подробный отчет о не удаче (не нужен)
			CURLOPT_FILE 			=> $file_to_desc,//дескриптор файла для помещения тела ответа
			CURLOPT_SSL_VERIFYPEER	=> 0,	//Не проверяем ССЛ
		];

		$ch = curl_init();
		curl_setopt_array($ch, $option);
		curl_exec($ch);
		fclose($file_to_desc);

		$output = array('info' => curl_getinfo($ch), 'status' => curl_errno($ch), 'status_text' => curl_error($ch));
		curl_close($ch);

		if((int) $output['info']['http_code'] !== 200){
			return new WP_Error('download_undefined_error', 'Не удалось скачать файл', $output);
		}
		
		$ret = new WP_Error('download_success', 'Файл скачан', $output);
		if(!is_null($new_etag)){
			$ret->add('new_etag', '', $new_etag);
		}
		
		return $ret;
	}
	

	/**
	 * @param string $zip - абсолютный путь к архиву
	 * @param string $dir_to - папка внутри плагина куда распакуются файлы без начального слэша
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

		$plugin_path = str_replace(ABSPATH, $wp_filesystem->abspath(), $this->plugin_dir);
		$plugin_path .= $dir_to;
		
		return unzip_file($zip, $plugin_path);
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
	
	public function activate(){
		if(!current_user_can('activate_plugins'))
			return;
		$plugin = $_REQUEST['plugin'] ?? '';
		check_admin_referer('activate-plugin_'.$plugin);
		
		//создаем таблицы
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;
		
		//rad_chess_players
		$sql_rad_chess_players =
			'CREATE TABLE '.$wpdb->prefix.'rad_chess_players (
				id_ruchess    bigint(11) unsigned NOT NULL,
				id_fide       bigint(11) unsigned,
				name          text                NOT NULL,
				sex           bool                NOT NULL COMMENT \'0 - м, 1 - ж\',
				country       tinytext            NOT NULL,
				birth_year    year,
				region_number smallint   unsigned NOT NULL default \'0\',
				region_name   text                NOT NULL default \'\',
				PRIMARY KEY  (id_ruchess)
			) DEFAULT CHARACTER SET '.$wpdb->charset.' COLLATE '.$wpdb->collate.';';
		
		//rad_chess_players_ratings
		$sql_rad_chess_players_ratings =
			'CREATE TABLE '.$wpdb->prefix.'rad_chess_players_ratings (
				id_ruchess    bigint(11) unsigned NOT NULL,
				rating_type   tinyint    unsigned NOT NULL default \'0\' COMMENT \'1 - ruchess standard, 2 - fide standard, 3 - ruchess rapid, 4 - fide rapid, 5 - ruchess blitz, 6 - fide blitz\',
				rating        int                          default NULL,
				update_date   date                NOT NULL,
				KEY key_ratings_id_ruchess (id_ruchess)
			) DEFAULT CHARACTER SET '.$wpdb->charset.' COLLATE '.$wpdb->collate.';';

		dbDelta([$sql_rad_chess_players, $sql_rad_chess_players_ratings]);
		
		error_log('activate');//todo delme
	}

	public function deactivate(){
		if(!current_user_can('activate_plugins'))
			return;
		$plugin = $_REQUEST['plugin'] ?? '';
		check_admin_referer('deactivate-plugin_'.$plugin);

		error_log('deactivate');//todo
	}

	public static function uninstall(){
		if(!current_user_can('activate_plugins'))
			return;
		
		if(!defined('WP_UNINSTALL_PLUGIN'))
			return;

		global $wpdb;
		$wpdb->query('DROP TABLE '.$wpdb->prefix.'rad_chess_players_ratings, '.$wpdb->prefix.'rad_chess_players');
	}
}