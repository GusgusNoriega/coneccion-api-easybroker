/*document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('easy-broker-api-btn');
    if (btn) {
        btn.addEventListener('click', function() {
            var apiKey = this.getAttribute('data-api-key');
            var pagina_actual = 1; // Inicia en la primera página

            function cargarPropiedades() {
                fetch('/wp-admin/admin-ajax.php?action=obtener_propiedades&api_key=' + apiKey + '&pagina=' + pagina_actual)
                .then(response => response.json())
                .then(data => {
                    console.log(data);
                    // Calcula cuántas páginas faltan
                    var totalPaginas = Math.ceil(data.pagination.total / data.pagination.limit);
                    var paginasRestantes = totalPaginas - pagina_actual;
                    
                    // Encuentra el div y actualiza el contenido
                    var divInfo = document.getElementById('propiedades-info');
                    if (data.content.length > 0) {
                        var propiedad = data.content[0]; // Toma la primera propiedad
                        divInfo.innerHTML = `<strong>Título:</strong> ${propiedad.title}<br>
                                             <strong>Propiedad actual:</strong> ${pagina_actual}<br>
                                             <strong>Propiedades restantes:</strong> ${paginasRestantes}`;
                    } else {
                        divInfo.innerHTML = "No se encontraron propiedades.";
                    }

                    // Si hay más páginas, incrementa `pagina_actual` y carga la siguiente página
                    if (paginasRestantes > 0) {
                        pagina_actual++;
                        cargarPropiedades(); // Llama recursivamente para cargar la próxima página
                    }
                })
                
                .catch(error => console.error('Error:', error));
            }

            cargarPropiedades(); // Inicia la carga de propiedades
        });
    }

});
*/

document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('easy-broker-api-btn');
    if (btn) {
        btn.addEventListener('click', function() {
            var apiKey = this.getAttribute('data-api-key');
            var pagina_actual = 1; // Inicia en la primera página

            function cargarPropiedades() {
                fetch('/wp-admin/admin-ajax.php?action=obtener_propiedades&api_key=' + apiKey + '&pagina=' + pagina_actual)
                .then(response => response.json())
                .then(data => {
                    console.log(data);
                    // Calcula cuántas páginas faltan
                    var totalPaginas = Math.ceil(data.pagination.total / data.pagination.limit);
                    var paginasRestantes = totalPaginas - pagina_actual;
                    
                    // Encuentra el div y actualiza el contenido
                    var divInfo = document.getElementById('propiedades-info');
                    if (data.content.length > 0) {
                        var propiedad = data.content[0]; // Toma la primera propiedad
                        divInfo.innerHTML = `<strong>Título:</strong> ${propiedad.title}<br>
                                             <strong>Propiedad actual:</strong> ${pagina_actual}<br>
                                             <strong>Propiedades restantes:</strong> ${paginasRestantes}`;
                    } else {
                        divInfo.innerHTML = "No se encontraron propiedades.";
                    }

                    // Si hay más páginas, incrementa `pagina_actual` y carga la siguiente página
                    if (paginasRestantes > 0) {
                        pagina_actual++;
                        cargarPropiedades(); // Llama recursivamente para cargar la próxima página
                    } else {
                        // Llama a la función para eliminar posts una vez que se han cargado todas las propiedades
                        eliminarPostsNoEncontrados(apiKey);
                    }
                })
                .catch(error => console.error('Error:', error));
            }

            cargarPropiedades(); // Inicia la carga de propiedades
        });
    }
});

function eliminarPostsNoEncontrados(apiKey) {
    fetch('/wp-admin/admin-ajax.php?action=eliminar_posts_no_encontrados&api_key=' + apiKey)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log("Mensaje de éxito:", data.data);
        } else {
            console.error("Error en el servidor:", data.data);
        }
    })
    .catch(error => console.error('Error en la respuesta AJAX:', error));
}