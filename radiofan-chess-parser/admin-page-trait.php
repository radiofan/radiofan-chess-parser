<?php
namespace Radiofan\ChessParser;

trait AdminPage{
	/** @var null|PlayersTable $PlayersTable */
	private $PlayersTable = null;
	
	/**
	 * добавляет разделы меню связанные с парсером
	 * Данные игроков
	 * Настройки Chess Parser
	 * 
	 */
	public function add_admin_menu_item(){
		
		add_menu_page(
			'Данные игроков',
			'Chess Parser',
			'edit_pages',
			'radiofan_chess_parser',
			[$this, 'view_players_page'],
			'none',
			61
		);

		$hook = add_submenu_page(
			'radiofan_chess_parser',
			'Данные игроков',
			'Данные игроков',
			'edit_pages',
			'radiofan_chess_parser'
		);

		if($hook !== false){
			wp_add_inline_style('admin-menu', '#toplevel_page_radiofan_chess_parser .wp-menu-image:before {content: "\\265E";}');

			add_action('load-'.$hook, [$this, 'init_players_page']);
		}

		$hook = add_submenu_page(
			'radiofan_chess_parser',
			'Настройки Chess Parser',
			'Настройки',
			'manage_options',
			'radiofan_chess_parser__settings',
			[$this, 'view_settings_page']
		);
		if($hook){
			add_action('load-'.$hook, [$this, 'init_settings_page']);
		}

		$hook = add_submenu_page(
			'radiofan_chess_parser',
			'Логи Chess Parser',
			'Логи',
			'edit_pages',
			'radiofan_chess_parser__logs',
			[$this, 'view_logs_page']
		);
		add_action('load-'.$hook, [$this, 'init_logs_page']);
		
	}

