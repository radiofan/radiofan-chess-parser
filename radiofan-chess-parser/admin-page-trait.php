<?php
namespace Radiofan\ChessParser;

trait AdminPage{
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
		}
		/*
		add_action("load-$hook", 'radС_edit_create_course');
		//add_action("load-$hook", 'radС_import_export_excel');
		add_action("load-$hook", 'radС_price_table_load');
		*/
		
		add_submenu_page(
			'radiofan_chess_parser',
			'Настройки Chess Parser',
			'Настройки',
			'manage_options',
			'radiofan_chess_parser__settings',
			[$this, 'view_settings_page']
		);

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
	 * провизводит действия до загрузки страницы логов
	 */
	public function init_logs_page(){
		//подключаем скрипты и стили спойлера
		wp_add_inline_style('admin-menu', '.spoiler-wrap{border:1px solid #c3c4c7;margin:.5em 0;} .spoiler-head{padding:3px;cursor:pointer;} .folded:before{content:"+";margin-right:5px;} .unfolded:before{content:"–";margin-right:5px;} .spoiler-body{display:none;padding:3px;border-top:1px solid #c3c4c7;background-color:background:rgba(0,0,0,.07);}');
		wp_add_inline_script('jquery', 'jQuery(document).ready(function($){$(".spoiler-head").click(function(e){$(this).toggleClass("folded").toggleClass("unfolded").next().toggle();});});');
		
		$this->action_clear_logs();
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
			<h2><?= get_admin_page_title() ?></h2>
		</div>
		<?php
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
			<h2>'.get_admin_page_title().'</h2>';

		settings_errors();
		echo '<form method="POST" action="options.php">';
		settings_fields('radiofan_chess_parser_fields');
		do_settings_sections('radiofan_chess_parser__settings');
		submit_button();
		echo '</form>
		</div>';
		

	}

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
				Код должен <span style="color:red;">возращать bool</span> (или присваивать перемнной $accept), true - игрок подходит<br>
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
	 * Событе очистки таблицы rad_chess_logs
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
}