<?php

/**

 * Plugin Name: Coneccion con la api de easy broker

 * Plugin URI: https://mypageweb.com/

 * Description: Este plugin realiza la sincronización de la api de Easy Broker con wordpress, permitiendo sincronizar las propiedades.

 * Version: 1.21

 * Author: Gustavo Noriega

 * Author URI: https://mypageweb.com/

 */





//seccion en el administrador de wordpress

function easy_broker_admin_menu() {

    add_options_page(

        __('Configuración de Easy Broker', 'domain'), // Título de la página

        'Easy Broker', // Título del menú

        'manage_options', // Capacidad requerida para ver esta página

        'easy-broker-settings', // Slug del menú

        'easy_broker_settings_page' // Función que renderiza la página de opciones

    );

}

add_action('admin_menu', 'easy_broker_admin_menu');



function easy_broker_settings_page() {

    $options = get_option('easy_broker_api_key');

    $api_key = isset($options['easy_broker_api_key']) ? $options['easy_broker_api_key'] : '';

    ?>

<div class="wrap">

    <h2><?php echo esc_html(get_admin_page_title()); ?></h2>

    <form action="options.php" method="post">

        <?php

            // Registra las opciones para la sección 'easy_broker'

            settings_fields('easy_broker');

            // Muestra los campos de la sección 'easy_broker'

            do_settings_sections('easy-broker-settings');

            // Botón de guardar cambios

            submit_button(__('Guardar Cambios', 'domain'));

            ?>

    </form>

    <button id="easy-broker-api-btn" data-api-key="<?php echo esc_attr($api_key); ?>">Clic para sincronizar</button>

</div>

<?php

}





function easy_broker_settings_init() {

    register_setting('easy_broker', 'easy_broker_api_key');



    add_settings_section(

        'easy_broker_api_settings',

        __('Configuración de la API de Easy Broker', 'domain'),

        'easy_broker_api_settings_section_callback',

        'easy-broker-settings'

    );



    add_settings_field(

        'easy_broker_api_key',

        __('API Key', 'domain'),

        'easy_broker_api_key_render',

        'easy-broker-settings',

        'easy_broker_api_settings'

    );

}



add_action('admin_init', 'easy_broker_settings_init');



function easy_broker_api_settings_section_callback() {

    echo __('Por favor, ingresa tu API Key de Easy Broker.', 'domain');

}



function easy_broker_api_key_render() {

    $options = get_option('easy_broker_api_key');

    ?>

<input type='text' name='easy_broker_api_key[easy_broker_api_key]'
    value='<?php echo $options['easy_broker_api_key']; ?>'>

<div id="propiedades-info"></div>

<?php



}



function easy_broker_enqueue_admin_js($hook) {

    if ('settings_page_easy-broker-settings' !== $hook) {

        return;

    }

    wp_enqueue_script('easy-broker-admin-js', plugin_dir_url(__FILE__) . 'admin.js', array('jquery'), null, true);

}



add_action('admin_enqueue_scripts', 'easy_broker_enqueue_admin_js');









function json_todas_las_propiedades($pagina_actual, $api_key) {

	

	$ch = curl_init();



	curl_setopt($ch, CURLOPT_URL, 'https://api.easybroker.com/v1/properties?page=' . $pagina_actual . '&limit=1');

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	curl_setopt($ch, CURLOPT_HTTPHEADER, [

		'X-Authorization: ' . $api_key,

		'Accept: application/json'

	]);



	$response = curl_exec($ch);



	if(curl_errno($ch)) {

		echo 'Error:' . curl_error($ch);

	} else {

		// Configura los encabezados de respuesta adecuados.

		//header('Content-Type: application/json');

        crear_post_desde_json($response, $api_key);

		echo $response;

	}



	// Cierra la sesión cURL.

	curl_close($ch);

}	