	/**
	 * вывод страницы с таблицей игроков
	 */
	public function view_players_page(){
		if(!current_user_can('edit_pages')){
			wp_nonce_ays('');
			return;
		}
		?>
		<div class="wrap">
			<h2><?= get_admin_page_title(); ?></h2>
			<hr>
			<form action="" method="POST">
				<?php $this->PlayersTable->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * провизводит действия до загрузки страницы игроков
	 * создает таблицу для вывода
	 */
	public function init_players_page(){
		require_once 'players-table-class.php';
		$this->PlayersTable = new PlayersTable();
	}

	/**
	 * метод вывода страницы с настройками
	 */
	public function view_settings_page(){
		if(!current_user_can('manage_options')){
			wp_nonce_ays('');
			return;
		}
		
		echo '<div class="wrap">
			<h1 class="wp-heading-inline">'.get_admin_page_title().'</h1>
			<a href="?page=radiofan_chess_parser__settings&action=radiofan_chess_parser__update_current_ratings&_wpnonce='.wp_create_nonce('radiofan_chess_parser__update_current_ratings').'" class="page-title-action">Обновить текущие рейтинги</a>';

		settings_errors();
		echo '<form method="POST" action="options.php">';
		settings_fields('radiofan_chess_parser_fields');
		do_settings_sections('radiofan_chess_parser__settings');
		submit_button();
		echo '</form>
		</div>';
		

	}

	/**
	 * провизводит действия до загрузки страницы настроек
	 * обрабатывает событие обновления текущих рейтингов
	 * @see AdminPage::action_update_current_ratings
	 */
	public function init_settings_page(){
		$this->action_update_current_ratings();
	}

	/**
	 * инициализурует секции настроек плагина
	 */
	public function settings_init(){
		//Добавляем блок опций
		add_settings_section(
			'radiofan_chess_parser_section',
			'',
			'',
			'radiofan_chess_parser__settings'
		);

		//Добавляем поля опций
		add_settings_field(
			'radiofan_chess_parser__import_filter',
			'Фильтр игроков для их вставки (обновления) в БД',
			function(){
				echo '<span style="color:red;">ФИЛЬТР ЯВЛЯЕТСЯ ИСПОЛНЯЕМЫМ PHP КОДОМ!!! Вносить изменения только при полной уверенности</span><br>
				Доступные переменные -
				<ul>
					<li><code>int $id_ruchess</code> - id игрока из системы ruchess</li>
					<li><code>null|int $id_fide</code> - id игрока из системы fide</li>
					<li><code>string $name</code> - ФИО игрока</li>
					<li><code>bool $sex</code> - пол игрока (0 - м, 1 - ж)</li>
					<li><code>string $country</code> - код страны</li>
					<li><code>null|int $birth_year</code> - год рождения</li>
					<li><code>int $region_number</code> - номер региона</li>
					<li><code>int $region_name</code> - наименование региона</li>
				</ul>
				Код должен <span style="color:red;">возращать bool</span> (или присваивать переменной $accept), true - игрок подходит<br>
				<textarea
					name="radiofan_chess_parser__import_filter"
					id="radiofan_chess_parser__import_filter"
					readonly="readonly"
					cols="100"
					rows="10"
					style="font-family:\'courier new\', monospace;"
				>'.esc_html(get_option('radiofan_chess_parser__import_filter', '')).'</textarea>';
			},
			'radiofan_chess_parser__settings',
			'radiofan_chess_parser_section'
		);
		add_settings_field(
			'radiofan_chess_parser__players_update',
			'Обноволение игроков',
			function(){
				echo '<input
					type="checkbox"
					name="radiofan_chess_parser__players_update"
					id="radiofan_chess_parser__players_update"
					value="1"';
				checked(get_option('radiofan_chess_parser__players_update',false));
				echo '>';
			},
			'radiofan_chess_parser__settings',
			'radiofan_chess_parser_section'
		);
		add_settings_field(
			'radiofan_chess_parser__default_sort',
			'Столбец сортировки таблицы по умолчанию',
			function(){
				$sort_v = [
					'name' => 'ФИО',
					'id_ruchess' => 'ФШР ID',
					'id_fide' => 'FIDE ID',
					'birth_year' => 'Год рождения',
					'rating_ru_s' => 'Классика ФШР',
					'rating_fi_s' => 'Классика FIDE',
					'rating_ru_r' => 'Рапид ФШР',
					'rating_fi_r' => 'Рапид FIDE',
					'rating_ru_b' => 'Блиц ФШР',
					'rating_fi_b' => 'Блиц FIDE',
				];
				$val = get_option('radiofan_chess_parser__default_sort',PlayersTableOptions::DEFAULT_SORT_STR);
				echo '<select name="radiofan_chess_parser__default_sort" id="radiofan_chess_parser__default_sort">';
				foreach($sort_v as $key => $text){
					echo '<option value="'.$key.'"'.($val === $key ? ' selected="selected"' : '').'>'.$text.'</option>';
				}
				echo '</select>';
			},
			'radiofan_chess_parser__settings',
			'radiofan_chess_parser_section'
		);
		add_settings_field(
			'radiofan_chess_parser__default_sort_order',
			'Направление сортировки таблицы по умолчанию',
			function(){
				$val = get_option('radiofan_chess_parser__default_sort_order',PlayersTableOptions::DEFAULT_SORT_ORDER_STR);
				echo '<select name="radiofan_chess_parser__default_sort_order" id="radiofan_chess_parser__default_sort_order">
					<option value="asc"'.($val === 'asc' ? ' selected="selected"' : '').'>Возрастание</option>
					<option value="desc"'.($val === 'desc' ? ' selected="selected"' : '').'>Убывание</option>
				</select>';
			},
			'radiofan_chess_parser__settings',
			'radiofan_chess_parser_section'
		);

		//Регистрируем опции по умолчанию 0, и функция валидации чисел
		register_setting('radiofan_chess_parser_fields', 'radiofan_chess_parser__import_filter');
		register_setting(
			'radiofan_chess_parser_fields',
			'radiofan_chess_parser__players_update',
			[
				'type' => 'bool',
				'sanitize_callback' => function($val){
					return !!$val;
				},
				'show_in_rest' => false,
				'default' => false
			]
		);
		register_setting(
			'radiofan_chess_parser_fields',
			'radiofan_chess_parser__default_sort',
			[
				'type' => 'string',
				'sanitize_callback' => function($val){
					$sort_v = [
						'name' => 'ФИО',
						'id_ruchess' => 'ФШР ID',
						'id_fide' => 'FIDE ID',
						'birth_year' => 'Год рождения',
						'rating_ru_s' => 'Классика ФШР',
						'rating_fi_s' => 'Классика FIDE',
						'rating_ru_r' => 'Рапид ФШР',
						'rating_fi_r' => 'Рапид FIDE',
						'rating_ru_b' => 'Блиц ФШР',
						'rating_fi_b' => 'Блиц FIDE',
					];
					if(isset($sort_v[$val]))
						return $val;
					
					return PlayersTableOptions::DEFAULT_SORT_STR;
				},
				'show_in_rest' => false,
				'default' => PlayersTableOptions::DEFAULT_SORT_STR
			]
		);
		register_setting(
			'radiofan_chess_parser_fields',
			'radiofan_chess_parser__default_sort_order',
			[
				'type' => 'string',
				'sanitize_callback' => function($val){
					if($val === 'desc' || $val === 'asc')
						return $val;

					return PlayersTableOptions::DEFAULT_SORT_ORDER_STR;
				},
				'show_in_rest' => false,
				'default' => PlayersTableOptions::DEFAULT_SORT_ORDER_STR
			]
		);
	}

	/**
	 * метод вывода страницы с логами
	 */
	public function view_logs_page(){
		if(!current_user_can('edit_pages')){
			wp_nonce_ays('');
			return;
		}

		echo '<div class="wrap">
			<a href="?page=radiofan_chess_parser__logs&action=radiofan_chess_parser__clear_logs&_wpnonce='.wp_create_nonce('radiofan_chess_parser__clear_logs').'" class="page-title-action">Очистить логи</a>
			<h1 class="wp-heading-inline">'.get_admin_page_title().'</h1>
			<hr>
		';
		global $wpdb;
		$res = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'rad_chess_logs', ARRAY_A);

		$len = sizeof($res);
		for($i=0; $i<$len; $i++){
			echo '<div class="notice'.($res[$i]['type'] ? ' notice-'.esc_attr($res[$i]['type']) : '').'">
				<p><code>'.$res[$i]['log_time'].'</code>:&nbsp;&nbsp;&nbsp;&nbsp;'.esc_html($res[$i]['content']).'</p>
				<div class="spoiler-wrap">
					<div class="spoiler-head folded">Доп. данные</div>
					<div class="spoiler-body"><pre>'.esc_html($res[$i]['data']).'</pre></div>
				</div>
			</div>';
		}
		echo '</div>';
	}

