(function () {
  function byId(id) {
    return document.getElementById(id);
  }

  function attr(name, value) {
    if (!value) {
      return '';
    }
    return ' ' + name + '="' + String(value).replace(/"/g, '&quot;') + '"';
  }

  function buildShortcode() {
    var kind = byId('ddys-typecho-shortcode-kind');
    var output = byId('ddys-typecho-shortcode-output');
    if (!kind || !output) {
      return;
    }

    var tag = kind.value || 'ddys_latest';
    var slug = byId('ddys-typecho-shortcode-slug').value.trim();
    var id = byId('ddys-typecho-shortcode-id').value.trim();
    var type = byId('ddys-typecho-shortcode-type').value.trim();
    var query = byId('ddys-typecho-shortcode-q').value.trim();
    var year = byId('ddys-typecho-shortcode-year').value.trim();
    var month = byId('ddys-typecho-shortcode-month').value.trim();
    var limit = byId('ddys-typecho-shortcode-limit').value.trim();
    var perPage = byId('ddys-typecho-shortcode-per-page').value.trim();
    var layout = byId('ddys-typecho-shortcode-layout').value;

    var code = '[' + tag;
    code += attr('slug', slug);
    code += attr('id', id);
    code += attr('type', type);
    code += attr('q', query);
    code += attr('year', year);
    code += attr('month', month);

    if (tag === 'ddys_latest' || tag === 'ddys_hot' || tag === 'ddys_suggest') {
      code += attr('limit', limit);
    }

    if (tag !== 'ddys_movie' && tag !== 'ddys_sources' && tag !== 'ddys_share') {
      code += attr('per_page', perPage);
    }

    code += attr('layout', layout);
    code += ']';
    output.value = code;
  }

  document.addEventListener('click', function (event) {
    if (event.target && event.target.id === 'ddys-typecho-shortcode-build') {
      buildShortcode();
    }

    if (event.target && event.target.id === 'ddys-typecho-shortcode-copy') {
      var output = byId('ddys-typecho-shortcode-output');
      if (output && navigator.clipboard) {
        navigator.clipboard.writeText(output.value);
      }
    }
  });

  document.addEventListener('change', function (event) {
    if (event.target && event.target.id && event.target.id.indexOf('ddys-typecho-shortcode-') === 0) {
      buildShortcode();
    }
  });
}());