function json_propiedad_individual($post_id, $property_id, $api_key) {

    $ch = curl_init();

    

    // Usa el parámetro $property_id en la URL

    curl_setopt($ch, CURLOPT_URL, 'https://api.easybroker.com/v1/properties/' . $property_id);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [

        'X-Authorization: ' . $api_key, // Usa el parámetro $api_key para la autorización

        'Accept: application/json'

    ]);

    

    $response = curl_exec($ch);

    

    if(curl_errno($ch)) {

        echo 'Error:' . curl_error($ch);

    } else {

        // Configura los encabezados de respuesta adecuados.

        //header('Content-Type: application/json');

        actualizar_post_desde_json($post_id, $response);

    }

    

    // Cierra la sesión cURL.

    curl_close($ch);

}





add_action('wp_ajax_obtener_propiedades', 'manejador_ajax_obtener_propiedades');

add_action('wp_ajax_nopriv_obtener_propiedades', 'manejador_ajax_obtener_propiedades');



function manejador_ajax_obtener_propiedades() {



     if (!current_user_can('manage_options')) {

        wp_send_json_error('No tienes permisos para realizar esta acción');

        wp_die();

    }



    

    // Obtener los parámetros de la solicitud

    $pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1; // Ejemplo de valor predeterminado

    $api_key = isset($_GET['api_key']) ? sanitize_text_field($_GET['api_key']) : '';



    // Llamar a tu función original y pasarle los parámetros

    json_todas_las_propiedades($pagina_actual, $api_key);



    // Finalizar la ejecución para no retornar un 0 al final

    wp_die();

}





function crear_post_desde_json($json_data, $api_key) {

    // Decodificar el JSON

    $data = json_decode($json_data, true);

    

    // Verificar si 'content' existe

    if (!empty($data['content'])) {

        foreach ($data['content'] as $item) {

            $public_id = $item['public_id'];

            $title = $item['title'];

            

            // Verificar si ya existe un post con el mismo public_id

            $existing_posts = get_posts(array(

                'post_type' => 'property',

                'meta_query' => array(

                    array(

                        'key' => 'public_id',

                        'value' => $public_id,

                        'compare' => '=',

                    ),

                ),

                'fields' => 'ids', // Solo necesitamos los IDs

            ));

            

            // Si no existe, crear el post

            if (count($existing_posts) == 0) {

                // Crear el post

                $post_id = wp_insert_post(array(

                    'post_title' => wp_strip_all_tags($title),

                    'post_status' => 'publish',

                    'post_type' => 'property',

                ));

                

                // Añadir el public_id como meta dato

                if (!is_wp_error($post_id)) {

                    update_post_meta($post_id, 'public_id', $public_id);

                          

                }

            }else {

                $existing_post_id = $existing_posts[0];

                update_post_meta($existing_post_id, 'title_image_thumb', $item['title_image_full']);

                json_propiedad_individual($existing_post_id, $public_id, $api_key);

                mi_funcion_al_actualizar_post($existing_post_id);

            }

        }

    }

}



