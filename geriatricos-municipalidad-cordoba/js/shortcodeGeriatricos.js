(function() {
  tinymce.create('tinymce.plugins.buscgercba_button', {
    init: function(ed, url) {
      ed.addCommand('buscgercba_insertar_shortcode', function() {
        selected = tinyMCE.activeEditor.selection.getContent();
        var content = '';

        ed.windowManager.open({
          title: 'Buscador de Geriátricos',
          body: [{
            type: 'textbox',
            name: 'pag',
            label: 'Cantidad de Resultados'
          }],
          onsubmit: function(e) {
            var pags = Number(e.data.pag.trim());
            ed.insertContent( '[buscador_geriatricos_cba' + (pags && Number.isInteger(pags) ? ' pag="'+pags+'"' : '') + ']' );
          }
        });
        tinymce.execCommand('mceInsertContent', false, content);
      });
      ed.addButton('buscgercba_button', {title : 'Insertar buscador de Geriátricos', cmd : 'buscgercba_insertar_shortcode', image: url.replace('/js', '') + '/images/logo-shortcode.png' });
    }
  });
  tinymce.PluginManager.add('buscgercba_button', tinymce.plugins.buscgercba_button);
})();