	/**
	 * провизводит действия до загрузки страницы логов
	 * подключает доп стили и скрипты
	 * обрабатывает событие очистки таблицы rad_chess_logs
	 * @see AdminPage::action_clear_logs
	 */
	public function init_logs_page(){
		//подключаем скрипты и стили спойлера
		wp_add_inline_style('admin-menu', '.spoiler-wrap{border:1px solid #c3c4c7;margin:.5em 0;} .spoiler-head{padding:3px;cursor:pointer;} .folded:before{content:"+";margin-right:5px;} .unfolded:before{content:"–";margin-right:5px;} .spoiler-body{display:none;padding:3px;border-top:1px solid #c3c4c7;background-color:rgba(0,0,0,.07);}');
		wp_add_inline_script('jquery', 'jQuery(document).ready(function($){$(".spoiler-head").click(function(e){$(this).toggleClass("folded").toggleClass("unfolded").next().toggle();});});');

		$this->action_clear_logs();
	}

	/**
	 * Событие очистки таблицы rad_chess_logs
	 * требуется $_REQUEST['action'] == 'radiofan_chess_parser__clear_logs' и wpnonce('radiofan_chess_parser__clear_logs')
	 */
	protected function action_clear_logs(){
		if(empty($_REQUEST['_wpnonce']) || empty($_REQUEST['action']) || $_REQUEST['action'] != 'radiofan_chess_parser__clear_logs'){
			return;
		}

		if(!current_user_can('edit_pages')){
			wp_nonce_ays('');
			return;
		}

		check_admin_referer('radiofan_chess_parser__clear_logs');
		
		global $wpdb;
		$wpdb->query('TRUNCATE TABLE '.$wpdb->prefix.'rad_chess_logs');

		wp_redirect(self_admin_url('admin.php?page=radiofan_chess_parser__logs'));
		exit;
	}