function actualizar_post_desde_json($post_id, $json_data) {

    // Decodificar el JSON



      if (!current_user_can('manage_options')) {

            wp_send_json_error('No tienes permisos para realizar esta acción');

            wp_die();

        }



    $data = json_decode($json_data, true);

    

    // Asegurarse de que el JSON esté bien formado y contenga los campos necesarios

    if (json_last_error() === JSON_ERROR_NONE && !empty($data)) {

        // Preparar los datos a actualizar

        $post_data = array(

            'ID'           => $post_id,

            'post_content' => $data['description'], // Actualizar la descripción del post

        );

        

        // Actualizar el post

        wp_update_post($post_data);

        

        // Actualizar el número de habitaciones como metadato del post

        // Asumiendo que el nombre del campo personalizado para las habitaciones es 'bedrooms'

        if (get_post_meta($post_id, '_property_bedrooms', true) != ($data['bedrooms'] ? $data['bedrooms'] : 0)) {

           update_post_meta($post_id, '_property_bedrooms', $data['bedrooms'] ? $data['bedrooms'] : 0);

        }

        if (get_post_meta($post_id, 'property_rooms', true) != ($data['bedrooms'] ? $data['bedrooms'] : 0)) {

             update_post_meta($post_id, 'property_rooms', $data['bedrooms'] ? $data['bedrooms'] : 0);

        }

         update_post_meta($post_id, 'header_transparent', 'global');

         update_post_meta($post_id, 'topbar_transparent', 'global');

         update_post_meta($post_id, 'page_show_adv_search', 'global');

         update_post_meta($post_id, 'page_use_float_search', 'global');

         update_post_meta($post_id, 'topbar_border_transparent', 'global');

         update_post_meta($post_id, 'sidebar_agent_option', 'global');

         update_post_meta($post_id, 'local_pgpr_slider_type', 'global');

         update_post_meta($post_id, 'local_pgpr_content_type', 'global');

         update_post_meta($post_id, 'sidebar_option', 'global');

         update_post_meta($post_id, 'page_header_image_full_screen', 'no');

         update_post_meta($post_id, 'page_header_video_full_screen', 'no');



         update_post_meta($post_id, 'property_year_tax', 0);

         update_post_meta($post_id, 'property_hoa', 0);

         update_post_meta($post_id, 'property_hoa', 0);

         update_post_meta($post_id, 'prop_featured', 0);

         update_post_meta($post_id, 'property_theme_slider', 0);



         update_post_meta($post_id, 'page_custom_zoom', 16);

         update_post_meta($post_id, 'google_camera_angle', 0);

         update_post_meta($post_id, 'use_floor_plans', 0);

         update_post_meta($post_id, 'header_type', 0);

         update_post_meta($post_id, 'min_height', 0);

         update_post_meta($post_id, 'max_height', 0);


        
         if (isset($data['location']) && isset($data['location']['latitude'])) {

            update_post_meta($post_id, 'property_latitude', $data['location']['latitude']);

         }



         if (isset($data['location']) && isset($data['location']['longitude'])) {

            update_post_meta($post_id, 'property_longitude', $data['location']['longitude']);

         }



         if (isset($data['location']) && isset($data['location']['name'])) {

            $locationName = $data['location']['name'];

            update_post_meta($post_id, '_property_st_address', $locationName);

            /*$address = $locationName; // Puedes asignar aquí el valor de la dirección si lo tienes
            $latitude = isset($data['location']['latitude']) ? $data['location']['latitude'] : '';
            $longitude = isset($data['location']['longitude']) ? $data['location']['longitude'] : '';
            $zoom = '7'; // Puedes ajustar este valor según lo necesites

            $map_data = array(
                'address' => $address,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'zoom' => $zoom,
            );

            // Serializar el array y guardarlo en el meta campo "_property_map"
            // Asignar valores a las variables
            */
            // Asignar valores a las variables
            $address = $locationName; 
            $latitude = isset($data['location']['latitude']) ? number_format((float)$data['location']['latitude'], 15, '.', '') : '18.895892559415024';
            $longitude = isset($data['location']['longitude']) ? number_format((float)$data['location']['longitude'], 15, '.', '') : '-9.664331823585172';
            $zoom = isset($data['location']['zoom']) ? '4' : '4'; // Cambia a "4" si es necesario

            // Crear el array con los datos del mapa
            $map_data = array(
                'address' => $address,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'zoom' => $zoom,
            );

          
            // Guardar el array serializado en el meta campo "_property_map"
            update_post_meta($post_id, '_property_map', $map_data);
            update_post_meta($post_id, '_property_panorama', 'a:8:{s:3:"url";s:0:"";s:2:"id";s:0:"";s:5:"width";s:0:"";s:6:"height";s:0:"";s:9:"thumbnail";s:0:"";s:3:"alt";s:0:"";s:5:"title";s:0:"";s:11:"description";s:0:"";}');
            



            $locationComponents = explode(', ', $locationName);



            $barrio = '';

            $ciudad = '';

            $estado = '';



            if (!empty($locationComponents[0])) {

                $barrio = $locationComponents[0];

            }

            if (!empty($locationComponents[1])) {

                $ciudad = $locationComponents[1];

            }

            if (!empty($locationComponents[2])) {

                $estado = $locationComponents[2];

            }



            if ($barrio !== '') {

                $textoSlug3 = $barrio;

                if ($textoSlug3) {

                    $textoSlug3 = trim( $textoSlug3 );

                    $slugTag3 = sanitize_title($textoSlug3);

                    $property_area = obtener_id_termino_por_slug( 'property_province', $slugTag3 );

                    if ( $property_area !== false ) {

                            asociar_post_a_termino( $post_id, $property_area, 'property_province' );
                            update_post_meta($post_id, '_property_state', $property_area);
                    }

                }

            }

            if ($ciudad !== '') {

                $textoSlug4 = $ciudad;

                if ($textoSlug4) {

                    $textoSlug4 = trim( $textoSlug4 );

                    $slugTag4 = sanitize_title($textoSlug4);

                    $property_city = obtener_id_termino_por_slug( 'property_city', $slugTag4 );

                    if ( $property_city !== false ) {

                            asociar_post_a_termino( $post_id, $property_city, 'property_city' );
                            update_post_meta($post_id, '_property_city', $property_city);

                    }

                }   

            }

            if ($estado !== '') {

                $textoSlug5 = $estado;

                if ($textoSlug5) {

                    $textoSlug5 = trim( $textoSlug5 );

                    $slugTag5 = sanitize_title($textoSlug5);

                    $property_county_state = obtener_id_termino_por_slug( 'property_country', $slugTag5 );

                    if ( $property_county_state !== false ) {

                            asociar_post_a_termino( $post_id, $property_county_state, 'property_country' );
                            update_post_meta($post_id, '_property_country', $property_county_state);
                    }

                }   

            }



         }



         if (!empty($data['operations']) && is_array($data['operations'])) {

            // Suponiendo que podría haber varias operaciones, pero solo nos interesa actualizar si alguna es en dólares

            foreach ($data['operations'] as $operation) {

                if ($operation['currency'] === 'USD' || $operation['currency'] === 'MXN') {

                    // Solo actualizar si la moneda es dólares

                    if($operation['type'] != '') {

                        $textoSlug2 = $operation['type'];

                        if ($textoSlug2) {

                            $textoSlug2 = trim( $textoSlug2 );

                            $slugTag2 = sanitize_title($textoSlug2);

                            $property_action_category = $slugTag2;

                            if ( $property_action_category !== false && $property_action_category == 'rental' ) {

                                    asociar_post_a_termino( $post_id, array(67), 'property_status' );
                                    update_post_meta($post_id, '_property_status', '67');

                            }else if ($property_action_category !== false && $property_action_category == 'sale' ){

                                  asociar_post_a_termino( $post_id, array(66), 'property_status' );
                                  update_post_meta($post_id, '_property_status', '66');
                            }

                        }

                    } 

                    update_post_meta($post_id, '_property_price', $operation['amount']);

                    update_post_meta($post_id, '_property_price_prefix', $operation['currency']);

                    update_post_meta($post_id, 'property_sale_formatted_amount_usd', $operation['formatted_amount']);

                    // No necesitamos revisar más operaciones si solo queremos una en dólares

                    break;

                }

            }

        }



        if (get_post_meta($post_id, '_property_bathrooms', true) != ($data['bathrooms'] ? $data['bathrooms'] : 0)) {

            update_post_meta($post_id, '_property_bathrooms', $data['bathrooms'] ? $data['bathrooms'] : 0);

        }



         update_post_meta($post_id, '_property_parking', $data['parking_spaces']);



         if ($data['property_type'] != '') { 

            update_post_meta($post_id, 'property_type', $data['property_type']);

            $textoSlug = $data['property_type'];

            if ($textoSlug) {

                    $textoSlug = trim( $textoSlug );

                    $slugTag = sanitize_title($textoSlug);

                    $property_category = obtener_id_termino_por_slug( 'property_type', $slugTag );

                    if ( $property_category !== false ) {

                            asociar_post_a_termino( $post_id, $property_category, 'property_type' );
                            update_post_meta($post_id, '_property_type', $property_category);

                    }

            }

          }




         if (get_post_meta($post_id, 'property_size', true) != ($data['construction_size'] ? $data['construction_size'] : 0)) {

             update_post_meta($post_id, 'property_size', $data['construction_size'] ? $data['construction_size'] : 0);

         }


         update_post_meta($post_id, '_property_area', $data['construction_size'] ? $data['construction_size'] : 0);
         update_post_meta($post_id, '_property_larea', $data['construction_size'] ? $data['construction_size'] : 0);
         
         update_post_meta($post_id, 'property_updated_at', $data['updated_at']);

         update_post_meta($post_id, '_property_build', $data['age']);



         $features_ids = []; // Inicializa un arreglo vacío para almacenar los IDs de término



         if (isset($data['features']) && is_array($data['features'])) {

             foreach ($data['features'] as $feature) {

                 if (isset($feature['name'])) {

                     $textoSlug7 = trim($feature['name']);

                     if ($textoSlug7) {

                         $slugTag7 = sanitize_title($textoSlug7);

                         $property_feature_id = obtener_id_termino_por_slug('property_feature', $slugTag7);

                         if ($property_feature_id !== false) {

                             // En lugar de asociar cada término inmediatamente, lo agregamos al arreglo

                             $features_ids[] = $property_feature_id;

                         }

                     }

                 }

             }

             

             // Verifica si hay términos para asociar

             if (!empty($features_ids)) {

                 // Asocia todos los términos acumulados al post una sola vez

                 asociar_post_a_termino($post_id, $features_ids, 'property_feature');

             }

         } else {

             echo "No se encontraron características (features).";

         }

         

         



         // Supongamos que $data es tu arreglo asociativo obtenido de decodificar el JSON

            $imagen_ids_array = []; // Inicializa un arreglo para almacenar los IDs de las imágenes



           foreach ($data['property_images'] as $image) {

    if (isset($image['url'])) {

                $image_url = $image['url'];

                $image_id = $image_url; // Asegúrate de que esta línea esté descomentada.

                if (!is_wp_error($image_id)) {

                    // Si se descargó la imagen correctamente, agregar su ID al arreglo

                    $imagen_ids_array[] = $image_id;

                } else {

                    // Manejar el error, por ejemplo, registrando el mensaje de error o ignorando esta imagen

                    error_log('Error descargando imagen: ' . $image_id->get_error_message());

                }

            }

        }



        if (!empty($imagen_ids_array)) {

            // Convertir el arreglo de IDs de imágenes en una cadena separada por comas

            $image_ids_str = implode(',', $imagen_ids_array);

            // Actualizar el campo personalizado 'image_to_attach' con los IDs de las imágenes

            update_post_meta($post_id, 'image_to_attach_ids', $image_ids_str);

        } 

           



         // Ajustar para cualquier otro campo que necesites actualizar

    } else {

        // Manejar error en caso de que el JSON no sea válido

        error_log('Error al decodificar JSON');

    }

}



