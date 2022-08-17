<?php
namespace Radiofan\ChessParser;

use PHPExcel;
use PHPExcel_Style_Alignment;
use PHPExcel_Style_Border;
use PHPExcel_Style_Fill;
use PHPExcel_Style_NumberFormat;

require_once 'functions.php';
require_once 'log-class.php';
require_once 'install-uninstall-trait.php';
require_once 'parser-trait.php';
require_once 'admin-page-trait.php';
require_once 'view-trait.php';

/**
 * Class ChessParser
 * @package Radiofan\ChessParser
 * @property-read string $plugin_path - путь к главному файлу
 * @property-read string $plugin_dir - путь к папке плагина со слешем на конце
 * @property-read string $plugin_dir_name - название папки плагина
 * @property-read string $plugin_url - url к папке плагина со слешем на конце
 * @property-read array $plugin_data - данные плагина, то что возвращает get_plugin_data
 * @see get_plugin_data()
 */
class ChessParser{
	
	use InstallUninstall;
	use Parser;
	use AdminPage;
	use View;

	/**
	 * @var bool будет ли проводиться проверка etag перед скачиванием файлов
	 * @see https://developer.mozilla.org/ru/docs/Web/HTTP/Headers/ETag
	 */
	const CHECK_ETAG = true;
	const RUCHESS_HREF = 'https://ratings.ruchess.ru/people/';
	const FIDE_HREF = 'https://ratings.fide.com/profile/';

	/** @var string $plugin_path - путь к главному файлу */
	protected $plugin_path;
	/** @var string $plugin_dir - путь к папке плагина со слешем на конце */
	protected $plugin_dir;
	/** @var string $plugin_dir_name - название папки плагина */
	protected $plugin_dir_name;
	/** @var string $plugin_url - url к папке плагина со слешем на конце */
	protected $plugin_url;
	/**
	 * @var array $plugin_data - данные плагина, перед использованием вызвать ChessParser::init_plugin_data()
	 * @see ChessParser::init_plugin_data()
	 */
	protected $plugin_data = [];
	
	const GAME_TYPE = [0 => 'standard', 1 => 'rapid', 2 => 'blitz'];

