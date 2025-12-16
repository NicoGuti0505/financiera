function formatearMoneda(valor) {
    if (valor == null || isNaN(valor)) { return null; }
    valor = parseFloat(valor);
    return new Intl.NumberFormat('es-CO', { 
        style: 'currency', 
        currency: 'COP', 
        minimumFractionDigits: 2,
        maximumFractionDigits: 2 
    }).format(valor);
}

function parsearMoneda(valor) {
    if (valor == null || valor === '') { return null; }
    return parseFloat(valor.replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.'));
}

function formatearPorcentaje(valor) {
    if (valor == null || isNaN(valor)) { return null; }
    return new Intl.NumberFormat('es-CO', { style: 'percent', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(valor / 100);
}

function parsearPorcentaje(valor) { 
    if (valor == null || valor === '') { return null; }
    return parseFloat(valor.replace('%', '').replace(',', '.'));
}