/*function json_propiedad_individual2($property_id, $api_key) {

    $ch = curl_init();

    

    // Usa el parámetro $property_id en la URL

    curl_setopt($ch, CURLOPT_URL, 'https://api.easybroker.com/v1/properties/' . $property_id);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [

        'X-Authorization: ' . $api_key, // Usa el parámetro $api_key para la autorización

        'Accept: application/json'

    ]);

    

    $response = curl_exec($ch);

    

    if(curl_errno($ch)) {

        echo 'Error:' . curl_error($ch);

    } else {

        // Configura los encabezados de respuesta adecuados.

        header('Content-Type: application/json');

        echo $response;

    }

    

    // Cierra la sesión cURL.

    curl_close($ch);

}*/



function json_propiedad_individual2($property_id, $api_key) {

    $ch = curl_init();

    

    // Usa el parámetro $property_id en la URL

    curl_setopt($ch, CURLOPT_URL, 'https://api.easybroker.com/v1/properties/' . $property_id);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [

        'X-Authorization: ' . $api_key, // Usa el parámetro $api_key para la autorización

        'Accept: application/json'

    ]);

    

    $response = curl_exec($ch);

    

    if(curl_errno($ch)) {

        return 'Error:' . curl_error($ch);

    } else {

        // Devuelve el resultado como JSON

        return $response;

    }

    

    // Cierra la sesión cURL.

    curl_close($ch);

}



