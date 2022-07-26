<?php
//============================================================+
// File name   : tcpdf_autoconfig.php
// Version     : 1.1.1
// Begin       : 2013-05-16
// Last Update : 2014-12-18
// Authors     : Nicola Asuni - Tecnick.com LTD - www.tecnick.com - info@tecnick.com
// License     : GNU-LGPL v3 (http://www.gnu.org/copyleft/lesser.html)
// -------------------------------------------------------------------
// Copyright (C) 2011-2014 Nicola Asuni - Tecnick.com LTD
//
// This file is part of TCPDF software library.
//
// TCPDF is free software: you can redistribute it and/or modify it
// under the terms of the GNU Lesser General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// TCPDF is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// See the GNU Lesser General Public License for more details.
//
// You should have received a copy of the License
// along with TCPDF. If not, see
// <http://www.tecnick.com/pagefiles/tcpdf/LICENSE.TXT>.
//
// See LICENSE.TXT file for more information.
// -------------------------------------------------------------------
//
// Description : Try to automatically configure some TCPDF
//               constants if not defined.
//
//============================================================+

/**
 * @file
 * Try to automatically configure some TCPDF constants if not defined.
 * @package com.tecnick.tcpdf
 * @version 1.1.1
 */
// DOCUMENT_ROOT fix for IIS Webserver
if ((!isset($_SERVER['DOCUMENT_ROOT'])) OR (empty($_SERVER['DOCUMENT_ROOT']))) {
	if(isset($_SERVER['SCRIPT_FILENAME'])) {
		$_SERVER['DOCUMENT_ROOT'] = str_replace( '\\', '/', substr($_SERVER['SCRIPT_FILENAME'], 0, 0-strlen($_SERVER['PHP_SELF'])));
	} elseif(isset($_SERVER['PATH_TRANSLATED'])) {
		$_SERVER['DOCUMENT_ROOT'] = str_replace( '\\', '/', substr(str_replace('\\\\', '\\', $_SERVER['PATH_TRANSLATED']), 0, 0-strlen($_SERVER['PHP_SELF'])));
	} else {
		// define here your DOCUMENT_ROOT path if the previous fails (e.g. '/var/www')
		$_SERVER['DOCUMENT_ROOT'] = '/';
	}
}
$_SERVER['DOCUMENT_ROOT'] = str_replace('//', '/', $_SERVER['DOCUMENT_ROOT']);
if (substr($_SERVER['DOCUMENT_ROOT'], -1) != '/') {
	$_SERVER['DOCUMENT_ROOT'] .= '/';
}

// Load main configuration file only if the K_TCPDF_EXTERNAL_CONFIG constant is set to false.
if (!defined('K_TCPDF_EXTERNAL_CONFIG') OR !K_TCPDF_EXTERNAL_CONFIG) {
	// define a list of default config files in order of priority
	$tcpdf_config_files = array(dirname(__FILE__).'/config/tcpdf_config.php', '/etc/php-tcpdf/tcpdf_config.php', '/etc/tcpdf/tcpdf_config.php', '/etc/tcpdf_config.php');
	foreach ($tcpdf_config_files as $tcpdf_config) {
		if (@file_exists($tcpdf_config) AND is_readable($tcpdf_config)) {
			require_once($tcpdf_config);
			break;
		}
	}
}

if (!defined('K_PATH_MAIN')) {
	define ('K_PATH_MAIN', dirname(__FILE__).'/');
}

if (!defined('K_PATH_FONTS')) {
	define ('K_PATH_FONTS', K_PATH_MAIN.'fonts/');
}

if (!defined('K_PATH_URL')) {
	$k_path_url = K_PATH_MAIN; // default value for console mode
	if (isset($_SERVER['HTTP_HOST']) AND (!empty($_SERVER['HTTP_HOST']))) {
		if(isset($_SERVER['HTTPS']) AND (!empty($_SERVER['HTTPS'])) AND (strtolower($_SERVER['HTTPS']) != 'off')) {
			$k_path_url = 'https://';
		} else {
			$k_path_url = 'http://';
		}
		$k_path_url .= $_SERVER['HTTP_HOST'];
		$k_path_url .= str_replace( '\\', '/', substr(K_PATH_MAIN, (strlen($_SERVER['DOCUMENT_ROOT']) - 1)));
	}
	define ('K_PATH_URL', $k_path_url);
}

if (!defined('K_PATH_IMAGES')) {
	$tcpdf_images_dirs = array(K_PATH_MAIN.'examples/images/', K_PATH_MAIN.'images/', '/usr/share/doc/php-tcpdf/examples/images/', '/usr/share/doc/tcpdf/examples/images/', '/usr/share/doc/php/tcpdf/examples/images/', '/var/www/tcpdf/images/', '/var/www/html/tcpdf/images/', '/usr/local/apache2/htdocs/tcpdf/images/', K_PATH_MAIN);
	foreach ($tcpdf_images_dirs as $tcpdf_images_path) {
		if (@file_exists($tcpdf_images_path)) {
			define ('K_PATH_IMAGES', $tcpdf_images_path);
			break;
		}
	}
}

if (!defined('PDF_HEADER_LOGO')) {
	$tcpdf_header_logo = '';
	if (@file_exists(K_PATH_IMAGES.'tcpdf_logo.jpg')) {
		$tcpdf_header_logo = 'tcpdf_logo.jpg';
	}
	define ('PDF_HEADER_LOGO', $tcpdf_header_logo);
}

