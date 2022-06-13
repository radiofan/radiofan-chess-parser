<?php
namespace Radiofan\ChessParser;

trait View{
	/**
	 * регистрирует скрипты и стили для работы шорткода [chess_top_scoreboard]
	 */
	public function enqueue_scripts(){
		wp_register_script('radiofan_chess_parser__script_top_scoreboard', $this->plugin_url.'assets/top-scoreboard.js', ['jquery'], filemtime($this->plugin_dir.'assets/top-scoreboard.js'), 1);
		wp_register_script('radiofan_chess_parser__script_rating_table', $this->plugin_url.'assets/rating-table.js', ['jquery'], filemtime($this->plugin_dir.'assets/rating-table.js'), 1);
		wp_register_style('radiofan_chess_parser__style_top_scoreboard', $this->plugin_url.'assets/top-scoreboard.css', false, filemtime($this->plugin_dir.'assets/top-scoreboard.css'));
		wp_register_style('radiofan_chess_parser__style_rating_table', $this->plugin_url.'assets/rating-table.css', false, filemtime($this->plugin_dir.'assets/rating-table.css'));
	}

	/**
	 * Выводит данные шорткода [chess_top_scoreboard]
	 * А именно блок с топом игроков
	 * @param array $atts - ['list_url' => string]
	 * @param string $content
	 * @return string
	 */
	public function view_top_scoreboard($atts, $content){
		
		wp_enqueue_script('radiofan_chess_parser__script_top_scoreboard');
		wp_enqueue_style('radiofan_chess_parser__style_top_scoreboard');

		$atts = shortcode_atts(['list_url' => ''], $atts);
		
		$data = [
			'man-ruchess' => [
				'classic' => get_option('radiofan_chess_parser__top_man_1'),
				'rapid' => get_option('radiofan_chess_parser__top_man_3'),
				'blitz' => get_option('radiofan_chess_parser__top_man_5'),
			],
			'woman-ruchess' => [
				'classic' => get_option('radiofan_chess_parser__top_woman_1'),
				'rapid' => get_option('radiofan_chess_parser__top_woman_3'),
				'blitz' => get_option('radiofan_chess_parser__top_woman_5'),
			],
			'man-fide' => [
				'classic' => get_option('radiofan_chess_parser__top_man_2'),
				'rapid' => get_option('radiofan_chess_parser__top_man_4'),
				'blitz' => get_option('radiofan_chess_parser__top_man_6'),
			],
			'woman-fide' => [
				'classic' => get_option('radiofan_chess_parser__top_woman_2'),
				'rapid' => get_option('radiofan_chess_parser__top_woman_4'),
				'blitz' => get_option('radiofan_chess_parser__top_woman_6'),
			],
		];
		
		$svg_path = $this->plugin_url.'assets/sprites.svg';
		$ret = '
	<div class="chess-top-block">
		<div class="chess-top-block__header tab-header">
			<div class="tab tab-man-ruchess active" data-box="man-ruchess">
				<h5>Мужчины <sub>(фшр)</sub></h5>
			</div>
			<div class="tab tab-woman-ruchess" data-box="woman-ruchess">
				<h5>Женщины <sub>(фшр)</sub></h5>
			</div>
			<div class="tab tab-man-fide" data-box="man-fide">
				<h5>Мужчины <sub>(fide)</sub></h5>
			</div>
			<div class="tab tab-woman-fide" data-box="woman-fide">
				<h5>Женщины <sub>(fide)</sub></h5>
			</div>
		</div>
		<div class="chess-top-block__content tab-content">
		';
		
		$first = 1;
		foreach($data as $key => $item){
			$ret .= '<div class="box box-'.$key.($first ? ' active' : '').'" data-box="'.$key.'">
				<div class="chess-section__header tab-header">
					<div class="tab tab-classic active" data-box="classic">
						<svg class="svg-icon"><use xlink:href="'.$svg_path.'#board"></use></svg>
						<h6>Классика</h6>
					</div>
					<div class="tab tab-rapid" data-box="rapid">
						<svg class="svg-icon"><use xlink:href="'.$svg_path.'#clock"></use></svg>
						<h6>Рапид</h6>
					</div>
					<div class="tab tab-blitz" data-box="blitz">
						<svg class="svg-icon"><use xlink:href="'.$svg_path.'#flash"></use></svg>
						<h6>Блиц</h6>
					</div>
				</div>
				<div class="chess-section__content tab-content">';
			
			$first_n = 1;
			foreach($item as $type => $val){
				$ret .= '<div class="box box-'.$type.($first_n ? ' active' : '').'" data-box="'.$type.'"><table><tbody>';
				
				$len = sizeof($val);
				for($i=0; $i<$len; $i++){
					$ret .= '<tr><td>'.($i+1).'</td><td><a href="'.self::RUCHESS_HREF.$val[$i]['id_ruchess'].'">'.$val[$i]['name'].'</a></td><td>'.$val[$i]['rating'].'</td></tr>';
				}
				
				$ret .= '</tbody></table></div>';
				$first_n = 0;
			}
			
			$ret .= '
				</div>
			</div>
			';
			$first = 0;
		}
		
		$ret .= ($atts['list_url'] ? '<a href="'.trim(esc_html($atts['list_url'])).'" class="top__other">Рейтинг-лист Алтайского края</a>' : '').'
		</div>
	</div>
';
		return $ret;
	}