	public function __construct(string $plugin_path = __FILE__){

		$this->plugin_path = $plugin_path;
		$this->plugin_dir = plugin_dir_path($this->plugin_path);
		$this->plugin_dir_name = basename($this->plugin_dir);
		$this->plugin_url = plugin_dir_url($this->plugin_path);
		/*
		$this->plugin_data = get_file_data(
			$this->plugin_path,
			[
				'Name'        => 'Plugin Name',
				'PluginURI'   => 'Plugin URI',
				'Version'     => 'Version',
				'Description' => 'Description',
				'Author'      => 'Author',
				'AuthorURI'   => 'Author URI',
				'TextDomain'  => 'Text Domain',
				'DomainPath'  => 'Domain Path',
				'Network'     => 'Network',
				'RequiresWP'  => 'Requires at least',
				'RequiresPHP' => 'Requires PHP',
				'UpdateURI'   => 'Update URI',
			],
			'plugin'
		);
		*/

		register_activation_hook($this->plugin_path, [$this, 'activate']);
		register_deactivation_hook($this->plugin_path, [$this, 'deactivate']);
		
		add_action('radiofan_chess_parser_parse', [$this, 'parse_data'], 10, 3);//cron radiofan_chess_parser_parse
		add_action('radiofan_chess_parser_create_month_ratings_file', [$this, 'create_month_ratings_file'], 10);//cron radiofan_chess_parser_create_month_ratings_file
		
		//add_action('current_screen', [$this, 'init_screen_admin_side']);
		add_action('admin_menu', [$this, 'add_admin_menu_item']);
		add_action('admin_init', [$this, 'settings_init']);

		//Пользовательские настройки пагинации в таблице игроков
		add_filter('set-screen-option', function($status, $option, $value){
			return ($option == 'radiofan_chess_parser__players_per_page') ? absint($value) : $status;
		}, 10, 3 );

		add_shortcode('chess_top_scoreboard', [$this, 'view_top_scoreboard']);
		add_shortcode('chess_players_page', [$this, 'view_players_page_table']);
		add_shortcode('chess_month_ratings_files', [$this, 'view_month_ratings_files']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
	}

	private function init_plugin_data(){
		if(!$this->plugin_data){
			$this->plugin_data = get_file_data(
				$this->plugin_path,
				[
					'Name'        => 'Plugin Name',
					'PluginURI'   => 'Plugin URI',
					'Version'     => 'Version',
					'Description' => 'Description',
					'Author'      => 'Author',
					'AuthorURI'   => 'Author URI',
					'TextDomain'  => 'Text Domain',
					'DomainPath'  => 'Domain Path',
					'Network'     => 'Network',
					'RequiresWP'  => 'Requires at least',
					'RequiresPHP' => 'Requires PHP',
					'UpdateURI'   => 'Update URI',
				],
				'plugin'
			);
		}
	}

	/**
	 * Скачивает файл с игроками и рейтингами с сайта ratings.ruchess.ru указанного типа игры, если etag отличается от предыдущего
	 * Извлекает данные из файла
	 * Если хэш сериализованного массива данных игроков отличается от хэша предыдущего парсинга, происходит импортирование игроков
	 * Если хэш сериализованного массива рейтингов отличается от хэша предыдущего парсинга, происходит импортирование рейтингов
	 * Результаты парсинга записываются в логи
	 * @param string $type - тип из GAME_TYPE
	 * @param string $site - 'fide' или 'ruchess'
	 * @param int $type_id - тип из GAME_TYPE
	 */
	public function parse_data($type, $site, $type_id){
		if($site !== 'fide' && $site !== 'ruchess')
			throw new \Exception('Undefined site');
		
		//автоматическая очистка логов
		if(get_option('radiofan_chess_parser__auto_clear_log',true)){
			$this->delete_old_logs();
		}
		
		//скачивание файла
		$etag = (self::CHECK_ETAG) ? get_option('radiofan_chess_parser__etag_'.$site.'_'.$type_id, '') : null;
		$file_url = $site === 'ruchess' ? 'https://ratings.ruchess.ru/api/smanager_'.$type.'.csv.zip' : 'http://ratings.fide.com/download/'.$type.'_rating_list.zip';
		$wp_error = $this->download_file($file_url, $this->plugin_dir.'files/download/'.$site.'_'.$type.'.zip', $etag);
		if(!$wp_error->get_error_messages('download_success')){
			rad_log::log_wp_error($wp_error);
			return;
		}
		if($wp_error->get_error_messages('new_etag')){
			$etag = $wp_error->get_error_data('new_etag');
			$wp_error->remove('new_etag');
			if(self::CHECK_ETAG){
				update_option('radiofan_chess_parser__etag_'.$site.'_'.$type_id, $etag, false);
			}
		}
		rad_log::log_wp_error($wp_error);
		
		//распаковка файла
		$wp_error = $this->unzip_file($this->plugin_dir.'files/download/'.$site.'_'.$type.'.zip', $this->plugin_dir.'files/unzip/'.$site.'_'.$type.'/');
		rad_log::log_wp_error($wp_error);
		if(!$wp_error->get_error_messages('unzip_file_success')){
			return;
		}
		$data_file_path = list_files($this->plugin_dir.'files/unzip/'.$site.'_'.$type.'/', 1);
		if(!$data_file_path){
			rad_log::log('Не найдены распакованные файлы', 'error', $this->plugin_dir.'files/download/'.$site.'_'.$type.'.zip -> '.$this->plugin_dir.'files/unzip/'.$site.'_'.$type.'/');
			return;
		}
		$data_file_path = $data_file_path[0];
		wp_delete_file($this->plugin_dir.'files/download/'.$site.'_'.$type.'.zip');
		
		
		$data = null;
		if($site == 'ruchess'){

			//парсинг файла
			$wp_error = new \WP_Error();
			$data = $this->parse_csv($data_file_path, $wp_error);
			rad_log::log_wp_error($wp_error);
			if(!$wp_error->get_error_messages('csv_parsing_success')){
				return;
			}

			//импорт игроков
			$wp_error = new \WP_Error();
			$data_players_hash = sha1(serialize($data['player']));
			$data_players_hash_old = get_option('radiofan_chess_parser__players_hash_'.$site.'_'.$type_id, false);
			if($data_players_hash_old !== $data_players_hash){
				$this->db_import_players($data['player'], $wp_error, get_option('radiofan_chess_parser__players_update', false));
				rad_log::log_wp_error($wp_error);
				if(!$wp_error->get_error_messages('db_import_players_error')){
					update_option('radiofan_chess_parser__players_hash_'.$site.'_'.$type_id, $data_players_hash, false);
				}
			}else{
				rad_log::log('Игроки '.$site.' '.$type.' не требуют обновления', 'info', 'radiofan_chess_parser__players_hash_'.$site.'_'.$type_id.' = '.$data_players_hash);
			}
			unset($data['player']);
			
		}else{
			//$site == 'fide'
			//парсинг файла
			$wp_error = new \WP_Error();
			$data = $this->parse_fide_txt($data_file_path, $wp_error);
			rad_log::log_wp_error($wp_error);
			if(!$wp_error->get_error_messages('fide_txt_parsing_success')){
				return;
			}
			
		}
		
		//импорт рейтингов
		$wp_error = new \WP_Error();
		$data_ratings_hash = sha1(serialize($data['rating']));
		$data_ratings_hash_old = get_option('radiofan_chess_parser__ratings_hash_'.$site.'_'.$type_id, false);
		if($data_ratings_hash_old !== $data_ratings_hash){
			$this->db_import_ratings($data['rating'], $wp_error, $site, $type_id);
			rad_log::log_wp_error($wp_error);
			if(!$wp_error->get_error_messages('db_import_ratings_error')){
				update_option('radiofan_chess_parser__ratings_hash_'.$site.'_'.$type_id, $data_ratings_hash, false);
			}
		}else{
			rad_log::log('Рейтинги '.$site.' '.$type.' не требуют обновления', 'info', 'radiofan_chess_parser__ratings_hash_'.$site.'_'.$type_id.' = '.$data_ratings_hash);
		}
		unset($data['rating']);
		
	}

	/**
	 * Пробует сгенерировать excel файл методом create_excel_ratings_with_dynamic за последний полный месяц, если он отсутсвует
	 * Файлы хранятся в /ratings/
	 * Наименование Obschiy_rating-list_igrokov_Altayskogo_kraya_na_МЕСЯЦ2ЦИФРЫ_ГОД4ЦИФРЫ.xlsx
	 */
	public function create_month_ratings_file(){
		$prev_month_dates = get_start_end_prev_month_days();
		$file_name = 'Obschiy_rating-list_igrokov_Altayskogo_kraya_na_'.$prev_month_dates['end_day']->format('m_Y').'.xlsx';
		if(file_exists($this->plugin_dir.'ratings/'.$file_name)){
			return;
		}

		$excel = $this->create_excel_ratings_with_dynamic(clone $prev_month_dates['first_day'], clone $prev_month_dates['end_day']);
		$excelWriter = new \PHPExcel_Writer_Excel2007($excel);
		try{
			$excelWriter->save($this->plugin_dir.'ratings/'.$file_name);
		}catch(\Exception $ex){
			rad_log::log('save_excel_error: Не удалось сохранить файл рейтингов за последний полный месяц ('.$prev_month_dates['end_day']->format('F Y').')', 'error', $ex);
		}
		rad_log::log('save_excel_success: Создан файл рейтингов за последний полный месяц ('.$prev_month_dates['end_day']->format('F Y').')', 'success', null);
	}

	/**
	 * @param string $sql_time_interval - возраст, старше которого записи лога будут удалены; параметр не проверяется и используется на прмую в запросе вида `log_time` + INTERVAL '.$sql_time_interval.'
	 */
	protected function delete_old_logs($sql_time_interval = '1 MONTH'){
		global $wpdb;
		$wpdb->query('DELETE FROM '.$wpdb->prefix.'rad_chess_logs WHERE `log_time` + INTERVAL '.$sql_time_interval.' <= NOW()');
	}


	/**
	 * Создает Excel документ, заполняет его игроками и их рейтингами и динамикой на основе метода get_players_with_rating_dynamics
	 * Подключает 'libs/PHPExcel/PHPExcel.php', если класс PHPExcel не существует
	 * @see ChessParser::get_players_with_rating_dynamics()
	 * @param \DateTime|null $date_start - параметр для get_players_with_rating_dynamics()
	 * @param \DateTime|null $date_end - параметр для get_players_with_rating_dynamics()
	 * @throws \Exception 'date_start more or equal date_end'
	 * @return PHPExcel
	 */
	protected function create_excel_ratings_with_dynamic($date_start = null, $date_end = null){

		global $wpdb;
		$ratings = $this->get_players_with_rating_dynamics(clone $date_start, clone $date_end);
		$players = $wpdb->get_results('SELECT `id_ruchess`, `id_fide`, `name`, `sex`, `birth_year` FROM `'.$wpdb->prefix.'rad_chess_players` ORDER BY `id_ruchess`',ARRAY_A );

		if(!class_exists('PHPExcel')){
			require_once 'libs/PHPExcel/PHPExcel.php';
		}


		$excel = new PHPExcel();

		//устанавливаем дефолтные стили
		$excel->getDefaultStyle()
			  ->getFont()
			  ->setName('Times New Roman')
			  ->setSize(11)
			  ->getColor()->applyFromArray(array('rgb' => '000000'));
		$excel->getDefaultStyle()->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);

		$title = 'Общий рейтинг-лист игроков Алтайского края на период с '.$date_start->format('d.m.Y').' по '.$date_end->format('d.m.Y');

		$this->init_plugin_data();
		$excel->getProperties()
			  ->setTitle($title)
			  ->setCreator($this->plugin_data['Name'].' V'.$this->plugin_data['Version'])
			  ->setCompany(site_url().' | '.get_bloginfo('name'))
			  ->setDescription('Файл сгенерирован на сайте '.get_bloginfo('name'). ' ('.site_url().')');

		//стили
		$excel_styles = [
			'header' => [
				'font' => [
					'bold' => true,
					'size' => 14,
					'color' => ['rgb' => '254061']
				],
				'numberformat' => [
					'code' => PHPExcel_Style_NumberFormat::FORMAT_TEXT
				]
			],
			'table_header' => [
				'font' => [
					'bold' => true,
					'size' => 10.5,
					'color' => ['rgb' => 'FFFFFF']
				],
				'numberformat' => [
					'code' => PHPExcel_Style_NumberFormat::FORMAT_TEXT
				],
				'alignment' => [
					'wrap' => true,
					'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
					'vertical' => PHPExcel_Style_Alignment::VERTICAL_TOP
				],
				'fill' => [
					'type' => PHPExcel_Style_Fill::FILL_SOLID,
					'color' => ['rgb' => '376091']
				],
				'borders' => [
					'allborders' => [
						'style' => PHPExcel_Style_Border::BORDER_THIN,
						'color' => ['rgb' => 'FFFFFF']
					]
				],
			],
			'link' => [
				'font' => [
					'color' => ['rgb' => '0000FF'],
					'underline' => \PHPExcel_Style_Font::UNDERLINE_SINGLE,
				],
			],
			'text_horizontal_center' => [
				'alignment' => [
					'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER
				]
			],
			'table_frame' => [
				'borders' => [
					'outline' => [
						'style' => PHPExcel_Style_Border::BORDER_THIN,
						'color' => ['rgb' => '000000']
					]
				]
			],
		];

		//заполняем общую страницу
		$excel->setActiveSheetIndex(0);
		$sheet = $excel->getActiveSheet();
		$sheet->setTitle('Общий');
		$sheet->freezePane('A6');//делаем плавающую шапку

		//заголовок таблицы
		$sheet->getRowDimension(1)->setRowHeight(18.75);
		$sheet->getStyle('A1')->applyFromArray($excel_styles['header']);
		$sheet->setCellValue('A1', $title);

		$sheet->getRowDimension(2)->setRowHeight(28.5);

		//шапка таблицы
		$sheet->getStyle('A3:Q5')->applyFromArray($excel_styles['table_header']);

		$sheet->mergeCells('A3:A5');
		$sheet->setCellValue('A3', 'ФШР ID');
		//$sheet->getStyle('A')->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);
		$sheet->mergeCells('B3:B5');
		$sheet->setCellValue('B3', 'FIDE ID');
		//$sheet->getStyle('B')->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);
		$sheet->getColumnDimension('C')->setAutoSize(true);
		$sheet->mergeCells('C3:C5');
		$sheet->setCellValue('C3', 'ФИО');
		$sheet->getColumnDimension('D')->setWidth(4.5);
		$sheet->mergeCells('D3:D5');
		$sheet->setCellValue('D3', 'Пол');
		$sheet->getColumnDimension('E')->setWidth(5);
		$sheet->mergeCells('E3:E5');
		$sheet->setCellValue('E3', 'г.р.');
		//$sheet->getStyle('E')->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);
		$sheet->mergeCells('F3:Q3');
		$sheet->setCellValue('F3', 'Рейтинг');

		$sheet->mergeCells('F4:I4');
		$sheet->setCellValue('F4', 'Классика');
		$sheet->mergeCells('J4:M4');
		$sheet->setCellValue('J4', 'Рапид');
		$sheet->mergeCells('N4:Q4');
		$sheet->setCellValue('N4', 'Блиц');

		$sheet->setCellValue('F5', 'ФШР');
		$sheet->getColumnDimension('G')->setWidth(5.5);
		$sheet->setCellValue('G5', '↓↑');
		$sheet->setCellValue('H5', 'FIDE');
		$sheet->getColumnDimension('I')->setWidth(5.5);
		$sheet->setCellValue('I5', '↓↑');
		$sheet->setCellValue('J5', 'ФШР');
		$sheet->getColumnDimension('K')->setWidth(5.5);
		$sheet->setCellValue('K5', '↓↑');
		$sheet->setCellValue('L5', 'FIDE');
		$sheet->getColumnDimension('M')->setWidth(5.5);
		$sheet->setCellValue('M5', '↓↑');
		$sheet->setCellValue('N5', 'ФШР');
		$sheet->getColumnDimension('O')->setWidth(5.5);
		$sheet->setCellValue('O5', '↓↑');
		$sheet->setCellValue('P5', 'FIDE');
		$sheet->getColumnDimension('Q')->setWidth(5.5);
		$sheet->setCellValue('Q5', '↓↑');

		//заполняем игроков
		$shift = 6;//строка с которой начинаем заполнять данные
		$len = sizeof($players);
		$row = $shift+$len-1;
		$sheet->getStyleByColumnAndRow(0, $shift, 4, $row)->applyFromArray($excel_styles['table_frame']);
		$sheet->getStyleByColumnAndRow(3, $shift, 4, $row)->applyFromArray($excel_styles['text_horizontal_center']);
		$tmp = array_merge($excel_styles['table_frame'], $excel_styles['text_horizontal_center']);
		for($i=0; $i<6; $i++){
			$sheet->getStyleByColumnAndRow(5+$i*2, $shift, 6+$i*2, $row)->applyFromArray($tmp);
		}

		//`id_ruchess`, `id_fide`, `name`, `sex`, `birth_year`
		for($i=0; $i<$len; $i++){
			$row = $shift + $i;
			$id = (int)$players[$i]['id_ruchess'];
			$sheet->setCellValueByColumnAndRow(0, $row, $id);
			$sheet->getCellByColumnAndRow(0, $row)->getHyperlink()->setUrl(ChessParser::RUCHESS_HREF.$id);
			$sheet->getStyleByColumnAndRow(0, $row)->applyFromArray($excel_styles['link']);
			if(!empty($players[$i]['id_fide'])){
				$sheet->setCellValueByColumnAndRow(1, $row, $players[$i]['id_fide']);
				$sheet->getCellByColumnAndRow(1, $row)->getHyperlink()->setUrl(ChessParser::FIDE_HREF.$players[$i]['id_fide']);
				$sheet->getStyleByColumnAndRow(1, $row)->applyFromArray($excel_styles['link']);
			}
			$sheet->setCellValueByColumnAndRow(2, $row, $players[$i]['name']);
			$sheet->setCellValueByColumnAndRow(3, $row, $players[$i]['sex'] ? 'Ж' : 'М');
			$sheet->setCellValueByColumnAndRow(4, $row, $players[$i]['birth_year']);

			if(!isset($ratings[$id]))
				continue;

			for($r_t=1; $r_t <= 6; $r_t++){
				if(isset($ratings[$id][$r_t])){
					if(!is_null($ratings[$id][$r_t]['rating_end'])){
						$sheet->setCellValueByColumnAndRow(5+($r_t-1)*2, $row, $ratings[$id][$r_t]['rating_end']['rating']);
						$difference = $ratings[$id][$r_t]['rating_end']['rating']-$ratings[$id][$r_t]['rating_start']['rating'];
						if($difference > 0){
							$sheet->setCellValueExplicitByColumnAndRow(4+$r_t*2, $row, '+'.$difference);
							$sheet->getStyleByColumnAndRow(4+$r_t*2, $row)->getFont()->getColor()->setRGB('008000');
						}else if($difference < 0){
							$sheet->setCellValueExplicitByColumnAndRow(4+$r_t*2, $row, $difference);
							$sheet->getStyleByColumnAndRow(4+$r_t*2, $row)->getFont()->getColor()->setRGB('FF0000');
						}

					}
				}
			}
		}

		return $excel;
	}


