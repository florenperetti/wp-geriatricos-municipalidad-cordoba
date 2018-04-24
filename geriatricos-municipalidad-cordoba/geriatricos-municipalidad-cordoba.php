<?php
/*
Plugin Name: Buscador de Geri&aacute;tricos de la Municipalidad de C&oacute;rdoba
Plugin URI: https://github.com/ModernizacionMuniCBA/plugin-wordpress-geriatricos-municipales
Description: Este plugin a&ntilde;ade un shortcode que genera un buscador de los geri&aacute;tricos de la Municipalidad de C&oacute;rdoba.
Version: 0.1.2
Author: Florencia Peretti
Author URI: https://github.com/florenperetti/wp-geriatricos-municipalidad-cordoba
*/

setlocale(LC_ALL,"es_ES");
date_default_timezone_set('America/Argentina/Cordoba');

add_action('plugins_loaded', array('GeriatricosMuniCordoba', 'get_instancia'));

class GeriatricosMuniCordoba
{
	public static $instancia = null;

	private static $URL_API_GOB = 'https://gobiernoabierto.cordoba.gob.ar/api/v2/entes-privados/';
	private static $FILTRO_GERIATRICOS = 'geriatricos/?tipo_id=1';

	public $nonce_busquedas = '';

	public static function get_instancia()
	{
		if (null == self::$instancia) {
			self::$instancia = new GeriatricosMuniCordoba();
		} 
		return self::$instancia;
	}

	private function __construct()
	{
		add_action('wp_enqueue_scripts', array($this, 'cargar_assets'));

		add_action('wp_ajax_buscar_geriatricos', array($this, 'buscar_geriatricos')); 
		add_action('wp_ajax_nopriv_buscar_geriatricos', array($this, 'buscar_geriatricos'));
		
		add_action('wp_ajax_buscar_geriatricos_pagina', array($this, 'buscar_geriatricos_pagina')); 
		add_action('wp_ajax_nopriv_buscar_geriatricos_pagina', array($this, 'buscar_geriatricos_pagina'));
		
		add_shortcode('buscador_geriatricos_cba', array($this, 'render_shortcode_buscador_geriatricos'));

		add_action('init', array($this, 'boton_shortcode_buscador_geriatricos'));
	}

	public function render_shortcode_buscador_geriatricos($atributos = [], $content = null, $tag = '')
	{
	    $atributos = array_change_key_case((array)$atributos, CASE_LOWER);
	    $atr = shortcode_atts([
            'pag' => 10
        ], $atributos, $tag);

	    $cantidad_por_pagina = $atr['pag'] == 0 ? '' : '&page_size='.$atr['pag'];

	    $url = self::$URL_API_GOB.self::$FILTRO_GERIATRICOS.$cantidad_por_pagina;

    	$api_response = wp_remote_get($url);

    	$resultado = $this->chequear_respuesta($api_response, 'los geri&aacute;', 'geriatricos_muni_cba');
		
		echo '<div id="GM">
	<form>
		<div class="filtros">
			<div class="filtros__columnas">
				<label class="filtros__label" for="nombre">Buscar</label>
				<input type="text" name="nombre">
				<button id="filtros__buscar" type="submit">Buscar</button>
			</div>
			<div class="filtros__columnas">
				<button id="filtros__reset">Todos</button>
			</div>
		</div>
	</form>
	<div class="resultados">';
		echo $this->renderizar_resultados($resultado,$atr['pag']);
		echo '</div></div>';
	}
	
