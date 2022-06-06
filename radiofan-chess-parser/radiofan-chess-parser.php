<?php
/**
 * @wordpress-plugin
 * Plugin Name: RADIOFAN Chess Parser
 * Plugin URI: https://github.com/radiofan
 * Version: 0.3
 * Author: RADIOFAN
 * Author URI: http://vk.com/radio_fan
 * Description: шорткод <code>[chess_top_scoreboard list_url=""]</code> выводит актуальный блок топа игроков, атрибут list_url может содержать ссылку на список всех игроков
 * Requires PHP: 7.0
 * Requires at least: 4.7
*/

if(!defined('ABSPATH')) die();

require_once 'radiofan-chess-parser-class.php';
$radiofan_chess_parser = new Radiofan\ChessParser\ChessParser(__FILE__);