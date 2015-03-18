jQuery(document).ready(function() {
	jQuery('.singlebutton').click( submit_check );
    }
);
function submit_check( event ) {
    event.preventDefault();
    var xhr = new XMLHttpRequest();
    var wwwroot = jQuery('.wwwroot').val();
    xhr.onreadystatechange = function() { //TODO: error handling
        if (xhr.readyState == 4 && xhr.status == 200) {
            var response = xhr.responseText; //If all goes to plan then the response is the id of the new discussion
            if (response == '"1"') {
            	jQuery(".notification-success").removeClass("hide");    	
            } else {
            	jQuery(".notification-fail").removeClass("hide");
            }
        }
    };
    xhr.open('GET', wwwroot + '/admin/tool/coursestore/ajax.php?action=conncheck', true);
    xhr.send();
}