function mostrar_json_propiedad($atts) {

    $atts = shortcode_atts([

        'id' => '',

        'api_key' => ''

    ], $atts);

    

    if (empty($atts['id']) || empty($atts['api_key'])) {

        return 'Faltan parámetros en el shortcode.';

    }

    

    $json_response = json_propiedad_individual2($atts['id'], $atts['api_key']);

    

    if (strpos($json_response, 'Error:') !== false) {

        // Si hay un error, muestra el mensaje de error

        return $json_response;

    } else {

        // Muestra el JSON directamente

        return '<pre>' . esc_html($json_response) . '</pre>';

    }

}

add_shortcode('mostrar_json_propiedad', 'mostrar_json_propiedad');



/*ejemplo de shorcode [mostrar_json_propiedad id="YOUR_PROPERTY_ID" api_key="YOUR_API_KEY"]*/



function obtener_id_termino_por_slug( $taxonomy, $slug ) {

    // Verificar si el término ya existe en la taxonomía

    $term = get_term_by( 'slug', $slug, $taxonomy );



    if ( $term !== false ) {

        // El término ya existe, devolver su ID

        return $term->term_id;

    } else {

        // El término no existe, crearlo

        $term_args = array(

            'slug' => $slug,

        );



        $result = wp_insert_term( $slug, $taxonomy, $term_args );



        if ( ! is_wp_error( $result ) ) {

            // El término se ha creado con éxito, devolver su ID

            return $result['term_id'];

        } else {

            // Ha ocurrido un error al crear el término, devolver false

            trigger_error( 'Error intencional: ' . $result->get_error_message(), E_USER_ERROR );

            return false;

        }

    }

}