	public function view_players_page_table($atts, $content){
		global $wpdb;
		
		wp_enqueue_style('radiofan_chess_parser__style_rating_table');
		wp_enqueue_script('radiofan_chess_parser__script_rating_table');
		
		$player_sex = isset($_GET['sex']) ? $_GET['sex'] : 'all';
		$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
		$sort_order = isset($_GET['order']) ? $_GET['order'] : 'asc';
		$search_value = isset($_GET['q']) ? $_GET['q'] : false;
		$query = '';
		//выборка типов пользователя
		switch($player_sex){
			case 'm':
			case 'male':
			case 'man':
			case 'м':
			case 'муж':
				$player_sex = 'male';
				$query = ' WHERE `sex` = 0';
				break;
			case 'f':
			case 'w':
			case 'female':
			case 'woman':
			case 'ж':
			case 'жен':
				$player_sex = 'female';
				$query = ' WHERE `sex` = 1';
				break;
			case 'all':
			default:
				$player_sex = 'all';
				break;
		}

		//сортировка
		$sort_ui = [
			'name' => false,
			'id_ruchess' => false,
			'id_fide' => false,
			'birth_year' => false,
		];
		
		if(!isset($sort_ui[$sort]))
			$sort = 'name';
		
		$sort_order = ($sort_order != 'desc' && $sort_order != 'asc') ? 'asc' : $sort_order;
		//поиск в выборке
		if($search_value){
			$query .= ($query ? ' AND' : ' WHERE');
			$like_search_value = '%'.$wpdb->esc_like($search_value).'%';
			$query .= $wpdb->prepare(' (`id_ruchess` LIKE %s OR `id_fide` LIKE %s OR `name` LIKE %s OR `birth_year` LIKE %s)', $like_search_value, $like_search_value, $like_search_value, $like_search_value);
		}
		//пагинация по выборке
		$players_c = absint($wpdb->get_var('SELECT COUNT(*) FROM `'.$wpdb->prefix.'rad_chess_players`'.$query));
		$elem_per_page = isset($_GET['per_page']) ? absint($_GET['per_page']) : 50;
		if($elem_per_page < 5){
			$elem_per_page = 5;
		}
		if($elem_per_page > 300){
			$elem_per_page = 300;
		}

		$max_page = (int)($players_c / $elem_per_page);
		if($max_page == 0 || $players_c % $elem_per_page)
			$max_page += 1;

		$curr_page = isset($_GET['page_n']) ? absint($_GET['page_n']) : 1;
		if($curr_page < 1)
			$curr_page = 1;
		if($curr_page > $max_page)
			$curr_page = $max_page;
		
		//данные для вывода в html
		$search_value_attr = esc_attr($search_value);
		$elements_count = num_decline($players_c, 'игрок', 'игрока', 'игроков');
		
		//создание ссылок для перехода по страницам
		$href_builder = [
			'page_n' => 0,
		];
		
		if($player_sex != 'all')
			$href_builder['sex'] = $player_sex;
		if($sort != 'name')
			$href_builder['sort'] = $sort;
		if($sort_order != 'asc')
			$href_builder['order'] = $sort_order;
		if($search_value)
			$href_builder['q'] = $search_value;

		$href_builder['page_n'] = 1;
		$href_first_page = http_build_query($href_builder);

		$href_builder['page_n'] = $curr_page-1;
		$href_pre_page = http_build_query($href_builder);

		$href_builder['page_n'] = $curr_page+1;
		$href_next_page = http_build_query($href_builder);

		$href_builder['page_n'] = $max_page;
		$href_last_page = http_build_query($href_builder);
		
		unset($href_builder['page_n']);
		
		foreach($sort_ui as $sort_key => $data){
			$href_builder['sort'] = $sort_key;
			$triangle = $sort_key != $sort ? 0 : ($sort_order == 'asc' ? 1 : -1);
			$href_builder['order'] = $triangle == 1 ? 'desc' : 'asc';
			$sort_ui[$sort_key] = ['href' => http_build_query($href_builder), 'triangle_html' => ''];
			switch($triangle){
				case 1:
					$sort_ui[$sort_key]['triangle_html'] = '<span class="sort_triangle">▲</span>';
					break;
				case -1:
					$sort_ui[$sort_key]['triangle_html'] = '<span class="sort_triangle">▼</span>';
					break;
				default:
					break;
			}
		}

		//генерация строк таблицы
		$players_res = $wpdb->get_results(
			'SELECT `id_ruchess`, `id_fide`, `name`, `sex`, `birth_year`
FROM `'.$wpdb->prefix.'rad_chess_players`
'.$query.' ORDER BY `'.$sort.'` '.mb_strtoupper($sort_order).$wpdb->prepare(' LIMIT %d OFFSET %d', $elem_per_page, ($curr_page-1)*$elem_per_page), ARRAY_A);
		
		$players = [];
		
		$len = sizeof($players_res);
		for($i=0; $i<$len; $i++){
			$players[$players_res[$i]['id_ruchess']] = $players_res[$i];
			unset($players_res[$i]);
		}
		$ratings = $this->get_players_ratings(array_keys($players));

		$players_list = '';
		foreach($players as $id_ruchess => $data){
			$id = $data['id_ruchess'];
			$players_list .= '
<tr>
	<td class="td-text td-id_ruchess"><a href="'.self::RUCHESS_HREF.$id.'">'.$id.'</a></td>
	<td class="td-text td-id_fide">'.($data['id_fide'] ? '<a href="'.self::FIDE_HREF.$data['id_fide'].'">'.$data['id_fide'].'</a>' : '').'</td>
	<td class="td-text td-name">'.esc_html($data['name']).'</td>
	<td class="td-text td-sex">'.($data['sex'] ? 'ж' : 'м').'</td>
	<td class="td-text td-birth_year">'.$data['birth_year'].'</td>';
			
			foreach(self::GAME_TYPE as $type_id => $type){
				foreach([1 => 'ruchess', 2 => 'fide'] as $platform_id => $platform){
					$r_id = $type_id*2+$platform_id;
					$players_list .= '<td class="td-text td-'.$type.'-'.$platform.' td-type-'.$r_id.'">';
					$len = isset($ratings[$id][$r_id]) ? sizeof($ratings[$id][$r_id]) : 0;
					for($i=0; $i<$len; $i++){
						if($i == 1){
							$players_list .= '<div class="spoiler-wrap"><div class="spoiler-head folded">Ещё</div><div class="spoiler-body">';
						}
						$players_list .= '<p><code>'.mb_substr($ratings[$id][$r_id][$i]['update_date'], 0, 10).'</code>:&nbsp;&nbsp;&nbsp;&nbsp;'.$ratings[$id][$r_id][$i]['rating'].'</p>';
					}
					if($len > 1){
						$players_list .= '</div></div>';
					}
					$players_list .= '</td>';
				}
			}
			$players_list .= '</tr>';
		}
		
		//генерация html таблицы и пагинации
		$ret = '<div class="players-table">
					<div class="list-group list-group-horizontal float-left">
						<a href="?sex=all" class="list-group-item list-group-item-action'.($player_sex == 'all' ? ' active' : '').'">Все</a>
						<a href="?sex=m" class="list-group-item list-group-item-action'.($player_sex == 'male' ? ' active' : '').'">Мужчины</a>
						<a href="?sex=f" class="list-group-item list-group-item-action'.($player_sex == 'female' ? ' active' : '').'">Женщины</a>
					</div>
					<form method="get" data-not-ajax="true">
						<div class="float-right form-search">
							<input type="hidden" name="sex" value="'.$player_sex.'">
							<input type="text" name="q" autocomplete="off" value="'.$search_value_attr.'">
							<button class="button-search" type="submit">
								<img src="'.get_theme_root_uri().'/chess/assets/icons/search-solid.svg" alt="поиск">
							</button>
						</div>
					</form>
					<br class="clear">
					<div class="table-nav">
						<div class="table-nav-pages float-right">
							<span class="displaying-num">'.$elements_count.'</span>
							<span class="pagination-links">
								<a href="?'.$href_first_page.'">«</a>
								<a href="?'.$href_pre_page.'">‹</a>
								<span class="paging-input">
									<form method="get" data-not-ajax="true">
										<input type="hidden" name="sex" value="'.$player_sex.'">
										<input type="hidden" name="q" value="'.$search_value_attr.'">
										<input type="hidden" name="sort" value="'.$sort.'">
										<input type="hidden" name="sort_order" value="'.$sort_order.'">
										<span><input type="number" step="1" min="1" max="'.$max_page.'" name="page_n" value="'.$curr_page.'" class="curr-pages"> &nbsp;of&nbsp; <span class="total-pages">'.$max_page.'&nbsp;</span></span>
									</form>
								</span>
								<a href="?'.$href_next_page.'">›</a>
								<a href="?'.$href_last_page.'">»</a>
							</span>
						</div>
						<br class="clear">
					</div>
					<hr>
					<div class="table-box">
						<table class="table table-striped">
							<thead>
								<tr>
									<th class="td-text" rowspan="3"><a href="?'.$sort_ui['id_ruchess']['href'].'">ФШР ID '.$sort_ui['id_ruchess']['triangle_html'].'</a></th>
									<th class="td-text" rowspan="3"><a href="?'.$sort_ui['id_fide']['href'].'">FIDE ID '.$sort_ui['id_fide']['triangle_html'].'</a></th>
									<th class="td-text" rowspan="3"><a href="?'.$sort_ui['name']['href'].'">ФИО '.$sort_ui['name']['triangle_html'].'</a></th>
									<th class="td-text" rowspan="3">Пол</th>
									<th class="td-text" rowspan="3"><a href="?'.$sort_ui['birth_year']['href'].'">Год рождения '.$sort_ui['birth_year']['triangle_html'].'</a></th>
									<th class="td-text" colspan="6">Рейтинг</th>
								</tr>
								<tr>
									<th class="td-text" colspan="2">Классика</th>
									<th class="td-text" colspan="2">Рапид</th>
									<th class="td-text" colspan="2">Блиц</th>
								</tr>
								<tr>
									<th class="td-text">ФШР</th>
									<th class="td-text">FIDE</th>
									<th class="td-text">ФШР</th>
									<th class="td-text">FIDE</th>
									<th class="td-text">ФШР</th>
									<th class="td-text">FIDE</th>
								</tr>
							</thead>
							<tbody>
							'.$players_list.'
							</tbody>
							<thead>
								<tr>
									<th class="td-text" rowspan="3"><a href="?'.$sort_ui['id_ruchess']['href'].'">ФШР ID '.$sort_ui['id_ruchess']['triangle_html'].'</a></th>
									<th class="td-text" rowspan="3"><a href="?'.$sort_ui['id_fide']['href'].'">FIDE ID '.$sort_ui['id_fide']['triangle_html'].'</a></th>
									<th class="td-text" rowspan="3"><a href="?'.$sort_ui['name']['href'].'">ФИО '.$sort_ui['name']['triangle_html'].'</a></th>
									<th class="td-text" rowspan="3">Пол</th>
									<th class="td-text" rowspan="3"><a href="?'.$sort_ui['birth_year']['href'].'">Год рождения '.$sort_ui['birth_year']['triangle_html'].'</a></th>
									<th class="td-text">ФШР</th>
									<th class="td-text">FIDE</th>
									<th class="td-text">ФШР</th>
									<th class="td-text">FIDE</th>
									<th class="td-text">ФШР</th>
									<th class="td-text">FIDE</th>
								</tr>
								<tr>
									<th class="td-text" colspan="2">Классика</th>
									<th class="td-text" colspan="2">Рапид</th>
									<th class="td-text" colspan="2">Блиц</th>
								</tr>
								<tr>
									<th class="td-text" colspan="6">Рейтинг</th>
								</tr>
							</thead>
						</table>
					</div>
					<hr>
					<div class="table-nav">
						<div class="table-nav-pages float-right">
							<span class="displaying-num">'.$elements_count.'</span>
							<span class="pagination-links">
								<a href="?'.$href_first_page.'">«</a>
								<a href="?'.$href_pre_page.'">‹</a>
								<span class="paging-input">
									<form method="get" data-not-ajax="true">
										<input type="hidden" name="sex" value="'.$player_sex.'">
										<input type="hidden" name="q" value="'.$search_value_attr.'">
										<input type="hidden" name="sort" value="'.$sort.'">
										<input type="hidden" name="sort_order" value="'.$sort_order.'">
										<span><input type="number" step="1" min="1" max="'.$max_page.'" name="page_n" value="'.$curr_page.'" class="curr-pages"> &nbsp;of&nbsp; <span class="total-pages">'.$max_page.'&nbsp;</span></span>
									</form>
								</span>
								<a href="?'.$href_next_page.'">›</a>
								<a href="?'.$href_last_page.'">»</a>
							</span>
						</div>
						<br class="clear">
					</div>
				</div>';
		
		return $ret;
	}

	/**
	 * передавать только массив чисел, данные перед запросом не обрабатываются!!!
	 * @param int[] $players_id
	 * @return array
	 */
	protected function get_players_ratings($players_id = []){
		global $wpdb; 
		$query = implode(', ', $players_id);
		$ret = $wpdb->get_results('SELECT `id_ruchess`, `rating_type`, `rating`, `update_date` FROM `'.$wpdb->prefix.'rad_chess_players_ratings` WHERE `id_ruchess` IN('.$query.') ORDER BY `update_date` DESC', ARRAY_A);
		$ratings = [];
		$len = sizeof($ret);
		for($i=0; $i<$len; $i++){
			$id = $ret[$i]['id_ruchess'];
			$r_type = $ret[$i]['rating_type'];
			if(!isset($ratings[$id]))
				$ratings[$id] = [];
			if(!isset($ratings[$id][$r_type]))
				$ratings[$id][$r_type] = [];
			$ratings[$id][$r_type][] = ['rating' => $ret[$i]['rating'], 'update_date' => $ret[$i]['update_date']];
			
			unset($ret[$i]);
		}
		
		return $ratings;
	}

	/**
	 * обновляет топ игроков для данного типа игроков
	 * @see View::save_top()
	 * @param int $rating_type - тип рейтинга из таблицы rad_chess_players_ratings
	 */
	protected function update_top($rating_type){
		global $wpdb;
		$data = $wpdb->get_results(
			'SELECT `rat`.`id_ruchess`, `rating`, `sex` FROM `'.$wpdb->prefix.
			'rad_chess_players_ratings` AS `rat` LEFT JOIN `'.$wpdb->prefix.
			'rad_chess_players` AS `p` ON `rat`.`id_ruchess` = `p`.`id_ruchess` WHERE '.
			$wpdb->prepare('`rating_type` = %d', $rating_type).
			' ORDER BY `id_ruchess`, `update_date` DESC',
			ARRAY_A
		);
		
		$man_top = new TopList(10, ['Radiofan\ChessParser\TopList', 'compare_ratings']);
		$woman_top = new TopList(10, ['Radiofan\ChessParser\TopList', 'compare_ratings']);
		$curr_id = 0;
		
		$len = sizeof($data);
		for($i=0; $i<$len; $i++){
			$tmp = $data[$i];
			unset($data[$i]);
			if($tmp['id_ruchess'] == $curr_id)
				continue;
			$curr_id = $tmp['id_ruchess'];
			if($tmp['sex']){//woman
				unset($tmp['sex']);
				$woman_top->add($tmp);
			}else{//man
				unset($tmp['sex']);
				$man_top->add($tmp);
			}
		}

		$this->save_top($man_top->get_top_desc(), 'man', $rating_type);
		$this->save_top($woman_top->get_top_desc(), 'woman', $rating_type);
	}

	/**
	 * топ сохраняется в опцию 'radiofan_chess_parser__top_'.['man'|'woman'].'_'.$rating_type
	 * топ имеет структуру
	 * [
	 * 		['id_ruchess' => int, 'id_fide' => null|int, 'rating' => int, 'name' => string],
	 * 		...
	 * ] - длина не больше 10 элементов
	 * @param array $top - массив массивов ['id_ruchess' => int|numeric-string, 'rating' => int|numeric-string]
	 * @param string $sex_type - 'man' | 'woman'
	 * @param int $rating_type - тип рейтинга из таблицы rad_chess_players_ratings
	 * @return bool
	 */
	protected function save_top($top, $sex_type, $rating_type){
		global $wpdb;
		$query_str = '';
		$len = sizeof($top);
		if(!$len)
			return false;
		for($i = 0; $i < $len; $i++){
			$top[$i]['id_ruchess'] = absint($top[$i]['id_ruchess']);
			$top[$i]['rating'] = absint($top[$i]['rating']);
			$query_str .= $top[$i]['id_ruchess'].', ';
		}
		$query_str = mb_substr($query_str, 0, -2);
		$data = $wpdb->get_results('SELECT `id_ruchess`, `id_fide`, `name` FROM `'.$wpdb->prefix.'rad_chess_players` WHERE `id_ruchess` IN('.$query_str.')', ARRAY_A);
		$players_data = [];
		for($i = 0; $i < $len; $i++){
			$id = $data[$i]['id_ruchess'];
			unset($data[$i]['id_ruchess']);
			$players_data[$id] = $data[$i];
		}
		for($i = 0; $i < $len; $i++){
			$top[$i] = array_merge($top[$i], $players_data[$top[$i]['id_ruchess']]);
			$top[$i]['id_fide'] = is_null($top[$i]['id_fide']) ?: absint($top[$i]['id_fide']);
		}
		
		update_option('radiofan_chess_parser__top_'.$sex_type.'_'.$rating_type, $top, false);
		return true;
	}
}

/**
 * Class TopList - при добавлении новых элементов формирует топ
 * @package Radiofan\ChessParser
 */
class TopList{
	
