define(function(require, exports, module) {
    var init = require('init');


    exports.deleteAll = function(){
        $('._del_selected').click(function(){
            var url = $(this).attr('data-url');
            $.post(url,$('#Form').serializeArray(),function(d){
                if(d.done == true){
                    init.DoMessage('处理成功');
                }else{
                    init.DoMessage(d.error);
                }
            },'json');
        });
    };

    exports.delete = function(){
        $('._delete').click(function(){
            var url = $(this).attr('data-url');
            init.confirm(url,"您确定要删除么？",true);
        });
    }
});