$(document).ready(function(){
    var delay = 300;
    $(".progress-bar").each(function(i){
        $(this).delay(delay*i).animate({ width: $(this).attr('aria-valuenow') + '%' }, delay);
    });
});