function asociar_post_a_termino($post_id, $term_ids, $taxonomy) {

    // Asegúrate de que $term_ids sea un arreglo. Si no lo es, conviértelo en uno.

    if (!is_array($term_ids)) {

        $term_ids = array($term_ids);

    }



    // Ahora $term_ids es definitivamente un arreglo, así que lo pasamos directamente a wp_set_post_terms

    $result = wp_set_post_terms($post_id, $term_ids, $taxonomy, true);



    if (!is_wp_error($result)) {

        return true; // Éxito: los términos se han asociado al post

    } else {

        // En lugar de usar trigger_error, considera otra forma de manejar el error que no detenga la ejecución del script

        error_log('Error al asociar términos al post: ' . $result->get_error_message());

        return false; // Error: ha ocurrido un error al asociar los términos al post

    }

}



function limpiar_url_imagen($url) {

    $partes_url = explode('?', $url); // Separar la URL por el signo de interrogación

    return $partes_url[0]; // Devolver la parte de la URL antes del signo de interrogación

}



function agregar_imagen_desde_url($url) {

    $url = limpiar_url_imagen($url);

    // Buscar imágenes con el mismo campo personalizado "url-base"

    $args = array(

        'post_type'      => 'attachment',

        'post_status'    => 'inherit',

        'posts_per_page' => -1,

        'meta_query'     => array(

            array(

                'key'     => 'url-base',

                'value'   => $url,

                'compare' => '=',

            ),

        ),

    );



    $query = new WP_Query($args);



    if ($query->have_posts()) {

        // Si se encuentra, devolver el ID de la primera imagen encontrada

        while ($query->have_posts()) {

            $query->the_post();

            return get_the_ID();

        }

    } else {

        // Si no se encuentra, descargar y agregar la imagen

          

        require_once ABSPATH . 'wp-admin/includes/file.php';

        require_once ABSPATH . 'wp-admin/includes/media.php';

        require_once ABSPATH . 'wp-admin/includes/image.php';

 

     



        // Descargar la imagen

        $tmp = download_url($url);



        // Asegurarse de que la descarga fue exitosa

        if (is_wp_error($tmp)) {

            return $tmp;

        }



        // Determinar el tipo MIME de la imagen descargada

        $file_type = mime_content_type($tmp);



        // Preparar un array para la imagen

        $file = array(

            'name'     => basename($url),

            'type'     => $file_type,

            'tmp_name' => $tmp,

            'error'    => 0,

            'size'     => filesize($tmp),

        );



        // Hacer el archivo accesible a la biblioteca de medios

        $id = media_handle_sideload($file, 0);



        // Verificar si se produjo un error

        if (is_wp_error($id)) {

            @unlink($file['tmp_name']); // Eliminar archivo temporal

            return $id;

        }



        // Asignar el campo personalizado "url-base"

        update_post_meta($id, 'url-base', $url);



        // Devolver el ID de la imagen

        return $id;

    }

}



