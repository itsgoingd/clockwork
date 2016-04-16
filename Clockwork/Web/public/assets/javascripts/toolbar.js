Clockwork.factory('toolbar', function()
{

return {

    buttons: [],

    createButton: function(icon, name, callback)
    {
        this.buttons.push({
            icon: icon,
            name: name,
            callback: callback
        });
    },

    render: function()
    {
        var $html = $('<div class="toolbar"></div>');

        $.each(this.buttons, function(i, button)
        {
            var $button = $('<a href="#" title="' + button.name + '"><i class="fa fa-' + button.icon + '"></i></a>');

            $button.on('click', button.callback);

            $html.append($button);
        });

        return $html;
    }

};

});