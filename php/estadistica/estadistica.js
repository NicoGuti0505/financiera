
function decodeUrl(encodedUrl) {
    try {
        return decodeURIComponent(atob(encodedUrl));
    } catch (error) {
        console.error("Error al decodificar la URL:", error);
        return null;
    }
}


function fetchPowerBIUrl() {
    fetch('get_url.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('No se pudo obtener la URL de Power BI');
            }
            return response.json();
        })
        .then(data => {
            const powerbiUrl = decodeUrl(data.url);
            if (powerbiUrl) {
                console.log("Decoded Power BI URL:", powerbiUrl);
                document.getElementById('powerbi-iframe').src = powerbiUrl;
            } else {
                console.error("No se pudo cargar la URL de Power BI.");
            }
        })
        .catch(error => console.error("Error al obtener la URL:", error));
}

window.onload = function() {
    fetchPowerBIUrl();

    // Crear márgenes solo si no existen
    if (!document.querySelector(".transparent-margin")) {
        let marginOverlay = document.createElement("div");
        marginOverlay.className = "transparent-margin";

        let leftMargin = document.createElement("div");
        leftMargin.className = "transparent-margin-block left-margin";

        let rightMargin = document.createElement("div");
        rightMargin.className = "transparent-margin-block right-margin";

        let topMargin = document.createElement("div");
        topMargin.className = "transparent-margin-block top-margin";

        let bottomMargin = document.createElement("div");
        bottomMargin.className = "transparent-margin-block bottom-margin";

        marginOverlay.appendChild(leftMargin);
        marginOverlay.appendChild(rightMargin);
        marginOverlay.appendChild(topMargin);
        marginOverlay.appendChild(bottomMargin);
        document.body.appendChild(marginOverlay);
    }

    bloquearEventos();
    detectarDevTools();
};

