$(function(){
    $('#cloudflare-clear-cache-button').on('click', function(evt){
        evt.preventDefault();

        var $btn = $(this);

        var urls = [
            window.location.toString()
        ];

        $('link[href^="/"]').not('[href^="//"]').each(function(){
            urls.push($(this).attr('href'));
        });

        $('script[src^="/"]').not('[src^="//"]').each(function(){
            urls.push($(this).attr('src'));
        });

        var request = $.ajax({
            type: 'POST',
            url: $btn.attr('href'),
            data: {
                cID: CCM_CID,
                urls: urls
            },
            cache: false
        });

        request.then(function(response){
            window.location.reload();
        });

        return false;
    });
});