/*add_action( 'post_updated', 'mi_funcion_al_actualizar_post', 10, 3 );*/

function mi_funcion_al_actualizar_post( $post_id ) {

    // Registrar inicio de la función

    error_log( 'Inicio de la actualización del post: ' . $post_id );



    // Obtener las URLs del campo personalizado 'image_to_attach'

    $image_urls = get_post_meta( $post_id, 'image_to_attach_ids', true );

    $imagen_destacada = get_post_meta( $post_id, 'title_image_thumb', true );



    if ( ! empty( $imagen_destacada ) ) { 

        $url_imagen_destacada = $imagen_destacada;

        $imagen_destacada_id = agregar_imagen_desde_url( $url_imagen_destacada ); 



        if ( is_wp_error( $imagen_destacada_id ) ) {

            error_log( 'Error al procesar la URL: ' . $url_imagen_destacada . ' | Error: ' . $imagen_destacada_id->get_error_message() );

        }

        

        if ( ! empty( $imagen_destacada_id ) && ! is_wp_error( $imagen_destacada_id )) {

            // Almacenar el ID obtenido

            set_post_thumbnail( $post_id, $imagen_destacada_id );

        }



    }



    if ( ! empty( $image_urls ) ) {

        // Dividir las URLs por comas para procesarlas individualmente

        $urls = explode( ',', $image_urls );

        $ids = array();

        $existing_ids_string = get_post_meta( $post_id, '_property_gallery', true ); // Obtener los IDs existentes

        $existing_ids = explode( ',', $existing_ids_string );



        foreach ( $urls as $url ) {

                $url = trim( $url ); 

            

                $image_id = agregar_imagen_desde_url( $url ); 

    

                if ( is_wp_error( $image_id ) ) {

                    error_log( 'Error al procesar la URL: ' . $url . ' | Error: ' . $image_id->get_error_message() );

                    continue; 

                }



                if ( ! empty( $image_id ) ) {

                    $ids[] = $image_id; // Almacenar el ID obtenido

                    error_log( 'ID de imagen obtenido: ' . $image_id ); 

                    if(! in_array( $image_id, $existing_ids )){

                      wp_update_post( array( 'ID' => $image_id, 'post_parent' => $post_id ) );

                    }     

                }

        }



        // Si se obtuvieron IDs, guardarlos en el campo 'image_to_attach_ids'

        if ( ! empty( $ids ) ) {

            $ids_string = implode( ',', $ids ); // Convertir el array de IDs en una string

            update_post_meta( $post_id, '_property_gallery', $ids_string . ','); // Actualizar/Crear el campo personalizado con los IDs

            error_log( 'IDs guardados: ' . $ids_string ); // Registrar los IDs guardados

        }

    } else {

        error_log( 'No se encontraron URLs para procesar.' ); // Registrar si no se encontraron URLs

    }



}



