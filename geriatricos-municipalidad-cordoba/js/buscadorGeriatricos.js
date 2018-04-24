(function(window, document, $) {

  const $GM = $('#GM');
  const $form = $GM.find('form');
  const $resultados = $GM.find('.resultados');
  const $reset = $GM.find('#filtros__reset');

  $reset.click(function(e) {
    e.preventDefault();
    $form[0].reset();
    $form.submit();
  });

  $form.submit(function(e) {
    e.preventDefault();
    $.ajax({
      type: "POST",
      dataType: "JSON",
      url: buscarGeriatricos.url,
      data: {
        action: 'buscar_geriatricos',
        nonce: buscarGeriatricos.nonce,
        nombre: $form.serializeArray()[0].value
      },
      success: function(response) {
        if (response.data) {
          $resultados.html(response.data);
        }
      }
    });
  });

  $(document).on('click','#GM .paginacion__boton', function(e) {
    const pagina = $(this).data('pagina');
    const $boton = $(e.target);
    const texto = $boton.html();
    $boton.html('...');
    $.ajax({
      type: "POST",
      dataType: "JSON",
      url: buscarGeriatricos.url,
      data: {
        action: 'buscar_geriatricos_pagina',
        nonce: buscarGeriatricos.nonce,
        pagina: pagina,
        nombre: $form.serializeArray()[0].value
      },
      success: function(response) {
        if (response.data) {
          $resultados.html(response.data);
          $('body').animate({scrollTop: 50}, 1000);
        }
      },
      done: function() {
        $boton.html(texto);
      }
    });
  });
})(window, document, jQuery);