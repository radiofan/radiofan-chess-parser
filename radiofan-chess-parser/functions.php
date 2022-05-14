<?php
namespace Radiofan\ChessParser;

function user_import_filter(
	int $id_ruchess,
	?int $id_fide,
	string $name,
	bool $sex,
	string $country,
	?int $birth_year,
	int $region_number,
	string $region_name,
	?int $rating_ruchess,
	?int $rating_fide
){
	$accept = true;
	eval(" ");//todo фильтр из админки
	return $accept;
}