<?php
/**
 * @wordpress-plugin
 * Plugin Name: RADIOFAN Chess Parser
 * Plugin URI: https://github.com/radiofan/radiofan-chess-parser
 * Version: 1.2
 * Author: RADIOFAN
 * Author URI: http://vk.com/radio_fan
 * Description: Данный плагин позволяет парсить данные игроков с сайта <a href="https://ratings.ruchess.ru/api">ratings.ruchess.ru</a> и  <a href="https://ratings.fide.com/download_lists.phtml>ratings.fide.com</a>. С помощью шорткода <code>[chess_top_scoreboard list_url=""]</code> можно вывести актуальный блок топа игроков, атрибут list_url может содержать ссылку на список всех игроков. С помощью шорткода <code>[chess_players_page]</code> можно вывести таблицу с поиском, сортировкой и пагинацией всех игроков. Также доступна возможность выгрузить таблицу рейтингов с динамикой (для админа за любой период). С помощью шорткода <code>[chess_month_ratings_files last="1|0"]</code> можно список таблиц рейтингов с динамикой за месяц доступных для скачивания пользователям, атрибут last отвечает за вывод только последнего файла, или всего списка (по умолчанию).
 * Requires PHP: 7.0
 * Requires at least: 4.7
*/

if(!defined('ABSPATH')) die();

require_once 'radiofan-chess-parser-class.php';
$radiofan_chess_parser = new Radiofan\ChessParser\ChessParser(__FILE__);


//add_action('cron_request', 'so_add_cron_xdebug_cookie', 10, 2);
/**
 * Allow debugging of wp_cron jobs
 *
 * @param array $cron_request_array
 * @param string $doing_wp_cron
 *
 * @return array $cron_request_array with the current XDEBUG_SESSION cookie added if set
 */
/*
function so_add_cron_xdebug_cookie($cron_request_array, $doing_wp_cron)
{
	if (empty ($_COOKIE['XDEBUG_SESSION'])) {
		return ($cron_request_array) ;
	}

	if (empty ($cron_request_array['args']['cookies'])) {
		$cron_request_array['args']['cookies'] = array () ;
	}
	$cron_request_array['args']['cookies']['XDEBUG_SESSION'] = $_COOKIE['XDEBUG_SESSION'] ;

	return ($cron_request_array) ;
}
*/