if (!defined('PDF_HEADER_LOGO_WIDTH')) {
	if (!empty($tcpdf_header_logo)) {
		define ('PDF_HEADER_LOGO_WIDTH', 30);
	} else {
		define ('PDF_HEADER_LOGO_WIDTH', 0);
	}
}

if (!defined('K_PATH_CACHE')) {
	$K_PATH_CACHE = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();
	if (substr($K_PATH_CACHE, -1) != '/') {
		$K_PATH_CACHE .= '/';
	}
	define ('K_PATH_CACHE', $K_PATH_CACHE);
}

$namespace = 'LpcTcpdfConfig';
if (!defined($namespace.'\K_BLANK_IMAGE')) {
	define ($namespace.'\K_BLANK_IMAGE', '_blank.png');
}

if (!defined($namespace.'\PDF_PAGE_FORMAT')) {
	define ($namespace.'\PDF_PAGE_FORMAT', 'A4');
}

if (!defined($namespace.'\PDF_PAGE_ORIENTATION')) {
	define ($namespace.'\PDF_PAGE_ORIENTATION', 'P');
}

if (!defined($namespace.'\PDF_CREATOR')) {
	define ($namespace.'\PDF_CREATOR', 'LPC_TCPDF');
}

if (!defined($namespace.'\PDF_AUTHOR')) {
	define ($namespace.'\PDF_AUTHOR', 'LPC_TCPDF');
}

if (!defined($namespace.'\PDF_HEADER_TITLE')) {
	define ($namespace.'\PDF_HEADER_TITLE', 'LPC_TCPDF Example');
}

if (!defined($namespace.'\PDF_HEADER_STRING')) {
	define ($namespace.'\PDF_HEADER_STRING', "by Nicola Asuni - Tecnick.com\nwww.tcpdf.org");
}

if (!defined($namespace.'\PDF_UNIT')) {
	define ($namespace.'\PDF_UNIT', 'mm');
}

if (!defined($namespace.'\PDF_MARGIN_HEADER')) {
	define ($namespace.'\PDF_MARGIN_HEADER', 5);
}

if (!defined($namespace.'\PDF_MARGIN_FOOTER')) {
	define ($namespace.'\PDF_MARGIN_FOOTER', 10);
}

if (!defined($namespace.'\PDF_MARGIN_TOP')) {
	define ($namespace.'\PDF_MARGIN_TOP', 27);
}

if (!defined($namespace.'\PDF_MARGIN_BOTTOM')) {
	define ($namespace.'\PDF_MARGIN_BOTTOM', 25);
}

if (!defined($namespace.'\PDF_MARGIN_LEFT')) {
	define ($namespace.'\PDF_MARGIN_LEFT', 15);
}

if (!defined($namespace.'\PDF_MARGIN_RIGHT')) {
	define ($namespace.'\PDF_MARGIN_RIGHT', 15);
}

if (!defined($namespace.'\PDF_FONT_NAME_MAIN')) {
	define ($namespace.'\PDF_FONT_NAME_MAIN', 'helvetica');
}

if (!defined($namespace.'\PDF_FONT_SIZE_MAIN')) {
	define ($namespace.'\PDF_FONT_SIZE_MAIN', 10);
}

if (!defined($namespace.'\PDF_FONT_NAME_DATA')) {
	define ($namespace.'\PDF_FONT_NAME_DATA', 'helvetica');
}

if (!defined($namespace.'\PDF_FONT_SIZE_DATA')) {
	define ($namespace.'\PDF_FONT_SIZE_DATA', 8);
}

if (!defined($namespace.'\PDF_FONT_MONOSPACED')) {
	define ($namespace.'\PDF_FONT_MONOSPACED', 'courier');
}

if (!defined($namespace.'\PDF_IMAGE_SCALE_RATIO')) {
	define ($namespace.'\PDF_IMAGE_SCALE_RATIO', 1.25);
}

if (!defined($namespace.'\HEAD_MAGNIFICATION')) {
	define($namespace.'\HEAD_MAGNIFICATION', 1.1);
}

if (!defined($namespace.'\K_CELL_HEIGHT_RATIO')) {
	define($namespace.'\K_CELL_HEIGHT_RATIO', 1.25);
}

if (!defined($namespace.'\K_TITLE_MAGNIFICATION')) {
	define($namespace.'\K_TITLE_MAGNIFICATION', 1.3);
}

if (!defined($namespace.'\K_SMALL_RATIO')) {
	define($namespace.'\K_SMALL_RATIO', 2/3);
}

if (!defined($namespace.'\K_THAI_TOPCHARS')) {
	define($namespace.'\K_THAI_TOPCHARS', true);
}

if (!defined($namespace.'\K_TCPDF_CALLS_IN_HTML')) {
	define($namespace.'\K_TCPDF_CALLS_IN_HTML', false);
}

if (!defined($namespace.'\K_TCPDF_THROW_EXCEPTION_ERROR')) {
	define($namespace.'\K_TCPDF_THROW_EXCEPTION_ERROR', false);
}

if (!defined($namespace.'\K_TIMEZONE')) {
	define($namespace.'\K_TIMEZONE', @date_default_timezone_get());
}

//============================================================+
// END OF FILE
//============================================================+
