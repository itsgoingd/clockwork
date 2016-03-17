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

	var $itemName = $('<span><span class="pretty-jason-icon"><i class="pretty-jason-icon-closed"></i></span>Object </span>');
	$itemName.click(this._objectNodeClickedCallback);

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
		var val = data[key];
		var valType = this._getValueType(val);
		var $item;

		if (valType == 'string') {
			val = '"' + val + '"';
		}

		if (valType == 'object') {
			$item = $('<li></li>');

			var $itemName = $('<span><span class="pretty-jason-icon"><i class="pretty-jason-icon-closed"></i></span><span class="pretty-jason-key">' + key + ':</span> Object</span>');
			$itemName.click(this._objectNodeClickedCallback);

			$item.append($itemName);
			$item.append(this._generateHtmlNode(val));
		} else {
			$item = $('<li><span><span class="pretty-jason-icon"></span><span class="pretty-jason-key">' + key + ':</span> <span class="pretty-jason-value-' + valType + '">' + val + '</span></span></li>');
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
		var val = data[key];
		var valType = this._getValueType(val);
		var $item;

		if (valType == 'string') {
			val = '"' + val + '"';
		}

		if (valType == 'object') {
			$item = $('<span class="pretty-jason-preview-item"><span class="pretty-jason-key">' + key + ':</span> <span class="pretty-jason-value">Object</span></span>');
		} else {
			$item = $('<span class="pretty-jason-preview-item"><span class="pretty-jason-key">' + key + ':</span> <span class="pretty-jason-value-' + valType + '">' + val + '</span></span>');
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

g.PrettyJason.prototype._objectNodeClickedCallback = function()
{
	var $list = $(this).parent().find('> ul');
	var $icon = $(this).find('i');

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

g.PrettyJasonException = function(message, exception)
{
	this.message = message;
	this.exception = exception;
};

})(window);
