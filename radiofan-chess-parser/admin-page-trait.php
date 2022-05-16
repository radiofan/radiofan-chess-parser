<?php
namespace Radiofan\ChessParser;

trait AdminPage{

	public function view_notices(){
		$screen = get_current_screen();
		$test_logs_page = mb_substr($screen->id, -mb_strlen('radiofan_chess_parser__logs'));
		if($test_logs_page === 'radiofan_chess_parser__logs'){
			$this->view_logs_notices();
		}
	}
	
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

		add_submenu_page(
			'radiofan_chess_parser',
			'Логи Chess Parser',
			'Логи',
			'edit_pages',
			'radiofan_chess_parser__logs',
			[$this, 'view_logs_page']
		);
	}

	public function view_players_page(){
		if(!current_user_can('edit_pages')){
			wp_nonce_ays('');
			return;
		}
		?>
		<div class="wrap">
			<h2> <?= get_admin_page_title() ?></h2>
		</div>
		<?php
	}

	public function view_settings_page(){
		if(!current_user_can('manage_options')){
			wp_nonce_ays('');
			return;
		}
		?>
		<div class="wrap">
			<h2> <?= get_admin_page_title() ?></h2>
		</div>
		<?php

	}

	public function view_logs_page(){
		if(!current_user_can('edit_pages')){
			wp_nonce_ays('');
			return;
		}
		?>
		<div class="wrap">
			<h2> <?= get_admin_page_title() ?></h2>
		</div>
		<?php
	}
	
	protected function view_logs_notices(){
		
	}
}