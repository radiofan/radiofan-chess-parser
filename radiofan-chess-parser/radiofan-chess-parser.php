<?php
/**
 * @wordpress-plugin
 * Plugin Name: RADIOFAN Chess Parser
 * Plugin URI: https://github.com/radiofan
 * Version: 0.1
 * Author: RADIOFAN
 * Author URI: http://vk.com/radio_fan
 * Description: todo add desc
 * Requires PHP: 7.0
*/

if(!defined('ABSPATH')) die();

require_once 'functions.php';
require_once 'log-class.php';
require_once 'install-uninstall-trait.php';
require_once 'parser-trait.php';
require_once 'admin-page-trait.php';
require_once 'radiofan-chess-parser-class.php';
$radiofan_chess_parser = new Radiofan\ChessParser\ChessParser(__FILE__);