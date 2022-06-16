<?php
/**
 * @wordpress-plugin
 * Plugin Name: RADIOFAN Chess Parser
 * Plugin URI: https://github.com/radiofan/radiofan-chess-parser
 * Version: 1.1
 * Author: RADIOFAN
 * Author URI: http://vk.com/radio_fan
 * Description: Данный плагин позволяет парсить и данные игроков с сайта <a href="https://ratings.ruchess.ru/api">ratings.ruchess.ru</a>.<br>С помощью шорткода <code>[chess_top_scoreboard list_url=""]</code> можно вывести актуальный блок топа игроков, атрибут list_url может содержать ссылку на список всех игроков.<br>С помощью шорткода <code>[chess_players_page]</code> можно вывести таблицу с поиском, сортировкой и пагинацией всех игроков.<br>Также доступна настройка парсинга по фильтру
 * Requires PHP: 7.0
 * Requires at least: 4.7
*/

if(!defined('ABSPATH')) die();

require_once 'radiofan-chess-parser-class.php';
$radiofan_chess_parser = new Radiofan\ChessParser\ChessParser(__FILE__);