	private function renderizar_resultados($datos,$pag = 10,$query='')
	{
		$html = '';
		
		if (count($datos['results']) > 0) {
			$html .= '<p class="cantidad-resultados"><small><a href="https://gobiernoabierto.cordoba.gob.ar/data/datos-abiertos/categoria/sociedad/geriatricos-habilitados/217" rel="noopener" target="_blank"><b>&#161;Descarg&aacute; toda la informaci&oacute;n&#33;</b></a></small>
				<small>Mostrando '.count($datos['results']).' de '.$datos['count'].' resultados</small></p>';
			foreach ($datos['results'] as $key => $geriatrico) {
				$estado = $geriatrico['estado'];
				if (strrpos($estado, 'vencida') !== false) {
					$estado = ' resultado__estado--vencido';
				} elseif (strrpos($estado, 'Vence en') !== false) {
					$estado = ' resultado__estado--por-vencer';
				} else {
					$estado = '';
				}
				
				$plazas = '';
				if ($geriatrico['plazas_habilitadas'] > 0) {
					$plazas = '<li><b>Plazas Habilitadas:</b> '.$geriatrico['plazas_habilitadas'].'</li>';
				}
				
				$html .= '<div class="resultado__container">
						<div class="resultado__cabecera"><span class="resultado__nombre">'.$geriatrico['nombre'].'</span><span class="resultado__estado'.$estado.'">'.$geriatrico['estado'].'</span></div>
						<div class="resultado__info">
							<ul>
								<li><b>Direcci&oacute;n:</b> '.$geriatrico['direccion'].'</li>
								<li><b>Titular:</b> '.$geriatrico['titular'].'</li>
								<li><b>CUIT:</b> '.$geriatrico['CUIT'].'</li>
								<li><b>Fecha de inscripci&oacute;n:</b> '.$this->formatear_fecha($geriatrico['fecha_inscripcion']).'</li>
								'.$plazas.'
							</ul>
						</div>
					</div>';
			}
			
			if ($datos['next'] != 'null' || $datos['previous'] != 'null') {
				$html .= $this->renderizar_paginacion($datos['previous'], $datos['next'], ($pag ? 10 : $pag), $datos['count'], $query);
			}
			
		} else {
			$html .= '<p class="resultados__mensaje">No hay resultados</p>';
		}
		
		return $html;
	}
	
	public function renderizar_paginacion($anterior, $siguiente, $tamanio, $total, $query)
	{
		$html = '<div class="paginacion">';
		
		$botones = $total % $tamanio == 0 ? $total / $tamanio : ($total / $tamanio) + 1;

		$actual = 1;
		if ($anterior != null) {
			$actual = $this->obtener_parametro($anterior,'page', 1) + 1;;
		} elseif ($siguiente != null) {
			$actual = $this->obtener_parametro($siguiente,'page', 1) - 1;
		}
		$query = $query ? '&q='.$query : '';
		for	($i = 1; $i <= $botones; $i++) {
			if ($i == $actual) {
				$html .= '<button type="button" class="paginacion__boton paginacion__boton--activo" disabled>'.$i.'</button>';
			} else {
				$html .= '<button type="button" class="paginacion__boton" data-pagina="'.self::$URL_API_GOB.self::$FILTRO_GERIATRICOS.'&page='.$i.'&page_size='.$tamanio.$query.'">'.$i.'</button>';
			}
		}
		
		$html .= '</div>';
		
		return $html;
	}

	public function boton_shortcode_buscador_geriatricos()
	{
		if (!current_user_can('edit_posts') && !current_user_can('edit_pages') && get_user_option('rich_editing') == 'true')
			return;

		add_filter("mce_external_plugins", array($this, "registrar_tinymce_plugin")); 
		add_filter('mce_buttons', array($this, 'agregar_boton_tinymce_shortcode_buscador_geriatricos'));
	}

	public function registrar_tinymce_plugin($plugin_array)
	{
		$plugin_array['buscgercba_button'] = $this->cargar_url_asset('/js/shortcodeGeriatricos.js');
	    return $plugin_array;
	}

	public function agregar_boton_tinymce_shortcode_buscador_geriatricos($buttons)
	{
	    $buttons[] = "buscgercba_button";
	    return $buttons;
	}

