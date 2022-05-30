jQuery(document).ready(function($){
	$('.chess-top-block .tab').click(function(e){
		let $this = $(this);
		if($this.hasClass('active'))
			return;

		$this.siblings('.tab').removeClass('active');
		$this.addClass('active');
		let box_id = $this.data('box');
		let $box_container = $this.parent().siblings('.tab-content');
		$box_container.slideUp(200, function(){
			$box_container.children('.box.active').removeClass('active');
			$box_container.children('*[data-box="'+ box_id + '"]').addClass('active');
			$box_container.slideDown(200);
		});
	});
});