	/**
	 * Событие обновления текущих рейтингов
	 * заполняет таблицу rad_chess_current_ratings текущими рейтингами из таблицы rad_chess_players_ratings
	 * требуется $_REQUEST['action'] == 'radiofan_chess_parser__update_current_ratings' и wpnonce('radiofan_chess_parser__update_current_ratings')
	 */
	protected function action_update_current_ratings(){
		if(empty($_REQUEST['_wpnonce']) || empty($_REQUEST['action']) || $_REQUEST['action'] != 'radiofan_chess_parser__update_current_ratings'){
			return;
		}

		if(!current_user_can('manage_options')){
			wp_nonce_ays('');
			return;
		}

		check_admin_referer('radiofan_chess_parser__update_current_ratings');
		
		$time_statistic = ['current_ratings_update_start' => microtime(1)];

		global $wpdb;
		$wpdb->query('TRUNCATE TABLE '.$wpdb->prefix.'rad_chess_current_ratings');
		
		$players_c = $wpdb->get_var('SELECT COUNT(*) FROM '.$wpdb->prefix.'rad_chess_players');
		for($player_start = 0; $player_start < $players_c; $player_start += 500){
			$players = $wpdb->get_col('SELECT `id_ruchess` FROM '.$wpdb->prefix.'rad_chess_players LIMIT 500 OFFSET '.$player_start);
			$players_ratings = $this->get_players_ratings($players);
			$insert_query = 'INSERT INTO '.$wpdb->prefix.'rad_chess_current_ratings (`id_ruchess`, `rating_ru_s`, `rating_fi_s`, `rating_ru_r`, `rating_fi_r`, `rating_ru_b`, `rating_fi_b`) VALUES ';
			$type_converter = [
				1 => 'rating_ru_s',
				2 => 'rating_fi_s',
				3 => 'rating_ru_r',
				4 => 'rating_fi_r',
				5 => 'rating_ru_b',
				6 => 'rating_fi_b',
			];
			foreach($players_ratings as $player_id => $all_ratings){
				if(empty($all_ratings))
					continue;
				$insert_values = [
					'id_ruchess' => $player_id,
					'rating_ru_s' => 'NULL',
					'rating_fi_s' => 'NULL',
					'rating_ru_r' => 'NULL',
					'rating_fi_r' => 'NULL',
					'rating_ru_b' => 'NULL',
					'rating_fi_b' => 'NULL',
				];
				foreach($all_ratings as $r_type => $ratings){
					if(!isset($type_converter[$r_type])){
						rad_log::log('update_current_ratings_warning: Неопознанный тип рейтинга '.$r_type, 'warning', 'player ruchess_id: '.$player_id);
						continue;
					}
					$insert_values[$type_converter[$r_type]] = $ratings[0]['rating'];
				}
				$insert_query .= '('.implode(', ', $insert_values).'), ';
			}
			$insert_query = mb_substr($insert_query, 0, -2);
			if($wpdb->query($insert_query) === false){
				rad_log::log('update_current_ratings_error: Не удалось обновить текущий рейтинг', 'error', [$insert_query, $wpdb->last_error]);
			}
		}

		$time_statistic['current_ratings_update_end'] = microtime(1);
		$time_statistic['current_ratings_update_time'] = $time_statistic['current_ratings_update_end'] - $time_statistic['current_ratings_update_start'];
		rad_log::log('update_current_ratings_success: Текущие рейтинги обновлены', 'success', $time_statistic);

		wp_redirect(self_admin_url('admin.php?page=radiofan_chess_parser__logs'));
		exit;
	}
}