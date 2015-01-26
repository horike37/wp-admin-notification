jQuery(document).ready(function($){
	var dateFormat = 'yy-mm-dd';
	$('#wpan_term_start').datepicker({dateFormat: dateFormat});
	$('#wpan_term_end').datepicker({dateFormat: dateFormat});
});