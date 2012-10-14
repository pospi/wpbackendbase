jQuery.curCSS = jQuery.css;			// maintain compatibility with deprecated CSS method which WP uses heavily
var jQpb = jQuery.noConflict();		// backup our plugin's jQuery reference to its own variable and simultaneously restore the default
