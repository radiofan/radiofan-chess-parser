<?php
namespace Radiofan\ChessParser;

trait View{

	public function enqueue_scripts(){
		wp_enqueue_script('radiofan_chess_parser___script_top_scoreboard', $this->plugin_url.'assets/top-scoreboard.js', ['jquery'], filemtime($this->plugin_dir.'assets/top-scoreboard.js'), 1);
		wp_enqueue_style('radiofan_chess_parser__style_top_scoreboard', $this->plugin_url.'assets/top-scoreboard.css', false, filemtime($this->plugin_dir.'assets/top-scoreboard.css'));
	}
	
	public function view_top_scoreboard($atts, $content){
		
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
		$ret =  '
<div class="tourn-top__top col-lg-4 col-md-12">
	<h3>Топ Алтайского края</h3>
	<div class="chess-top-block">
		<div class="chess-top-block__header tab-header">
			<div class="tab tab-man-ruchess active" data-box="man-ruchess">
				<h5>Мужчины (ruchess)</h5>
			</div>
			<div class="tab tab-woman-ruchess" data-box="woman-ruchess">
				<h5>Женщины (ruchess)</h5>
			</div>
			<div class="tab tab-man-fide" data-box="man-fide">
				<h5>Мужчины (fide)</h5>
			</div>
			<div class="tab tab-woman-fide" data-box="woman-fide">
				<h5>Женщины (fide)</h5>
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
		
		$ret .='
		</div>
		<a href="/chess-players/rating/" class="top__other">Рейтинг-лист Алтайского края</a>
	</div>
</div>
';
		return $ret;
	}

	/**
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
				$woman_top->add($tmp);
			}else{//man
				$man_top->add($tmp);
			}
		}

		$this->save_top($man_top->get_top_desc(), 'man', $rating_type);
		$this->save_top($woman_top->get_top_desc(), 'woman', $rating_type);
	}

	protected function save_top($top, $sex_type, $rating_type){
		global $wpdb;
		$query_str = '';
		$len = sizeof($top);
		if(!$len)
			return false;
		for($i = 0; $i < $len; $i++){
			$query_str .= absint($top[$i]['id_ruchess']).', ';
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
		}
		
		update_option('radiofan_chess_parser__top_'.$sex_type.'_'.$rating_type, $top, false);
		return true;
	}
}

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

	public function get_top_desc(){
		return array_reverse($this->container);
	}

	public function get_top_asc(){
		return $this->container;
	}
	
	public static function compare_ratings($addable, $added){
		return $addable['rating'] < $added['rating'] ? -1 : ($addable['rating'] == $added['rating'] ? 0 : 1);
	}
}
