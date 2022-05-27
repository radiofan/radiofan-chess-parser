<?php
namespace Radiofan\ChessParser;

if(!defined('ABSPATH')) die();

if(!class_exists('WP_List_Table')){
	require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

//todo удаление
//todo текущие рейтинги
//todo активация (может быть)

class PlayersTable extends \WP_List_Table{
	public function __construct(){
		parent::__construct([
			'singular'	=> 'Player',
			'plural'	=> 'Players',
			'ajax'		=> false
		]);

		$this->bulk_action_handler();

		//Добавляем настройки пагинации вверху экрана
		add_screen_option('per_page', array(
			'label'		=> 'Показывать на странице: ',
			'default'	=> 50,
			'option'	=> 'radiofan_chess_parser__players_per_page',//Ключ в таблице метаюзерс
		));

		$this->prepare_items();

		//Добавляем стили
		wp_add_inline_style('admin-menu', 'table.players .column-id_ruchess{width:7em;} table.players .column-id_fide{width:7em;} table.players .column-sex{width:5em;} table.players .column-country{width:6em;} table.players .column-birth_year{width:9em;} table.players .column-region_number{width:10em;}');
	}

	//Заполняем таблицу
	public function prepare_items(){
		global $wpdb;

		//Настройки пагинации
		$per_page = get_user_meta(get_current_user_id(), get_current_screen()->get_option('per_page', 'option'), true) ?: 50;
		$count_page = (int) $wpdb->get_var('SELECT COUNT(*) FROM '.$wpdb->prefix.'rad_chess_players');

		$this->set_pagination_args([
			'total_items'	=> $count_page,//Количество элементов всего
			'per_page'		=> $per_page,//Количество элементов отображаемых на странице
		]);
		//Создаём отступ записей
		$offset = (int) ($this->get_pagenum() - 1)*$per_page;

		//Получаем элементы
		$query = 'SELECT `id_ruchess`, `id_fide`, `name`, `sex`, `country`, `birth_year`, `region_number`, `region_name` FROM '.$wpdb->prefix.'rad_chess_players';

		//Сортировка
		$oredrby = '`id_ruchess`';
		if(isset($_GET['orderby']) && isset($_GET['order'])){
			if(array_key_exists($_GET['orderby'], $this->get_sortable_columns())){
				$oredrby = '`' . mb_strtolower($_GET['orderby']) . '`';
				if($_GET['order'] == 'desc'){
					$oredrby .= ' DESC';
				}
			}
		}
		$query .= ' ORDER BY '.$oredrby.' LIMIT '.$offset.', '.$per_page;
		$this->items = $wpdb->get_results($query, OBJECT);
	}

	//Колонки таблицы
	public function get_columns(){
		return [
			'cb'			=> '<input type="checkbox">',
			'id_ruchess'	=> 'ID ruchess',
			'id_fide'		=> 'ID fide',
			'name'			=> 'ФИО',
			'sex'			=> 'Пол',
			'country'		=> 'Страна',
			'birth_year'	=> 'Год рождения',
			'region_number'	=> 'Номер региона',
			'region_name'	=> 'Назв. региона',
		];
	}

	//Сортируемые колонки
	protected function get_sortable_columns(){
		return [
			'id_ruchess'	=> ['id_ruchess', true],
			'id_fide'		=> 'id_fide',
			'name'			=> 'name',
			'sex'			=> 'sex',
			'country'		=> 'country',
			'birth_year'	=> 'birth_year',
			'region_number'	=> 'region_number',
			'region_name'	=> 'region_name',
		];
	}

	protected function get_bulk_actions(){
		return [];
	}

	//Элементы управления таблицей. Расположены между групповыми действиями и пагинацией.
	protected function extra_tablenav($which){
		
	}

	//Вывод неуказанной ячейки таблицы
	protected function column_default($item, $colname){
		//Выводим город откуда
		if($colname === 'sex'){
			return $item->sex ? 'ж' : 'м'; 
		}else{
			return isset($item->$colname) ? esc_html($item->$colname) : '';
		}
	}

	//Заполнение колонки чекбоксов
	protected function column_cb($item){
		echo '<input type="checkbox" name="licids[]" id="cb-select-' . $item->id_ruchess . '" value="' . $item->id_ruchess . '" />';
	}

	//Обработка действий
	private function bulk_action_handler(){
		if(empty($_REQUEST['licids']) || empty($_REQUEST['_wpnonce']))
			return;

		$action = $this->current_action();
		if(!$action)
			return;

		check_admin_referer('bulk-'.$this->_args['plural']);
		
		//обработка
	}
}