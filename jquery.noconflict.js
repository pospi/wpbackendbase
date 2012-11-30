jQuery.curCSS = jQuery.css;			// maintain compatibility with deprecated CSS method which WP uses heavily

if (typeof PB_WPJQ != 'undefined') {
	// backup our plugin's jQuery reference to its own variable and simultaneously restore the default
	var jQpb = jQuery.noConflict();
	jQuery = PB_WPJQ;

	// reference across required prototypes from other jQuery libs so that jqUI can function in both jQuery instances
	for (var i in jQuery.fn) {
		if (typeof jQpb.fn[i] == 'undefined') {
			jQpb.fn[i] = jQuery.fn[i];
		}
	}
	jQpb.ui = jQuery.ui;
	jQpb.widget = jQuery.widget;
	jQpb.datepicker = jQuery.datepicker;
}