	/**
	 * Возвращает пару рейтингов для всех игроков и всех типов рейтинга - стартовый и конечный
	 * Стартовый рейтинг устанавливается последним рейтингом до $date_start
	 * Конечный рейтинг устанавливается последним рейтингом до $date_end
	 * @param \DateTime|null $date_start - если null, то стартовый рейтинг - первый до $date_end; Объект изменяется в функции!!!
	 * @param \DateTime|null $date_end - если null, конечный рейтинг - последний; Объект изменяется в функции!!!
	 * @throws \Exception 'date_start more or equal date_end'
	 * @return array
	 * [
	 * 	int (id_ruchess) => [
	 * 		string (raiting_type, [1-6]) => [
	 * 			'rating_start' => null | [
	 * 				'rating' => int,
	 * 				'update_time' => string (timestamp)
	 * 			],
	 * 			'rating_end' => null | [
	 * 				'rating' => int,
	 * 				'update_time' => string (timestamp)
	 * 			]
	 * 		],
	 * 		...
	 * 	],
	 * 	...
	 * ]
	 */
	protected function get_players_with_rating_dynamics($date_start = null, $date_end = null){
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

			unset($ret[$i]);
		}

		return $ratings;
	}
	
	public function __get($key){
		switch($key){
			case 'plugin_path':
			case 'plugin_dir':
			case 'plugin_dir_name':
			case 'plugin_url':
				return $this->$key;
			case 'plugin_data':
				$this->init_plugin_data();
				return $this->plugin_data;
			default:
				throw new \Exception('Undefined property '.$key);
		}
	}
}