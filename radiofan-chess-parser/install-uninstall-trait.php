<?php
namespace Radiofan\ChessParser;

trait InstallUninstall{

	public function activate(){
		if(!current_user_can('activate_plugins'))
			return;
		$plugin = $_REQUEST['plugin'] ?? '';
		check_admin_referer('activate-plugin_'.$plugin);

		//создаем таблицы
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;

		//rad_chess_players
		$sql_rad_chess_players =
			'CREATE TABLE '.$wpdb->prefix.'rad_chess_players (
				id_ruchess    bigint(11) unsigned NOT NULL,
				id_fide       bigint(11) unsigned,
				name          text                NOT NULL,
				sex           bool                NOT NULL COMMENT \'0 - м, 1 - ж\',
				country       tinytext            NOT NULL,
				birth_year    year,
				region_number smallint   unsigned NOT NULL default \'0\',
				region_name   text                NOT NULL default \'\',
				PRIMARY KEY  (id_ruchess)
			) DEFAULT CHARACTER SET '.$wpdb->charset.' COLLATE '.$wpdb->collate.';';

		//rad_chess_players_ratings
		$sql_rad_chess_players_ratings =
			'CREATE TABLE '.$wpdb->prefix.'rad_chess_players_ratings (
				id_ruchess    bigint(11) unsigned NOT NULL,
				rating_type   tinyint    unsigned NOT NULL default \'0\' COMMENT \'1 - ruchess standard, 2 - fide standard, 3 - ruchess rapid, 4 - fide rapid, 5 - ruchess blitz, 6 - fide blitz\',
				rating        int        unsigned NOT NULL,
				update_date   timestamp           NOT NULL default CURRENT_TIMESTAMP,
				KEY key_ratings_id_ruchess (id_ruchess)
			) DEFAULT CHARACTER SET '.$wpdb->charset.' COLLATE '.$wpdb->collate.';';

		//rad_chess_current_ratings
		$sql_rad_chess_current_ratings =
			'CREATE TABLE '.$wpdb->prefix.'rad_chess_current_ratings (
				id_ruchess    bigint(11) unsigned NOT NULL,
				rating_ru_s   int        unsigned NULL default NULL COMMENT \'ruchess standard\',
				rating_fi_s   int        unsigned NULL default NULL COMMENT \'fide standard\',
				rating_ru_r   int        unsigned NULL default NULL COMMENT \'ruchess rapid\',
				rating_fi_r   int        unsigned NULL default NULL COMMENT \'fide rapid\',
				rating_ru_b   int        unsigned NULL default NULL COMMENT \'ruchess blitz\',
				rating_fi_b   int        unsigned NULL default NULL COMMENT \'fide blitz\',
				PRIMARY KEY  (id_ruchess)
			) DEFAULT CHARACTER SET '.$wpdb->charset.' COLLATE '.$wpdb->collate.';';


		//rad_chess_logs
		$sql_rad_chess_logs =
			'CREATE TABLE '.$wpdb->prefix.'rad_chess_logs (
				log_time      timestamp           NOT NULL default CURRENT_TIMESTAMP,
				content       mediumtext          NOT NULL,
				type          tinytext            NOT NULL,
				data          mediumtext          NOT NULL
			) DEFAULT CHARACTER SET '.$wpdb->charset.' COLLATE '.$wpdb->collate.';';

		dbDelta([$sql_rad_chess_players, $sql_rad_chess_players_ratings, $sql_rad_chess_current_ratings, $sql_rad_chess_logs]);
		
		
		//создание cron задачи обновления
		wp_unschedule_hook('radiofan_chess_parser_parse');
		//первый запуск в 0:00 след. дня
		$time = current_datetime();
		$time = $time->setTime(0, 0);
		$time = $time->add(new \DateInterval('P1D'));
		
		//для каждого типа создадим свою cron задачу с интервалом в 10 мин
		foreach(self::GAME_TYPE as $type_id => $type){
			wp_schedule_event($time->getTimestamp(), 'daily', 'radiofan_chess_parser_parse', [$type, $type_id]);
			$time = $time->add(new \DateInterval('PT10M'));
		}
		
		//создадим опции плагина
		add_option('radiofan_chess_parser__import_filter', '', '', 'no');
		add_option('radiofan_chess_parser__players_update', '', '', 'no');
		add_option('radiofan_chess_parser__default_sort', PlayersTableOptions::DEFAULT_SORT_STR, '', 'no');
		add_option('radiofan_chess_parser__default_sort_order', PlayersTableOptions::DEFAULT_SORT_ORDER_STR, '', 'no');
		add_option('radiofan_chess_parser__hide_rating_date', false, '', 'no');
	}

	public function deactivate(){
		if(!current_user_can('activate_plugins'))
			return;
		$plugin = $_REQUEST['plugin'] ?? '';
		check_admin_referer('deactivate-plugin_'.$plugin);
		
		//чистим логи
		global $wpdb;
		$wpdb->query('TRUNCATE TABLE '.$wpdb->prefix.'rad_chess_logs');
		
		//отключаем cron
		wp_unschedule_hook('radiofan_chess_parser_parse');

		//удаляем опции парсинга
		foreach(self::GAME_TYPE as $type_id => $type){
			delete_option('radiofan_chess_parser__etag_'.$type_id);
			delete_option('radiofan_chess_parser__players_hash_'.$type_id);
			delete_option('radiofan_chess_parser__ratings_hash_'.$type_id);
		}
	}

	public static function uninstall(){
		if(!current_user_can('activate_plugins'))
			return;

		if(!defined('WP_UNINSTALL_PLUGIN'))
			return;
		
		//удаляем таблицы
		global $wpdb;
		$wpdb->query('DROP TABLE '.$wpdb->prefix.'rad_chess_players_ratings, '.$wpdb->prefix.'rad_chess_players, '.$wpdb->prefix.'rad_chess_current_ratings, '.$wpdb->prefix.'rad_chess_logs');
		
		//удаляем опции
		delete_option('radiofan_chess_parser__import_filter');
		delete_option('radiofan_chess_parser__players_update');
		delete_option('radiofan_chess_parser__default_sort');
		delete_option('radiofan_chess_parser__default_sort_order');
		delete_option('radiofan_chess_parser__hide_rating_date');
		foreach(self::GAME_TYPE as $type_id => $type){
			delete_option('radiofan_chess_parser__etag_'.$type_id);
			delete_option('radiofan_chess_parser__players_hash_'.$type_id);
			delete_option('radiofan_chess_parser__ratings_hash_'.$type_id);
		}
	}
}