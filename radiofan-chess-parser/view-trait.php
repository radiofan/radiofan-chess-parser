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
		
		global $wpdb;
		
		wp_enqueue_script('radiofan_chess_parser__script_top_scoreboard');
		wp_enqueue_style('radiofan_chess_parser__style_top_scoreboard');

		$atts = shortcode_atts(['list_url' => ''], $atts);
		
		$data = [
			'man-ruchess' => [
				'classic' => 's',
				'rapid' => 'r',
				'blitz' => 'b',
				'sex' => '0',
				'r_type' => 'ru'
			],
			'woman-ruchess' => [
				'classic' => 's',
				'rapid' => 'r',
				'blitz' => 'b',
				'sex' => '1',
				'r_type' => 'ru'
			],
			'man-fide' => [
				'classic' => 's',
				'rapid' => 'r',
				'blitz' => 'b',
				'sex' => '0',
				'r_type' => 'fi'
			],
			'woman-fide' => [
				'classic' => 's',
				'rapid' => 'r',
				'blitz' => 'b',
				'sex' => '1',
				'r_type' => 'fi'
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
				if($type == 'sex' || $type == 'r_type')
					continue;
				
				$rating_col = 'rating_'.$item['r_type'].'_'.$val;
				$rating_top = $wpdb->get_results('SELECT `p`.`id_ruchess`, `name`, `'.$rating_col.'` AS `rating` FROM '.$wpdb->prefix.'rad_chess_players AS `p` LEFT JOIN '.$wpdb->prefix.'rad_chess_current_ratings AS `r` ON `p`.`id_ruchess` = `r`.`id_ruchess` WHERE `sex` = '.$item['sex'].' ORDER BY `'.$rating_col.'` DESC LIMIT 10', ARRAY_A);
				
				$ret .= '<div class="box box-'.$type.($first_n ? ' active' : '').'" data-box="'.$type.'"><table><tbody>';
				
				$len = sizeof($rating_top);
				for($i=0; $i<$len; $i++){
					$ret .= '<tr><td>'.($i+1).'</td><td><a href="'.self::RUCHESS_HREF.$rating_top[$i]['id_ruchess'].'">'.$rating_top[$i]['name'].'</a></td><td>'.$rating_top[$i]['rating'].'</td></tr>';
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
		
		wp_enqueue_style('radiofan_chess_parser__style_rating_table');
		wp_enqueue_script('radiofan_chess_parser__script_rating_table');
		
		$players_table_options = new PlayersTableOptions();
		
		
		//данные для вывода в html
		$search_value_attr = esc_attr($players_table_options->search_value);
		$elements_count = num_decline($players_table_options->players_count, 'игрок', 'игрока', 'игроков');
		
		
		//генерация строк таблицы
		$players = $players_table_options->get_players();
		$ratings = $this->get_players_ratings(array_keys($players));
		
		$hide_rating_date = get_option('radiofan_chess_parser__hide_rating_date', false);

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
						$date = $hide_rating_date ? '' : '<code>'.mb_substr($ratings[$id][$r_id][$i]['update_date'], 0, 10).'</code>:&nbsp;&nbsp;&nbsp;&nbsp;';
						$players_list .= '<p>'.$date.$ratings[$id][$r_id][$i]['rating'].'</p>';
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
					<div class="table-header-options">
						<div class="filter-options">
							<div class="list-group list-group-horizontal">
								<a href="?'.$players_table_options->get_sex_options_href('all').'" class="list-group-item list-group-item-action'.($players_table_options->player_sex == 'all' ? ' active' : '').'">Все</a>
								<a href="?'.$players_table_options->get_sex_options_href('m').'" class="list-group-item list-group-item-action'.($players_table_options->player_sex == 'm' ? ' active' : '').'">Мужчины</a>
								<a href="?'.$players_table_options->get_sex_options_href('f').'" class="list-group-item list-group-item-action'.($players_table_options->player_sex == 'f' ? ' active' : '').'">Женщины</a>
							</div>
							<div class="hide-players-option">
								'.($players_table_options->hide_empty_players ? '<a href="?'.$players_table_options->get_hide_empty_options_href('show').'">Показывать игроков без рейтинга</a>' : '<a href="?'.$players_table_options->get_hide_empty_options_href('hide').'">Скрывать игроков без рейтинга</a>').'
							</div>
						</div>
						<div>
							<form method="get" data-not-ajax="true">
								<div class="form-search">
									<input type="hidden" name="sex" value="'.$players_table_options->player_sex.'">
									<input type="hidden" name="hide_empty" value="'.$players_table_options->hide_empty_players.'">
									<input type="hidden" name="sort" value="'.$players_table_options->sort.'">
									<input type="hidden" name="order" value="'.$players_table_options->sort_order.'">
									<input type="text" name="q" autocomplete="off" value="'.$search_value_attr.'">
									<button class="button-search" type="submit">
										<img src="'.get_theme_root_uri().'/chess/assets/icons/search-solid.svg" alt="поиск">
									</button>
								</div>
							</form>
						</div>
					</div>
					<div class="table-nav">
						<div class="table-nav-pages">
							<span class="displaying-num">'.$elements_count.'</span>
							<span class="pagination-links">
								<a href="?'.$players_table_options->get_pages_href('first').'">«</a>
								<a href="?'.$players_table_options->get_pages_href('pre').'">‹</a>
								<span class="paging-input">
									<form method="get" data-not-ajax="true">
										<input type="hidden" name="sex" value="'.$players_table_options->player_sex.'">
										<input type="hidden" name="q" value="'.$search_value_attr.'">
										<input type="hidden" name="sort" value="'.$players_table_options->sort.'">
										<input type="hidden" name="order" value="'.$players_table_options->sort_order.'">
										<input type="hidden" name="hide_empty" value="'.$players_table_options->hide_empty_players.'">
										<span><input type="number" step="1" min="1" max="'.$players_table_options->max_page.'" name="page_n" value="'.$players_table_options->current_page.'" class="curr-pages"> &nbsp;/&nbsp; <span class="total-pages">'.$players_table_options->max_page.'&nbsp;</span></span>
									</form>
								</span>
								<a href="?'.$players_table_options->get_pages_href('next').'">›</a>
								<a href="?'.$players_table_options->get_pages_href('last').'">»</a>
							</span>
						</div>
					</div>
					<hr>
					<div class="table-box">
						<table class="table table-striped">
							<thead>
								<tr>
									<th class="td-text" rowspan="3"><a href="?'.$players_table_options->get_sort_ui_href('id_ruchess')['href'].'">ФШР ID '.$players_table_options->get_sort_ui_href('id_ruchess')['triangle_html'].'</a></th>
									<th class="td-text" rowspan="3"><a href="?'.$players_table_options->get_sort_ui_href('id_fide')['href'].'">FIDE ID '.$players_table_options->get_sort_ui_href('id_fide')['triangle_html'].'</a></th>
									<th class="td-text" rowspan="3"><a href="?'.$players_table_options->get_sort_ui_href('name')['href'].'">ФИО '.$players_table_options->get_sort_ui_href('name')['triangle_html'].'</a></th>
									<th class="td-text" rowspan="3">Пол</th>
									<th class="td-text" rowspan="3"><a href="?'.$players_table_options->get_sort_ui_href('birth_year')['href'].'">Год рождения '.$players_table_options->get_sort_ui_href('birth_year')['triangle_html'].'</a></th>
									<th class="td-text" colspan="6">Рейтинг</th>
								</tr>
								<tr>
									<th class="td-text" colspan="2">Классика</th>
									<th class="td-text" colspan="2">Рапид</th>
									<th class="td-text" colspan="2">Блиц</th>
								</tr>
								<tr>
									<th class="td-text"><a href="?'.$players_table_options->get_sort_ui_href('rating_ru_s')['href'].'">ФШР '.$players_table_options->get_sort_ui_href('rating_ru_s')['triangle_html'].'</a></th>
									<th class="td-text"><a href="?'.$players_table_options->get_sort_ui_href('rating_fi_s')['href'].'">FIDE '.$players_table_options->get_sort_ui_href('rating_fi_s')['triangle_html'].'</a></th>
									<th class="td-text"><a href="?'.$players_table_options->get_sort_ui_href('rating_ru_r')['href'].'">ФШР '.$players_table_options->get_sort_ui_href('rating_ru_r')['triangle_html'].'</a></th>
									<th class="td-text"><a href="?'.$players_table_options->get_sort_ui_href('rating_fi_r')['href'].'">FIDE '.$players_table_options->get_sort_ui_href('rating_fi_r')['triangle_html'].'</a></th>
									<th class="td-text"><a href="?'.$players_table_options->get_sort_ui_href('rating_ru_b')['href'].'">ФШР '.$players_table_options->get_sort_ui_href('rating_ru_b')['triangle_html'].'</a></th>
									<th class="td-text"><a href="?'.$players_table_options->get_sort_ui_href('rating_fi_b')['href'].'">FIDE '.$players_table_options->get_sort_ui_href('rating_fi_b')['triangle_html'].'</a></th>
								</tr>
							</thead>
							<tbody>
							'.$players_list.'
							</tbody>
							<thead>
								<tr>
									<th class="td-text" rowspan="3"><a href="?'.$players_table_options->get_sort_ui_href('id_ruchess')['href'].'">ФШР ID '.$players_table_options->get_sort_ui_href('id_ruchess')['triangle_html'].'</a></th>
									<th class="td-text" rowspan="3"><a href="?'.$players_table_options->get_sort_ui_href('id_fide')['href'].'">FIDE ID '.$players_table_options->get_sort_ui_href('id_fide')['triangle_html'].'</a></th>
									<th class="td-text" rowspan="3"><a href="?'.$players_table_options->get_sort_ui_href('name')['href'].'">ФИО '.$players_table_options->get_sort_ui_href('name')['triangle_html'].'</a></th>
									<th class="td-text" rowspan="3">Пол</th>
									<th class="td-text" rowspan="3"><a href="?'.$players_table_options->get_sort_ui_href('birth_year')['href'].'">Год рождения '.$players_table_options->get_sort_ui_href('birth_year')['triangle_html'].'</a></th>
									<th class="td-text"><a href="?'.$players_table_options->get_sort_ui_href('rating_ru_s')['href'].'">ФШР '.$players_table_options->get_sort_ui_href('rating_ru_s')['triangle_html'].'</a></th>
									<th class="td-text"><a href="?'.$players_table_options->get_sort_ui_href('rating_fi_s')['href'].'">FIDE '.$players_table_options->get_sort_ui_href('rating_fi_s')['triangle_html'].'</a></th>
									<th class="td-text"><a href="?'.$players_table_options->get_sort_ui_href('rating_ru_r')['href'].'">ФШР '.$players_table_options->get_sort_ui_href('rating_ru_r')['triangle_html'].'</a></th>
									<th class="td-text"><a href="?'.$players_table_options->get_sort_ui_href('rating_fi_r')['href'].'">FIDE '.$players_table_options->get_sort_ui_href('rating_fi_r')['triangle_html'].'</a></th>
									<th class="td-text"><a href="?'.$players_table_options->get_sort_ui_href('rating_ru_b')['href'].'">ФШР '.$players_table_options->get_sort_ui_href('rating_ru_b')['triangle_html'].'</a></th>
									<th class="td-text"><a href="?'.$players_table_options->get_sort_ui_href('rating_fi_b')['href'].'">FIDE '.$players_table_options->get_sort_ui_href('rating_fi_b')['triangle_html'].'</a></th>
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
						<div class="table-nav-pages">
							<span class="displaying-num">'.$elements_count.'</span>
							<span class="pagination-links">
								<a href="?'.$players_table_options->get_pages_href('first').'">«</a>
								<a href="?'.$players_table_options->get_pages_href('pre').'">‹</a>
								<span class="paging-input">
									<form method="get" data-not-ajax="true">
										<input type="hidden" name="sex" value="'.$players_table_options->player_sex.'">
										<input type="hidden" name="q" value="'.$search_value_attr.'">
										<input type="hidden" name="sort" value="'.$players_table_options->sort.'">
										<input type="hidden" name="order" value="'.$players_table_options->sort_order.'">
										<input type="hidden" name="hide_empty" value="'.$players_table_options->hide_empty_players.'">
										<span><input type="number" step="1" min="1" max="'.$players_table_options->max_page.'" name="page_n" value="'.$players_table_options->current_page.'" class="curr-pages"> &nbsp;/&nbsp; <span class="total-pages">'.$players_table_options->max_page.'&nbsp;</span></span>
									</form>
								</span>
								<a href="?'.$players_table_options->get_pages_href('next').'">›</a>
								<a href="?'.$players_table_options->get_pages_href('last').'">»</a>
							</span>
						</div>
						<br class="clear">
					</div>
				</div>';
		
		return $ret;
	}

	/**
	 * Возвращает массив со всеми рейтингами переданных id игроков
	 * @param int[] $players_id передавать только массив чисел, данные перед запросом не обрабатываются!!!
	 * @return array
	 * [
	 * 		player_id => [
	 * 			raiting_type => [
	 * 				['rating' => numeric_string, 'update_date' => string],
	 * 				рейтинги отсортированы по дате обновления от самой свежей до самой старой
	 * 				...
	 * 			],
	 * 			raiting_type (1 - 6), 
	 * 			...
	 * 		],
	 * 		...
	 * ]
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
}


//todo добавить документации
class PlayersTableOptions{
	const DEFAULT_SORT_STR = 'name';
	const DEFAULT_SORT_ORDER_STR = 'asc';
	const SORT_COLUMNS = [
		'name' => false,
		'id_ruchess' => false,
		'id_fide' => false,
		'birth_year' => false,
		'rating_ru_s' => false,
		'rating_fi_s' => false,
		'rating_ru_r' => false,
		'rating_fi_r' => false,
		'rating_ru_b' => false,
		'rating_fi_b' => false
	];
	
	/** @var string $player_sex - пол игроков которые нужны; 'm', 'f', 'all' */
	protected $player_sex;
	/** @var string $sort - наименование столбца сортировки */
	protected $sort;
	/** @var string $sort_order - направление сортировки; 'asc', 'desc' */
	protected $sort_order;
	/** @var bool $hide_empty_players - убрать ли из выборки игроков без рейтинга */
	protected $hide_empty_players;
	/** @var string $search_value - строка поиска */
	protected $search_value;
	
	/** @var int $players_count - число игроков удовлетворяющих условию в $this->where_query */
	protected $players_count;
	/** @var int $elements_per_page - количество записей на странице таблицы */
	protected $elements_per_page;
	/** @var int $max_page - номер последней страницы */
	protected $max_page;
	/** @var int $current_page - номер текущей страницы */
	protected $current_page;
	
	/** @var string[] $pages_hrefs - массив ссылок для перехода по страницам ['first' => первая, 'pre' => предыдущая, 'next' => следущая, 'last' => последняя] */
	protected $pages_hrefs;
	/** @var string[] $sex_options_hrefs - массив ссылок для переключения фильтра пола ['all' => все, 'm' => муж, 'f' => жен] */
	protected $sex_options_hrefs;
	/** @var string[] $hide_empty_options_hrefs - массив ссылок для переключения фильтра игроков без рейтинга ['hide' => исключить, 'show' => оставить] */
	protected $hide_empty_options_hrefs;
	/** @var array $sort_ui_hrefs - массив ссылок для переключения сортировки [название столбца => ['href' => string, 'triangle_html' => string], ...] */
	protected $sort_ui_hrefs;
	
	/** @var string $where_query - строка условие при выборке данных */
	protected $where_query;
	/** @var bool $join_current_ratings - подключать ли текущие рейтинги к запросу */
	protected $join_current_ratings;
	
	/** @var string $DEFAULT_SORT - столбец сортировки по умолчанию (настраивается в админке) */
	protected $DEFAULT_SORT;
	/** @var string $DEFAULT_SORT_ORDER - направление сортировки по умолчанию (настраивается в админке) */
	protected $DEFAULT_SORT_ORDER;
	/** @var string $JOIN_CURRENT_RATINGS_STR - часть запроса для join'a таблицы текущих рейтингов */
	protected $JOIN_CURRENT_RATINGS_STR;
	
	
	public function __construct(){
		global $wpdb;
		$this->DEFAULT_SORT = get_option('radiofan_chess_parser__default_sort', self::DEFAULT_SORT_STR);
		$this->DEFAULT_SORT_ORDER = get_option('radiofan_chess_parser__default_sort_order', self::DEFAULT_SORT_ORDER_STR);
		$this->JOIN_CURRENT_RATINGS_STR = 'LEFT JOIN `'.$wpdb->prefix.'rad_chess_current_ratings` AS `r` ON `p`.`id_ruchess` = `r`.`id_ruchess`';

		$this->where_query = '';
		$this->join_current_ratings = false;
		
		
		$this->init_options();
		$this->init_pagination();
		$this->init_hrefs();
	}

	protected function init_options(){
		$this->player_sex_validation(isset($_GET['sex']) ? $_GET['sex'] : 'all');
		$this->hide_empty_players_validation(!empty($_GET['hide_empty']));

		$this->sort_validation(
			isset($_GET['sort']) ? $_GET['sort'] : $this->DEFAULT_SORT,
			isset($_GET['order']) ? $_GET['order'] : $this->DEFAULT_SORT_ORDER
		);

		$this->search_value_validation(isset($_GET['q']) ? $_GET['q'] : '');
	}

	protected function init_pagination(){
		global $wpdb;
		$left_join = $this->join_current_ratings ? ' '.$this->JOIN_CURRENT_RATINGS_STR : '';

		$this->players_count = absint($wpdb->get_var('SELECT COUNT(*) FROM `'.$wpdb->prefix.'rad_chess_players` AS `p`'.$left_join.' '.$this->where_query));
		$this->elements_per_page_validation(isset($_GET['per_page']) ? absint($_GET['per_page']) : 50);

		$this->max_page = (int)($this->players_count / $this->elements_per_page);
		if($this->max_page == 0 || $this->players_count % $this->elements_per_page)
			$this->max_page += 1;

		$this->current_page_validation(isset($_GET['page_n']) ? absint($_GET['page_n']) : 1);
	}

	protected function init_hrefs(){
		$this->init_pages_hrefs();
		$this->init_sex_options_hrefs();
		$this->init_hide_empty_options_hrefs();
		$this->init_sort_ui_hrefs();
	}

	/*
	 * инициализация ссылок переключателя страниц
	 * сохраняемые опции -
	 * недефолтный пол
	 * недефолтный столбец сортировки
	 * недефолтное направление сортировки
	 * непустой поисковый запрос
	 * скрытие игроков без рейтинга
	 * 
	 * номер страницы
	 */
	protected function init_pages_hrefs(){
		$this->pages_hrefs = [];
		$href_builder = [];

		if($this->player_sex != 'all')
			$href_builder['sex'] = $this->player_sex;
		if($this->sort != $this->DEFAULT_SORT)
			$href_builder['sort'] = $this->sort;
		if($this->sort_order != $this->DEFAULT_SORT_ORDER)
			$href_builder['order'] = $this->sort_order;
		if($this->search_value !== '')
			$href_builder['q'] = $this->search_value;
		if($this->hide_empty_players)
			$href_builder['hide_empty'] = 1;

		$href_builder['page_n'] = 1;
		$this->pages_hrefs['first'] = http_build_query($href_builder);

		$href_builder['page_n'] = $this->current_page-1;
		$this->pages_hrefs['pre'] = http_build_query($href_builder);

		$href_builder['page_n'] = $this->current_page+1;
		$this->pages_hrefs['next'] = http_build_query($href_builder);

		$href_builder['page_n'] = $this->max_page;
		$this->pages_hrefs['last'] = http_build_query($href_builder);
	}
	
	/**
	 * инициализация ссылок переключателя пола
	 * сохраняемые опции -
	 * недефолтный столбец сортировки
	 * недефолтное направление сортировки
	 * скрытие игроков без рейтинга
	 *
	 * пол
	 */
	protected function init_sex_options_hrefs(){
		$this->sex_options_hrefs = [];

		$href_builder = [];

		if($this->sort != $this->DEFAULT_SORT)
			$href_builder['sort'] = $this->sort;
		if($this->sort_order != $this->DEFAULT_SORT_ORDER)
			$href_builder['order'] = $this->sort_order;
		if($this->hide_empty_players)
			$href_builder['hide_empty'] = 1;
		
		foreach(['all', 'm', 'f'] as $key){
			$href_builder['sex'] = $key;
			$this->sex_options_hrefs[$key] = http_build_query($href_builder);
		}
	}

	/**
	 * инициализация ссылок переключателя видимости пустых записей
	 * сохраняемые опции -
	 * недефолтный пол
	 * недефолтный столбец сортировки
	 * недефолтное направление сортировки
	 *
	 * скрытие или показ игроков без рейтинга
	 */
	protected function init_hide_empty_options_hrefs(){
		$this->hide_empty_options_hrefs = [];

		$href_builder = [];

		if($this->player_sex != 'all')
			$href_builder['sex'] = $this->player_sex;
		if($this->sort != $this->DEFAULT_SORT)
			$href_builder['sort'] = $this->sort;
		if($this->sort_order != $this->DEFAULT_SORT_ORDER)
			$href_builder['order'] = $this->sort_order;

		$this->hide_empty_options_hrefs['show'] = http_build_query($href_builder);
		
		$href_builder['hide_empty'] = 1;
		$this->hide_empty_options_hrefs['hide'] = http_build_query($href_builder);
		
	}

	/**
	 * инициализация ссылок переключения сортировки в шапке таблицы
	 * сохраняемые опции -
	 * скрытие или показ игроков без рейтинга
	 * недефолтный пол
	 * непустой поисковый запрос
	 * 
	 * столбец сортировки
	 * направление сортировки
	 */
	protected function init_sort_ui_hrefs(){
		$this->sort_ui_hrefs = [];

		$href_builder = [];

		if($this->player_sex != 'all')
			$href_builder['sex'] = $this->player_sex;
		if($this->sort != $this->DEFAULT_SORT)
			$href_builder['sort'] = $this->sort;
		if($this->sort_order != $this->DEFAULT_SORT_ORDER)
			$href_builder['order'] = $this->sort_order;
		if($this->search_value !== '')
			$href_builder['q'] = $this->search_value;
		if($this->hide_empty_players)
			$href_builder['hide_empty'] = 1;

		foreach(self::SORT_COLUMNS as $sort_key => $data){
			$href_builder['sort'] = $sort_key;
			$triangle = $sort_key != $this->sort ? 0 : ($this->sort_order == 'asc' ? 1 : -1);
			$href_builder['order'] = $triangle == 1 ? 'desc' : 'asc';
			$this->sort_ui_hrefs[$sort_key] = ['href' => http_build_query($href_builder), 'triangle_html' => ''];
			switch($triangle){
				case 1:
					$this->sort_ui_hrefs[$sort_key]['triangle_html'] = '<span class="sort_triangle">▲</span>';
					break;
				case -1:
					$this->sort_ui_hrefs[$sort_key]['triangle_html'] = '<span class="sort_triangle">▼</span>';
					break;
				default:
					break;
			}
		}
	}

	/**
	 * устанавливает $this->player_sex
	 * изменяет $this->where_query
	 * @param string $player_sex
	 */
	protected function player_sex_validation($player_sex){
		//выборка типов пользователя
		switch($player_sex){
			case 'm':
			case 'male':
			case 'man':
			case 'м':
			case 'муж':
				$this->player_sex = 'm';
				$this->add_and_where_query('`sex` = 0');
				break;
			case 'f':
			case 'w':
			case 'female':
			case 'woman':
			case 'ж':
			case 'жен':
				$this->player_sex = 'f';
				$this->add_and_where_query('`sex` = 1');
				break;
			case 'all':
			default:
				$this->player_sex = 'all';
				break;
		}
	}

	/**
	 * устанавливает $this->sort
	 * устанавливает $this->sort_order
	 * может подключить join с текущими рейтингами
	 * @param string $sort
	 * @param string $order
	 */
	protected function sort_validation($sort, $order){
		if(!isset(self::SORT_COLUMNS[$sort])){
			$this->sort = $this->DEFAULT_SORT;
			$this->sort_order = $this->DEFAULT_SORT_ORDER;
		}else{
			$this->sort = $sort;
			$this->sort_order = ($order !== 'desc' && $order !== 'asc') ? 'asc' : $order;
		}
		if(mb_substr($this->sort, 0, 6) === 'rating'){
			$this->join_current_ratings = true;
		}
	}

	/**
	 * устанавливает $this->hide_empty_players
	 * изменяет $this->where_query
	 * может подключить join с текущими рейтингами
	 * @param bool $hide
	 */
	protected function hide_empty_players_validation($hide){
		$this->hide_empty_players = !!$hide;
		if($this->hide_empty_players){
			$this->add_and_where_query('NOT(`rating_ru_s` IS NULL AND `rating_fi_s` IS NULL AND `rating_ru_r` IS NULL AND `rating_fi_r` IS NULL AND `rating_ru_b` IS NULL AND `rating_fi_b` IS NULL)');
			$this->join_current_ratings = true;
		}
	}

	/**
	 * устанавливает $this->search_value
	 * изменяет $this->where_query
	 * @param string $q
	 */
	protected function search_value_validation($q){
		global $wpdb;
		$this->search_value = $q;
		
		if(mb_strlen($this->search_value)){
			$like_search_value = '%'.$wpdb->esc_like($this->search_value).'%';
			$this->add_and_where_query($wpdb->prepare('(`p`.`id_ruchess` LIKE %s OR `id_fide` LIKE %s OR `name` LIKE %s OR `birth_year` LIKE %s)', $like_search_value, $like_search_value, $like_search_value, $like_search_value));
		}
	}

	/**
	 * устанавливает $this->elements_per_page
	 * @param int $elem_per_page
	 */
	protected function elements_per_page_validation($elem_per_page){
		if($elem_per_page < 5){
			$elem_per_page = 5;
		}
		if($elem_per_page > 300){
			$elem_per_page = 300;
		}
		$this->elements_per_page = $elem_per_page;
	}

	/**
	 * устанавливает $this->current_page
	 * $this->max_page должене быть инициализирован
	 * @param int $curr_page
	 */
	protected function current_page_validation($curr_page){
		if($curr_page < 1)
			$curr_page = 1;
		if($curr_page > $this->max_page)
			$curr_page = $this->max_page;
		
		$this->current_page = $curr_page;
	}

	protected function add_and_where_query($query){
		if(mb_strlen($this->where_query)){
			$this->where_query .= ' AND';
		}else{
			$this->where_query = 'WHERE';
		}

		$this->where_query .= ' '.$query;
	}
	
	
	public function __get($key){
		switch($key){
			case 'player_sex':
			case 'hide_empty_players':
			case 'search_value':
			case 'sort':
			case 'sort_order':
			case 'players_count':
			case 'elements_per_page':
			case 'max_page':
			case 'current_page':
				return $this->$key;
			default:
				throw new \Exception('Undefined property '.$key);
		}
		
	}

	/**
	 * @param string|null $key - ключ к ссылке
	 * @return string|string[]
	 */
	public function get_pages_href($key=null){
		if(is_null($key))
			return $this->pages_hrefs;
		return $this->pages_hrefs[$key];
	}

	/**
	 * @param string|null $key - ключ к ссылке
	 * @return string|string[]
	 */
	public function get_sex_options_href($key=null){
		if(is_null($key))
			return $this->sex_options_hrefs;
		return $this->sex_options_hrefs[$key];
	}

	/**
	 * @param string|null $key - ключ к ссылке
	 * @return string|string[]
	 */
	public function get_hide_empty_options_href($key=null){
		if(is_null($key))
			return $this->hide_empty_options_hrefs;
		return $this->hide_empty_options_hrefs[$key];
	}

	/**
	 * @param string|null $key - ключ к ссылке
	 * @return string[]|array
	 */
	public function get_sort_ui_href($key=null){
		if(is_null($key))
			return $this->sort_ui_hrefs;
		return $this->sort_ui_hrefs[$key];
	}

	/**
	 * Возвращает записи о игроках основываясь на опциях и пагинации
	 * @return array
	 */
	public function get_players(){
		global $wpdb;
		$left_join = $this->join_current_ratings ? $this->JOIN_CURRENT_RATINGS_STR : '';
		
		$players_res = $wpdb->get_results(
			'SELECT `p`.`id_ruchess`, `id_fide`, `name`, `sex`, `birth_year`
			FROM `'.$wpdb->prefix.'rad_chess_players` AS `p`
			'.$left_join.'
			'.$this->where_query.'
			ORDER BY `'.$this->sort.'` '.mb_strtoupper($this->sort_order).'
			'.$wpdb->prepare('LIMIT %d OFFSET %d', $this->elements_per_page, ($this->current_page-1)*$this->elements_per_page), ARRAY_A);

		$players = [];

		$len = sizeof($players_res);
		for($i=0; $i<$len; $i++){
			$players[$players_res[$i]['id_ruchess']] = $players_res[$i];
			unset($players_res[$i]);
		}
		return $players;
	}
}