	public function cargar_assets()
	{
		$urlJSBuscador = $this->cargar_url_asset('/js/buscadorGeriatricos.js');
		$urlCSSBuscador = $this->cargar_url_asset('/css/shortcodeGeriatricos.css');
		
		wp_register_style('buscador_geriatricos_cba.css', $urlCSSBuscador);
		wp_register_script('buscador_geriatricos_cba.js', $urlJSBuscador);
		
		global $post;
	    if( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'buscador_geriatricos_cba') ) {
			wp_enqueue_script(
				'buscar_geriatricos_ajax', 
				$urlJSBuscador, 
				array('jquery'), 
				'1.0.0',
				TRUE
			);
			wp_enqueue_style('buscador_geriatricos.css', $this->cargar_url_asset('/css/shortcodeGeriatricos.css'));
			
			$nonce_busquedas = wp_create_nonce("buscar_geriatrico_nonce");
			
			wp_localize_script(
				'buscar_geriatricos_ajax', 
				'buscarGeriatricos', 
				array(
					'url'   => admin_url('admin-ajax.php'),
					'nonce' => $nonce_busquedas
				)
			);
		}
	}
	
	public function buscar_geriatricos()
	{
		$nombre = $_REQUEST['nombre'];
		check_ajax_referer('buscar_geriatrico_nonce', 'nonce');

		if(true && $nombre !== '') {
			$api_response = wp_remote_get(self::$URL_API_GOB.self::$FILTRO_GERIATRICOS.'&page_size=10&q='.$nombre);
			$api_data = json_decode(wp_remote_retrieve_body($api_response), true);
			
			wp_send_json_success($this->renderizar_resultados($api_data,10,$nombre));
		} elseif (true && $nombre == '') {
			$api_response = wp_remote_get(self::$URL_API_GOB.self::$FILTRO_GERIATRICOS.'&page_size=10');
			$api_data = json_decode(wp_remote_retrieve_body($api_response), true);
			wp_send_json_success($this->renderizar_resultados($api_data,10,''));
		} else {
			wp_send_json_error($api_data);
		}
		
		die();
	}
	
	public function buscar_geriatricos_pagina()
	{
		$pagina = $_REQUEST['pagina'];
		$nombre = $_REQUEST['nombre'];
		check_ajax_referer('buscar_geriatrico_nonce', 'nonce');

		if(true && $pagina !== '') {
			$api_response = wp_remote_get($pagina);
			$api_data = json_decode(wp_remote_retrieve_body($api_response), true);
			
			wp_send_json_success($this->renderizar_resultados($api_data,10,($nombre ? $nombre : '')));
		} else {
			wp_send_json_error($api_data);
		}
		
		die();
	}

	/*
	* Mira si la respuesta es un error, si no lo es, cachea por una hora el resultado.
	*/
	private function chequear_respuesta($api_response, $tipoObjeto)
	{
		if (is_null($api_response)) {
			return [ 'results' => [] ];
		} else if (is_wp_error($api_response)) {
			$mensaje = WP_DEBUG ? ' '.$this->mostrar_error($api_response) : '';
			return [ 'results' => [], 'error' => 'Ocurri&oacute; un error al cargar '.$tipoObjeto.'.'.$mensaje];
		} else {
			return json_decode(wp_remote_retrieve_body($api_response), true);
		}
	}


	/* Funciones de utilidad */

	private function mostrar_error($error)
	{
		if (WP_DEBUG === true) {
			return $error->get_error_message();
		}
	}

	private function formatear_fecha($original)
	{
		return date("d/m/Y", strtotime($original));
	}

	private function cargar_url_asset($ruta_archivo)
	{
		return plugins_url($this->minified($ruta_archivo), __FILE__);
	}

	// Se usan archivos minificados en producción.
	private function minified($ruta_archivo)
	{
		if (WP_DEBUG === true) {
			return $ruta_archivo;
		} else {
			$extension = strrchr($ruta_archivo, '.');
			return substr_replace($ruta_archivo, '.min'.$extension, strrpos($ruta_archivo, $extension), strlen($extension));
		}
	}
	
	private function obtener_parametro($url, $param, $fallback)
	{
		$partes = parse_url($url);
		parse_str($partes['query'], $query);
		$resultado = $query[$param] ? $query[$param] : $fallback;
		return $resultado;
	}
}