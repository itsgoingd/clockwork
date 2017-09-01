(function(g){

"use strict";

g.PrettyJason = function(data)
{
	if (data instanceof Object) {
		this.data = data;
	} else {
		try {
			this.data = JSON.parse(data);
		} catch(e) {
			throw new PrettyJasonException('Input is not a valid JSON string.', e);
		}

		if (!(this.data instanceof Object)) {
			throw new PrettyJasonException('Input does not contain serialized object.');
		}
	}
};

g.PrettyJason.prototype.print = function(target)
{
	$(target).html(this.generateHtml());
};

g.PrettyJason.prototype.generateHtml = function()
{
	var $list = $('<ul class="pretty-jason"></ul>');

	var $item = $('<li></li>');

	var className = this.data.__class__ || 'Object'
	var $itemName = $('<span><span class="pretty-jason-icon"><i class="pretty-jason-icon-closed"></i></span>' + className + ' </span>');
	var that = this;
	$itemName.click(function(){ that._objectNodeClickedCallback(this); });
	$itemName.data('rendered', true);

	$item.append($itemName);
	$item.append(this._generateHtmlPreview(this.data));
	$item.append(this._generateHtmlNode(this.data));

	$list.append($item);

	return $list;
};

g.PrettyJason.prototype._generateHtmlNode = function(data)
{
	var $list = $('<ul style="display:none"></ul>');

	for (var key in data) {
		if (key == '__class__') continue;

		var val = data[key];
		var valType = this._getValueType(val);
		var $item;

		if (valType == 'string') {
			val = '"' + val + '"';
		}

		if (valType == 'object') {
			$item = $('<li></li>');

			var className = val.__class__ || 'Object'
			var $itemName = $('<span><span class="pretty-jason-icon"><i class="pretty-jason-icon-closed"></i></span><span class="pretty-jason-key"></span> ' + className + '</span>');
			$itemName.find('.pretty-jason-key').text(key + ':');
			var that = this;
			$itemName.click(function(){ that._objectNodeClickedCallback(this); });

			$item.append($itemName);
		} else {
			$item = $('<li><span><span class="pretty-jason-icon"></span><span class="pretty-jason-key"></span> <span class="pretty-jason-value-' + valType + '"></span></span></li>');
			$item.find('.pretty-jason-key').text(key + ':');
			$item.find('.pretty-jason-value-' + valType).text(val);
		}

		$list.append($item);
	}

	return $list;
};

g.PrettyJason.prototype._generateHtmlPreview = function(data)
{
	var $html = $('<span class="pretty-jason-preview"></span>');

	var i = 0;
	for (var key in data) {
		if (key == '__class__') continue;

		var val = data[key];
		var valType = this._getValueType(val);
		var $item;

		if (valType == 'string') {
			val = '"' + val + '"';
		}

		if (valType == 'object') {
			var className = val.__class__ || 'Object'
			$item = $('<span class="pretty-jason-preview-item"><span class="pretty-jason-key"></span> <span class="pretty-jason-value">' + className + '</span></span>');
			$item.find('.pretty-jason-key').text(key + ':');
			$item.find('.pretty-jason-value-' + valType).text(val);
		} else {
			$item = $('<span class="pretty-jason-preview-item"><span class="pretty-jason-key"></span> <span class="pretty-jason-value-' + valType + '"></span></span>');
			$item.find('.pretty-jason-key').text(key + ':');
			$item.find('.pretty-jason-value-' + valType).text(val);
		}

		$html.append($item);

		if (++i >= 3) {
			$html.append('<span class="pretty-jason-preview-item">...</span>');
			break;
		}
	}

	return $html;
};

g.PrettyJason.prototype._getValueType = function(val)
{
	var valType = typeof val;

	if (val === null) {
		valType = 'null';
	} else if (val === undefined) {
		valType = 'undefined';
	}

	return valType;
};

g.PrettyJason.prototype._objectNodeClickedCallback = function(node)
{
	this._renderObjectNode(node);

	var $list = $(node).parent().find('> ul');
	var $icon = $(node).find('i');

	$icon.removeClass('pretty-jason-icon-closed');
	$icon.removeClass('pretty-jason-icon-open');

	if ($list.css('display') == 'none') {
		$list.show();
		$icon.addClass('pretty-jason-icon-open');
	} else {
		$list.hide();
		$icon.addClass('pretty-jason-icon-closed');
	}
};

g.PrettyJason.prototype._renderObjectNode = function(node)
{
	if ($(node).data('rendered')) {
		return;
	}

	var path = [];

	$(node).parents('li').each(function(i, node)
	{
		if (! $(node).parents('.pretty-jason').length || $(node).find('.pretty-jason-preview').length) {
			return;
		}

		var segment = $(node).find('.pretty-jason-key').first().text().slice(0, -1);

		path.unshift(! isNaN(parseInt(segment, 10)) ? parseInt(segment, 10) : segment);
	});

	$(node).parent().append(this._generateHtmlNode(this._getDataFromPath(path)));

	$(node).data('rendered', true);
};

g.PrettyJason.prototype._getDataFromPath = function(path)
{
	var data = this.data;
	var segment;

	while ((segment = path.shift()) !== undefined) {
		data = data[segment];
	}

	return data;
};

g.PrettyJasonException = function(message, exception)
{
	this.message = message;
	this.exception = exception;
};

})(window);
