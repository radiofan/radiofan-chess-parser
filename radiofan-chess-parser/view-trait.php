<?php
namespace Radiofan\ChessParser;

trait View{

	public function enqueue_scripts(){
		wp_enqueue_script('radiofan_chess_parser___script_top_scoreboard', $this->plugin_url.'assets/top-scoreboard.js', ['jquery'], filemtime($this->plugin_dir.'assets/top-scoreboard.js'), 1);
		wp_enqueue_style('radiofan_chess_parser__style_top_scoreboard', $this->plugin_url.'assets/top-scoreboard.css', false, filemtime($this->plugin_dir.'assets/top-scoreboard.css'));
	}
	
	public function view_top_scoreboard($atts, $content){
		
		$svg_path = $this->plugin_url.'assets/sprites.svg';
		return '
<div class="tourn-top__top col-lg-4 col-md-12">
	<h3>Топ Алтайского края</h3>
	<div class="chess-top-block">
		<div class="chess-top-block__header tab-header">
			<div class="tab tab-man active" data-box="man">
				<h5>Мужчины</h5>
			</div>
			<div class="tab tab-woman" data-box="woman">
				<h5>Женщины</h5>
			</div>
		</div>
		<div class="chess-top-block__content tab-content">
			<div class="box box-man active" data-box="man">
				<div class="chess-section__header tab-header">
					<div class="tab tab-classic active" data-box="classic">
						<svg class="svg-icon"><use xlink:href="'.$svg_path.'#board"></use></svg>
						<h6>Классика</h6>
					</div>
					<div class="tab tab-rapid" data-box="rapid">
						<svg class="svg-icon"><use xlink:href="'.$svg_path.'#clock"></use></svg>
						<h6>Рапид</h6>
					</div>
					<div class="tab tab-blitz" data-box="blitz">
						<svg class="svg-icon"><use xlink:href="'.$svg_path.'#flash"></use></svg>
						<h6>Блиц</h6>
					</div>
				</div>
				<div class="chess-section__content tab-content">
					<div class="box box-classic active" data-box="classic">
					<table>
						<tbody>
						<tr>
							<td>1</td>
							<td>Сорокин Алексей</td>
							<td>2521</td>
							<td>1983</td>
						</tr>
						</tbody>
					</table>
					</div>
				</div>
			</div>
			<div class="box box-woman" data-box="woman">
			</div>
		</div>
		<a href="/chess-players/rating/" class="top__other">Рейтинг-лист Алтайского края</a>
	</div>
</div>
';
	}
}