function obtener_propiedades_api($api_key) {

    $propiedades_api = [];

    $pagina_actual = 1;

    $total_pages = 1; // Suponiendo que al menos hay una página para empezar



    // Continuar paginando mientras no hayamos llegado a la última página

    while ($pagina_actual <= $total_pages) {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.easybroker.com/v1/properties?page=' . $pagina_actual . '&limit=50');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [

            'X-Authorization: ' . $api_key,

            'Accept: application/json'

        ]);



        $response = curl_exec($ch);



        if (curl_errno($ch)) {

            echo 'Error:' . curl_error($ch);

        } else {

            $data = json_decode($response, true);

            if (isset($data['pagination']['total_pages'])) {

                $total_pages = $data['pagination']['total_pages'];

            }

            if (!empty($data['content'])) {

                foreach ($data['content'] as $property) {

                    if (isset($property['public_id'])) {

                        $propiedades_api[] = $property['public_id'];

                    }

                }

            }

        }



        curl_close($ch);

        $pagina_actual++;

    }



    return $propiedades_api;

}



function manejador_ajax_eliminar_posts_no_encontrados() {

    if (!current_user_can('manage_options')) {

        wp_send_json_error('No tienes permisos para realizar esta acción');

        wp_die();

    }



    $api_key = isset($_GET['api_key']) ? sanitize_text_field($_GET['api_key']) : '';

    $propiedades_api = obtener_propiedades_api($api_key);



    if (empty($propiedades_api)) {

        wp_send_json_error('No se encontraron propiedades en la API.');

        wp_die();

    }



    // Query para obtener todas las propiedades con 'public_id' existente

    $args_exist = [

        'post_type' => 'property',

        'posts_per_page' => -1,

        'fields' => 'ids',  // Obten solo los IDs para mejorar la eficiencia

        'meta_query' => [

            [

                'key' => 'public_id',

                'compare' => 'EXISTS'  // Asegurarse de que el meta 'public_id' exista

            ]

        ]

    ];



    // Query para obtener todas las propiedades donde 'public_id' no exista o esté vacío

    $args_not_exist = [

        'post_type' => 'property',

        'posts_per_page' => -1,

        'fields' => 'ids',  // Obten solo los IDs para mejorar la eficiencia

        'meta_query' => [

            'relation' => 'OR',

            [

                'key' => 'public_id',

                'compare' => 'NOT EXISTS'  // Asegurarse de que el meta 'public_id' no exista

            ],

            [

                'key' => 'public_id',

                'value' => '',

                'compare' => '='  // Asegurarse de que el meta 'public_id' esté vacío

            ]

        ]

    ];



    $posts_exist = get_posts($args_exist);

    $posts_not_exist = get_posts($args_not_exist);



    // Combinar ambas listas de posts

    $posts = array_merge($posts_exist, $posts_not_exist);



    foreach ($posts as $post_id) {

        $public_id = get_post_meta($post_id, 'public_id', true);



        // Eliminar si el public_id no está en la lista de propiedades de la API o si es vacío

        if (empty($public_id) || !in_array($public_id, $propiedades_api)) {

            wp_delete_post($post_id, true);  // Borrar permanentemente

        }

    }



    wp_send_json_success('Proceso completado. Las propiedades no encontradas en la API o con public_id vacío han sido eliminadas.');

    wp_die();

}



add_action('wp_ajax_eliminar_posts_no_encontrados', 'manejador_ajax_eliminar_posts_no_encontrados');















   