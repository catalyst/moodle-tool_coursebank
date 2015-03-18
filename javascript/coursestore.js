jQuery(document).ready(function() {
	    jQuery('.singlebutton').click( submit_check );
    }
);

function submit_check( event ) {
    event.preventDefault();
    var xhr = new XMLHttpRequest();
    var wwwroot = jQuery('.wwwroot').val();
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var response = xhr.responseText; 
            if (response == '"1"') {
            	jQuery(".notification-success").removeClass("hide");
            	if (!jQuery(".notification-fail").hasClass("hide")) {
            	    jQuery(".notification-fail").addClass("hide");
            	}
            } else {
            	jQuery(".notification-fail").removeClass("hide");
            	if (!jQuery(".notification-success").hasClass("hide")) {
            	    jQuery(".notification-success").addClass("hide");
            	}
            }
        }
    };
    xhr.open('GET', wwwroot + '/admin/tool/coursestore/ajax.php?action=conncheck', true);
    xhr.send();
}
