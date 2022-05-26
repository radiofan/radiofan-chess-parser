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
		?>
		<div class="wrap">
			<h2><?= get_admin_page_title() ?></h2>
		</div>
		<?php

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