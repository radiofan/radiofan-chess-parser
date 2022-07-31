<?php
namespace Radiofan\ChessParser;

use PHPExcel;
use PHPExcel_Style_Alignment;
use PHPExcel_Style_Border;
use PHPExcel_Style_Fill;
use PHPExcel_Style_NumberFormat;

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
 * Создает Excel документ, заполняет его игроками и их рейтингами и динамикой на основе функции get_players_with_rating_dynamics
 * Подключает 'libs/PHPExcel/PHPExcel.php', если класс PHPExcel не существует
 * @see get_players_with_rating_dynamics()
 * @param \DateTime|null $date_start - параметр для get_players_with_rating_dynamics()
 * @param \DateTime|null $date_end - параметр для get_players_with_rating_dynamics()
 * @throws \Exception 'date_start more or equal date_end'
 * @return PHPExcel
 */
function create_excel_ratings_with_dynamic($date_start = null, $date_end = null){
	
	global $wpdb;
	$ratings = get_players_with_rating_dynamics(clone $date_start, clone $date_end);
	$players = $wpdb->get_results('SELECT `id_ruchess`, `id_fide`, `name`, `sex`, `birth_year` FROM `'.$wpdb->prefix.'rad_chess_players` ORDER BY `id_ruchess`',ARRAY_A );
	
	if(!class_exists('PHPExcel')){
		require_once 'libs/PHPExcel/PHPExcel.php';
	}
	
	
	$excel = new PHPExcel();
	
	//устанавливаем дефолтные стили
	$excel->getDefaultStyle()->getFont()->setName('Times New Roman');
	$excel->getDefaultStyle()->getFont()->setSize(11);
	$excel->getDefaultStyle()->getFont()->getColor()->applyFromArray(array('rgb' => '000000'));
	$excel->getDefaultStyle()->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
	
	$title = 'Общий рейтинг-лист игроков Алтайского края на период с '.$date_start->format('d.m.Y').' по '.$date_end->format('d.m.Y');
	
	global $radiofan_chess_parser;
	
	$excel->getProperties()->setTitle($title);
	$excel->getProperties()->setCreator($radiofan_chess_parser->plugin_data['Name'].' V'.$radiofan_chess_parser->plugin_data['Version']);
	$excel->getProperties()->setCompany(site_url().' | '.get_bloginfo('name'));
	$excel->getProperties()->setDescription('Файл сгенерирован на сайте '.get_bloginfo('name'). ' ('.site_url().')');
	
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
		
		unset($ret[$i]);
	}
	
	return $ratings;
}