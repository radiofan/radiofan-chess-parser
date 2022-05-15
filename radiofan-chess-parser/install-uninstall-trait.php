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

		dbDelta([$sql_rad_chess_players, $sql_rad_chess_players_ratings]);

		error_log('activate');//todo delme
	}

	public function deactivate(){
		if(!current_user_can('activate_plugins'))
			return;
		$plugin = $_REQUEST['plugin'] ?? '';
		check_admin_referer('deactivate-plugin_'.$plugin);

		error_log('deactivate');//todo
	}

	public static function uninstall(){
		if(!current_user_can('activate_plugins'))
			return;

		if(!defined('WP_UNINSTALL_PLUGIN'))
			return;

		global $wpdb;
		$wpdb->query('DROP TABLE '.$wpdb->prefix.'rad_chess_players_ratings, '.$wpdb->prefix.'rad_chess_players');
	}
}