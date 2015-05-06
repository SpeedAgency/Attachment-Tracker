jQuery(function($){
    $("*[data-monitor='click']").click(function(e){
        //e.preventDefault();
        var url = $(this).attr('href');
        var form_data = {
            'action':'record_click',
            'id':$(this).data("id")
        }
        $.post(ajaxurl, form_data, function(data){
            if(data['success']==1){
                //location.href = url;
            }
        }, 'json');
        return false;
    });

    $(".get-data").click(function(){

        var formdata = {'action':'collect_data', 'key':'bntXKAPLnLQLL5Elz7UR1Su1'};

        $.post(ajaxurl, formdata, function(data){

        }, 'json');

    });
});