	private $max_top;
	private $container;
	private $comparator;

	/**
	 * TopList constructor.
	 * @param int $max_top - максимальное количество элементов в топе
	 * @param callable|false $comparator - функция для сравнения элементов (добавляемый < уже_добавленный = -1, добавляемый == уже_добавленный = 0, добавляемый > уже_добавленный = 1)
	 * если false то производится сравнение элементов через операторы сравнения
	 */
	public function __construct($max_top, $comparator = false){
		$this->max_top = absint($max_top);
		$this->container = [];
		$this->comparator = is_callable($comparator) ? $comparator : false;
	}

	/**
	 * @param mixed $elem - добавляемый элемент, будет сравниваться с помощью $this->$comparator или через операторы сравнения
	 * @return bool был ли добавлен элемент
	 */
	public function add($elem){
		$len = sizeof($this->container);
		$i=0;
		for(; $i<$len; $i++){
			if($this->comparator){
				if(call_user_func($this->comparator, $elem, $this->container[$i]) < 1)
					break;
			}else{
				if($elem <= $this->container[$i])
					break;
			}
		}
		if($i == 0 && $len == $this->max_top)
			return false;
		
		array_splice($this->container, $i, 0, [$elem]);
		if($len+1 > $this->max_top)
			array_shift($this->container);
		return true;
	}

	/**
	 * возвращает массив добавленных элементов, от большего к меньшему
	 * @return array
	 */
	public function get_top_desc(){
		return array_reverse($this->container);
	}

	/**
	 * возвращает массив добавленных элементов, от меньшего к большему
	 * @return array
	 */
	public function get_top_asc(){
		return $this->container;
	}

	/**
	 * комапаратор для топа игроков по рейтингам
	 * @param array $addable - должен содеражать ключ 'rating'
	 * @param array $added - должен содеражать ключ 'rating'
	 * @return int $addable < $added = -1, $addable == $added = 0, $addable > $added = 1
	 */
	public static function compare_ratings($addable, $added){
		return $addable['rating'] < $added['rating'] ? -1 : ($addable['rating'] == $added['rating'] ? 0 : 1);
	}
}
